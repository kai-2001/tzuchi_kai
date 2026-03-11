<?php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/moodle/config.php');

$username = 'teacher1';
$rolename = 'teacherplus';
$category_name_part = '台北'; // Search for Taipei category

echo "Fixing Role for User: $username\n";
echo "================================\n";

// 1. Get User
$user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
if (!$user) {
    die("[-] User '$username' not found.\n");
}
echo "[+] Found User: {$user->username} (ID: {$user->id})\n";

// 2. Get Role
$role = $DB->get_record('role', ['shortname' => $rolename]);
if (!$role) {
    die("[-] Role '$rolename' not found.\n");
}
echo "[+] Found Role: {$role->name} (ID: {$role->id})\n";

// 3. Find Category
$categories = $DB->get_records_sql("SELECT * FROM {course_categories} WHERE name LIKE ?", ["%$category_name_part%"]);
if (!$categories) {
    die("[-] No category found matching '$category_name_part'.\n");
}
$target_category = reset($categories); // Take the first one
echo "[+] Found Target Category: {$target_category->name} (ID: {$target_category->id})\n";

// 4. Get Context
$context = context_coursecat::instance($target_category->id);
echo "[+] Found Context ID: {$context->id}\n";

// 5. Assign Role
// Check if already assigned
if (user_has_role_assignment($user->id, $role->id, $context->id)) {
    echo "[!] Role already assigned.\n";
} else {
    role_assign($role->id, $user->id, $context->id);
    echo "[✓] SUCCESS: Assigned '$rolename' to '$username' in '{$target_category->name}'\n";
}

// 6. Purge Caches to ensure immediate effect
purge_all_caches();
echo "[*] Caches purged.\n";
