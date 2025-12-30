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

    // 檢查快取 (只在 type 為 all 時使用全域快取，分段載入較適合即時抓取或獨立快取)
    if ($type === 'all' && isset($_SESSION['moodle_cache']) && isset($_SESSION['moodle_cache_time'])) {
        if (time() - $_SESSION['moodle_cache_time'] < CACHE_DURATION) {
            return $_SESSION['moodle_cache'];
        }
    }

    try {
        // 步驟 1: 取得 Moodle 使用者 ID (核心步驟)
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

        // 根據 type 決定要抓取的資料

        // 1. 課程資料 (courses)
        if ($type === 'all' || $type === 'courses') {
            $p_requests = [
                [
                    'key' => 'user_courses',
                    'func' => 'core_enrol_get_users_courses',
                    'params' => ['userid' => $moodle_uid]
                ],
                [
                    'key' => 'all_courses',
                    'func' => 'core_course_search_courses',
                    'params' => ['criterianame' => 'search', 'criteriavalue' => '']
                ]
            ];
            $p_results = call_moodle_parallel($moodle_url, $moodle_token, $p_requests);

            $data['my_courses_raw'] = isset($p_results['user_courses']) ? $p_results['user_courses'] : [];
            $all_courses = isset($p_results['all_courses']['courses']) ? $p_results['all_courses']['courses'] : [];

            // 整理學習歷程
            if (!empty($data['my_courses_raw'])) {
                foreach ($data['my_courses_raw'] as $course) {
                    $start_ts = isset($course['startdate']) ? $course['startdate'] : 0;
                    $year = ($start_ts > 0) ? date('Y', $start_ts) : '未設定年份';
                    $data['history_by_year'][$year][] = $course;
                }
                krsort($data['history_by_year']);
            }

            // 整理可選修與課程狀態 (不再過濾已選課程)
            $my_courses_by_id = [];
            foreach ($data['my_courses_raw'] as $c) {
                $my_courses_by_id[$c['id']] = $c;
            }

            // 取得所有分類（確保包含階層資訊）
            $all_categories = call_moodle($moodle_url, $moodle_token, 'core_course_get_categories', ['addsubcategories' => 1]);
            $cat_info = [];
            if (is_array($all_categories) && !isset($all_categories['exception'])) {
                foreach ($all_categories as $cat) {
                    $cat_info[$cat['id']] = $cat;
                }
            }

            foreach ($all_courses as $course) {
                if ($course['id'] == 1)
                    continue;

                $cid = $course['id'];
                $cat_id = isset($course['categoryid']) ? $course['categoryid'] : null;

                // 初始為空
                $parent_name = '';
                $child_name = $course['categoryname'] ?? '';

                // 解析分類階層（向上追溯到頂層分類）
                if ($cat_id && isset($cat_info[$cat_id])) {
                    $curr_cat = $cat_info[$cat_id];
                    $child_name = $curr_cat['name'];

                    // 如果有父類別，持續往上找直到頂層
                    $temp_cat = $curr_cat;
                    while ($temp_cat['parent'] > 0 && isset($cat_info[$temp_cat['parent']])) {
                        $temp_cat = $cat_info[$temp_cat['parent']];
                    }
                    $parent_name = $temp_cat['name'];

                    // 如果目前就是頂層，則子類別為空
                    if ($curr_cat['id'] == $temp_cat['id']) {
                        $child_name = '';
                    }
                } elseif (isset($course['categoryname'])) {
                    $parent_name = $course['categoryname'];
                    $child_name = '';
                }

                // 確保 parent_name 有值
                if (empty($parent_name)) {
                    $parent_name = ($course['categoryname'] ?? '') ?: '其他';
                }

                $course['parent_category'] = $parent_name;
                $course['child_category'] = ($child_name && $child_name !== $parent_name) ? $child_name : '';
                $course['display_category'] = $course['child_category'] ? ($parent_name . ' - ' . $course['child_category']) : $parent_name;

                $course['is_enrolled'] = isset($my_courses_by_id[$cid]);
                if ($course['is_enrolled']) {
                    $course['progress'] = isset($my_courses_by_id[$cid]['progress']) ? $my_courses_by_id[$cid]['progress'] : 0;
                    $course['completed'] = isset($my_courses_by_id[$cid]['completed']) ? $my_courses_by_id[$cid]['completed'] : false;
                } else {
                    $course['progress'] = 0;
                    $course['completed'] = false;
                }
                $data['available_courses'][] = $course;
            }
        }

        // 2. 必修進度 (curriculum) - 依賴 my_courses_raw
        if ($type === 'all' || $type === 'curriculum') {
            $courses = ($type === 'curriculum' && empty($data['my_courses_raw'])) ? fetch_my_courses_simple($moodle_uid) : $data['my_courses_raw'];
            $data['curriculum_status'] = fetch_curriculum_status($courses);
        }

        // 3. 最新公告 (announcements)
        if ($type === 'all' || $type === 'announcements') {
            $courses = ($type === 'announcements' && empty($data['my_courses_raw'])) ? fetch_my_courses_simple($moodle_uid) : $data['my_courses_raw'];
            $data['latest_announcements'] = fetch_announcements($courses);
        }

        // 4. 成績資料 (grades)
        if ($type === 'all' || $type === 'grades') {
            $courses = ($type === 'grades' && empty($data['my_courses_raw'])) ? fetch_my_courses_simple($moodle_uid) : $data['my_courses_raw'];
            $data['grades'] = fetch_user_grades($moodle_uid, $courses);
        }

        // 如果是 type 為 all，則更新快取
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
 * 輔助函式：快速取得我的課程清單（用於分段載入時不需要完整資料的報表）
 */
function fetch_my_courses_simple($moodle_uid)
{
    global $moodle_url, $moodle_token;
    $result = call_moodle($moodle_url, $moodle_token, 'core_enrol_get_users_courses', ['userid' => $moodle_uid]);
    return is_array($result) ? $result : [];
}


/**
 * 取得必修課程進度
 */
function fetch_curriculum_status($my_courses_raw)
{
    global $moodle_url, $moodle_token;

    // 1. 取得所有可視課程 (用於確定必修藍圖)
    // 使用 search_courses 取得學生能看到的所有課程，這比逐個分類抓取更穩定且全面
    $search_results = call_moodle($moodle_url, $moodle_token, 'core_course_search_courses', [
        'criterianame' => 'search',
        'criteriavalue' => '', // 搜尋全部
        'page' => 0,
        'perpage' => 500       // 提高上限，確保抓到所有課程
    ]);

    $all_courses = [];
    if (isset($search_results['courses']) && is_array($search_results['courses'])) {
        $all_courses = $search_results['courses'];
    }

    // 2. 建立已選課程對照表
    $my_courses_lookup = [];
    if (!empty($my_courses_raw) && is_array($my_courses_raw)) {
        foreach ($my_courses_raw as $c) {
            if (is_array($c) && isset($c['id'])) {
                $my_courses_lookup[$c['id']] = $c;
            }
        }
    }

    // 3. 依分類分組課程
    $curriculum_status = [];

    // 取得全分類資訊以解析階層
    $all_categories = call_moodle($moodle_url, $moodle_token, 'core_course_get_categories', []);
    $cat_info = [];
    if (is_array($all_categories) && !isset($all_categories['exception'])) {
        foreach ($all_categories as $cat) {
            $cat_info[$cat['id']] = $cat;
        }
    }

    // 將課程放入對應的桶子
    foreach ($all_courses as $course) {
        if ($course['id'] == 1)
            continue;

        $cat_id = isset($course['categoryid']) ? $course['categoryid'] : null;
        $original_cat_name = $course['categoryname'] ?? '其他';
        $parent_name = '';
        $child_name = '';

        // 向上尋找頂層分類
        if ($cat_id && isset($cat_info[$cat_id])) {
            $curr_cat = $cat_info[$cat_id];
            $child_name = $curr_cat['name'];
            $temp_cat = $curr_cat;
            $visited = [];
            while ($temp_cat['parent'] > 0 && isset($cat_info[$temp_cat['parent']]) && !in_array($temp_cat['parent'], $visited)) {
                $visited[] = $temp_cat['id'];
                $temp_cat = $cat_info[$temp_cat['parent']];
            }
            $parent_name = $temp_cat['name'];
            if ($curr_cat['id'] == $temp_cat['id']) {
                $child_name = '';
            }
        }

        if (empty($parent_name)) {
            $parent_name = $original_cat_name;
        }

        $group_name = $parent_name;
        $display_cat_name = ($child_name && $child_name !== $parent_name) ? ($parent_name . ' - ' . $child_name) : $parent_name;

        $tid = $course['id'];
        $status = 'red';

        if (isset($my_courses_lookup[$tid])) {
            $user_course = $my_courses_lookup[$tid];
            $progress = isset($user_course['progress']) ? $user_course['progress'] : 0;
            $is_completed = isset($user_course['completed']) ? $user_course['completed'] : false;

            if ($progress >= 100 || $is_completed) {
                $status = 'green';
            } else {
                $status = 'yellow';
            }
        }

        $course_data = [
            'id' => $tid,
            'fullname' => $course['fullname'],
            'status' => $status,
            'category_name' => $display_cat_name
        ];

        if (!isset($curriculum_status[$group_name])) {
            $curriculum_status[$group_name] = [];
        }
        $curriculum_status[$group_name][] = $course_data;
    }

    // 移除沒有課程的分類，避免介面太亂
    foreach ($curriculum_status as $cat_name => $courses) {
        if (empty($courses)) {
            unset($curriculum_status[$cat_name]);
        }
    }

    return $curriculum_status;
}

/**
 * 取得最新公告
 */
function fetch_announcements($my_courses_raw)
{
    global $moodle_url, $moodle_token;

    $latest_announcements = [];

    if (empty($my_courses_raw)) {
        return $latest_announcements;
    }

    $course_names = [];
    foreach ($my_courses_raw as $c) {
        $course_names[$c['id']] = $c['fullname'];
    }

    $course_ids = array_column($my_courses_raw, 'id');
    $f_params = ['courseids' => $course_ids];
    $forums = call_moodle($moodle_url, $moodle_token, 'mod_forum_get_forums_by_courses', $f_params);

    if (!empty($forums)) {
        $p_requests = [];
        foreach ($forums as $forum) {
            if ($forum['type'] === 'news' || strpos($forum['name'], '公告') !== false) {
                $p_requests[] = [
                    'key' => $forum['id'],
                    'func' => 'mod_forum_get_forum_discussions',
                    'params' => ['forumid' => $forum['id']]
                ];
            }
        }

        if (!empty($p_requests)) {
            $p_results = call_moodle_parallel($moodle_url, $moodle_token, $p_requests);

            foreach ($forums as $forum) {
                $forum_id = $forum['id'];
                if (isset($p_results[$forum_id]['discussions'])) {
                    foreach ($p_results[$forum_id]['discussions'] as $disc) {
                        $latest_announcements[] = [
                            'course_name' => isset($course_names[$forum['course']]) ? $course_names[$forum['course']] : '全站公告',
                            'subject' => $disc['subject'],
                            'author' => $disc['userfullname'],
                            'date' => $disc['created'],
                            'link' => $moodle_url . '/mod/forum/discuss.php?d=' . $disc['discussion']
                        ];
                    }
                }
            }
        }
    }

    usort($latest_announcements, function ($a, $b) {
        return $b['date'] - $a['date'];
    });

    return array_slice($latest_announcements, 0, 5);
}

/**
 * 取得使用者課程成績 (最近5門)
 * @param int $moodle_uid Moodle 使用者 ID
 * @param array $my_courses 使用者課程列表
 * @return array 課程成績資料
 */
function fetch_user_grades($moodle_uid, $my_courses)
{
    global $moodle_url, $moodle_token;

    $grades = [];

    if (empty($my_courses)) {
        return $grades;
    }

    // 只取最近5門課程的成績 (按開始日期排序)
    $recent_courses = array_slice($my_courses, 0, 5);

    // 平行請求每門課程的成績
    $parallel_requests = [];
    foreach ($recent_courses as $course) {
        $parallel_requests[] = [
            'key' => 'grade_' . $course['id'],
            'func' => 'gradereport_user_get_grade_items',
            'params' => [
                'courseid' => $course['id'],
                'userid' => $moodle_uid
            ]
        ];
    }

    $grade_results = call_moodle_parallel($moodle_url, $moodle_token, $parallel_requests);

    // 處理成績結果
    foreach ($recent_courses as $course) {
        $key = 'grade_' . $course['id'];
        $grade_data = isset($grade_results[$key]) ? $grade_results[$key] : null;

        $course_grade = [
            'course_id' => $course['id'],
            'course_name' => $course['fullname'],
            'grade' => null,
            'grade_max' => 100,
            'grade_formatted' => '-'
        ];

        // 找出課程總成績 (itemtype = 'course')
        if ($grade_data && isset($grade_data['usergrades']) && !empty($grade_data['usergrades'])) {
            $user_grade = $grade_data['usergrades'][0];
            if (isset($user_grade['gradeitems'])) {
                foreach ($user_grade['gradeitems'] as $item) {
                    if (isset($item['itemtype']) && $item['itemtype'] === 'course') {
                        $course_grade['grade'] = isset($item['graderaw']) ? round($item['graderaw'], 1) : null;
                        $course_grade['grade_max'] = isset($item['grademax']) ? $item['grademax'] : 100;
                        $course_grade['grade_formatted'] = isset($item['gradeformatted']) ? $item['gradeformatted'] : '-';
                        break;
                    }
                }
            }
        }

        // 只加入有成績的課程
        if ($course_grade['grade'] !== null) {
            $grades[] = $course_grade;
        }
    }

    return $grades;
}
?>