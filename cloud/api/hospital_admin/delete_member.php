<?php
/**
 * 刪除院區成員
 * api/hospital_admin/delete_member.php
 */
session_start();
// 開啟緩衝區，防止有些 include 檔或 hook 輸出額外訊息導致 JSON 格式錯誤
ob_start();

require_once '../../includes/config.php';
require_once '../../includes/functions.php'; // 引入 call_moodle 等函式
header('Content-Type: application/json; charset=utf-8');

// 權限檢查 - 允許 hospital_admin 或系統管理員
$is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

if (!$is_hospital_admin && !$is_admin) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => '權限不足']));
}

$institution = $_SESSION['institution'] ?? '';

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    die(json_encode(['success' => false, 'error' => '無效的成員 ID']));
}

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('資料庫連線失敗');
    }
    $conn->set_charset('utf8mb4');

    // 確認成員存在且可刪除（不能刪除管理員）
    if (!empty($institution)) {
        // hospital_admin 只能刪除自己院區的成員
        $stmt = $conn->prepare("
            SELECT id, username, role FROM users 
            WHERE id = ? AND institution = ? AND role IN ('student', 'coursecreator')
        ");
        $stmt->bind_param("is", $id, $institution);
    } else {
        // 系統管理員可以刪除所有非管理員
        $stmt = $conn->prepare("
            SELECT id, username, role FROM users 
            WHERE id = ? AND role IN ('student', 'coursecreator')
        ");
        $stmt->bind_param("i", $id);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        die(json_encode(['success' => false, 'error' => '成員不存在或無權限刪除']));
    }
    $member = $result->fetch_assoc();
    $username = $member['username'];
    $stmt->close();

    // 執行 Portal 資料庫刪除
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        throw new Exception('刪除失敗: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    // 🚀 同步刪除 Moodle 用戶
    // 引入 API (如果尚未引入)
    if (!function_exists('moodle_delete_user')) {
        require_once __DIR__ . '/../../includes/moodle_api.php';
    }
    $moodle_res = moodle_delete_user($username);
    if (isset($moodle_res['error'])) {
        error_log("Moodle user deletion warning for $username: " . print_r($moodle_res, true));
    }

    // ... code before ...

    // 清除前面的任何輸出，確保只回傳 JSON
    if (ob_get_length())
        ob_clean();

    echo json_encode([
        'success' => true,
        'message' => '成員已刪除'
    ]);

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    error_log("delete_member error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '系統錯誤']);
}
?>