<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

session_start();
header('Content-Type: text/plain; charset=utf-8');

if (!isset($_SESSION['username'])) {
    die("請先登入入口網");
}

$username = $_SESSION['username'];
echo "Current Portal Username: $username\n";

$u_params = ['field' => 'username', 'values' => [$username]];
echo "Checking Moodle for user: $username...\n";
$moodle_users = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);

echo "Moodle API Response:\n";
print_r($moodle_users);

if (empty($moodle_users)) {
    echo "\nTrying lowercase username: " . strtolower($username) . "...\n";
    $u_params_lc = ['field' => 'username', 'values' => [strtolower($username)]];
    $moodle_users_lc = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params_lc);
    print_r($moodle_users_lc);
}
?>