<?php
// debug_check_job_titles.php
require_once 'includes/config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');

// 1. Get Type ID for job_title
echo "Looking for 'job_title' type...\n";
$stmt = $conn->prepare("SELECT id, scope FROM attribute_types WHERE code = 'job_title'");
$stmt->execute();
$type = $stmt->get_result()->fetch_assoc();

if (!$type) {
    die("Job Title type not found.\n");
}

$type_id = $type['id'];
echo "Type ID: $type_id, Scope: " . $type['scope'] . "\n\n";

// 2. List all Job Titles
echo "Listing Job Titles:\n";
$sql = "SELECT id, name, code, hospital_id FROM attribute_values WHERE type_id = $type_id";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $hid = $row['hospital_id'] === null ? 'NULL (Global)' : $row['hospital_id'];
    echo "ID: {$row['id']}, Name: {$row['name']}, Hospital: $hid\n";
}
