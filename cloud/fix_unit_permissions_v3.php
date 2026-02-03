<?php
// fix_unit_permissions_v3.php
require_once 'includes/config.php';
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    echo "Hospitals Table:\n";
    $res = $conn->query("SELECT id, name, code FROM hospitals");
    while ($row = $res->fetch_assoc()) {
        echo "ID: " . $row['id'] . " Name: " . $row['name'] . " Code: " . $row['code'] . "\n";
    }

    // Try to find Taipei
    $res = $conn->query("SELECT id FROM hospitals WHERE name LIKE '%台北%' LIMIT 1");
    if ($row = $res->fetch_assoc()) {
        $real_h_id = $row['id'];
        echo "Found Taipei Hospital ID: $real_h_id\n";

        $stmt = $conn->prepare("UPDATE attribute_values SET hospital_id = ? WHERE id = 39");
        $stmt->bind_param("i", $real_h_id);
        $stmt->execute();
        echo "Updated ID 39 to hospital_id $real_h_id. Rows: " . $stmt->affected_rows . "\n";
    } else {
        echo "Could not find Taipei hospital.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
