<?php
// includes/moodle_api.php - Moodle 資料抓取

/**
 * 確保 Moodle 使用者存在 (若不存在則建立)
 * @param string $username 帳號
 * @param string $fullname 全名
 * @param string $email Email
 * @return array|null 成功回傳使用者資料陣列(含id)，失敗回傳 null
 */
/**
 * 確保 Moodle 使用者存在 (若不存在則建立)
 * @param string $username 帳號
 * @param string $fullname 全名
 * @param string $email Email
 * @param string $institution 機構 (選填)
 * @return array|null 成功回傳使用者資料陣列(含id)，失敗回傳 null
 */
function ensure_moodle_user_exists($username, $fullname, $email, $institution = '')
{
    global $moodle_url, $moodle_token;

    // 1. 準備建立資料
    $last_name = mb_substr($fullname, 0, 1, "utf-8");
    $first_name = mb_substr($fullname, 1, null, "utf-8");
    if (empty($first_name))
        $first_name = $last_name;

    // 一律使用符合 Moodle 規定之強密碼
    $moodle_password = "Tzuchi!" . bin2hex(random_bytes(4)) . "2025";

    $user_payload = [
        'username' => $username,
        'password' => $moodle_password,
        'firstname' => $first_name,
        'lastname' => $last_name,
        'email' => $email,
        'auth' => 'manual',
    ];

    if (!empty($institution)) {
        $user_payload['institution'] = $institution;
    }

    $moodle_user_data = [
        'users' => [$user_payload]
    ];

    // 2. 呼叫 Moodle API 建立
    $serverurl = $moodle_url . '/webservice/rest/server.php' . '?wstoken=' . $moodle_token . '&wsfunction=core_user_create_users&moodlewsrestformat=json';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $serverurl);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($moodle_user_data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($curl);

    // Debug logging
    if ($resp === false) {
        error_log("ensure_moodle_user_exists curl error: " . curl_error($curl));
    } else {
        error_log("ensure_moodle_user_exists resp: " . substr($resp, 0, 500));
    }

    curl_close($curl);

    // 3. 阻塞驗證 (Blocking Verification) - 確保帳號建立完成
    // 剛發出建立指令，Moodle 可能還在處理，這裡我們跑一個小迴圈去詢問
    $max_retries = 5;

    for ($i = 0; $i < $max_retries; $i++) {
        if ($i > 0)
            usleep(500000); // 0.5s

        $u_params = ['field' => 'username', 'values' => [$username]];
        $check_result = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);

        if (is_array($check_result) && !empty($check_result) && isset($check_result[0]['id'])) {
            return $check_result; // 驗證成功，回傳包含 ID 的使用者資料
        }
    }

    error_log("Warning: ensure_moodle_user_exists verification timed out for '$username'");
    return null;
}



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

    try {
        // 步驟 1: 取得 Moodle 使用者 ID (通常已在 Session 中)
        if (isset($_SESSION['moodle_uid'])) {
            $moodle_uid = $_SESSION['moodle_uid'];
        } else {
            // 🚀 關鍵優化：增加 ID 查詢重試機制，區分「逾時」與「不存在」
            $moodle_users = null;
            $max_id_retries = 3;
            $u_params = ['field' => 'username', 'values' => [$_SESSION['username']]];

            for ($retry = 0; $retry < $max_id_retries; $retry++) {
                if ($retry > 0)
                    usleep(500000); // 0.5s
                $moodle_users = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);

                // 如果成功抓到資料且沒有錯誤，直接跳出
                if (is_array($moodle_users) && isset($moodle_users[0]['id'])) {
                    break;
                }

                // 如果是逾時，繼續重試一次
                if (isset($moodle_users['error']) && $moodle_users['error'] === 'MOODLE_TIMEOUT') {
                    continue;
                }

                // 如果不是逾時也不是成功，可能是真的查無此人，進修復邏輯
                break;
            }

            if (!is_array($moodle_users) || empty($moodle_users) || !isset($moodle_users[0]['id'])) {
                // 如果最後結果還是逾時，直接拋出逾時錯誤，不要進修復（避免 Race Condition）
                if (isset($moodle_users['error']) && $moodle_users['error'] === 'MOODLE_TIMEOUT') {
                    $data['error'] = 'MOODLE_TIMEOUT';
                    return $data;
                }

                // [JIT Auto-Repair] 確定查無 Moodle 帳號，嘗試自動修復
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
                    // 使用共用的確保存在函式
                    $fullname = $local_user['fullname'] ?? $_SESSION['username'];
                    $input_user = $local_user['username'];
                    $email = $local_user['email'] ?? ($input_user . "@example.com");
                    $institution = $local_user['institution'] ?? '';

                    $moodle_users = ensure_moodle_user_exists($input_user, $fullname, $email, $institution);
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
 * 將使用者加入 Moodle 群組 (Cohort)
 * @param string $username 使用者帳號 (Portal username)
 * @param string $cohort_idnumber 群組 ID Number
 * @return array success or error
 */
function moodle_add_cohort_member($username, $cohort_idnumber)
{
    global $moodle_url, $moodle_token;

    // 1. 取得使用者的 Moodle ID
    // 這裡我們假設使用者已經存在，因為通常是在 ensure_moodle_user_exists 之後呼叫
    // 但為了保險，我們可以再查一次，或者把 ensure 的結果存起來傳進來
    // 為了簡化介面，我們這裡快速查一次
    $u_params = ['field' => 'username', 'values' => [$username]];
    $users = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);

    if (isset($users['error']))
        return $users;
    if (empty($users) || !isset($users[0]['id']))
        return ['error' => 'User not found in Moodle'];

    $userid = $users[0]['id'];

    // 2. 呼叫 API 加入 Cohort
    $members = [
        [
            'cohorttype' => ['type' => 'idnumber', 'value' => $cohort_idnumber],
            'usertype' => ['type' => 'id', 'value' => $userid]
        ]
    ];

    $result = call_moodle($moodle_url, $moodle_token, 'core_cohort_add_cohort_members', ['members' => $members]);

    // API 回傳 null 表示成功 (void)，有錯通常會回傳 exception array 或是我們 call_moodle 的 error
    if ($result === null || (is_array($result) && empty($result)) || (is_array($result) && isset($result['warnings']) && empty($result['warnings']))) {
        return ['success' => true];
    }

    return $result;
}

/**
 * 分配 Moodle 角色 (Course Creator)
 * @param string $username 使用者 Moodle 帳號
 * @param int $category_id 類別 ID
 * @param string $role_shortname 角色名稱 (預設 coursecreator)
 */
function moodle_assign_role($username, $category_id, $role_shortname = 'coursecreator')
{
    global $moodle_url, $moodle_token;

    // 1. 取得使用者 ID
    $u_params = ['field' => 'username', 'values' => [$username]];
    $users = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);
    if (empty($users) || !isset($users[0]['id']))
        return ['error' => 'User not found'];
    $userid = $users[0]['id'];

    // 2. 取得角色 ID
    // 改用 Direct SQL 查詢，因為 core_role_get_roles 可能無法使用
    global $db_host, $db_user, $db_pass;
    $moodle_db_name = 'moodle';
    $moodle_prefix = 'mdl_';

    $roleid = 0;

    try {
        $mconn = new mysqli($db_host, $db_user, $db_pass, $moodle_db_name);
        if (!$mconn->connect_error) {
            $mconn->set_charset('utf8mb4');
            $stmt = $mconn->prepare("SELECT id FROM {$moodle_prefix}role WHERE shortname = ?");
            $stmt->bind_param("s", $role_shortname);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $roleid = $row['id'];
            }
            $stmt->close();
            $mconn->close();
        }
    } catch (Exception $e) {
        error_log("moodle_assign_role DB lookup error: " . $e->getMessage());
    }
    if ($roleid === 0)
        return ['error' => "Role '$role_shortname' not found"];

    // 3. 取得 Context ID (Category Context)
    // Moodle API core_role_assign_roles 支援直接使用 contextlevel 和 instanceid
    // contextlevel: 'coursecat' (或 'block', 'course', 'module', 'user', 'system')
    // instanceid: category_id

    $assignments = [
        [
            'roleid' => $roleid,
            'userid' => $userid,
            'contextlevel' => 'coursecat',
            'instanceid' => $category_id
        ]
    ];

    $result = call_moodle($moodle_url, $moodle_token, 'core_role_assign_roles', ['assignments' => $assignments]);

    // void return on success
    if ($result === null || empty($result))
        return ['success' => true];
    return $result;
}

