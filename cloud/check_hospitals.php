<?php
// check_hospitals.php
require_once 'includes/config.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');

echo "Listing ALL Hospitals from table 'hospitals':\n";
$res = $conn->query("SELECT id, name, code FROM hospitals");
if ($res) {
    if ($res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            echo "ID: " . $row['id'] . " | " . $row['name'] . " (" . $row['code'] . ")\n";
        }
    } else {
        echo "0 results in hospitals table.\n";
    }
} else {
    echo "Query failed: " . $conn->error . "\n";
}

// Check attribute_values foreign key linkage
echo "\nChecking existing hospital_ids in attribute_values:\n";
$res2 = $conn->query("SELECT DISTINCT hospital_id FROM attribute_values WHERE hospital_id IS NOT NULL");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        echo "Found hospital_id use: " . $row['hospital_id'] . "\n";
    }
}
