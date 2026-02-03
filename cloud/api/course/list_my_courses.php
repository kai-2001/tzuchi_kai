<?php
// api/course/list_my_courses.php
// Lists courses where the current user is a teacher/manager

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $username = $_SESSION['username'] ?? '';
    if (empty($username)) {
        throw new Exception('Not logged in');
    }

    // 1. Get Moodle User ID
    // We can use the parallel call later, but simplest is lookup by username
    $user_resp = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', [
        'field' => 'username',
        'values' => [$username]
    ]);

    if (empty($user_resp[0]['id'])) {
        throw new Exception('Moodle user not found');
    }
    $moodle_user_id = $user_resp[0]['id'];

    // 2. Get User's Courses
    $courses_resp = call_moodle($moodle_url, $moodle_token, 'core_enrol_get_users_courses', [
        'userid' => $moodle_user_id
    ]);

    // DEBUG
    file_put_contents('../../debug_course_list.log', date('Y-m-d H:i:s') . " UserID: $moodle_user_id, Count: " . count($courses_resp) . "\n", FILE_APPEND);

    if (isset($courses_resp['exception'])) {
        throw new Exception($courses_resp['message']);
    }

    $courses = [];
    $course_ids = [];

    foreach ($courses_resp as $c) {
        $courses[$c['id']] = [
            'id' => $c['id'],
            'fullname' => $c['fullname'],
            'shortname' => $c['shortname'],
            'category' => $c['category'] ?? 0, // category ID
            'portal_rules' => null // Placeholder
        ];
        $course_ids[] = $c['id'];
    }

    // 3. Get Rules from Portal DB
    if (!empty($course_ids)) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        $conn->set_charset('utf8mb4');

        $ids_str = implode(',', array_map('intval', $course_ids));
        $sql = "SELECT moodle_course_id, rules_json, is_active FROM course_rules WHERE moodle_course_id IN ($ids_str)";
        $res = $conn->query($sql);

        while ($row = $res->fetch_assoc()) {
            if (isset($courses[$row['moodle_course_id']])) {
                $courses[$row['moodle_course_id']]['portal_rules'] = json_decode($row['rules_json'], true);
                $courses[$row['moodle_course_id']]['is_active'] = $row['is_active'];
            }
        }

        $conn->close();
    }

    echo json_encode(['success' => true, 'data' => array_values($courses)]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
