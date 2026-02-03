<?php
// debug_check_hospital.php
require_once 'includes/config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');

$hid = 16;
echo "Checking Hospital ID: $hid\n";
$sql = "SELECT * FROM hospitals WHERE id = $hid";
$result = $conn->query($sql);

if ($row = $result->fetch_assoc()) {
    print_r($row);
} else {
    echo "Hospital $hid not found.\n";
}
