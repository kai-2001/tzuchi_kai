<?php
require_once __DIR__ . '/../includes/config.php';
$conn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
$conn->set_charset('utf8mb4');

echo "=== Theme records in Moodle DB ===\n";
$result = $conn->query("SELECT * FROM mdl_config_plugins WHERE plugin IN ('theme_academi', 'theme_academi_clean')");
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']} | Plugin: {$row['plugin']} | Name: {$row['name']} | Value: {$row['value']}\n";
}

echo "\n=== Current theme setting ===\n";
$result = $conn->query("SELECT * FROM mdl_config WHERE name = 'theme'");
while ($row = $result->fetch_assoc()) {
    echo "Name: {$row['name']} | Value: {$row['value']}\n";
}
