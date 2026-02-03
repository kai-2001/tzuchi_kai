<?php
/**
 * 取得使用者的開課類別權限
 * api/hospital_admin/get_user_categories.php
 */
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

// 權限檢查
if (empty($_SESSION['is_hospital_admin']) && empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => '權限不足']));
}

$userId = $_GET['user_id'] ?? null;
if (!$userId) {
    die(json_encode(['success' => false, 'error' => '缺少 user_id']));
}

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset('utf8mb4');

    // 取得使用者的 Moodle username
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        throw new Exception('使用者不存在');
    }

    $username = $result->fetch_assoc()['username'];
    $stmt->close();

    // 取得 Moodle 使用者 ID
    $moodleUser = call_moodle($moodle_url, $moodle_token, 'core_user_get_users', [
        'criteria' => [['key' => 'username', 'value' => $username]]
    ]);

    if (empty($moodleUser['users'])) {
        throw new Exception('Moodle 使用者不存在');
    }

    $moodleUserId = $moodleUser['users'][0]['id'];

    // 取得使用者的角色指派
    // 使用 core_role_get_users_in_context 或直接從資料庫查
    // Moodle Web Service 沒有直接的 API 取得使用者的所有角色指派
    // 我們改用本地記錄

    $stmt = $conn->prepare("
        SELECT category_id FROM user_category_roles 
        WHERE user_id = ? AND role = 'coursecreator'
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $categoryIds = [];
    while ($row = $result->fetch_assoc()) {
        $categoryIds[] = (int) $row['category_id'];
    }
    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'data' => $categoryIds
    ]);

} catch (Exception $e) {
    error_log("get_user_categories error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
