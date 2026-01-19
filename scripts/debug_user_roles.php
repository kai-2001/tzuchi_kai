<?php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/moodle/config.php');

$username = 'teacher1'; // Hardcoded for diagnosis
$user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);

if (!$user) {
    die("User '$username' not found.\n");
}

echo "Role assignments for user: $username (ID: {$user->id})\n";
echo "---------------------------------------------------\n";

$sql = "SELECT ra.id, r.shortname, r.name, c.contextlevel, c.instanceid
        FROM {role_assignments} ra
        JOIN {role} r ON r.id = ra.roleid
        JOIN {context} c ON c.id = ra.contextid
        WHERE ra.userid = ?
        ORDER BY c.contextlevel, c.instanceid";

$assignments = $DB->get_records_sql($sql, [$user->id]);

if (!$assignments) {
    echo "No role assignments found anywhere.\n";
} else {
    foreach ($assignments as $ra) {
        $context_name = '';
        switch ($ra->contextlevel) {
            case CONTEXT_SYSTEM:
                $context_name = 'System';
                break;
            case CONTEXT_USER:
                $context_name = 'User';
                break;
            case CONTEXT_COURSECAT:
                $cat = $DB->get_record('course_categories', ['id' => $ra->instanceid]);
                $context_name = "Category: " . ($cat ? $cat->name : 'Unknown');
                break;
            case CONTEXT_COURSE:
                $course = $DB->get_record('course', ['id' => $ra->instanceid]);
                $context_name = "Course: " . ($course ? $course->fullname : 'Unknown');
                break;
            case CONTEXT_MODULE:
                $context_name = 'Module';
                break;
            case CONTEXT_BLOCK:
                $context_name = 'Block';
                break;
            default:
                $context_name = 'Level ' . $ra->contextlevel;
        }

        echo "- Role: {$ra->shortname} ({$ra->name})\n";
        echo "  Context: $context_name (Level: {$ra->contextlevel}, Instance: {$ra->instanceid})\n\n";
    }
}
