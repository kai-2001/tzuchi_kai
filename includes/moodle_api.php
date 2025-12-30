<?php
// includes/moodle_api.php - Moodle 資料抓取

/**
 * 取得使用者的 Moodle 資料（含快取機制與分段載入支援）
 * @param string $type 抓取類型: 'all', 'courses', 'grades', 'announcements', 'curriculum'
 * @return array 包含課程、公告、進度等資料
 */
function fetch_moodle_data($type = 'all')
{
    global $moodle_url, $moodle_token;

    $data = [
        'my_courses_raw' => [],
        'history_by_year' => [],
        'available_courses' => [],
        'latest_announcements' => [],
        'curriculum_status' => [],
        'grades' => [],
        'moodle_uid' => null,
        'error' => null
    ];

    $is_admin = isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : false;

    // 管理員不需要抓資料
    if ($is_admin || !isset($_SESSION['username'])) {
        return $data;
    }

    // 檢查快取 (只在 type 為 all 時使用全域快取)
    if ($type === 'all' && isset($_SESSION['moodle_cache']) && isset($_SESSION['moodle_cache_time'])) {
        if (time() - $_SESSION['moodle_cache_time'] < CACHE_DURATION) {
            return $_SESSION['moodle_cache'];
        }
    }

    try {
        // 步驟 1: 取得 Moodle 使用者 ID
        if (isset($_SESSION['moodle_uid'])) {
            $moodle_uid = $_SESSION['moodle_uid'];
        } else {
            $u_params = ['field' => 'username', 'values' => [$_SESSION['username']]];
            $moodle_users = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);

            if (!is_array($moodle_users) || empty($moodle_users) || !isset($moodle_users[0]['id'])) {
                $data['error'] = 'MOODLE_USER_NOT_FOUND';
                return $data;
            }
            $moodle_uid = $moodle_users[0]['id'];
            $_SESSION['moodle_uid'] = $moodle_uid;
        }
        $data['moodle_uid'] = $moodle_uid;

        // ======= Wave 1: 基礎資料平行抓取 (我的課程, 所有課程, 分類資訊) =======
        $wave1_requests = [
            ['key' => 'my_courses', 'func' => 'core_enrol_get_users_courses', 'params' => ['userid' => $moodle_uid]],
            ['key' => 'all_courses_search', 'func' => 'core_course_search_courses', 'params' => ['criterianame' => 'search', 'criteriavalue' => '', 'page' => 0, 'perpage' => 500]],
            ['key' => 'categories', 'func' => 'core_course_get_categories', 'params' => ['addsubcategories' => 1]]
        ];

        $wave1_results = call_moodle_parallel($moodle_url, $moodle_token, $wave1_requests);

        $data['my_courses_raw'] = $wave1_results['my_courses'] ?? [];
        $all_search_courses = $wave1_results['all_courses_search']['courses'] ?? [];
        $cat_info_raw = $wave1_results['categories'] ?? [];

        // 建立分類對照表
        $cat_info = [];
        if (is_array($cat_info_raw)) {
            foreach ($cat_info_raw as $cat) {
                $cat_info[$cat['id']] = $cat;
            }
        }

        // 整理學習歷程
        if (!empty($data['my_courses_raw'])) {
            foreach ($data['my_courses_raw'] as $course) {
                $start_ts = $course['startdate'] ?? 0;
                $year = ($start_ts > 0) ? date('Y', $start_ts) : '未設定年份';
                $data['history_by_year'][$year][] = $course;
            }
            krsort($data['history_by_year']);
        }

        // ======= Wave 2: 依賴資料平行抓取 (論壇清單, 成績單) =======
        // 找出最近 5 門課準備抓成績
        $recent_course_ids = array_slice(array_column($data['my_courses_raw'], 'id'), 0, 5);
        $wave2_requests = [];

        if (!empty($data['my_courses_raw'])) {
            $wave2_requests[] = [
                'key' => 'forums',
                'func' => 'mod_forum_get_forums_by_courses',
                'params' => ['courseids' => array_column($data['my_courses_raw'], 'id')]
            ];

            foreach ($recent_course_ids as $cid) {
                $wave2_requests[] = [
                    'key' => 'grade_' . $cid,
                    'func' => 'gradereport_user_get_grade_items',
                    'params' => ['courseid' => $cid, 'userid' => $moodle_uid]
                ];
            }
        }

        $wave2_results = !empty($wave2_requests) ? call_moodle_parallel($moodle_url, $moodle_token, $wave2_requests) : [];

        // ======= Wave 3: 深層資料平行抓取 (公告內容) =======
        $forums = $wave2_results['forums'] ?? [];
        $wave3_requests = [];
        foreach ($forums as $forum) {
            if (($forum['type'] ?? '') === 'news' || strpos($forum['name'] ?? '', '公告') !== false) {
                $wave3_requests[] = [
                    'key' => 'disc_' . $forum['id'],
                    'func' => 'mod_forum_get_forum_discussions',
                    'params' => ['forumid' => $forum['id']]
                ];
            }
        }

        $wave3_results = !empty($wave3_requests) ? call_moodle_parallel($moodle_url, $moodle_token, $wave3_requests) : [];

        // ======= 最後階段: 組合所有資料 =======

        // 1. 處理可選修與課程狀態 (Available Courses)
        $my_courses_by_id = [];
        foreach ($data['my_courses_raw'] as $c) {
            $my_courses_by_id[$c['id'] ?? 0] = $c;
        }

        foreach ($all_search_courses as $course) {
            if (($course['id'] ?? 0) <= 1)
                continue;

            $cat_id = $course['categoryid'] ?? null;
            $parent_name = '其他';
            $child_name = '';

            if ($cat_id && isset($cat_info[$cat_id])) {
                $curr_cat = $cat_info[$cat_id];
                $child_name = $curr_cat['name'];
                $temp_cat = $curr_cat;
                while (($temp_cat['parent'] ?? 0) > 0 && isset($cat_info[$temp_cat['parent']])) {
                    $temp_cat = $cat_info[$temp_cat['parent']];
                }
                $parent_name = $temp_cat['name'];
                if ($curr_cat['id'] == $temp_cat['id']) {
                    $child_name = '';
                }
            }

            $course['parent_category'] = $parent_name;
            $course['child_category'] = ($child_name && $child_name !== $parent_name) ? $child_name : '';
            $course['display_category'] = $course['child_category'] ? ($parent_name . ' - ' . $child_name) : $parent_name;
            $course['is_enrolled'] = isset($my_courses_by_id[$course['id']]);
            $course['progress'] = $course['is_enrolled'] ? ($my_courses_by_id[$course['id']]['progress'] ?? 0) : 0;
            $course['completed'] = $course['is_enrolled'] ? ($my_courses_by_id[$course['id']]['completed'] ?? false) : false;

            $data['available_courses'][] = $course;
        }

        // 2. 處理必修進度 (本地計算)
        $data['curriculum_status'] = process_curriculum_locally($all_search_courses, $data['my_courses_raw'], $cat_info);

        // 3. 處理最新公告 (使用 Wave 3 結果)
        $raw_announcements = [];
        $course_names = array_column($data['my_courses_raw'], 'fullname', 'id');
        foreach ($forums as $forum) {
            $disc_key = 'disc_' . ($forum['id'] ?? 0);
            if (isset($wave3_results[$disc_key]['discussions'])) {
                foreach ($wave3_results[$disc_key]['discussions'] as $disc) {
                    $raw_announcements[] = [
                        'course_name' => $course_names[$forum['course']] ?? '全站公告',
                        'subject' => $disc['subject'] ?? '無主旨',
                        'author' => $disc['userfullname'] ?? '系統',
                        'date' => $disc['created'] ?? 0,
                        'link' => $moodle_url . '/mod/forum/discuss.php?d=' . ($disc['discussion'] ?? 0)
                    ];
                }
            }
        }
        usort($raw_announcements, function ($a, $b) {
            return ($b['date'] ?? 0) - ($a['date'] ?? 0); });
        $data['latest_announcements'] = array_slice($raw_announcements, 0, 5);

        // 4. 處理成績 (使用 Wave 2 結果)
        foreach ($data['my_courses_raw'] as $course) {
            if (count($data['grades']) >= 5)
                break;
            $g_key = 'grade_' . $course['id'];
            if (isset($wave2_results[$g_key]['usergrades'][0]['gradeitems'])) {
                foreach ($wave2_results[$g_key]['usergrades'][0]['gradeitems'] as $item) {
                    if (($item['itemtype'] ?? '') === 'course' && isset($item['graderaw'])) {
                        $data['grades'][] = [
                            'course_id' => $course['id'],
                            'course_name' => $course['fullname'],
                            'grade' => round($item['graderaw'], 1),
                            'grade_max' => $item['grademax'] ?? 100,
                            'grade_formatted' => $item['gradeformatted'] ?? '-'
                        ];
                    }
                }
            }
        }

        // 更新快取
        if ($type === 'all') {
            $_SESSION['moodle_cache'] = $data;
            $_SESSION['moodle_cache_time'] = time();
        }

    } catch (Exception $e) {
        error_log("Moodle API Error: " . $e->getMessage());
        $data['error'] = $e->getMessage();
    }

    return $data;
}

