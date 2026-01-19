<?php
// scripts/debug_roles_direct.php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'root123';
$moodle_db = 'moodle';

$username = 'test_admin_taipei';

echo "Checking $username in $moodle_db...\n";

$conn = new mysqli($db_host, $db_user, $db_pass, $moodle_db);
if ($conn->connect_error)
    die("Conn failed");
$conn->set_charset('utf8mb4');

// Get ID
$res = $conn->query("SELECT id FROM mdl_user WHERE username = '$username'");
$row = $res->fetch_assoc();
if (!$row)
    die("User not found via SQL\n");
$uid = $row['id'];
echo "User ID: $uid\n";

// Get Roles
$sql = "
SELECT ra.id, r.shortname, c.contextlevel, c.instanceid 
FROM mdl_role_assignments ra
JOIN mdl_role r ON r.id = ra.roleid
JOIN mdl_context c ON c.id = ra.contextid
WHERE ra.userid = $uid
";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "Done.\n";
