<?php
// debug_get_attr_17.php
require_once 'includes/config.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');
$sql = "SELECT * FROM attribute_values WHERE id = 17";
$res = $conn->query($sql);
print_r($res->fetch_assoc());
