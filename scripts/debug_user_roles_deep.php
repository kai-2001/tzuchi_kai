<?php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/moodle/config.php');

$username = 'teacher1';
$user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);

if (!$user) {
    die("User '$username' not found.\n");
}

echo "Full Role Analysis for: $username (ID: {$user->id})\n";
echo "=================================================\n";

// 1. Check System Level Assignments (Global Roles)
echo "[System Context]\n";
$sys_context = context_system::instance();
$sys_roles = get_user_roles($sys_context, $user->id);
if ($sys_roles) {
    foreach ($sys_roles as $r) {
        echo "  - Role: {$r->shortname} ({$r->name})\n";
    }
} else {
    echo "  - No roles assigned at system level.\n";
}

// 2. Check Category Level Assignments
echo "\n[Category Context]\n";
$sql_cat = "SELECT ra.id, r.shortname, r.name, c.instanceid, cat.name as catname
            FROM {role_assignments} ra
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} c ON c.id = ra.contextid
            JOIN {course_categories} cat ON cat.id = c.instanceid
            WHERE ra.userid = ? AND c.contextlevel = " . CONTEXT_COURSECAT;
$cat_roles = $DB->get_records_sql($sql_cat, [$user->id]);

if ($cat_roles) {
    foreach ($cat_roles as $r) {
        echo "  - Role: {$r->shortname} ({$r->name}) in Category: {$r->catname} (ID: {$r->instanceid})\n";
    }
} else {
    echo "  - No roles assigned at category level.\n";
}

// 3. Check All Other Assignments
echo "\n[Other Contexts]\n";
$sql_other = "SELECT ra.id, r.shortname, r.name, c.contextlevel, c.instanceid
              FROM {role_assignments} ra
              JOIN {role} r ON r.id = ra.roleid
              JOIN {context} c ON c.id = ra.contextid
              WHERE ra.userid = ? AND c.contextlevel NOT IN (" . CONTEXT_SYSTEM . ", " . CONTEXT_COURSECAT . ")";
$other_roles = $DB->get_records_sql($sql_other, [$user->id]);

if ($other_roles) {
    foreach ($other_roles as $r) {
        echo "  - Role: {$r->shortname} ({$r->name}) at Level {$r->contextlevel} (Instance: {$r->instanceid})\n";
    }
} else {
    echo "  - No other roles found.\n";
}
