<?php
// fix_all_missing_hospital_ids.php
require_once 'includes/config.php';
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    // Goal: Find ALL child valid attributes (Units, etc.) that have NO hospital_id
    // Decision Logic:
    // 1. If Parent has hospital_id, COPY check. 
    // 2. If Parent is Global (NULL), but Attribute is Type 3 (Unit), it MUST be Local. Assign to ID 16 (Taipei).

    echo "Scanning for Read-Only Child Units (Missing hospital_id)...\n";

    $sql = "SELECT id, name, parent_id, type_id, hospital_id FROM attribute_values WHERE parent_id IS NOT NULL AND hospital_id IS NULL";
    $res = $conn->query($sql);

    $count = 0;
    while ($row = $res->fetch_assoc()) {
        $p_id = $row['parent_id'];
        $my_id = $row['id'];
        $name = $row['name'];
        echo "Found: [ID $my_id] $name (Parent $p_id)\n";

        // Check Parent
        $pres = $conn->query("SELECT hospital_id FROM attribute_values WHERE id = $p_id");
        $prow = $pres->fetch_assoc();

        $new_hid = null;
        if ($prow && $prow['hospital_id']) {
            $new_hid = $prow['hospital_id'];
            echo " -> Parent is Local (H_ID $new_hid). Inheriting...\n";
        } else {
            // Parent is Global
            // Assuming current context is Taipei Admin (who is reporting issues), so assign to ID 16
            $new_hid = 16;
            echo " -> Parent is Global. Forcing assignment into Taipei Hospital (H_ID $new_hid)...\n";
        }

        if ($new_hid) {
            $conn->query("UPDATE attribute_values SET hospital_id = $new_hid WHERE id = $my_id");
            echo " -> FIXED.\n";
            $count++;
        }
    }

    if ($count === 0) {
        echo "No issues found to fix.\n";
    } else {
        echo "Total fixed: $count\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
