<?php
require_once __DIR__ . '/../includes/config.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error)
    die("Conn failed");
$res = $conn->query("DESCRIBE users");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
