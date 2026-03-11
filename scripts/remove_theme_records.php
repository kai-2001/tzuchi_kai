<?php
require_once __DIR__ . '/../includes/config.php';
$conn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
$conn->set_charset('utf8mb4');

echo "=== Deleting Theme Records ===\n";
// Delete from mdl_config_plugins
$conn->query("DELETE FROM mdl_config_plugins WHERE plugin IN ('theme_academi', 'theme_academi_clean')");
echo "Deleted from mdl_config_plugins: " . $conn->affected_rows . " row(s)\n";

// Ensure current theme is a valid one (like 'boost' or 'classic')
$result = $conn->query("SELECT value FROM mdl_config WHERE name = 'theme'");
$row = $result->fetch_assoc();
$currentTheme = $row['value'];
echo "Current theme was: $currentTheme\n";

if ($currentTheme === 'academi' || $currentTheme === 'academi_clean') {
    $conn->query("UPDATE mdl_config SET value = 'boost' WHERE name = 'theme'");
    echo "Reset theme to 'boost'.\n";
}

// Clear all references to these themes in other plugin configs if any
$conn->query("DELETE FROM mdl_config_plugins WHERE value IN ('academi', 'academi_clean') AND name LIKE 'theme%'");
echo "Cleaned up other plugin theme references: " . $conn->affected_rows . " row(s)\n";

echo "Done!\n";
