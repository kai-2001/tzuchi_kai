<?php
/**
 * 新增院區成員
 * api/hospital_admin/add_member.php
 */
session_start();
// 開啟緩衝區，防止有些 include 檔或 hook 輸出額外訊息導致 JSON 格式錯誤
ob_start();

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/moodle_api.php';
header('Content-Type: application/json; charset=utf-8');

// 權限檢查 - 允許 hospital_admin 或系統管理員
$is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

if (!$is_hospital_admin && !$is_admin) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => '權限不足']));
}

$institution = $_SESSION['institution'] ?? '';
$category_id = $_SESSION['management_category_id'] ?? 0;

// 驗證輸入
$username = strtolower(trim($_POST['username'] ?? ''));
$fullname = trim($_POST['fullname'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'student';

if (empty($username) || empty($fullname)) {
    die(json_encode(['success' => false, 'error' => '帳號和姓名為必填']));
}

if (empty($password)) {
    die(json_encode(['success' => false, 'error' => '密碼為必填']));
}

// 驗證帳號格式
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    die(json_encode(['success' => false, 'error' => '帳號只能使用英文、數字和底線']));
}

// 驗證角色（hospital_admin 只能建立 student, teacherplus 或 coursecreator）
if (!in_array($role, ['student', 'teacherplus', 'coursecreator'])) {
    die(json_encode(['success' => false, 'error' => '無效的角色']));
}

// 預設 email
if (empty($email)) {
    $email = $username . '@' . str_replace(['台北', '嘉義', '大林', '花蓮'], ['taipei', 'chiayi', 'dalin', 'hualien'], $institution) . '.example.com';
}

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('資料庫連線失敗');
    }
    $conn->set_charset('utf8mb4');

    // 檢查帳號是否已存在
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $conn->close();
        die(json_encode(['success' => false, 'error' => '帳號已存在']));
    }
    $stmt->close();

    // 新增到 portal_db
    $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO users (username, fullname, email, password, role, institution) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssss", $username, $fullname, $email, $hashed_pass, $role, $institution);

    if (!$stmt->execute()) {
        throw new Exception('新增失敗: ' . $stmt->error);
    }
    $new_id = $conn->insert_id;
    $stmt->close();
    $conn->close();

    // 同步到 Moodle 建立用戶
    $moodle_user = null;
    if (function_exists('ensure_moodle_user_exists')) {
        $moodle_user = ensure_moodle_user_exists($username, $fullname, $email);
        error_log("add_member debug: ensure_moodle_user_exists result for $username: " . print_r($moodle_user, true));
    }

    // 同步到 Cohort
    $cohort_id = get_institution_cohort($institution);
    if ($cohort_id) {
        // use API function
        $res = moodle_add_cohort_member($username, $cohort_id);
    }

    // 🚀 如果是開課教師，同步 coursecreator 角色到 Moodle（類別層級）
    if (($role === 'teacherplus' || $role === 'coursecreator') && $category_id > 0) {
        // use API function
        $res = moodle_assign_role($username, $category_id, 'coursecreator');
        error_log("add_member debug: role assign result: " . print_r($res, true));
    }

    // 清除前面的任何輸出，確保只回傳 JSON
    if (ob_get_length())
        ob_clean();

    echo json_encode([
        'success' => true,
        'message' => '成員已新增',
        'id' => $new_id
    ]);

} catch (Throwable $e) { // 👈 改用 Throwable 以捕捉 Fatal Error
    if (ob_get_length())
        ob_clean();
    error_log("add_member error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '系統錯誤: ' . $e->getMessage()]);
}
?>