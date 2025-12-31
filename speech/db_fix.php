<?php
// db_fix.php - Force add columns
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
// Check for auth if config doesn't give conn
// require_once 'includes/auth.php'; 

global $conn;

if (!$conn) {
    echo "<b>Error:</b> \$conn is null after including config.php.<br>";
    // Try root config as fallback?
    if (file_exists('../includes/config.php')) {
        echo "Trying root config...<br>";
        require_once '../includes/config.php';
    }
}

if (!$conn) {
    if (defined('DB_HOST')) {
        echo "Defining conn manually using constants...<br>";
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    } else {
        // Hardcode fallback if all else fails (user verify required)
        die("Cannot connect to DB. Constants not defined.");
    }
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Database connected.<br>";

$queries = [
    "ALTER TABLE videos ADD COLUMN status ENUM('pending', 'processing', 'ready', 'error') DEFAULT 'ready'",
    "ALTER TABLE videos ADD COLUMN process_msg TEXT NULL",
    "ALTER TABLE videos ADD COLUMN job_id VARCHAR(50) NULL"
];

foreach ($queries as $sql) {
    echo "Executing: $sql ... ";
    try {
        if ($conn->query($sql) === TRUE) {
            echo "<span style='color:green'>Success</span><br>";
        } else {
            if ($conn->errno == 1060) {
                echo "<span style='color:orange'>Column already exists</span><br>";
            } else {
                echo "<span style='color:red'>Error: " . $conn->error . "</span><br>";
            }
        }
    } catch (Exception $e) {
        echo "<span style='color:red'>Exception: " . $e->getMessage() . "</span><br>";
    }
}

echo "Done.";
?>