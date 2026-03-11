<?php
// scripts/add_institution_column.php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/includes/db_connect.php');

// Check if column exists
$check = $conn->query("SHOW COLUMNS FROM users LIKE 'institution'");
if ($check->num_rows == 0) {
    echo "Adding institution column...\n";
    $sql = "ALTER TABLE users ADD COLUMN institution VARCHAR(50) DEFAULT '' AFTER email";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'institution' added successfully.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column 'institution' already exists.\n";
}

$conn->close();
