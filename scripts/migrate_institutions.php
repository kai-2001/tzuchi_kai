<?php
// scripts/migrate_institutions.php
// Create institutions table and seed data

require_once __DIR__ . '/../includes/config.php';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');

    // 1. Create Data Table
    $sql = "CREATE TABLE IF NOT EXISTS institutions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        cohort_idnumber VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if ($conn->query($sql) === TRUE) {
        echo "Table 'institutions' created successfully.<br>";
    } else {
        throw new Exception("Error creating table: " . $conn->error);
    }

    // 2. Seed Data
    $data = [
        '台北' => 'cohort_taipei',
        '嘉義' => 'cohort_chiayi',
        '大林' => 'cohort_dalin',
        '花蓮' => 'cohort_hualien'
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO institutions (name, cohort_idnumber) VALUES (?, ?)");

    foreach ($data as $name => $cohort) {
        $stmt->bind_param("ss", $name, $cohort);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo "Inserted: $name -> $cohort<br>";
            } else {
                echo "Skipped (already exists): $name<br>";
            }
        } else {
            echo "Error inserting $name: " . $stmt->error . "<br>";
        }
    }
    $stmt->close();
    $conn->close();
    echo "Migration completed.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
