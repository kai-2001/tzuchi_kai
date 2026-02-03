<?php
// sys_update_schema.php
// Updates learning_paths table to support enrollment rules

require_once 'includes/config.php';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');

    echo "Connected successfully.\n";

    // 1. Check if 'rules' column exists in 'learning_paths'
    $colCheck = $conn->query("SHOW COLUMNS FROM learning_paths LIKE 'rules'");
    if ($colCheck->num_rows == 0) {
        // Add 'rules' column (JSON)
        // Add 'enroll_policy' (ENUM/VARCHAR)
        $sql = "ALTER TABLE learning_paths 
                ADD COLUMN enroll_policy VARCHAR(20) DEFAULT 'open' AFTER description,
                ADD COLUMN rules JSON DEFAULT NULL AFTER enroll_policy";

        if ($conn->query($sql) === TRUE) {
            echo "Columns 'enroll_policy' and 'rules' added successfully.\n";
        } else {
            echo "Error adding columns: " . $conn->error . "\n";
        }
    } else {
        echo "Columns already exist.\n";
    }

    $conn->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>