/**
 * 移除 Moodle 角色
 */
function moodle_unassign_role($username, $category_id, $role_shortname = 'coursecreator')
{
    global $moodle_url, $moodle_token;

    // 1. 取得使用者 ID
    $u_params = ['field' => 'username', 'values' => [$username]];
    $users = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);
    if (empty($users) || !isset($users[0]['id']))
        return ['error' => 'User not found'];
    $userid = $users[0]['id'];

    // 2. 取得角色 ID
    global $db_host, $db_user, $db_pass;
    $moodle_db_name = 'moodle';
    $moodle_prefix = 'mdl_';

    $roleid = 0;

    try {
        $mconn = new mysqli($db_host, $db_user, $db_pass, $moodle_db_name);
        if (!$mconn->connect_error) {
            $mconn->set_charset('utf8mb4');
            $stmt = $mconn->prepare("SELECT id FROM {$moodle_prefix}role WHERE shortname = ?");
            $stmt->bind_param("s", $role_shortname);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $roleid = $row['id'];
            }
            $stmt->close();
            $mconn->close();
        }
    } catch (Exception $e) {
        error_log("moodle_unassign_role DB lookup error: " . $e->getMessage());
    }
    if ($roleid === 0)
        return ['error' => "Role '$role_shortname' not found"];

    // 3. 執行 Unassign
    $unassignments = [
        [
            'roleid' => $roleid,
            'userid' => $userid,
            'contextlevel' => 'coursecat',
            'instanceid' => $category_id
        ]
    ];

    $result = call_moodle($moodle_url, $moodle_token, 'core_role_unassign_roles', ['unassignments' => $unassignments]);

    if ($result === null || empty($result))
        return ['success' => true];
    return $result;
}

