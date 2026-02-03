<?php
/**
 * 設定使用者的開課類別權限
 * api/hospital_admin/set_user_categories.php
 * 
 * POST 參數:
 * - user_id: 使用者 ID
 * - category_ids: 類別 ID 陣列 (JSON)
 */
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

// 權限檢查
if (empty($_SESSION['is_hospital_admin']) && empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => '權限不足']));
}

$userId = $_POST['user_id'] ?? null;
$categoryIds = json_decode($_POST['category_ids'] ?? '[]', true);

if (!$userId) {
    die(json_encode(['success' => false, 'error' => '缺少 user_id']));
}

if (!is_array($categoryIds)) {
    $categoryIds = [];
}

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset('utf8mb4');

    // 確保資料表存在
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_category_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            category_id INT NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'coursecreator',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_cat_role (user_id, category_id, role),
            INDEX idx_user_id (user_id),
            INDEX idx_category_id (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $log_file = __DIR__ . '/debug_cat_role.log';
    file_put_contents($log_file, "Received req: user_id=$userId, cats=" . print_r($categoryIds, true) . "\n", FILE_APPEND);

    // Verify DB
    $res = $conn->query("SELECT MAX(id) as max_id FROM users");
    $row = $res->fetch_assoc();
    $max_id = $row['max_id'] ?? 'NULL';
    file_put_contents($log_file, "DB check: connect info=" . $conn->host_info . ", max_id in users is $max_id\n", FILE_APPEND);

    // 取得使用者的 Moodle username 和 user id 及其他資訊
    $stmt = $conn->prepare("SELECT username, fullname, email, institution FROM users WHERE id = ?");
    if (!$stmt) {
        file_put_contents($log_file, "Prepare failed: " . $conn->error . "\n", FILE_APPEND);
    }
    $userIdInt = (int) $userId;
    $stmt->bind_param("i", $userIdInt);
    $stmt->execute();
    $result = $stmt->get_result();

    file_put_contents($log_file, "Query for ID $userIdInt returned " . $result->num_rows . " rows.\n", FILE_APPEND);

    if ($result->num_rows == 0) {
        throw new Exception("使用者 ID $userIdInt 不存在");
    }

    $userData = $result->fetch_assoc();
    $username = $userData['username'];
    $fullname = $userData['fullname'];
    $email = $userData['email'];
    $institution = $userData['institution'] ?? '';

    file_put_contents($log_file, "Found username: $username\n", FILE_APPEND);
    $stmt->close();

    // 取得 Moodle 使用者 ID
    $moodleUser = call_moodle($moodle_url, $moodle_token, 'core_user_get_users', [
        'criteria' => [['key' => 'username', 'value' => $username]]
    ]);

    file_put_contents($log_file, "Moodle user lookup: " . print_r($moodleUser, true) . "\n", FILE_APPEND);

    if (empty($moodleUser['users'])) {
        throw new Exception('Moodle 中找不到此帳號，請先確認該成員已登入過 Moodle 或手動建立帳號');
    }

    $moodleUserId = $moodleUser['users'][0]['id'];
    file_put_contents($log_file, "Moodle User ID: $moodleUserId\n", FILE_APPEND);

    // 取得目前的類別權限
    $stmt = $conn->prepare("SELECT category_id FROM user_category_roles WHERE user_id = ? AND role = 'coursecreator'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $currentCategoryIds = [];
    while ($row = $result->fetch_assoc()) {
        $currentCategoryIds[] = (int) $row['category_id'];
    }
    $stmt->close();

    $toAdd = array_diff($categoryIds, $currentCategoryIds);
    $toRemove = array_diff($currentCategoryIds, $categoryIds);

    // Coursecreator 角色 ID (通常是 2)
    $coursecreatorRoleId = 2;

    $log_file = __DIR__ . '/debug_cat_role.log';
    file_put_contents($log_file, "Setting categories for user $userId (Moodle: $moodleUserId)\n", FILE_APPEND);

    // 新增角色指派
    foreach ($toAdd as $catId) {
        $params = [
            'assignments' => [
                [
                    'roleid' => $coursecreatorRoleId,
                    'userid' => $moodleUserId,
                    'contextlevel' => 'coursecat',
                    'instanceid' => $catId
                ]
            ]
        ];

        file_put_contents($log_file, "Assigning role: " . json_encode($params) . "\n", FILE_APPEND);

        // Moodle 指派
        $result = call_moodle($moodle_url, $moodle_token, 'core_role_assign_roles', $params);

        file_put_contents($log_file, "Moodle response: " . print_r($result, true) . "\n", FILE_APPEND);

        // 檢查 Moodle 回傳錯誤
        if (isset($result['exception']) || isset($result['errorcode'])) {
            error_log("Moodle assign role failed: " . print_r($result, true));
            continue; // 如果失敗，不寫入本地 DB
        }

        // 本地記錄
        $stmt = $conn->prepare("INSERT IGNORE INTO user_category_roles (user_id, category_id, role) VALUES (?, ?, 'coursecreator')");
        $stmt->bind_param("ii", $userId, $catId);
        $stmt->execute();
        $stmt->close();
    }

    // 移除角色指派
    foreach ($toRemove as $catId) {
        $params = [
            'unassignments' => [
                [
                    'roleid' => $coursecreatorRoleId,
                    'userid' => $moodleUserId,
                    'contextlevel' => 'coursecat',
                    'instanceid' => $catId
                ]
            ]
        ];

        file_put_contents($log_file, "Unassigning role: " . json_encode($params) . "\n", FILE_APPEND);

        // Moodle 取消指派
        $result = call_moodle($moodle_url, $moodle_token, 'core_role_unassign_roles', $params);

        file_put_contents($log_file, "Moodle response: " . print_r($result, true) . "\n", FILE_APPEND);

        if (isset($result['exception']) || isset($result['errorcode'])) {
            error_log("Moodle unassign role failed: " . print_r($result, true));
            continue;
        }

        // 本地刪除
        $stmt = $conn->prepare("DELETE FROM user_category_roles WHERE user_id = ? AND category_id = ? AND role = 'coursecreator'");
        $stmt->bind_param("ii", $userId, $catId);
        $stmt->execute();
        $stmt->close();
    }

    // 更新 users.role 欄位
    // 重新計算目前的權限
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM user_category_roles WHERE user_id = ? AND role = 'coursecreator'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $currCount = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    $newRole = $currCount > 0 ? 'coursecreator' : 'student';
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param("si", $newRole, $userId);
    $stmt->execute();
    $stmt->close();

    $conn->close();

    echo json_encode([
        'success' => true,
        'added' => count($toAdd),
        'removed' => count($toRemove),
        'new_role' => $newRole
    ]);

} catch (Exception $e) {
    error_log("set_user_categories error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
