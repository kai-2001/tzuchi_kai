<?php
// api/hospital_admin/batch_add_members.php
// 批次匯入成員 API

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/moodle_api.php';
header('Content-Type: application/json; charset=utf-8');

try {
    session_start();

    // 1. 權限檢查
    $is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];
    $is_sys_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
    if (!$is_hospital_admin && !$is_sys_admin) {
        throw new Exception('無權限存取');
    }

    $mgmt_cat_id = $_SESSION['management_category_id'] ?? 0;
    $institution = $_SESSION['institution'] ?? '';

    // 2. 接收參數
    $csv_text = $_POST['csv_text'] ?? '';
    $default_role = $_POST['default_role'] ?? 'student';
    $default_cohort_id = $_POST['default_cohort_id'] ?? 0;
    $dim_cohort_ids = $_POST['dim_cohort_ids'] ?? []; // 維度群組 IDs

    if (empty($csv_text)) {
        throw new Exception('內容不能為空');
    }

    // 3. 解析 CSV
    $lines = explode("\n", $csv_text);
    $results = [];
    $summary = ['success' => 0, 'fail' => 0];

    // 初始化資料庫連線
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) throw new Exception("Portal DB Connection failed");
    $conn->set_charset("utf8mb4");

    $moodle_conn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
    if ($moodle_conn->connect_error) throw new Exception("Moodle DB Connection failed");
    $moodle_conn->set_charset("utf8mb4");

    foreach ($lines as $index => $line) {
        $line = trim($line);
        if (empty($line)) continue;

        $row_num = $index + 1;
        try {
            // 解析欄位：帳號*,姓名,Email,密碼*,角色,職類,所屬,屬性,標籤 (共9欄)
            $cols = str_getcsv($line);
            
            $username = trim($cols[0] ?? '');
            $fullname = trim($cols[1] ?? $username);
            $email = trim($cols[2] ?? ($username . '@' . $institution . '.example.com'));
            $password = trim($cols[3] ?? '');
            
            // 處理角色 (支援中文)
            $raw_role = trim($cols[4] ?? '');
            if (empty($raw_role)) {
                $role = $default_role;
            } else {
                $role_map = [
                    '學生' => 'student',
                    '學員' => 'student',
                    'student' => 'student',
                    '老師' => 'coursecreator',
                    '教師' => 'coursecreator',
                    '開課教師' => 'coursecreator',
                    'coursecreator' => 'coursecreator'
                ];
                $role = $role_map[$raw_role] ?? $default_role;
            }
            
            // 維度群組 (支援 ; 分隔多值) - 新順序：先維度
            $csv_dim1 = trim($cols[5] ?? ''); // 職類
            $csv_dim2 = trim($cols[6] ?? ''); // 所屬
            $csv_dim3 = trim($cols[7] ?? ''); // 屬性
            
            // 標籤放最後 (支援 ; 分隔多值)
            $tags = trim($cols[8] ?? '');

            if (empty($username)) throw new Exception("帳號為空");
            if (empty($password)) throw new Exception("密碼為必填欄位");

            // A. 處理 Portal 使用者 (比照 add_member.php 邏輯)
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $user_exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user_exists) {
                // 建立本地使用者
                if (empty($password)) $password = bin2hex(random_bytes(4)); // 隨機密碼
                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, institution, role) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $username, $hashed_pass, $fullname, $email, $institution, $role);
                if (!$stmt->execute()) throw new Exception("本地使用者建立失敗: " . $stmt->error);
                $stmt->close();
            }

            // B. 處理 Moodle 使用者
            $moodle_user = ensure_moodle_user_exists($username, $fullname, $email, $institution);
            if (!$moodle_user) throw new Exception("Moodle 帳號同步失敗");
            $moodle_uid = $moodle_user[0]['id'];

            // B.1 自動加入「院區大群組」
            $inst_cohort_idnumber = get_institution_cohort($institution);
            if ($inst_cohort_idnumber) {
                $inst_member_param = [
                    'members' => [[
                        'cohorttype' => ['type' => 'idnumber', 'value' => $inst_cohort_idnumber], 
                        'usertype' => ['type' => 'id', 'value' => $moodle_uid]
                    ]]
                ];
                call_moodle($moodle_url, $moodle_token, 'core_cohort_add_cohort_members', $inst_member_param);
            }

            // C. 取得管理類別路徑 (供群組名稱查詢使用)
            $path_sql = "SELECT path FROM mdl_course_categories WHERE id = ?";
            $ps = $moodle_conn->prepare($path_sql);
            $ps->bind_param("i", $mgmt_cat_id);
            $ps->execute();
            $path_result = $ps->get_result()->fetch_assoc();
            $mgmt_path = $path_result['path'] ?? '';
            $ps->close();
            
            // C.1 處理 CSV 群組欄位 (若有)
            $target_cohort_id = $default_cohort_id;
            
            // 注意：cohort_name 已移除（改用維度欄位），此段保留供向下相容

            if ($target_cohort_id > 0) {
                // 加入群組
                $member_param = ['members' => [['cohorttype' => ['type'=>'id','value'=>$target_cohort_id], 'usertype'=>['type'=>'id','value'=>$moodle_uid]]]];
                call_moodle($moodle_url, $moodle_token, 'core_cohort_add_cohort_members', $member_param);

                // D. 如果是開課教師，自動指派權限 (Role: coursecreator)
                if ($role === 'coursecreator') {
                    // 找出該 Cohort 所屬的 Category ID
                    $cat_id = get_cohort_category_id($target_cohort_id);
                    if ($cat_id > 0) {
                        moodle_assign_role($username, $cat_id, 'coursecreator');
                    }
                }
            }

            // C.2 處理維度群組 (優先使用CSV欄位，若空白則用下拉選單預設值)
            // 合併 CSV 維度 和 前端選擇的預設維度
            $dim_values = [];
            
            // CSV 維度值 (支援 ; 分隔多個群組名稱)
            if (!empty($csv_dim1)) $dim_values = array_merge($dim_values, preg_split('/[;；]+/', $csv_dim1, -1, PREG_SPLIT_NO_EMPTY));
            if (!empty($csv_dim2)) $dim_values = array_merge($dim_values, preg_split('/[;；]+/', $csv_dim2, -1, PREG_SPLIT_NO_EMPTY));
            if (!empty($csv_dim3)) $dim_values = array_merge($dim_values, preg_split('/[;；]+/', $csv_dim3, -1, PREG_SPLIT_NO_EMPTY));
            
            // 如果 CSV 維度為空，則使用前端選擇的維度ID
            if (empty($dim_values)) {
                foreach ($dim_cohort_ids as $dim_cid) {
                    $dim_cid = (int) $dim_cid;
                    if ($dim_cid > 0) {
                        $dim_param = ['members' => [['cohorttype' => ['type'=>'id','value'=>$dim_cid], 'usertype'=>['type'=>'id','value'=>$moodle_uid]]]];
                        call_moodle($moodle_url, $moodle_token, 'core_cohort_add_cohort_members', $dim_param);
                    }
                }
            } else {
                // 根據群組名稱查詢並加入
                foreach ($dim_values as $dim_name) {
                    $dim_name = trim($dim_name);
                    if (empty($dim_name)) continue;
                    
                    // 查詢群組 ID (限縮在管理範圍內)
                    $dim_sql = "
                        SELECT c.id 
                        FROM mdl_cohort c
                        JOIN mdl_context ctx ON c.contextid = ctx.id
                        JOIN mdl_course_categories cat ON ctx.instanceid = cat.id
                        WHERE ctx.contextlevel = 40 
                        AND c.name = ?
                        AND (cat.id = ? OR cat.path LIKE CONCAT(?, '/%'))
                        LIMIT 1
                    ";
                    $dim_stmt = $moodle_conn->prepare($dim_sql);
                    $dim_stmt->bind_param("sis", $dim_name, $mgmt_cat_id, $mgmt_path);
                    $dim_stmt->execute();
                    $dim_row = $dim_stmt->get_result()->fetch_assoc();
                    $dim_stmt->close();
                    
                    if ($dim_row) {
                        $dim_param = ['members' => [['cohorttype' => ['type'=>'id','value'=>$dim_row['id']], 'usertype'=>['type'=>'id','value'=>$moodle_uid]]]];
                        call_moodle($moodle_url, $moodle_token, 'core_cohort_add_cohort_members', $dim_param);
                    }
                }
            }

            // E. 處理標籤 (直接寫入 Moodle 資料庫，因為 API 有權限問題)
            if (!empty($tags)) {
                // 支援以 空格、分號 隔開
                $tag_array = preg_split('/[; \s]+/', $tags, -1, PREG_SPLIT_NO_EMPTY);
                
                // 取得使用者 Context ID
                $ctx_stmt = $moodle_conn->prepare("SELECT id FROM mdl_context WHERE instanceid = ? AND contextlevel = 30");
                $ctx_stmt->bind_param("i", $moodle_uid);
                $ctx_stmt->execute();
                $ctx_res = $ctx_stmt->get_result();
                $ctx_row = $ctx_res->fetch_assoc();
                $contextid = $ctx_row['id'] ?? 0;
                $ctx_stmt->close();
                
                foreach ($tag_array as $raw_tag) {
                    $tag_lower = strtolower(trim($raw_tag));
                    $tag_raw = trim($raw_tag);
                    if (empty($tag_lower)) continue;
                    
                    // 檢查 Tag 是否存在
                    $t_stmt = $moodle_conn->prepare("SELECT id FROM mdl_tag WHERE name = ?");
                    $t_stmt->bind_param("s", $tag_lower);
                    $t_stmt->execute();
                    $t_res = $t_stmt->get_result();
                    $existing_tag = $t_res->fetch_assoc();
                    $t_stmt->close();
                    
                    if ($existing_tag) {
                        $tag_id = $existing_tag['id'];
                    } else {
                        // 建立新 Tag
                        $now = time();
                        $ins_stmt = $moodle_conn->prepare("INSERT INTO mdl_tag (userid, tagcollid, name, rawname, isstandard, flag, timemodified) VALUES (2, 1, ?, ?, 0, 0, ?)");
                        $ins_stmt->bind_param("ssi", $tag_lower, $tag_raw, $now);
                        $ins_stmt->execute();
                        $tag_id = $ins_stmt->insert_id;
                        $ins_stmt->close();
                    }
                    
                    // 建立 Tag Instance (若不存在)
                    $check_inst = $moodle_conn->prepare("SELECT id FROM mdl_tag_instance WHERE tagid = ? AND itemtype = 'user' AND itemid = ?");
                    $check_inst->bind_param("ii", $tag_id, $moodle_uid);
                    $check_inst->execute();
                    if ($check_inst->get_result()->num_rows == 0) {
                        $now = time();
                        $inst_stmt = $moodle_conn->prepare("INSERT INTO mdl_tag_instance (tagid, component, itemtype, itemid, contextid, tiuserid, ordering, timecreated, timemodified) VALUES (?, 'core', 'user', ?, ?, 0, 0, ?, ?)");
                        $inst_stmt->bind_param("iiiii", $tag_id, $moodle_uid, $contextid, $now, $now);
                        $inst_stmt->execute();
                        $inst_stmt->close();
                    }
                    $check_inst->close();
                }
            }

            $results[] = ["row" => $row_num, "status" => "success", "message" => "帳號 [$username] 處理完成"];
            $summary['success']++;

        } catch (Exception $e) {
            $results[] = ["row" => $row_num, "status" => "fail", "message" => $e->getMessage()];
            $summary['fail']++;
        }
    }

    $conn->close();
    $moodle_conn->close();

    echo json_encode([
        'success' => true,
        'results' => $results,
        'summary' => $summary
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
