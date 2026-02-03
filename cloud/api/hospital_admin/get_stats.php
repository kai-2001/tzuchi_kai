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

    // 取得登入者的院區 ID
    $hospital_id = $_SESSION['hospital_id'] ?? 0;

    if ($show_all) {
        // 系統管理員看所有成員
        $students = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'student'")->fetch_assoc()['cnt'];
        $teachers = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role IN ('teacherplus', 'coursecreator')")->fetch_assoc()['cnt'];
    } else {
        // 院區管理員只看自己院區 (使用 user_attributes)
        $sql = "
            SELECT COUNT(DISTINCT u.id) as cnt
            FROM users u
            JOIN user_attributes ua ON u.id = ua.user_id
            JOIN attribute_values av ON ua.attribute_value_id = av.id
            JOIN attribute_types at ON av.type_id = at.id
            WHERE at.code = 'hospital' 
            AND av.id = ? 
            AND u.role = ?
        ";

        // 學生數
        $stmt = $conn->prepare($sql);
        $role = 'student';
        $stmt->bind_param("is", $hospital_id, $role);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // 教師數
        $stmt = $conn->prepare($sql . " OR u.role = 'teacherplus'"); // Allow teacherplus too
        // Use a simpler query for teachers to handle both roles if needed, or just strict match
        // Let's use IN clause or simplified logic
        // Re-write query for teachers

        $sql_teacher = "
            SELECT COUNT(DISTINCT u.id) as cnt
            FROM users u
            JOIN user_attributes ua ON u.id = ua.user_id
            JOIN attribute_values av ON ua.attribute_value_id = av.id
            JOIN attribute_types at ON av.type_id = at.id
            WHERE at.code = 'hospital' 
            AND av.id = ? 
            AND u.role IN ('coursecreator', 'teacherplus')
        ";
        $stmt = $conn->prepare($sql_teacher);
        $stmt->bind_param("i", $hospital_id);
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