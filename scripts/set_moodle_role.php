<?php
/**
/**
 * 設定用戶在 Moodle 中的 coursecreator 角色（類別層級）
 * scripts/set_moodle_role.php
 * 
 * Usage: php set_moodle_role.php <username> <role> <category_id>
 * role: 'student' or 'coursecreator'
 * category_id: 要分配角色的課程類別 ID
 * 
 * - coursecreator: 在指定類別分配 coursecreator 角色
 * - student: 移除指定類別的 coursecreator 角色
 */
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/moodle/config.php');

$username = isset($argv[1]) ? $argv[1] : '';
$new_role = isset($argv[2]) ? $argv[2] : '';
$category_id = isset($argv[3]) ? (int) $argv[3] : 0;

$result = ['success' => false, 'message' => ''];

if (empty($username) || empty($new_role)) {
    $result['message'] = 'Missing arguments: username and role required';
    echo json_encode($result);
    exit(1);
}

if (!in_array($new_role, ['student', 'coursecreator'])) {
    $result['message'] = 'Invalid role: must be student or coursecreator';
    echo json_encode($result);
    exit(1);
}

if ($category_id <= 0) {
    $result['message'] = 'Invalid category_id: must be a positive integer';
    echo json_encode($result);
    exit(1);
}

// Get user
$user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
if (!$user) {
    $result['message'] = 'User not found: ' . $username;
    echo json_encode($result);
    exit(1);
}

// Get coursecreator role (standard role for course creation)
$role_obj = $DB->get_record('role', ['shortname' => 'coursecreator']);
if (!$role_obj) {
    $result['message'] = 'coursecreator role not found in Moodle';
    echo json_encode($result);
    exit(1);
}

// Get category context
$category = $DB->get_record('course_categories', ['id' => $category_id]);
if (!$category) {
    $result['message'] = 'Category not found: ' . $category_id;
    echo json_encode($result);
    exit(1);
}

$category_context = context_coursecat::instance($category_id);

if ($new_role === 'coursecreator') {
    // Assign role at category level
    // Check if already assigned
    $existing = $DB->get_record('role_assignments', [
        'roleid' => $role_obj->id,
        'contextid' => $category_context->id,
        'userid' => $user->id
    ]);

    if (!$existing) {
        role_assign($role_obj->id, $user->id, $category_context->id);
        $result['message'] = 'coursecreator role assigned at category ' . $category->name;
    } else {
        $result['message'] = 'Role already assigned at category ' . $category->name;
    }
    $result['success'] = true;

} else {
    // Remove role at category level
    role_unassign($role_obj->id, $user->id, $category_context->id);
    $result['success'] = true;
    $result['message'] = 'coursecreator role removed from category ' . $category->name;
}

echo json_encode($result);
