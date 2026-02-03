<?php
// fix_create_hospital_17.php
require_once 'includes/config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');

echo "Inserting Hualien Hospital (ID 17) into `hospitals` table...\n";

// Force ID 17
$id = 17;
$code = 'HLN';
$name = '花蓮慈濟醫院';

$sql = "INSERT IGNORE INTO hospitals (id, code, name, is_active) VALUES (?, ?, ?, 1)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $id, $code, $name);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "Success: Created Hospital ID 17 ($name).\n";
    } else {
        echo "Info: Hospital ID 17 already exists.\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
$stmt->close();