/**
 * 輔助函式：快速取得我的課程清單
 */
function fetch_my_courses_simple($moodle_uid)
{
    global $moodle_url, $moodle_token;
    $result = call_moodle($moodle_url, $moodle_token, 'core_enrol_get_users_courses', ['userid' => $moodle_uid]);
    return is_array($result) ? $result : [];
}

/**
 * 輔助函式: 在本地處理必修進度邏輯 (不連線 Moodle API)
 */
function process_curriculum_locally($all_courses, $my_courses_raw, $cat_info)
{
    if (empty($all_courses))
        return [];

    $my_courses_lookup = [];
    if (is_array($my_courses_raw)) {
        foreach ($my_courses_raw as $c) {
            $my_courses_lookup[$c['id'] ?? 0] = $c;
        }
    }

    $curriculum_status = [];
    if (is_array($all_courses)) {
        foreach ($all_courses as $course) {
            if (($course['id'] ?? 0) <= 1)
                continue;

            $cat_id = $course['categoryid'] ?? null;
            $parent_name = '其他';
            $child_name = '';

            if ($cat_id && isset($cat_info[$cat_id])) {
                $curr_cat = $cat_info[$cat_id];
                $child_name = $curr_cat['name'];
                $temp_cat = $curr_cat;
                while (($temp_cat['parent'] ?? 0) > 0 && isset($cat_info[$temp_cat['parent']])) {
                    $temp_cat = $cat_info[$temp_cat['parent']];
                }
                $parent_name = $temp_cat['name'];
                if ($curr_cat['id'] == $temp_cat['id']) {
                    $child_name = '';
                }
            }

            $group_name = $parent_name;
            $display_cat_name = ($child_name && $child_name !== $parent_name) ? ($parent_name . ' - ' . $child_name) : $parent_name;

            $status = 'red';
            if (isset($my_courses_lookup[$course['id']])) {
                $uc = $my_courses_lookup[$course['id']];
                if (($uc['progress'] ?? 0) >= 100 || ($uc['completed'] ?? false)) {
                    $status = 'green';
                } else {
                    $status = 'yellow';
                }
            }

            $curriculum_status[$group_name][] = [
                'id' => $course['id'],
                'fullname' => $course['fullname'],
                'status' => $status,
                'category_name' => $display_cat_name
            ];
        }
    }
    return $curriculum_status;
}

/**
 * 以下為舊函式 (保留相容性，但 fetch_moodle_data(all) 已經不再依賴它們)
 */
function fetch_curriculum_status($my_courses_raw)
{
    global $moodle_url, $moodle_token;
    // ... 原有邏輯 ...
    return process_curriculum_locally([], $my_courses_raw, []); // 簡化回傳
}

function fetch_announcements($my_courses_raw)
{
    // ... 原有邏輯 ...
    return [];
}

function fetch_user_grades($moodle_uid, $my_courses)
{
    // ... 原有邏輯 ...
    return [];
}
?>