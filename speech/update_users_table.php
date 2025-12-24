<?php
require_once 'includes/config.php';

// SQL to update users table
$sqls = [
    "ALTER TABLE users ADD COLUMN password VARCHAR(255) AFTER username",
    "ALTER TABLE users ADD COLUMN display_name VARCHAR(100) AFTER password",
    "ALTER TABLE users ADD COLUMN email VARCHAR(255) AFTER display_name",
    "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER role",
    "ALTER TABLE users ADD COLUMN last_login DATETIME AFTER status",
    "ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER last_login"
];

foreach ($sqls as $sql) {
    try {
        if ($conn->query($sql) === TRUE) {
            echo "Successfully executed: $sql\n";
        } else {
            echo "Error executing $sql: " . $conn->error . "\n";
        }
    } catch (Exception $e) {
        echo "Skipping $sql (might already exist): " . $e->getMessage() . "\n";
    }
}

$conn->close();
?>