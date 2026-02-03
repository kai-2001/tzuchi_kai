<?php
// api/course/update.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input)
        throw new Exception('Invalid JSON');

    $course_id = intval($input['id'] ?? 0);
    $rules_data = $input['rules'] ?? null;
    $fullname = trim($input['fullname'] ?? '');
    $shortname = trim($input['shortname'] ?? '');
    $category_id = intval($input['category'] ?? 0);

    if ($course_id <= 0)
        throw new Exception('Invalid Course ID');

    // 1. Update Moodle Course (Optional, but good for completeness)
    if ($fullname && $shortname && $category_id) {
        $m_db_host = 'localhost';
        $m_db_user = 'root';
        $m_db_pass = 'root123';
        $m_db_name = 'moodle';
        $m_prefix = 'mdl_';

        $m_conn = new mysqli($m_db_host, $m_db_user, $m_db_pass, $m_db_name);
        if (!$m_conn->connect_error) {
            $stmt = $m_conn->prepare("UPDATE {$m_prefix}course SET fullname=?, shortname=?, category=? WHERE id=?");
            $stmt->bind_param("ssii", $fullname, $shortname, $category_id, $course_id);
            $stmt->execute();
            $stmt->close();
            $m_conn->close();
        }
    }

    // 2. Update Rules in Portal DB
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error)
        throw new Exception('DB Connection Failed');
    $conn->set_charset('utf8mb4');

    $rules_json = json_encode($rules_data);
    $rule_type = 'custom';

    // Check if exists
    $stmt = $conn->prepare("SELECT id FROM course_rules WHERE moodle_course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if ($res->num_rows > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE course_rules SET rules_json = ? WHERE moodle_course_id = ?");
        $stmt->bind_param("si", $rules_json, $course_id);
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO course_rules (moodle_course_id, rule_type, rules_json) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $course_id, $rule_type, $rules_json);
    }

    if (!$stmt->execute()) {
        throw new Exception("Failed to update rules: " . $stmt->error);
    }
    $stmt->close();
    // 3. Update Learning Path (Package) Link
    // Strategy: Remove existing link for this course, then add new one if package_id > 0
    // This handles moving between packages or removing from a package.
    $package_id = intval($input['package_id'] ?? 0);
    $package_required = !empty($input['package_required']) ? 1 : 0;

    // Remove existing
    $del = $conn->prepare("DELETE FROM learning_path_courses WHERE moodle_course_id = ?");
    $del->bind_param("i", $course_id);
    $del->execute();
    $del->close();

    // Add new if selected
    if ($package_id > 0) {
        // Get Max Order
        $stmt_ord = $conn->prepare("SELECT MAX(display_order) FROM learning_path_courses WHERE path_id = ?");
        $stmt_ord->bind_param("i", $package_id);
        $stmt_ord->execute();
        $stmt_ord->bind_result($max_order);
        $stmt_ord->fetch();
        $stmt_ord->close();

        $new_order = $max_order + 1;

        $ins = $conn->prepare("INSERT INTO learning_path_courses (path_id, moodle_course_id, display_order, is_required) VALUES (?, ?, ?, ?)");
        $ins->bind_param("iiii", $package_id, $course_id, $new_order, $package_required);
        $ins->execute();
        $ins->close();
    }

    $conn->close();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>