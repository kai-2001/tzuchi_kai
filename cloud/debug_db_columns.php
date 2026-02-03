<?php
require_once 'includes/config.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

echo "Database: $db_name\n";

// Check columns
echo "Columns in attribute_values:\n";
$res = $conn->query("SHOW COLUMNS FROM attribute_values");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\nData for ID 39:\n";
$res = $conn->query("SELECT * FROM attribute_values WHERE id = 39");
if ($row = $res->fetch_assoc()) {
    print_r($row);
} else {
    echo "ID 39 not found.\n";
}

echo "\nChecking API file content match:\n";
$content = file_get_contents('api/admin/attribute_values.php');
if (strpos($content, 'INSERT INTO attribute_values (type_id, code, name, hospital_id, parent_id, display_order)') !== false) {
    echo "INSERT statement includes parent_id correctly.\n";
} else {
    echo "INSERT statement MISSING parent_id column!\n";
}
