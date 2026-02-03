<?php
// debug_check_hospitals_data.php
require_once 'includes/config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');

echo "--- TABLE: hospitals ---\n";
$sql = "SELECT id, code, name FROM hospitals";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\n--- TABLE: attribute_values (type=hospital) ---\n";
// Find type id for hospital first
$stmt = $conn->prepare("SELECT id FROM attribute_types WHERE code = 'hospital'");
$stmt->execute();
$type_row = $stmt->get_result()->fetch_assoc();
$type_id = $type_row['id'];

$sql = "SELECT id, code, name FROM attribute_values WHERE type_id = $type_id";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
