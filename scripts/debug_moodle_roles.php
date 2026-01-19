<?php
// scripts/debug_moodle_roles.php
// Dump raw Moodle role assignments for a user to debug sync issues

require_once __DIR__ . '/../includes/config.php';

$username = isset($argv[1]) ? $argv[1] : 'taipei01'; // Default or pass via CLI
// If running from browser/tool without args, maybe try to guess or list recent

echo "Debug tool for checking Moodle roles (Direct DB)\n";
echo "Checking user: $username\n\n";

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, 'moodle'); // Explicitly connect to 'moodle' DB
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');

    // 1. Get User
    $stmt = $conn->prepare("SELECT id, username, email FROM mdl_user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        die("User '$username' not found in Moodle DB.\n");
    }
    echo "Found Moodle User ID: " . $user['id'] . "\n";

    // 2. Get Assignments
    $sql = "
        SELECT ra.id, ra.roleid, ra.contextid, 
               r.shortname, r.name as role_name,
               c.contextlevel, c.instanceid, c.path
        FROM mdl_role_assignments ra
        JOIN mdl_role r ON r.id = ra.roleid
        JOIN mdl_context c ON c.id = ra.contextid
        WHERE ra.userid = ?
        ORDER BY r.sortorder ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $res = $stmt->get_result();

    echo "--------------------------------------------------\n";
    echo "| Role (Short) | Level (10=Sys, 40=Cat) | InstID |\n";
    echo "--------------------------------------------------\n";

    while ($row = $res->fetch_assoc()) {
        printf(
            "| %-12s | %-22s | %-6s |\n",
            $row['shortname'],
            $row['contextlevel'],
            $row['instanceid']
        );
    }
    echo "--------------------------------------------------\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
