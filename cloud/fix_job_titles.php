<?php
// fix_job_titles.php
require_once 'includes/config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');

echo "Starting Job Title Scope Fix...\n";

// 1. Get Type ID for job_title
$stmt = $conn->prepare("SELECT id FROM attribute_types WHERE code = 'job_title'");
$stmt->execute();
$type = $stmt->get_result()->fetch_assoc();

if (!$type) {
    die("Error: 'job_title' type not found.\n");
}

$type_id = $type['id'];
echo "Found 'job_title' Type ID: $type_id\n";

// 2. Change Scope to 'hospital'
$sql = "UPDATE attribute_types SET scope = 'hospital' WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $type_id);
if ($stmt->execute()) {
    echo "Success: Changed scope to 'hospital'.\n";
} else {
    echo "Error updating scope: " . $conn->error . "\n";
}
$stmt->close();

// 3. Assign existing global job titles to Taipei (ID 16)
$taipei_id = 16;
$sql = "UPDATE attribute_values SET hospital_id = ? WHERE type_id = ? AND hospital_id IS NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $taipei_id, $type_id);

if ($stmt->execute()) {
    echo "Success: Assigned " . $stmt->affected_rows . " global job titles to Taipei (ID $taipei_id).\n";
} else {
    echo "Error updating values: " . $conn->error . "\n";
}
$stmt->close();

echo "Fix complete. Please refresh.\n";
