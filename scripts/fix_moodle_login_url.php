<?php
require_once __DIR__ . '/../includes/config.php';
$conn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
$conn->set_charset('utf8mb4');

$newUrl = 'http://kai/0213/index.php';
$stmt = $conn->prepare("UPDATE mdl_config SET value = ? WHERE name = 'alternateloginurl'");
$stmt->bind_param('s', $newUrl);
$stmt->execute();
echo "Updated alternateloginurl: {$stmt->affected_rows} row(s)\n";
echo "New value: $newUrl\n";
$stmt->close();

// Also purge Moodle cache by updating config timestamp
$conn->query("UPDATE mdl_config SET value = UNIX_TIMESTAMP() WHERE name = 'allversionshash'");
echo "Cache invalidated.\n";
