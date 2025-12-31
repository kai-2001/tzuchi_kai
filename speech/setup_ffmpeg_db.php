<?php
require_once 'includes/config.php';

// Check DB connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Updating database schema...<br>";

// 1. Add status column
try {
    $sql = "ALTER TABLE videos ADD COLUMN status ENUM('pending', 'processing', 'ready', 'error') DEFAULT 'ready'";
    if ($conn->query($sql) === TRUE) {
        echo "Successfully added 'status' column.<br>";
    } else {
        // Ignore if already exists (error 1060) but show others
        if ($conn->errno == 1060) {
            echo "'status' column already exists.<br>";
        } else {
            echo "Error adding 'status' column: " . $conn->error . "<br>";
        }
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "<br>";
}

// 2. Add process_msg column
try {
    $sql = "ALTER TABLE videos ADD COLUMN process_msg TEXT NULL";
    if ($conn->query($sql) === TRUE) {
        echo "Successfully added 'process_msg' column.<br>";
    } else {
        if ($conn->errno == 1060) {
            echo "'process_msg' column already exists.<br>";
        } else {
            echo "Error adding 'process_msg' column: " . $conn->error . "<br>";
        }
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "<br>";
}

// 3. Add job_id column (optional, for future multi-worker)
try {
    $sql = "ALTER TABLE videos ADD COLUMN job_id VARCHAR(50) NULL";
    if ($conn->query($sql) === TRUE) {
        echo "Successfully added 'job_id' column.<br>";
    } else {
        if ($conn->errno == 1060) {
            echo "'job_id' column already exists.<br>";
        } else {
            echo "Error adding 'job_id' column: " . $conn->error . "<br>";
        }
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "<br>";
}

echo "Database update completed.";
?>