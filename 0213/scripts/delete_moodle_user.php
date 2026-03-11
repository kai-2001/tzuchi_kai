<?php
/**
 * 刪除 Moodle 用戶
 * scripts/delete_moodle_user.php
 * 
 * Usage: php delete_moodle_user.php <username>
 * 
 * 注意：這會永久刪除用戶在 Moodle 的所有資料，包括課程參與記錄和成績！
 */
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/moodle/config.php');

$username = isset($argv[1]) ? $argv[1] : '';

$result = ['success' => false, 'message' => ''];

if (empty($username)) {
    $result['message'] = 'Missing username argument';
    echo json_encode($result);
    exit(1);
}

// Get user
$user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
if (!$user) {
    // User doesn't exist or already deleted - consider it success
    $result['success'] = true;
    $result['message'] = 'User not found or already deleted';
    echo json_encode($result);
    exit(0);
}

// Don't delete admin or guest
if ($user->username === 'admin' || $user->username === 'guest') {
    $result['message'] = 'Cannot delete admin or guest user';
    echo json_encode($result);
    exit(1);
}

try {
    // Delete the user using Moodle's API
    delete_user($user);

    $result['success'] = true;
    $result['message'] = 'User deleted from Moodle: ' . $username;
    echo json_encode($result);

} catch (Exception $e) {
    $result['message'] = 'Error deleting user: ' . $e->getMessage();
    echo json_encode($result);
    exit(1);
}
