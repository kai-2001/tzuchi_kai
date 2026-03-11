<?php
// scripts/check_moodle_user.php
require_once __DIR__ . '/../includes/config.php';
$username = isset($argv[1]) ? $argv[1] : 'teacher12';

echo "Checking Moodle User: $username\n";

$moodle_db = 'moodle';
$conn = new mysqli($db_host, $db_user, $db_pass, $moodle_db);
if ($conn->connect_error)
    die("Conn failed");
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare("SELECT id, username, email, deleted FROM mdl_user WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if ($user) {
    print_r($user);
} else {
    echo "User NOT FOUND in Moodle DB.\n";
}
