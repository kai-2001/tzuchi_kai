<?php
/**
 * Add missing cohort-related functions to Moodle external service
 */
require_once __DIR__ . '/../includes/config.php';

// Connect to Moodle DB directly
$conn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
$conn->set_charset('utf8mb4');

// Find the external service ID (Portal API)
$result = $conn->query("SELECT id, name, shortname FROM mdl_external_services WHERE shortname IS NOT NULL ORDER BY id");
echo "=== Existing External Services ===\n";
while ($row = $result->fetch_assoc()) {
    echo "  ID: {$row['id']}, Name: {$row['name']}, Shortname: {$row['shortname']}\n";
}

// Find which service the token belongs to
$token = $GLOBALS['moodle_token'] ?? '';
echo "\n=== Token Service ===\n";
$stmt = $conn->prepare("SELECT t.id, t.externalserviceid, s.name, s.shortname FROM mdl_external_tokens t JOIN mdl_external_services s ON t.externalserviceid = s.id WHERE t.token = ?");
$stmt->bind_param('s', $token);
$stmt->execute();
$tokenResult = $stmt->get_result();
$tokenRow = $tokenResult->fetch_assoc();

if (!$tokenRow) {
    echo "Token not found! Checking config...\n";
    // Read from config
    require_once __DIR__ . '/../core/config.php';
    echo "Token from config: " . substr($moodle_token, 0, 10) . "...\n";
    $stmt2 = $conn->prepare("SELECT t.id, t.externalserviceid, s.name, s.shortname FROM mdl_external_tokens t JOIN mdl_external_services s ON t.externalserviceid = s.id WHERE t.token = ?");
    $stmt2->bind_param('s', $moodle_token);
    $stmt2->execute();
    $tokenResult2 = $stmt2->get_result();
    $tokenRow = $tokenResult2->fetch_assoc();
}

if ($tokenRow) {
    $serviceId = $tokenRow['externalserviceid'];
    echo "Service ID: $serviceId, Name: {$tokenRow['name']}\n";

    // Functions to add
    $functions = [
        'core_cohort_create_cohorts',
        'core_cohort_delete_cohorts',
        'core_cohort_get_cohorts',
        'core_cohort_update_cohorts',
        'core_cohort_add_cohort_members',
        'core_cohort_delete_cohort_members',
        'core_cohort_get_cohort_members',
        'core_cohort_search_cohorts',
    ];

    echo "\n=== Adding Functions ===\n";
    foreach ($functions as $func) {
        // Check if already exists
        $check = $conn->prepare("SELECT id FROM mdl_external_services_functions WHERE externalserviceid = ? AND functionname = ?");
        $check->bind_param('is', $serviceId, $func);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if ($exists) {
            echo "  [SKIP] $func (already exists)\n";
        } else {
            $insert = $conn->prepare("INSERT INTO mdl_external_services_functions (externalserviceid, functionname) VALUES (?, ?)");
            $insert->bind_param('is', $serviceId, $func);
            if ($insert->execute()) {
                echo "  [ADDED] $func\n";
            } else {
                echo "  [ERROR] $func: " . $insert->error . "\n";
            }
            $insert->close();
        }
    }
    echo "\nDone!\n";
} else {
    echo "Could not find token service!\n";
}
