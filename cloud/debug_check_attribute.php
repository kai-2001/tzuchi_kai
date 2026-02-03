<?php
// debug_check_attribute.php
require_once 'includes/config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');

echo "Checking attribute '3a'...\n";
$sql = "SELECT id, code, name, hospital_id, parent_id FROM attribute_values WHERE name = '3a'";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    print_r($row);
}
