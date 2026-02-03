<?php
require_once 'includes/config.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$res = $conn->query("DESCRIBE attribute_values");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
