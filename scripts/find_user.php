<?php
require_once __DIR__ . '/../includes/config.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');
if ($conn->connect_error)
    die("Conn failed");

echo "Searching for '台北測試管理員'...\n";
$sql = "SELECT username, fullname, role FROM users WHERE fullname LIKE '%台北測試管理員%'";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "Done.\n";
