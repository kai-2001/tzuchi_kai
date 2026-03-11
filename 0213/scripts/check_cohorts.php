<?php
require_once __DIR__ . '/../includes/config.php';
$conn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
$conn->set_charset('utf8mb4');

echo "=== All config with 'cloud' in value ===\n";
$result = $conn->query("SELECT name, value FROM mdl_config WHERE value LIKE '%cloud%'");
while ($row = $result->fetch_assoc()) {
    echo "  {$row['name']} = {$row['value']}\n";
}

echo "\n=== All config with 'login' or 'redirect' or 'alternate' in name ===\n";
$result = $conn->query("SELECT name, value FROM mdl_config WHERE name LIKE '%login%' OR name LIKE '%redirect%' OR name LIKE '%alternate%' ORDER BY name");
while ($row = $result->fetch_assoc()) {
    echo "  {$row['name']} = {$row['value']}\n";
}

echo "\n=== Plugin config with 'cloud' in value ===\n";
$result = $conn->query("SELECT plugin, name, value FROM mdl_config_plugins WHERE value LIKE '%cloud%'");
while ($row = $result->fetch_assoc()) {
    echo "  [{$row['plugin']}] {$row['name']} = {$row['value']}\n";
}

echo "\n=== Verify alternateloginurl current value ===\n";
$result = $conn->query("SELECT value FROM mdl_config WHERE name = 'alternateloginurl'");
$row = $result->fetch_assoc();
echo "  alternateloginurl = {$row['value']}\n";

// Purge Moodle cache
echo "\n=== Purging Moodle cache files ===\n";
$cacheDir = 'C:\\moodledata\\localcache';
if (is_dir($cacheDir)) {
    // Delete cache files
    $files = glob($cacheDir . '\\*');
    foreach ($files as $f) {
        if (is_file($f))
            unlink($f);
    }
    echo "  Deleted cache files in $cacheDir\n";
}

$cacheDir2 = 'C:\\moodledata\\cache';
if (is_dir($cacheDir2)) {
    echo "  Cache dir exists: $cacheDir2\n";
}
