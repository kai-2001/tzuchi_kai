<?php
/**
 * 取得院區統計數據
 * api/hospital_admin/get_stats.php
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
$show_all = ($is_admin && !$is_hospital_admin && empty($institution));

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('資料庫連線失敗');
    }
    $conn->set_charset('utf8mb4');

    if ($show_all) {
        // 系統管理員看所有成員
        $students = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'student'")->fetch_assoc()['cnt'];
        $teachers = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'coursecreator'")->fetch_assoc()['cnt'];
    } else {
        // 院區管理員只看自己院區
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE institution = ? AND role = 'student'");
        $stmt->bind_param("s", $institution);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE institution = ? AND role = 'coursecreator'");
        $stmt->bind_param("s", $institution);
        $stmt->execute();
        $teachers = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'total' => $students + $teachers,
        'students' => (int) $students,
        'teachers' => (int) $teachers
    ]);

} catch (Exception $e) {
    error_log("get_stats error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '系統錯誤']);
}
?>