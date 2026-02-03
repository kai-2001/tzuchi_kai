<?php
// api/learning_path/get_courses.php
// Get list of courses assigned to a learning path

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // 1. Auth Check (Admins or Course Creators or Hospital Admins)
    // Actually, this might be needed for students too? 
    // Usually admin console uses this. Students use a different view (e.g. curriculum tab).
    // For now, allow logged in users?
    // User requested "Management Page" to see it. So Admin/Hospital Admin.

    $is_admin = isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : false;
    $is_hospital_admin = isset($_SESSION['is_hospital_admin']) ? $_SESSION['is_hospital_admin'] : false;
    // $is_coursecreator = isset($_SESSION['is_coursecreator']) ? $_SESSION['is_coursecreator'] : false;

    if (!$is_admin && !$is_hospital_admin) {
        throw new Exception('Permission denied');
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        throw new Exception('Invalid ID');
    }

    // 2. Get Course IDs from Portal DB
    $portal_conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($portal_conn->connect_error) {
        throw new Exception("DB Connection Error");
    }
    $portal_conn->set_charset('utf8mb4');

    // Permission check: If hospital admin, ensure path belongs to their hospital
    if ($is_hospital_admin) {
        $check = $portal_conn->prepare("SELECT hospital_id FROM learning_paths WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $res = $check->get_result();
        if ($row = $res->fetch_assoc()) {
            if ($row['hospital_id'] != $_SESSION['hospital_id']) {
                throw new Exception("Permission denied");
            }
        } else {
            throw new Exception("Package not found");
        }
        $check->close();
    }

    $stmt = $portal_conn->prepare("SELECT moodle_course_id, is_required, display_order FROM learning_path_courses WHERE path_id = ? ORDER BY display_order ASC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    $courses = [];
    $moodle_ids = [];

    while ($row = $res->fetch_assoc()) {
        $courses[] = $row;
        $moodle_ids[] = $row['moodle_course_id'];
    }

    $stmt->close();
    $portal_conn->close();

    if (empty($moodle_ids)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    // 3. Get Course Names from Moodle DB
    // Config (Match create.php)
    $m_db_host = 'localhost';
    $m_db_user = 'root';
    $m_db_pass = 'root123';
    $m_db_name = 'moodle';
    $m_prefix = 'mdl_';

    $m_conn = new mysqli($m_db_host, $m_db_user, $m_db_pass, $m_db_name);
    if ($m_conn->connect_error) {
        throw new Exception("Moodle DB Connection Error");
    }
    $m_conn->set_charset('utf8mb4');

    $ids_str = implode(',', array_map('intval', $moodle_ids));
    $sql = "SELECT id, fullname, shortname FROM {$m_prefix}course WHERE id IN ($ids_str)";
    $m_res = $m_conn->query($sql);

    $moodle_details = [];
    if ($m_res) {
        while ($row = $m_res->fetch_assoc()) {
            $moodle_details[$row['id']] = $row;
        }
    }

    $m_conn->close();

    // 4. Merge
    $final_list = [];
    foreach ($courses as $c) {
        $mid = $c['moodle_course_id'];
        if (isset($moodle_details[$mid])) {
            $final_list[] = array_merge($c, $moodle_details[$mid]);
        } else {
            // Course deleted in Moodle but exists in path?
            $c['fullname'] = "(Unknown/Deleted Course ID: $mid)";
            $c['shortname'] = "N/A";
            $final_list[] = $c;
        }
    }

    echo json_encode(['success' => true, 'data' => $final_list]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
