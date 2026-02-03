<?php
/**
 * 更新院區成員
 * api/hospital_admin/update_member.php
 */
session_start();
// 開啟緩衝區，防止有些 include 檔或 hook 輸出額外訊息導致 JSON 格式錯誤
ob_start();

require_once '../../includes/config.php';
require_once '../../includes/functions.php';  // call_moodle needed
require_once '../../includes/moodle_api.php'; // moodle_assign_role needed
require_once '../../includes/attribute_helper.php';
header('Content-Type: application/json; charset=utf-8');

$log_file = __DIR__ . '/debug_log.txt';
file_put_contents($log_file, "Start update_member.php [" . date('Y-m-d H:i:s') . "]\n", FILE_APPEND);

// 權限檢查
if (!isset($_SESSION['is_hospital_admin']) || !$_SESSION['is_hospital_admin']) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => '權限不足']));
}

$institution = $_SESSION['institution'] ?? '';
if (empty($institution)) {
    die(json_encode(['success' => false, 'error' => '未設定所屬院區']));
}

// 驗證輸入
$id = (int) ($_POST['id'] ?? 0);
$category_id = $_SESSION['management_category_id'] ?? 0;
$fullname = trim($_POST['fullname'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';
$hospital_id = intval($_POST['hospital_id'] ?? 0);
$department_id = intval($_POST['department_id'] ?? 0);
$job_title_ids = $_POST['job_title_ids'] ?? []; // 多選職稱陣列
if (!is_array($job_title_ids)) {
    $job_title_ids = [];
}

if ($id <= 0) {
    die(json_encode(['success' => false, 'error' => '無效的成員 ID']));
}

if (empty($fullname)) {
    die(json_encode(['success' => false, 'error' => '姓名為必填']));
}

// 驗證角色
if (!empty($role) && !in_array($role, ['student', 'teacherplus', 'coursecreator'])) {
    die(json_encode(['success' => false, 'error' => '無效的角色']));
}

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('資料庫連線失敗');
    }
    $conn->set_charset('utf8mb4');
    file_put_contents($log_file, "DB Connected\n", FILE_APPEND);

    // 1. 取得被編輯成員的原始帳號 (用於 Moodle 同步)
    $stmt = $conn->prepare("SELECT id, username, role, institution FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        throw new Exception('找不到該成員');
    }
    $target_member = $res->fetch_assoc();
    $target_username = $target_member['username'];
    $old_role = $target_member['role'];
    $stmt->close();

    // 2. 權限檢查：只能編輯同院區的成員
    // 2. 權限檢查：只能編輯同院區的成員
    $admin_hospital_id = $_SESSION['hospital_id'] ?? null;

    // 系統管理員 (is_admin 且沒有被限制在特定院區) 可以編輯任何人
    $is_super_admin = ($_SESSION['is_admin'] ?? false) && empty($admin_hospital_id);

    if (!$is_super_admin && $admin_hospital_id) {
        // 檢查成員是否具有目前管理員的院區屬性
        $stmt_check = $conn->prepare("SELECT 1 FROM user_attributes WHERE user_id = ? AND attribute_value_id = ?");
        $stmt_check->bind_param("ii", $id, $admin_hospital_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows === 0) {
            // 如果不屬於該院區，檢查是否屬於其他院區
            $target_hospital = get_user_hospital($id, $conn);
            if ($target_hospital) {
                $stmt_check->close();
                throw new Exception("無權限操作此成員 (成員屬於 {$target_hospital['name']})");
            }
            // 如果沒有任何院區屬性，允許編輯（視為新成員認領）
        }
        $stmt_check->close();
    }

    // 3. 建構更新 SQL
    $updates = ['fullname = ?'];
    $params = [$fullname];
    $types = 's';

    if (!empty($email)) {
        $updates[] = 'email = ?';
        $params[] = $email;
        $types .= 's';
    }

    if (!empty($password)) {
        $updates[] = 'password = ?';
        $params[] = password_hash($password, PASSWORD_DEFAULT);
        $types .= 's';
    }

    if (!empty($role)) {
        $updates[] = 'role = ?';
        $params[] = $role;
        $types .= 's';
    }

    // 加入 ID 條件
    $params[] = $id;
    $types .= 'i';

    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        throw new Exception('更新失敗: ' . $stmt->error);
    }
    $stmt->close();
    file_put_contents($log_file, "DB Update Success. Syncing Moodle if needed...\n", FILE_APPEND);

    // 4. 🚀 同步到 Moodle
    // 4a. 同步基本資料 (姓名、Email)
    $m_data = [];
    if (!empty($fullname) && $fullname !== ($target_member['fullname'] ?? '')) {
        $m_data['fullname'] = $fullname;
    }
    if (!empty($email) && $email !== ($target_member['email'] ?? '')) {
        $m_data['email'] = $email;
    }

    if (!empty($m_data)) {
        file_put_contents($log_file, "Syncing profile to Moodle: " . print_r($m_data, true) . "\n", FILE_APPEND);
        $m_up_res = moodle_update_user($target_username, $m_data);
        file_put_contents($log_file, "moodle_update_user result: " . print_r($m_up_res, true) . "\n", FILE_APPEND);
    }

    // 4b. 同步角色變更
    // 只有當角色明確有傳入且與舊角色不同時，才執行 Moodle 同步
    if (!empty($role) && $role !== $old_role && $category_id > 0) {
        file_put_contents($log_file, "Role changed from $old_role to $role. Syncing to Category $category_id...\n", FILE_APPEND);

        if ($role === 'teacherplus' || $role === 'coursecreator') {
            // 指派開課教師角色
            $m_res = moodle_assign_role($target_username, $category_id, 'coursecreator');
            file_put_contents($log_file, "moodle_assign_role result: " . print_r($m_res, true) . "\n", FILE_APPEND);
        } elseif ($role === 'student' && ($old_role === 'teacherplus' || $old_role === 'coursecreator')) {
            // 如果從教師改回學生，移除 coursecreator 角色
            $m_res = moodle_unassign_role($target_username, $category_id, 'coursecreator');
            file_put_contents($log_file, "moodle_unassign_role result: " . print_r($m_res, true) . "\n", FILE_APPEND);
        }
    }

    // 儲存使用者屬性（院區、部門、職稱）
    $attr_ids = [];
    if ($hospital_id > 0) {
        $attr_ids[] = $hospital_id;
    }
    if ($department_id > 0) {
        $attr_ids[] = $department_id;
    }
    // 多選職稱
    foreach ($job_title_ids as $jt_id) {
        $jt_id = intval($jt_id);
        if ($jt_id > 0) {
            $attr_ids[] = $jt_id;
        }
    }
    if (!empty($attr_ids)) {
        set_user_attributes($id, $attr_ids);
    }

    $conn->close();

    // 清除前面的任何輸出，確保只回傳 JSON
    if (ob_get_length())
        ob_clean();

    echo json_encode([
        'success' => true,
        'message' => '成員資料已更新'
    ]);

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    error_log("update_member error: " . $e->getMessage());
    file_put_contents($log_file, "Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>