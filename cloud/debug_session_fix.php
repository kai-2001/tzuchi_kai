<?php
require_once 'includes/config.php';

// Fix ID 39 (1A) to belong to Taipei Hospital (ID 16)
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->query("UPDATE attribute_values SET hospital_id = 16 WHERE id = 39 AND hospital_id IS NULL");
echo "Fixed affected rows: " . $conn->affected_rows . "\n";

echo "Session Data:\n";
print_r($_SESSION);
