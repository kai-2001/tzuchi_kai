<?php
// fix_unit_permissions_v2.php
require_once 'includes/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Enable exception mode
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    echo "Connecting...\n";
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    // Check if ID 39 exists
    $res = $conn->query("SELECT * FROM attribute_values WHERE id = 39");
    if ($row = $res->fetch_assoc()) {
        echo "Found ID 39: " . json_encode($row) . "\n";
    } else {
        echo "ID 39 NOT FOUND.\n";
        exit;
    }

    echo "Updating...\n";
    $stmt = $conn->prepare("UPDATE attribute_values SET hospital_id = ? WHERE id = ?");
    $h_id = 16;
    $Id = 39;
    $stmt->bind_param("ii", $h_id, $Id);
    $stmt->execute();

    echo "Updated rows: " . $stmt->affected_rows . "\n";

    // Verify
    $res = $conn->query("SELECT * FROM attribute_values WHERE id = 39");
    $row = $res->fetch_assoc();
    echo "After update: " . json_encode($row) . "\n";

} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
