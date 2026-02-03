<?php
// fix_db_state.php
require_once 'includes/config.php';
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// 1. Create Taipei Hospital if missing
// We try to force ID 16 if possible to match "test_taipei" attribute value convention
$h_id = 16;
$h_name = '台北慈濟醫院';
$h_code = 'TPE';

echo "Checking hospital ID $h_id...\n";
$res = $conn->query("SELECT id FROM hospitals WHERE id = $h_id");
if ($res->num_rows == 0) {
    echo "Creating hospital $h_name (ID $h_id)...\n";
    // Check if we can insert with ID
    $conn->query("INSERT INTO hospitals (id, code, name, is_active) VALUES ($h_id, '$h_code', '$h_name', 1)");
    echo "Hospital created.\n";
} else {
    echo "Hospital ID $h_id exists.\n";
}

// 2. Find Taipei Test Admin
// We look for a user that looks like 'taipei_admin' or similar
$res = $conn->query("SELECT id, username, hospital_id FROM users WHERE username LIKE '%taipei%' OR name LIKE '%台北%' LIMIT 1");
if ($u_row = $res->fetch_assoc()) {
    echo "Found User: " . $u_row['username'] . " (Current H_ID: " . var_export($u_row['hospital_id'], true) . ")\n";
    if ($u_row['hospital_id'] != $h_id) {
        $conn->query("UPDATE users SET hospital_id = $h_id WHERE id = " . $u_row['id']);
        echo "Updated User to be in Hospital $h_id.\n";
    }
} else {
    echo "Could not find 'Taipei' user. Please provide username.\n";
}

// 3. Fix Unit 39 (1A)
echo "Fixing Unit 39...\n";
$conn->query("UPDATE attribute_values SET hospital_id = $h_id WHERE id = 39");
// Also update parent if needed? No, parent 19 is Global (Nursing).
// But maybe update attribute_value 16? (The Type 4 one)
$conn->query("UPDATE attribute_values SET hospital_id = NULL WHERE id = 16"); // It should be NULL as it defines the hospital.

echo "Done. Unit 39 now belongs to Hospital $h_id.\n";
