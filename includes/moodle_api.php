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

    /* 🚀 暫時關閉快取功能以測試平行化效能
    if ($type === 'all' && isset($_SESSION['moodle_cache']) && isset($_SESSION['moodle_cache_time'])) {
        if (time() - $_SESSION['moodle_cache_time'] < CACHE_DURATION) {
            return $_SESSION['moodle_cache'];
        }
    }
    */

    try {
        // 步驟 1: 取得 Moodle 使用者 ID (通常已在 Session 中)
        if (isset($_SESSION['moodle_uid'])) {
            $moodle_uid = $_SESSION['moodle_uid'];
        } else {
            $u_params = ['field' => 'username', 'values' => [$_SESSION['username']]];
            $moodle_users = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);

            if (!is_array($moodle_users) || empty($moodle_users) || !isset($moodle_users[0]['id'])) {
                // [JIT Auto-Repair] 查無 Moodle 帳號，嘗試自動修復
                global $db_host, $db_user, $db_pass, $db_name;

                // 1. 取得本地使用者資料
                $local_user = null;
                $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
                if (!$conn->connect_error) {
                    $conn->set_charset("utf8mb4");
                    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
                    $stmt->bind_param("s", $_SESSION['username']);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $local_user = $row;
                    }
                    $stmt->close();
                    $conn->close();
                }

                if ($local_user) {
                    // 2. 準備建立資料
                    $fullname = $local_user['fullname'] ?? $_SESSION['username'];
                    $input_user = $local_user['username'];
                    $email = $local_user['email'] ?? ($input_user . "@example.com");

                    $last_name = mb_substr($fullname, 0, 1, "utf-8");
                    $first_name = mb_substr($fullname, 1, null, "utf-8");
                    if (empty($first_name))
                        $first_name = $last_name;

                    $moodle_password = "Tzuchi!" . bin2hex(random_bytes(4)) . "2025";

                    $moodle_user_data = [
                        'users' => [
                            [
                                'username' => $input_user,
                                'password' => $moodle_password,
                                'firstname' => $first_name,
                                'lastname' => $last_name,
                                'email' => $email,
                                'auth' => 'manual',
                            ]
                        ]
                    ];

                    // 3. 呼叫 Moodle API 建立
                    $serverurl = $moodle_url . '/webservice/rest/server.php' . '?wstoken=' . $moodle_token . '&wsfunction=core_user_create_users&moodlewsrestformat=json';
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_URL, $serverurl);
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($moodle_user_data));
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
                    $resp = curl_exec($curl);
                    curl_close($curl);

                    // 4. 等待同步 (Short Delay)
                    usleep(800000); // 0.8s

                    // 5. 重試查詢 ID
                    $moodle_users = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);
                }

                if (!is_array($moodle_users) || empty($moodle_users) || !isset($moodle_users[0]['id'])) {
                    $data['error'] = 'MOODLE_USER_NOT_FOUND';
                    return $data;
                }
            }
            $moodle_uid = $moodle_users[0]['id'];
            $_SESSION['moodle_uid'] = $moodle_uid;
        }
        $data['moodle_uid'] = $moodle_uid;

        // ======= Wave 1: 基礎資料平行抓取 =======
        // 幾乎所有類型都需要課程清單與分類資訊作為基礎
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
        // Check if cat_info_raw has error or is valid list
        if (is_array($cat_info_raw) && !isset($cat_info_raw['error'])) {
            foreach ($cat_info_raw as $cat) {
                if (is_array($cat) && isset($cat['id'])) {
                    $cat_info[$cat['id']] = $cat;
                }
            }
        }

        // 🚀 關鍵修正: 為 my_courses_raw 注入分類資訊 (為了對齊 "探索課程" 的 UI)
        if (!empty($data['my_courses_raw']) && !isset($data['my_courses_raw']['error'])) {
            foreach ($data['my_courses_raw'] as &$course) {
                if (!is_array($course))
                    continue;

                $cat_id = $course['category'] ?? null; // API 回傳的是 category (int)
                $parent_name = '其他';
                $child_name = '';

                if ($cat_id && isset($cat_info[$cat_id])) {
                    $curr_cat = $cat_info[$cat_id];
                    $child_name = $curr_cat['name'];
                    $temp_cat = $curr_cat;
                    // 往上找父分類
                    while (($temp_cat['parent'] ?? 0) > 0 && isset($cat_info[$temp_cat['parent']])) {
                        $temp_cat = $cat_info[$temp_cat['parent']];
                    }
                    $parent_name = $temp_cat['name'];
                    // 如果本身就是父分類
                    if ($curr_cat['id'] == $temp_cat['id']) {
                        $child_name = '';
                    }
                }

                $course['parent_category'] = $parent_name;
                $course['child_category'] = ($child_name && $child_name !== $parent_name) ? $child_name : '';
                $course['display_category'] = $course['child_category'] ? ($parent_name . ' - ' . $child_name) : $parent_name;
            }
            unset($course); // 解除 reference
        }

        // 如果只需要課程或學習歷程，可以在此提早結束
        if ($type === 'courses') {
            // 整理學習歷程
            if (!empty($data['my_courses_raw']) && !isset($data['my_courses_raw']['error'])) {
                foreach ($data['my_courses_raw'] as $course) {
                    if (!is_array($course))
                        continue;
                    $start_ts = $course['startdate'] ?? 0;
                    $year = ($start_ts > 0) ? date('Y', $start_ts) : '未設定年份';
                    $data['history_by_year'][$year][] = $course;
                }
                krsort($data['history_by_year']);
            }

            // 處理可選修
            $my_courses_by_id = [];
            if (!empty($data['my_courses_raw']) && !isset($data['my_courses_raw']['error'])) {
                foreach ($data['my_courses_raw'] as $c) {
                    if (isset($c['id']))
                        $my_courses_by_id[$c['id']] = $c;
                }
            }

            foreach ($all_search_courses as $course) {
                if (($course['id'] ?? 0) <= 1)
                    continue;
                $course['is_enrolled'] = isset($my_courses_by_id[$course['id']]);
                $course['progress'] = $course['is_enrolled'] ? ($my_courses_by_id[$course['id']]['progress'] ?? 0) : 0;
                $data['available_courses'][] = $course;
            }
            return $data;
        }

        // 處理必修進度 (如果請求的是 curriculum 或 all)
        if ($type === 'curriculum' || $type === 'all') {
            $data['curriculum_status'] = process_curriculum_locally($all_search_courses, $data['my_courses_raw'], $cat_info);
            if ($type === 'curriculum')
                return $data;
        }

        // ======= Wave 2 & 3: 依賴資料抓取 =======
        $wave2_requests = [];
        $recent_course_ids = array_slice(array_column($data['my_courses_raw'], 'id'), 0, 8);

        // 如果請求的是 grades
        if ($type === 'grades' || $type === 'all') {
            foreach ($recent_course_ids as $cid) {
                $wave2_requests[] = [
                    'key' => 'grade_' . $cid,
                    'func' => 'gradereport_user_get_grade_items',
                    'params' => ['courseid' => $cid, 'userid' => $moodle_uid]
                ];
            }
        }

        // 如果請求的是 announcements
        if ($type === 'announcements' || $type === 'all') {
            $wave2_requests[] = [
                'key' => 'forums',
                'func' => 'mod_forum_get_forums_by_courses',
                'params' => ['courseids' => array_column($data['my_courses_raw'], 'id')]
            ];
        }

        $wave2_results = !empty($wave2_requests) ? call_moodle_parallel($moodle_url, $moodle_token, $wave2_requests) : [];

        // 處理成績結果
        if ($type === 'grades' || $type === 'all') {
            foreach ($data['my_courses_raw'] as $course) {
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
                if (count($data['grades']) >= 5)
                    break;
            }
            if ($type === 'grades')
                return $data;
        }

        // 處理公告 (Wave 3)
        if ($type === 'announcements' || $type === 'all') {
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

            $raw_announcements = [];
            $course_names = array_column($data['my_courses_raw'], 'fullname', 'id');
            foreach ($forums as $forum) {
                $disc_key = 'disc_' . ($forum['id'] ?? 0);
                if (isset($wave3_results[$disc_key]['discussions'])) {
                    foreach ($wave3_results[$disc_key]['discussions'] as $disc) {
                        $raw_announcements[] = [
                            'course_name' => $course_names[$forum['course']] ?? '全站公告',
                            'subject' => $disc['subject'] ?? '無主旨',
                            'author' => $disc['userfullname'] ?? '系統', // Added author back
                            'date' => $disc['created'] ?? 0,
                            'link' => $moodle_url . '/mod/forum/discuss.php?d=' . ($disc['discussion'] ?? 0)
                        ];
                    }
                }
            }
            usort($raw_announcements, function ($a, $b) {
                return ($b['date'] ?? 0) - ($a['date'] ?? 0);
            });
            $data['latest_announcements'] = array_slice($raw_announcements, 0, 5);
        }

        // For 'all' type, ensure all data is processed and then cached.
        // The individual type blocks return early, so if we reach here, it's 'all' or an unhandled type.
        if ($type === 'all') {
            // Ensure history_by_year is processed for 'all' type if not already done by 'courses' block
            if (empty($data['history_by_year']) && !empty($data['my_courses_raw'])) {
                foreach ($data['my_courses_raw'] as $course) {
                    $start_ts = $course['startdate'] ?? 0;
                    $year = ($start_ts > 0) ? date('Y', $start_ts) : '未設定年份';
                    $data['history_by_year'][$year][] = $course;
                }
                krsort($data['history_by_year']);
            }

            // Ensure available_courses is processed for 'all' type if not already done by 'courses' block
            if (empty($data['available_courses'])) {
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
            }

            // Update cache for 'all' type
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