/**
 * 刪除 Moodle 使用者
 * @param string $username 使用者帳號
 * @return array success or error
 */
function moodle_delete_user($username)
{
    global $moodle_url, $moodle_token;

    // 1. 取得使用者 ID
    $u_params = ['field' => 'username', 'values' => [$username]];
    $users = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);

    if (empty($users) || !isset($users[0]['id'])) {
        // 使用者不存在，視為已刪除成功
        return ['success' => true, 'message' => 'User not found, skipped'];
    }
    $userid = $users[0]['id'];

    // 2. 呼叫 API 刪除
    $result = call_moodle($moodle_url, $moodle_token, 'core_user_delete_users', ['userids' => [$userid]]);

    // core_user_delete_users returns null on success
    if ($result === null || empty($result)) {
        return ['success' => true];
    }
    return $result;
}

/**
 * 更新 Moodle 使用者資料
 * @param string $username 使用者帳號
 * @param array $data 欲更新的資料 (firstname, lastname, email)
 * @return array success or error
 */
function moodle_update_user($username, $data = [])
{
    global $moodle_url, $moodle_token;

    // 1. 取得使用者 ID
    $u_params = ['field' => 'username', 'values' => [$username]];
    $users = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);

    if (empty($users) || !isset($users[0]['id'])) {
        return ['error' => 'User not found in Moodle'];
    }
    $userid = $users[0]['id'];

    // 2. 準備更新資資料
    $update_payload = ['id' => $userid];

    if (isset($data['fullname'])) {
        $fullname = $data['fullname'];
        $last_name = mb_substr($fullname, 0, 1, "utf-8");
        $first_name = mb_substr($fullname, 1, null, "utf-8");
        if (empty($first_name))
            $first_name = $last_name;

        $update_payload['firstname'] = $first_name;
        $update_payload['lastname'] = $last_name;
    }

    if (isset($data['email'])) {
        $update_payload['email'] = $data['email'];
    }

    // 3. 呼叫 API 更新
    $result = call_moodle($moodle_url, $moodle_token, 'core_user_update_users', ['users' => [$update_payload]]);

    if ($result === null || empty($result)) {
        return ['success' => true];
    }
    return $result;
}

