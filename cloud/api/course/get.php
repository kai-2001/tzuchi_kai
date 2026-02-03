<?php
// api/course/get.php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Permissions
$is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];
$is_coursecreator = isset($_SESSION['is_coursecreator']) && $_SESSION['is_coursecreator'];

if (!$is_hospital_admin && !$is_coursecreator) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('Database connection failed');
    }
    $conn->set_charset('utf8mb4');

    // 1. Get Rules
    $stmt = $conn->prepare("SELECT rules_json FROM course_rules WHERE moodle_course_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rules = null;
    if ($row = $result->fetch_assoc()) {
        $rules = json_decode($row['rules_json'], true);
    }
    $stmt->close();

    // 2. Get Course Details (Can fetch from Moodle if needed, or cache?)
    // For now, we only need rules for editing. 
    // If edit mode needs fullname/shortname/category, we might need to query Moodle DB.
    // Let's query Moodle DB for completeness.

    // Moodle DB creds (manual for now)
    $m_db_host = 'localhost';
    $m_db_user = 'root';
    $m_db_pass = 'root123';
    $m_db_name = 'moodle';
    $m_prefix = 'mdl_';

    $m_conn = new mysqli($m_db_host, $m_db_user, $m_db_pass, $m_db_name);
    if ($m_conn->connect_error) {
        // Graceful fallback if moodle db fails? No, critical.
        throw new Exception("Moodle DB Error");
    }

    $stmt_m = $m_conn->prepare("SELECT fullname, shortname, category FROM {$m_prefix}course WHERE id = ?");
    $stmt_m->bind_param("i", $id);
    $stmt_m->execute();
    $res_m = $stmt_m->get_result();
    $course_data = $res_m->fetch_assoc();
    $stmt_m->close();
    $m_conn->close();

    if (!$course_data) {
        throw new Exception("Course not found in Moodle");
    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $id,
            'fullname' => $course_data['fullname'],
            'shortname' => $course_data['shortname'],
            'category' => $course_data['category'],
            'rules' => $rules
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>