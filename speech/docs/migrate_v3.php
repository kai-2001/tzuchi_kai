<?php
/**
 * DB Migration Script v3
 * 
 * Creates 'upcoming_lectures' table for future lecture announcements.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (php_sapi_name() !== 'cli' && !is_manager()) {
    die("Unauthorized.");
}

try {
    echo "Creating 'upcoming_lectures' table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS upcoming_lectures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        speaker_name VARCHAR(100) NOT NULL,
        affiliation VARCHAR(255),
        event_date DATE NOT NULL,
        location VARCHAR(255),
        campus_id INT NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "Success: upcoming_lectures table created.\n";
    } else {
        throw new Exception("Error creating table: " . $conn->error);
    }

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage());
}