/**
 * 取得使用者在 Moodle 的角色與對應的 Category ID
 * (取代原本 scripts/get_user_category.php 的 CLI 邏輯)
 * 
 * @param string $username
 * @return array ['category_id' => int, 'portal_role' => string]
 */
function moodle_get_user_role_context($username)
{
    global $moodle_url, $moodle_token;

    $result = [
        'category_id' => 0,
        'portal_role' => 'student'
    ];

    // 1. 取得使用者 ID
    $u_params = ['field' => 'username', 'values' => [$username]];
    $users = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);
    if (empty($users) || !isset($users[0]['id']))
        return $result;
    $userid = $users[0]['id'];

    global $db_host, $db_user, $db_pass; // From includes/config.php
    $moodle_db_name = 'moodle';
    $moodle_prefix = 'mdl_';

    try {
        $conn = new mysqli($db_host, $db_user, $db_pass, $moodle_db_name);
        if ($conn->connect_error) {
            error_log("Connect Moodle DB failed: " . $conn->connect_error);
            return $result;
        }
        $conn->set_charset('utf8mb4');

        // 1. Get User ID
        $stmt = $conn->prepare("SELECT id FROM {$moodle_prefix}user WHERE username = ? AND deleted = 0");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $user_row = $res->fetch_assoc();
        $stmt->close();

        if (!$user_row) {
            $conn->close();
            return $result;
        }
        $userid = $user_row['id'];

        // 2. Get Roles
        // Context Levels: 40=Category, 10=System
        // We accept hospitaladmin or manager roles at these levels.

        $sql = "
            SELECT ra.id, r.shortname, c.contextlevel, c.instanceid
            FROM {$moodle_prefix}role_assignments ra
            JOIN {$moodle_prefix}role r ON r.id = ra.roleid
            JOIN {$moodle_prefix}context c ON c.id = ra.contextid
            WHERE ra.userid = ? 
              AND (c.contextlevel = 40 OR c.contextlevel = 10)
            ORDER BY r.sortorder ASC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $assignments_res = $stmt->get_result();

        $found_teacher = false;
        $target_cat = 0;

        while ($ra = $assignments_res->fetch_assoc()) {
            // 1. Hospital Admin / Manager (Specific Category or System)
            if (($ra['shortname'] === 'hospitaladmin' || $ra['shortname'] === 'manager')) {
                if ($ra['contextlevel'] == 40) {
                    $result['portal_role'] = 'hospital_admin';
                    $result['category_id'] = (int) $ra['instanceid'];
                    break; // Found highest priority (Category Manager), stop.
                } else if ($ra['contextlevel'] == 10) {
                    // System Manager -> Treat as Admin or Hospital Admin 0?
                    // Usually System Admin -> Admin. But 'manager' role at system level might just be a super-manager.
                    // Let's treat as hospital_admin with cat 0 (Global) -> logic in auth.php maps cat 0 to... something?
                    // Previous logic: if ($current_role === 'admin') ... 
                    // Let's map System Manager to 'hospital_admin' with cat 0 or keep searching.
                    // Actually, if they are System Manager, let's map to hospital_admin cat=0 (if that's handled)
                    // Or prioritize Category Manager if found?
                    // Let's hold this "System Manager" as a last resort if no Category Manager found.
                    if ($result['portal_role'] !== 'hospital_admin') {
                        $result['portal_role'] = 'hospital_admin'; // Or admin?
                        $result['category_id'] = 0;
                    }
                }
            }

            // 2. Course Creator
            if ($ra['shortname'] === 'coursecreator') {
                if (!$found_teacher) {
                    $found_teacher = true;
                    // System (10) -> Cat 0, Category (40) -> Cat ID
                    $target_cat = ($ra['contextlevel'] == 10) ? 0 : (int) $ra['instanceid'];
                } else {
                    if ($ra['contextlevel'] == 10) {
                        $target_cat = 0;
                    }
                }
            }
        }
        $stmt->close();
        $conn->close();

        // If not specific admin but found teacher
        if ($result['portal_role'] !== 'hospital_admin' && $found_teacher) {
            $result['portal_role'] = 'coursecreator';
            $result['category_id'] = $target_cat;
        }

    } catch (Exception $e) {
        error_log("moodle_get_user_role_context error: " . $e->getMessage());
    }

    return $result;
}
