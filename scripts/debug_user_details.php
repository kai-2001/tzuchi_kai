<?php
require_once __DIR__ . '/../includes/config.php';

$username = 'student7';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');

$sql = "SELECT username, fullname, institution FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

file_put_contents('user_debug.txt', print_r($row, true));
$conn->close();
