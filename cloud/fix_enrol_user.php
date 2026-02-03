<?php
define('CLI_SCRIPT', true);
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Hardcoded for the user's current situation
$moodle_user_id = 17; // From debug log
$course_id = 17;      // From debug log

// 1. Enable manual enrol if not enabled (using retry logic just in case, though it should exist now)
$m_conn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
$m_conn->query("UPDATE mdl_enrol SET status = 0 WHERE courseid = $course_id AND enrol = 'manual'");
$m_conn->close();

echo "Manual enrolment enabled for course $course_id.\n";

// 2. Get Teacher Role ID
$m_conn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
$res = $m_conn->query("SELECT id FROM mdl_role WHERE shortname = 'editingteacher'");
$row = $res->fetch_assoc();
$role_id = $row['id'];
$m_conn->close();

echo "Role ID for teacher: $role_id\n";

// 3. Enroll via API
$enrol_params = [
    'enrolments' => [
        [
            'roleid' => $role_id,
            'userid' => $moodle_user_id,
            'courseid' => $course_id
        ]
    ]
];

$resp = call_moodle($moodle_url, $moodle_token, 'enrol_manual_enrol_users', $enrol_params);

if (isset($resp['exception'])) {
    echo "Error enrolling: " . $resp['message'] . "\n";
} else {
    echo "Success! User $moodle_user_id enrolled in Course $course_id.\n";
}
