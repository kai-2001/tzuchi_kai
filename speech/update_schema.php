<?php
require_once 'includes/config.php';

// Disable error reporting for cleaner output if needed, but here we want to see errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Schema Update</h2>";

// Check if user_id column already exists
$result = $conn->query("SHOW COLUMNS FROM videos LIKE 'user_id'");
if ($result->num_rows == 0) {
    echo "Adding 'user_id' column to 'videos' table...<br>";
    $sql = "ALTER TABLE videos ADD COLUMN user_id INT, ADD CONSTRAINT fk_video_user FOREIGN KEY (user_id) REFERENCES users(id)";
    if ($conn->query($sql)) {
        echo "Column 'user_id' added successfully.<br>";
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'user_id' already exists in 'videos' table.<br>";
}

echo "<br><a href='index.php'>Return to Home</a>";
$conn->close();
?>