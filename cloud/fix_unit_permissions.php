<?php
// fix_unit_permissions.php
require_once 'includes/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Connecting to DB...\n";
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Fix ID 39 (1A) to belong to Taipei (ID 16)
echo "Fixing ID 39...\n";
$sql = "UPDATE attribute_values SET hospital_id = 16 WHERE id = 39 AND hospital_id IS NULL";
if ($conn->query($sql) === TRUE) {
    if ($conn->affected_rows > 0) {
        echo "Success: Updated ID 39 to hospital_id 16.\n";
    } else {
        echo "Info: ID 39 was not updated (maybe already fixed or not found).\n";
    }
} else {
    echo "Error updating record: " . $conn->error . "\n";
}

// 2. Fix any other orphaned children?
// Find parent_id IS NOT NULL, parent has hospital_id IS NULL (Global Parent), BUT child has hospital_id IS NULL (Global Child).
// If created by Hospital Admin, should be Local.
// We can't auto-fix others without knowing which hospital they belong to.
// But we can check if there are any others.
$check_orphans = "
SELECT c.id, c.name, c.created_at 
FROM attribute_values c
JOIN attribute_values p ON c.parent_id = p.id
WHERE p.hospital_id IS NULL 
AND c.hospital_id IS NULL
AND c.parent_id IS NOT NULL
";
$res = $conn->query($check_orphans);
if ($res->num_rows > 0) {
    echo "Warning: Found unmatched global child units:\n";
    while ($row = $res->fetch_assoc()) {
        echo "ID: " . $row['id'] . " Name: " . $row['name'] . " Created: " . $row['created_at'] . "\n";
    }
} else {
    echo "No other potential orphan units found.\n";
}

$conn->close();
