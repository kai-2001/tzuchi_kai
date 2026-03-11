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
    // 智慧解析：過濾掉開頭的英數字、底線或空格（例如 "0213北學生1" -> "北學生1"）
    $clean_name = preg_replace('/^[a-zA-Z0-9_\s]+/', '', $fullname);
    if (empty($clean_name)) {
        $clean_name = $fullname; // 全英數則不替換，保留原字串
    }

    $last_name = mb_substr($clean_name, 0, 1, "utf-8");
    $first_name = mb_substr($clean_name, 1, null, "utf-8");

    if (empty($first_name)) {
        $first_name = $last_name;
    }

    // 保險防護：如果提取後仍被認定為空值 (如字串 '0')，給予通用預設值
    if (empty($last_name) || $last_name === '0')
        $last_name = '-';
    if (empty($first_name) || $first_name === '0')
        $first_name = '-';

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
            // 🚀 從本地資料庫快速取得 Moodle UID，取代緩慢的 HTTP API
            global $db_host, $db_user, $db_pass;
            $m_conn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
            if (!$m_conn->connect_error) {
                $m_conn->set_charset('utf8mb4');
                $m_stmt = $m_conn->prepare("SELECT id FROM mdl_user WHERE username = ? AND deleted = 0");
                $m_stmt->bind_param("s", $_SESSION['username']);
                $m_stmt->execute();
                $m_res = $m_stmt->get_result();
                if ($m_row = $m_res->fetch_assoc()) {
                    $moodle_uid = $m_row['id'];
                }
                $m_stmt->close();
                $m_conn->close();
            }

            // [JIT Auto-Repair] 確定查無 Moodle 帳號，嘗試自動修復
            if (empty($moodle_uid)) {
                global $db_name;

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
                    if (is_array($moodle_users) && isset($moodle_users[0]['id'])) {
                        $moodle_uid = $moodle_users[0]['id'];
                    }
                }

                if (empty($moodle_uid)) {
                    $data['error'] = 'MOODLE_USER_NOT_FOUND';
                    return $data;
                }
            }

            // 嘗試將查詢到的 UID 寫入 $_SESSION (若尚未釋放鎖)
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['moodle_uid'] = $moodle_uid;
            }
        }
        $data['moodle_uid'] = $moodle_uid;

        // ======= Wave 1: 基礎資料平行抓取 (DB Native Fetch) =======
        $data['my_courses_raw'] = [];
        $cat_info_raw = [];
        $all_search_courses = [];
        $cat_info = [];
        $hidden_course_ids = [];

        try {
            global $db_host, $db_user, $db_pass;
            $moodle_db_name = 'moodle';
            $moodle_prefix = 'mdl_';

            $mconn = new mysqli($db_host, $db_user, $db_pass, $moodle_db_name);
            if (!$mconn->connect_error) {
                $mconn->set_charset('utf8mb4');

                // 1. 取得所有分類
                $sql_cat = "SELECT id, name, parent FROM {$moodle_prefix}course_categories ORDER BY sortorder ASC";
                $res_cat = $mconn->query($sql_cat);
                if ($res_cat) {
                    while ($row_cat = $res_cat->fetch_assoc()) {
                        $cat_info_raw[] = [
                            'id' => (int) $row_cat['id'],
                            'name' => $row_cat['name'],
                            'parent' => (int) $row_cat['parent']
                        ];
                        $cat_info[(int) $row_cat['id']] = $cat_info_raw[count($cat_info_raw) - 1]; // build lookup table
                    }
                }

                // 2. 取得用戶已報名的所有課程 (包含完成進度與失敗狀態)
                // 🚀 優化：利用原生 SQL 即時計算模組完成度，取代外部 API 且不受限於 Moodle 內部 cron 重算延遲
                $sql_my = "SELECT c.id, c.fullname, c.shortname, c.category, c.startdate, c.enddate, c.visible,
                                  cc.timecompleted,
                                  MAX(CASE WHEN cmc.completionstate = 3 THEN 1 ELSE 0 END) as has_failed_module,
                                  COUNT(DISTINCT CASE WHEN cm.completion > 0 THEN cm.id END) as total_required_modules,
                                  COUNT(DISTINCT CASE WHEN cm.completion > 0 AND cmc.completionstate IN (1, 2) THEN cm.id END) as completed_modules
                           FROM {$moodle_prefix}course c
                           JOIN {$moodle_prefix}enrol e ON e.courseid = c.id
                           JOIN {$moodle_prefix}user_enrolments ue ON ue.enrolid = e.id
                           LEFT JOIN {$moodle_prefix}course_completions cc ON cc.course = c.id AND cc.userid = ue.userid
                           LEFT JOIN {$moodle_prefix}course_modules cm ON cm.course = c.id
                           LEFT JOIN {$moodle_prefix}course_modules_completion cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = ue.userid
                           WHERE ue.userid = ? AND ue.status = 0
                           GROUP BY c.id, c.fullname, c.shortname, c.category, c.startdate, c.enddate, c.visible, cc.timecompleted";
                $stmt_my = $mconn->prepare($sql_my);
                $stmt_my->bind_param("i", $moodle_uid);
                $stmt_my->execute();
                $res_my = $stmt_my->get_result();
                while ($row_my = $res_my->fetch_assoc()) {
                    $c_id = (int) $row_my['id'];
                    $has_cc_completed = !empty($row_my['timecompleted']) && $row_my['timecompleted'] > 0;
                    $is_failed = !empty($row_my['has_failed_module']) && $row_my['has_failed_module'] > 0;

                    $total_req = (int) $row_my['total_required_modules'];
                    $comp_req = (int) $row_my['completed_modules'];

                    // 即使 cron 尚未寫入 cc.timecompleted，只要模組全達標我們就視為即時完成
                    $is_completed = false;
                    $progress = 0;

                    if ($has_cc_completed) {
                        $is_completed = true;
                        $progress = 100;
                    } else if ($total_req > 0) {
                        $progress = round(($comp_req / $total_req) * 100);
                        if ($comp_req >= $total_req && !$is_failed) {
                            $is_completed = true;
                            $progress = 100;
                        }
                    }

                    $data['my_courses_raw'][] = [
                        'id' => $c_id,
                        'fullname' => $row_my['fullname'],
                        'shortname' => $row_my['shortname'],
                        'category' => (int) $row_my['category'],
                        'startdate' => (int) $row_my['startdate'],
                        'enddate' => (int) $row_my['enddate'],
                        'visible' => (int) $row_my['visible'],
                        'progress' => $progress,
                        'completed' => $is_completed,
                        'failed' => $is_failed,
                        'is_hidden_course' => ((int) $row_my['visible'] === 0)
                    ];
                }
                $stmt_my->close();

                // 3. 獲取所有公開的課程加上有規則的隱藏課程
                // 修正：這裡不再限制 visible = 1，改為抓取所有課程，後續 PHP 迴圈會將沒有規則的隱藏課程過濾掉
                $sql_all = "SELECT id, fullname, shortname, category, startdate, enddate, visible FROM {$moodle_prefix}course WHERE id > 1";
                $res_all = $mconn->query($sql_all);
                if ($res_all) {
                    while ($row_all = $res_all->fetch_assoc()) {
                        $all_search_courses[] = [
                            'id' => (int) $row_all['id'],
                            'fullname' => $row_all['fullname'],
                            'shortname' => $row_all['shortname'],
                            'category' => (int) $row_all['category'],
                            'startdate' => (int) $row_all['startdate'],
                            'enddate' => (int) $row_all['enddate'],
                            'visible' => (int) $row_all['visible']
                        ];
                    }
                }

                // 4. 獲取所有隱藏課程 ID
                $sql_hidden = "SELECT id FROM {$moodle_prefix}course WHERE visible = 0";
                $res_hidden = $mconn->query($sql_hidden);
                if ($res_hidden) {
                    while ($row_hidden = $res_hidden->fetch_assoc()) {
                        $hidden_course_ids[] = (int) $row_hidden['id'];
                    }
                }

                $mconn->close();
            }
        } catch (Exception $e) {
            error_log("Error getting Moodle Data via DB Native Fetch: " . $e->getMessage());
        }
        // 合併已報名的隱藏課程到 my_courses_raw
        if (!empty($enrolled_hidden_courses)) {
            if (!is_array($data['my_courses_raw']) || isset($data['my_courses_raw']['error'])) {
                $data['my_courses_raw'] = [];
            }
            $existing_ids = array_column($data['my_courses_raw'], 'id');
            foreach ($enrolled_hidden_courses as $hc) {
                if (!in_array($hc['id'], $existing_ids)) {
                    $data['my_courses_raw'][] = $hc;
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

        // ===== 提取共同需要的 Portal 規則與學生受眾資料 =====
        // 查詢 course_visibility_rules 表
        $restricted_courses = [];
        $user_excluded_courses = [];
        $student_cohort_ids = [];
        $student_tag_ids = [];

        $portal_user_id = $_SESSION['user_id'] ?? null;

        try {
            global $db_host, $db_user, $db_pass, $db_name;
            $portal_conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
            if (!$portal_conn->connect_error) {
                $portal_conn->set_charset("utf8mb4");

                if ($portal_user_id) {
                    // (A) 取得所有有規則的課程
                    $rulesRes = $portal_conn->query("SELECT course_id, rule_snapshot FROM course_visibility_rules WHERE is_active = 1");
                    if ($rulesRes) {
                        while ($row = $rulesRes->fetch_assoc()) {
                            $snapshot = json_decode($row['rule_snapshot'], true);
                            if ($snapshot) {
                                $restricted_courses[(int) $row['course_id']] = $snapshot;
                            }
                        }
                    }

                    // (B) 取得該學生被排除的課程
                    $cvStmt = $portal_conn->prepare("SELECT DISTINCT course_id FROM course_visibility_exclusions WHERE user_id = ?");
                    if ($cvStmt) {
                        $cvStmt->bind_param("i", $portal_user_id);
                        $cvStmt->execute();
                        $cvRes = $cvStmt->get_result();
                        while ($row = $cvRes->fetch_assoc()) {
                            $user_excluded_courses[] = (int) $row['course_id'];
                        }
                        $cvStmt->close();
                    }

                    // (D) 取得該學生的標籤
                    $tagStmt = $portal_conn->prepare("SELECT tag_id FROM user_tags WHERE user_id = ?");
                    if ($tagStmt) {
                        $tagStmt->bind_param("i", $portal_user_id);
                        $tagStmt->execute();
                        $tagRes = $tagStmt->get_result();
                        while ($row = $tagRes->fetch_assoc()) {
                            $student_tag_ids[] = (int) $row['tag_id'];
                        }
                        $tagStmt->close();
                    }
                }

                $portal_conn->close();
            }

            // (C) 取得學生的 cohort 歸屬
            if ($moodle_uid) {
                $mconn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
                if (!$mconn->connect_error) {
                    $mconn->set_charset('utf8mb4');
                    $cohortStmt = $mconn->prepare("SELECT cohortid FROM mdl_cohort_members WHERE userid = ?");
                    if ($cohortStmt) {
                        $cohortStmt->bind_param("i", $moodle_uid);
                        $cohortStmt->execute();
                        $cohortRes = $cohortStmt->get_result();
                        while ($row = $cohortRes->fetch_assoc()) {
                            $student_cohort_ids[] = (int) $row['cohortid'];
                        }
                        $cohortStmt->close();
                    }
                    $mconn->close();
                }
            }
        } catch (Exception $e) {
            error_log("Error querying course_visibility_rules: " . $e->getMessage());
        }

        // 🚀 關鍵修正: 為 all_search_courses (選課中心所有課程) 注入分類資訊
        if (!empty($all_search_courses)) {
            foreach ($all_search_courses as &$course) {
                if (!is_array($course))
                    continue;

                $cat_id = $course['category'] ?? null;
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
            unset($course);
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

            // 處理可選修 - 只顯示已選課或有 course_visibility 權限的課程
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

                $cid = $course['id'];

                // 過濾隱藏課程 (沒有 active rules 且 visible=0)
                $is_hidden_in_moodle = in_array($cid, $hidden_course_ids);
                $has_rule = isset($restricted_courses[$cid]);
                if ($is_hidden_in_moodle && !$has_rule) {
                    continue;
                }

                $is_enrolled = isset($my_courses_by_id[$cid]);

                // 評估規則匹配 (與 get_catalog_courses.php 邏輯相同)
                if ($has_rule && !$is_enrolled) {
                    $snapshot = $restricted_courses[$cid];
                    $filter_groups = $snapshot['filter_groups'] ?? [];
                    $operators = $snapshot['operators'] ?? [];
                    $rule_tags = $snapshot['tag_ids'] ?? [];

                    // 1. 檢查標籤條件
                    $tag_matched = empty($rule_tags);
                    if (!empty($rule_tags)) {
                        $tag_matched = count(array_intersect($rule_tags, $student_tag_ids)) > 0;
                    }

                    // 2. 檢查群組條件
                    $group_matched = empty($filter_groups);
                    if (!$group_matched) {
                        $group_results = [];
                        foreach ($filter_groups as $group) {
                            if (empty($group)) {
                                $group_results[] = false;
                            } else {
                                $all_match = true;
                                foreach ($group as $cohort_id) {
                                    if (!in_array((int) $cohort_id, $student_cohort_ids)) {
                                        $all_match = false;
                                        break;
                                    }
                                }
                                $group_results[] = $all_match;
                            }
                        }

                        $final_result = $group_results[0] ?? false;
                        for ($i = 1; $i < count($group_results); $i++) {
                            $op = strtolower($operators[$i - 1] ?? 'or');
                            if ($op === 'and') {
                                $final_result = $final_result && $group_results[$i];
                            } else {
                                $final_result = $final_result || $group_results[$i];
                            }
                        }
                        $group_matched = $final_result;
                    }

                    // 不符合規則 → 不可見
                    if (!($tag_matched && $group_matched))
                        continue;

                    // 在排除名單中 → 不可見
                    if (in_array($cid, $user_excluded_courses))
                        continue;
                }

                $course['is_enrolled'] = $is_enrolled;
                $course['progress'] = $is_enrolled ? ($my_courses_by_id[$cid]['progress'] ?? 0) : 0;
                $data['available_courses'][] = $course;
            }
            return $data;
        }

        // 處理必修進度 (如果請求的是 curriculum 或 all)
        if ($type === 'curriculum' || $type === 'all') {
            $data['curriculum_status'] = process_curriculum_locally(
                $all_search_courses,
                $data['my_courses_raw'],
                $cat_info,
                $restricted_courses,
                $user_excluded_courses,
                $student_cohort_ids,
                $student_tag_ids,
                $hidden_course_ids
            );
            if ($type === 'curriculum')
                return $data;
        }

        // ======= Wave 2 & 3: 依賴資料抓取 (DB Native Fetch) =======
        $recent_course_ids = array_slice(array_column($data['my_courses_raw'], 'id'), 0, 8);
        $in_clause = count($recent_course_ids) > 0 ? implode(',', $recent_course_ids) : '0';

        try {
            global $db_host, $db_user, $db_pass;
            $mconn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
            if (!$mconn->connect_error) {
                $mconn->set_charset('utf8mb4');

                // 處理成績結果
                if ($type === 'grades' || $type === 'all') {
                    if (count($recent_course_ids) > 0) {
                        $sql_grades = "SELECT gi.courseid, gi.itemname, gi.grademax, gg.rawgrade AS graderaw
                                       FROM mdl_grade_items gi
                                       JOIN mdl_grade_grades gg ON gg.itemid = gi.id
                                       WHERE gg.userid = {$moodle_uid} 
                                       AND gi.itemtype = 'course' 
                                       AND gi.courseid IN ({$in_clause})";
                        $res_grades = $mconn->query($sql_grades);
                        if ($res_grades) {
                            $grade_lookup = [];
                            while ($row_grade = $res_grades->fetch_assoc()) {
                                $grade_lookup[$row_grade['courseid']] = $row_grade;
                            }

                            foreach ($data['my_courses_raw'] as $course) {
                                $cid = $course['id'];
                                if (isset($grade_lookup[$cid])) {
                                    $item = $grade_lookup[$cid];
                                    $grade_val = $item['graderaw'] !== null ? round($item['graderaw'], 1) : '-';
                                    $data['grades'][] = [
                                        'course_id' => $cid,
                                        'course_name' => $course['fullname'],
                                        'grade' => $grade_val,
                                        'grade_max' => $item['grademax'] ?? 100,
                                        'grade_formatted' => $grade_val
                                    ];
                                }
                                if (count($data['grades']) >= 5)
                                    break;
                            }
                        }
                    }
                    if ($type === 'grades') {
                        $mconn->close();
                        return $data;
                    }
                }

                // 處理公告
                if ($type === 'announcements' || $type === 'all') {
                    $all_course_ids = array_column($data['my_courses_raw'], 'id');
                    $all_in = count($all_course_ids) > 0 ? implode(',', $all_course_ids) : '1';
                    if (strpos($all_in, '1') === false) {
                        $all_in .= ',1';
                    }

                    $sql_anno = "SELECT d.id, d.name AS subject, d.timemodified, d.course as courseid, c.fullname as coursename, d.forum
                                 FROM mdl_forum_discussions d
                                 JOIN mdl_forum f ON d.forum = f.id
                                 JOIN mdl_course c ON d.course = c.id
                                 WHERE d.course IN ({$all_in}) AND f.type = 'news'
                                 ORDER BY d.timemodified DESC LIMIT 5";

                    $res_anno = $mconn->query($sql_anno);
                    if ($res_anno) {
                        while ($row_anno = $res_anno->fetch_assoc()) {
                            $data['latest_announcements'][] = [
                                'id' => (int) $row_anno['id'],
                                'title' => $row_anno['subject'],
                                'course_name' => $row_anno['coursename'],
                                'time' => date('Y-m-d', $row_anno['timemodified']),
                                'url' => rtrim($moodle_url, '/') . '/mod/forum/discuss.php?d=' . $row_anno['id']
                            ];
                        }
                    }
                }

                $mconn->close();
            }
        } catch (Exception $e) {
            error_log("DB Native Fetch Wave 2 Error: " . $e->getMessage());
        }

        // For 'all' type, ensure all data is processed and then cached.
        if ($type === 'all') {
            if (empty($data['history_by_year']) && !empty($data['my_courses_raw'])) {
                foreach ($data['my_courses_raw'] as $course) {
                    $start_ts = $course['startdate'] ?? 0;
                    $year = ($start_ts > 0) ? date('Y', $start_ts) : '未設定年份';
                    $data['history_by_year'][$year][] = $course;
                }
                krsort($data['history_by_year']);
            }

            if (empty($data['available_courses'])) {
                $my_courses_by_id = [];
                foreach ($data['my_courses_raw'] as $c) {
                    $my_courses_by_id[$c['id'] ?? 0] = $c;
                }


                foreach ($all_search_courses as $course) {
                    if (($course['id'] ?? 0) <= 1)
                        continue;

                    $cid = $course['id'];

                    // 過濾隱藏課程
                    $is_hidden_in_moodle = in_array($cid, $hidden_course_ids);
                    $has_rule = isset($restricted_courses[$cid]);
                    if ($is_hidden_in_moodle && !$has_rule) {
                        continue;
                    }

                    $is_enrolled = isset($my_courses_by_id[$cid]);

                    // 評估規則匹配 
                    if ($has_rule && !$is_enrolled) {
                        $snapshot = $restricted_courses[$cid];
                        $filter_groups = $snapshot['filter_groups'] ?? [];
                        $operators = $snapshot['operators'] ?? [];
                        $rule_tags = $snapshot['tag_ids'] ?? [];

                        // 1. 檢查標籤條件
                        $tag_matched = empty($rule_tags);
                        if (!empty($rule_tags)) {
                            $tag_matched = count(array_intersect($rule_tags, $student_tag_ids)) > 0;
                        }

                        // 2. 檢查群組條件
                        $group_matched = empty($filter_groups);
                        if (!$group_matched) {
                            $group_results = [];
                            foreach ($filter_groups as $group) {
                                if (empty($group)) {
                                    $group_results[] = false;
                                } else {
                                    $all_match = true;
                                    foreach ($group as $cohort_id) {
                                        if (!in_array((int) $cohort_id, $student_cohort_ids)) {
                                            $all_match = false;
                                            break;
                                        }
                                    }
                                    $group_results[] = $all_match;
                                }
                            }

                            $final_result = $group_results[0] ?? false;
                            for ($i = 1; $i < count($group_results); $i++) {
                                $op = strtolower($operators[$i - 1] ?? 'or');
                                if ($op === 'and') {
                                    $final_result = $final_result && $group_results[$i];
                                } else {
                                    $final_result = $final_result || $group_results[$i];
                                }
                            }
                            $group_matched = $final_result;
                        }

                        // 不符合規則 → 不可見
                        if (!($tag_matched && $group_matched))
                            continue;

                        // 在排除名單中 → 不可見
                        if (in_array($cid, $user_excluded_courses))
                            continue;
                    }

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
                    $course['is_enrolled'] = $is_enrolled;
                    $course['progress'] = $is_enrolled ? ($my_courses_by_id[$course['id']]['progress'] ?? 0) : 0;
                    $course['completed'] = $is_enrolled ? ($my_courses_by_id[$course['id']]['completed'] ?? false) : false;
                    $course['failed'] = $is_enrolled ? ($my_courses_by_id[$course['id']]['failed'] ?? false) : false;

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
 * 新版: 支援 portal_category_settings 的必修類別設定
 */
function process_curriculum_locally($all_courses, $my_courses_raw, $cat_info, $restricted_courses = [], $user_excluded_courses = [], $student_cohort_ids = [], $student_tag_ids = [], $hidden_course_ids = [])
{
    if (empty($all_courses))
        return [];

    // 建立使用者已選修課程對照表
    $my_courses_lookup = [];
    if (is_array($my_courses_raw)) {
        foreach ($my_courses_raw as $c) {
            $my_courses_lookup[$c['id'] ?? 0] = $c;
        }
    }

    // 取得類別設定 (必修類別、需通過次數等)
    $category_settings = get_category_settings_map();

    // 取得課程必修標記
    $mandatory_courses = get_mandatory_courses_map();

    $curriculum_status = [];
    $quotas = [];

    // 先按類別分組所有課程
    $courses_by_category = [];
    $seen_course_ids = []; // 追蹤已加入的課程ID

    $process_course = function ($course, $is_enrolled_only = false) use (&$courses_by_category, &$seen_course_ids, &$my_courses_lookup, &$mandatory_courses, &$category_settings, &$restricted_courses, &$user_excluded_courses, &$student_cohort_ids, &$student_tag_ids, &$hidden_course_ids) {
        $course_id = $course['id'] ?? 0;
        if ($course_id <= 1 || isset($seen_course_ids[$course_id]))
            return;

        $cat_id = $course['categoryid'] ?? $course['category'] ?? null;
        if (!$cat_id)
            return;

        $target_cat_id = $cat_id;
        $is_mandatory_cat = isset($category_settings[$target_cat_id]) && $category_settings[$target_cat_id]['is_mandatory_category'];

        $is_enrolled = isset($my_courses_lookup[$course_id]);

        $visible = isset($course['visible']) ? (int) $course['visible'] : 1;
        $is_hidden_in_moodle = $visible === 0 || in_array($course_id, $hidden_course_ids);
        $has_rule = isset($restricted_courses[$course_id]);
        $is_course_mandatory = isset($mandatory_courses[$course_id]) && $mandatory_courses[$course_id]['is_mandatory'] == 1;

        // 規則驗證邏輯
        if (!$is_enrolled) {
            if ($is_hidden_in_moodle && !$has_rule) {
                return; // 隱藏且無規則直接跳過
            }

            if ($has_rule) {
                $snapshot = $restricted_courses[$course_id];
                $filter_groups = $snapshot['filter_groups'] ?? [];
                $operators = $snapshot['operators'] ?? [];
                $rule_tags = $snapshot['tag_ids'] ?? [];

                // 1. 檢查標籤條件
                $tag_matched = empty($rule_tags);
                if (!empty($rule_tags)) {
                    $tag_matched = count(array_intersect($rule_tags, $student_tag_ids)) > 0;
                }

                // 2. 檢查群組條件
                $group_matched = empty($filter_groups);
                if (!$group_matched) {
                    $group_results = [];
                    foreach ($filter_groups as $group) {
                        if (empty($group)) {
                            $group_results[] = false;
                        } else {
                            $all_match = true;
                            foreach ($group as $cohort_id) {
                                if (!in_array((int) $cohort_id, $student_cohort_ids)) {
                                    $all_match = false;
                                    break;
                                }
                            }
                            $group_results[] = $all_match;
                        }
                    }

                    $final_result = $group_results[0] ?? false;
                    for ($i = 1; $i < count($group_results); $i++) {
                        $op = strtolower($operators[$i - 1] ?? 'or');
                        if ($op === 'and') {
                            $final_result = $final_result && $group_results[$i];
                        } else {
                            $final_result = $final_result || $group_results[$i];
                        }
                    }
                    $group_matched = $final_result;
                }

                // 不符合規則 → 不可見
                if (!($tag_matched && $group_matched)) {
                    return;
                }

                // 在排除名單中 → 不可見
                if (in_array($course_id, $user_excluded_courses)) {
                    return;
                }
            }
        }

        if (!isset($courses_by_category[$target_cat_id])) {
            $courses_by_category[$target_cat_id] = [];
        }

        $seen_course_ids[$course_id] = true;

        $status = '';
        if ($is_enrolled) {
            $uc = $my_courses_lookup[$course_id];
            if (($uc['failed'] ?? false)) {
                $status = 'red'; // 狀態 3: 已完成但未通過
            } elseif (($uc['progress'] ?? 0) >= 100 || ($uc['completed'] ?? false)) {
                $status = 'green';
            } else {
                $status = 'yellow';
            }
        }

        $courses_by_category[$target_cat_id][] = [
            'id' => $course_id,
            'fullname' => $course['fullname'] ?? $course['shortname'] ?? '未知課程',
            'status' => $status,
            'is_mandatory' => $is_course_mandatory,
            'display_order' => $mandatory_courses[$course_id]['display_order'] ?? 999
        ];
    };

    // 處理所有課程
    foreach ($all_courses as $course) {
        $process_course($course, false);
    }
    // 加入隱藏但已選的課程
    if (is_array($my_courses_raw)) {
        foreach ($my_courses_raw as $mc) {
            $process_course($mc, true);
        }
    }

    // 輔助函式：優化分類名稱顯示模式
    $generate_group_name = function ($cat_id) use (&$cat_info) {
        if (!isset($cat_info[$cat_id]))
            return '其他';

        $curr_cat = $cat_info[$cat_id];
        $child_name = $curr_cat['name'];
        $parent_id = $curr_cat['parent'] ?? 0;

        // 往上找一層 (而不是找最上層)
        if ($parent_id > 0 && isset($cat_info[$parent_id])) {
            $parent_name = $cat_info[$parent_id]['name'];
            if ($parent_name !== $child_name) {
                return $parent_name . ' - ' . $child_name;
            }
        }

        return $child_name;
    };

    // 分配到各必修領域
    foreach ($courses_by_category as $cat_id => $courses) {
        $settings = $category_settings[$cat_id] ?? null;
        $is_mandatory_cat = $settings && $settings['is_mandatory_category'];

        // ✨ 關鍵修正: 若非必修領域，不要把它放進 curriculum_status! 讓前端自己分類到「自由選修」
        if (!$is_mandatory_cat) {
            continue;
        }

        $group_name = $generate_group_name($cat_id);

        if ($settings['required_pass_count'] > 0) {
            $required_count = (int) $settings['required_pass_count'];

            $quotas[$group_name] = $required_count;

            $mandatory_course_ids_in_cat = [];
            foreach ($courses as $c) {
                if ($c['is_mandatory']) {
                    $mc_id = $c['id'];
                    $mc_info = $mandatory_courses[$mc_id] ?? [];
                    $mandatory_course_ids_in_cat[] = [
                        'id' => (int) $mc_id,
                        'order' => (int) ($mc_info['display_order'] ?? 999)
                    ];
                }
            }
            usort($mandatory_course_ids_in_cat, function ($a, $b) {
                return $a['order'] - $b['order'];
            });
            $mandatory_course_ids_in_cat = array_column($mandatory_course_ids_in_cat, 'id');

            $courses_lookup = [];
            foreach ($courses as $course) {
                $courses_lookup[$course['id']] = $course;
            }

            $lights = [];
            $filled = 0;
            $used_course_ids = [];

            // 1. 必填課
            foreach ($mandatory_course_ids_in_cat as $mc_id) {
                if ($filled >= $required_count)
                    break;

                if (isset($courses_lookup[$mc_id])) {
                    $course = $courses_lookup[$mc_id];
                    $lights[] = [
                        'id' => $course['id'],
                        'fullname' => $course['fullname'],
                        'status' => $course['status'],
                        'category_name' => $group_name,
                        'is_mandatory_section' => true
                    ];
                    $used_course_ids[$mc_id] = true;
                    $filled++;
                }
            }

            // 2. 綠燈優先補
            if ($filled < $required_count) {
                foreach ($courses as $course) {
                    if ($filled >= $required_count)
                        break;
                    if (!isset($used_course_ids[$course['id']]) && $course['status'] === 'green') {
                        $lights[] = [
                            'id' => $course['id'],
                            'fullname' => $course['fullname'],
                            'status' => $course['status'],
                            'category_name' => $group_name,
                            'is_mandatory_section' => false // 這裡改成 false 變成領域選修的進度列
                        ];
                        $used_course_ids[$course['id']] = true;
                        $filled++;
                    }
                }
            }

            // 3. 黃燈次之補
            if ($filled < $required_count) {
                foreach ($courses as $course) {
                    if ($filled >= $required_count)
                        break;
                    if (!isset($used_course_ids[$course['id']]) && $course['status'] === 'yellow') {
                        $lights[] = [
                            'id' => $course['id'],
                            'fullname' => $course['fullname'],
                            'status' => $course['status'],
                            'category_name' => $group_name,
                            'is_mandatory_section' => false // 領域選修
                        ];
                        $used_course_ids[$course['id']] = true;
                        $filled++;
                    }
                }
            }

            // 4. 其他紅燈補
            while ($filled < $required_count) {
                $lights[] = [
                    'id' => 0,
                    'fullname' => '請挑選此領域中的課程修課',
                    'status' => 'red',
                    'category_name' => $group_name,
                    'available_count' => count($courses) - count($used_course_ids),
                    'is_mandatory_section' => false // 領域選修空窗
                ];
                $filled++;
            }

            // 放剩下的課進去，但 is_mandatory_section = false
            foreach ($courses as $course) {
                if (!isset($used_course_ids[$course['id']])) {
                    $lights[] = [
                        'id' => $course['id'],
                        'fullname' => $course['fullname'],
                        'status' => $course['status'],
                        'category_name' => $group_name,
                        'is_mandatory_section' => false
                    ];
                }
            }

            if (!isset($curriculum_status[$group_name])) {
                $curriculum_status[$group_name] = [];
            }
            $curriculum_status[$group_name] = array_merge($lights, $curriculum_status[$group_name]);

        } else {
            // required_pass_count === 0 處理
            if (!isset($curriculum_status[$group_name])) {
                $curriculum_status[$group_name] = [];
            }
            foreach ($courses as $course) {
                $curriculum_status[$group_name][] = [
                    'id' => $course['id'],
                    'fullname' => $course['fullname'],
                    'status' => $course['status'],
                    'category_name' => $group_name,
                    'is_mandatory_section' => false
                ];
            }
        }
    }

    // 補空領域 (因為有些領域如果沒有任何課程，上面的 foreach 不會遍歷)
    foreach ($category_settings as $cat_id => $settings) {
        if ($settings['is_mandatory_category'] && $settings['required_pass_count'] > 0) {
            $group_name = $generate_group_name($cat_id);
            if (empty($curriculum_status[$group_name])) {
                $required_count = (int) $settings['required_pass_count'];

                $quotas[$group_name] = $required_count;

                $lights = [];
                for ($i = 0; $i < $required_count; $i++) {
                    $lights[] = [
                        'id' => 0,
                        'fullname' => '該領域目前無開放課程',
                        'status' => 'red',
                        'category_name' => $group_name,
                        'available_count' => 0,
                        'is_mandatory_section' => false
                    ];
                }
                $curriculum_status[$group_name] = $lights;
            }
        }
    }

    return ['status' => $curriculum_status, 'quotas' => $quotas];
}

/**
 * 取得所有類別設定的對照表，若有提供使用者，則僅回傳專屬於該使用者的必修要求
 */
function get_category_settings_map($username = null)
{
    try {
        $conn = get_portal_db();
        $map = [];

        // 如果沒有提供，嘗試從 session 取
        if (empty($username) && isset($_SESSION['username'])) {
            $username = $_SESSION['username'];
        }

        if (!empty($username)) {
            // 查詢這個學生的專屬必修領域 (由 portal_category_requirements 決定)
            $stmt = $conn->prepare("
                SELECT r.moodle_category_id, r.required_pass_count, r.deadline,
                       s.is_mandatory_category, s.period_months, s.require_order, s.visibility
                FROM portal_category_requirements r
                LEFT JOIN portal_category_settings s ON r.moodle_category_id = s.moodle_category_id
                JOIN users u ON r.user_id = u.id
                WHERE u.username = ?
            ");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    // 只要在 requirements 裡有紀錄，對這名使用者而言就是必定成立的必修
                    $row['is_mandatory_category'] = 1;
                    $map[$row['moodle_category_id']] = $row;
                }
                $stmt->close();
            }
        } else {
            // 退回全域設定 (這僅作為安全備用，正常學生登入後不應走這段)
            $result = $conn->query("SELECT * FROM portal_category_settings WHERE is_mandatory_category = 1");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $map[$row['moodle_category_id']] = $row;
                }
            }
        }

        $conn->close();
        return $map;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 取得所有必修課程的對照表
 */
function get_mandatory_courses_map()
{
    try {
        $conn = get_portal_db();
        $result = $conn->query("SELECT * FROM portal_mandatory_courses");

        $map = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $map[$row['moodle_course_id']] = $row;
            }
        }
        $conn->close();
        return $map;
    } catch (Exception $e) {
        return [];
    }
}
function moodle_get_user_role_context($username)
{
    $result = [
        'category_id' => 0,
        'portal_role' => 'student',
        'moodle_uid' => null
    ];

    global $db_host, $db_user, $db_pass;
    $moodle_db_name = 'moodle';
    $moodle_prefix = 'mdl_';

    try {
        $conn = new mysqli($db_host, $db_user, $db_pass, $moodle_db_name);
        if ($conn->connect_error) {
            error_log("Connect Moodle DB failed: " . $conn->connect_error);
            return null;  // 回傳 null 表示連線失敗，不是預設 student
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
        $result['moodle_uid'] = $userid;

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
        $coursecreator_cat_ids = []; // 收集所有 coursecreator 指派的類別 ID

        while ($ra = $assignments_res->fetch_assoc()) {
            // 1. Hospital Admin / Manager (Specific Category or System)
            if (($ra['shortname'] === 'hospitaladmin' || $ra['shortname'] === 'manager')) {
                if ($ra['contextlevel'] == 40) {
                    $result['portal_role'] = 'hospital_admin';
                    $result['category_id'] = (int) $ra['instanceid'];
                    break; // Found highest priority (Category Manager), stop.
                } else if ($ra['contextlevel'] == 10) {
                    if ($result['portal_role'] !== 'hospital_admin') {
                        $result['portal_role'] = 'hospital_admin';
                        $result['category_id'] = 0;
                    }
                }
            }

            // 2. Course Creator — 收集所有指派的類別
            if ($ra['shortname'] === 'coursecreator') {
                $found_teacher = true;
                if ($ra['contextlevel'] == 40) {
                    $cat_id = (int) $ra['instanceid'];
                    if (!in_array($cat_id, $coursecreator_cat_ids)) {
                        $coursecreator_cat_ids[] = $cat_id;
                    }
                    if ($target_cat === 0) {
                        $target_cat = $cat_id;
                    }
                } else if ($ra['contextlevel'] == 10) {
                    // System level coursecreator
                    $target_cat = 0;
                }
            }
        }
        $stmt->close();
        $conn->close();

        // If not specific admin but found teacher
        if ($result['portal_role'] !== 'hospital_admin' && $found_teacher) {
            $result['portal_role'] = 'coursecreator';
            $result['category_id'] = $target_cat;
            $result['coursecreator_category_ids'] = $coursecreator_cat_ids;
        }

    } catch (Exception $e) {
        error_log("moodle_get_user_role_context error: " . $e->getMessage());
    }

    return $result;
}

/**
 * 新增 Moodle 使用者至 Cohort 群組
 * @param string $username 使用者帳號
 * @param string $cohort_idnumber 群組 ID
 * @return array|null 
 */
function moodle_add_cohort_member($username, $cohort_idnumber)
{
    global $moodle_url, $moodle_token;

    try {
        // 1. 取得使用者 ID
        $u_params = ['field' => 'username', 'values' => [$username]];
        $users = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);

        if (empty($users) || !isset($users[0]['id'])) {
            return ['error' => 'User not found in Moodle'];
        }
        $userid = $users[0]['id'];

        // 2. 呼叫 API 加入群組
        $cohort_params = [
            'members' => [
                [
                    'cohorttype' => [
                        'type' => 'idnumber',
                        'value' => $cohort_idnumber
                    ],
                    'usertype' => [
                        'type' => 'id',
                        'value' => $userid
                    ]
                ]
            ]
        ];

        $result = call_moodle($moodle_url, $moodle_token, 'core_cohort_add_cohort_members', $cohort_params);
        return $result;
    } catch (Exception $e) {
        error_log("moodle_add_cohort_member error: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}