<?php
/**
 * 列出院區成員
 * api/hospital_admin/list_members.php
 */
session_start();
require_once '../../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

// 權限檢查 - 允許 hospital_admin 或系統管理員
$is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

if (!$is_hospital_admin && !$is_admin) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => '權限不足']));
}

$institution = $_SESSION['institution'] ?? '';

// hospital_admin 需要 institution，系統管理員可以看全部
$show_all = ($is_admin && empty($institution));

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('資料庫連線失敗: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');

    // 查詢成員（排除 admin 和 hospital_admin）
    if ($show_all) {
        // 系統管理員可以看到所有成員
        $sql = "SELECT id, username, fullname, email, role, institution FROM users WHERE role IN ('student', 'teacherplus', 'coursecreator') ORDER BY institution ASC, fullname ASC, username ASC";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception('查詢失敗 (show_all): ' . $conn->error);
        }
    } else {
        // 院區管理員只能看到自己院區的成員
        $sql = "SELECT id, username, fullname, email, role, institution FROM users WHERE institution = ? AND role IN ('student', 'teacherplus', 'coursecreator') ORDER BY fullname ASC, username ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('準備查詢失敗: ' . $conn->error);
        }
        $stmt->bind_param("s", $institution);
        $stmt->execute();
        $result = $stmt->get_result();
    }

    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = [
            'id' => (int) $row['id'],
            'username' => $row['username'],
            'fullname' => $row['fullname'],
            'email' => $row['email'],
            'role' => $row['role'],
            'institution' => $row['institution']
        ];
    }

    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();

    echo json_encode([
        'success' => true,
        'data' => $members,
        'total' => count($members)
    ]);

} catch (Exception $e) {
    error_log("list_members error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '系統錯誤']);
}
?>