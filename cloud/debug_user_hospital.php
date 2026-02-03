<?php
// debug_user_hospital.php
require_once 'includes/config.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

echo "Users Check:\n";
$res = $conn->query("SELECT id, username, hospital_id FROM users LIMIT 5");
while ($row = $res->fetch_assoc()) {
    echo "User: " . $row['username'] . " HospitalID: " . var_export($row['hospital_id'], true) . "\n";
}

echo "\nHospitals Table Schema:\n";
$res = $conn->query("SHOW CREATE TABLE hospitals");
if ($row = $res->fetch_assoc()) {
    echo $row['Create Table'] . "\n";
}

echo "\nHospitals Count:\n";
$res = $conn->query("SELECT COUNT(*) as cnt FROM hospitals");
$row = $res->fetch_assoc();
echo "Count: " . $row['cnt'] . "\n";
