<?php
/**
 * DB Migration Script
 * 
 * Adds columns to 'videos' table and creates 'video_progress' table.
 */
require_once __DIR__ . '/../includes/config.php';

if (php_sapi_name() !== 'cli' && !is_manager()) {
    die("Unauthorized.");
}

try {
    // 1. Update 'videos' table
    echo "Updating 'videos' table...\n";
    $queries = [
        "ALTER TABLE videos ADD COLUMN format ENUM('mp4', 'evercam') DEFAULT 'mp4' AFTER content_path",
        "ALTER TABLE videos ADD COLUMN metadata JSON AFTER format",
        "ALTER TABLE videos ADD COLUMN duration INT DEFAULT 0 AFTER metadata"
    ];

    foreach ($queries as $sql) {
        if ($conn->query($sql)) {
            echo "Success: $sql\n";
        } else {
            echo "Note: " . $conn->error . "\n";
        }
    }

    // 2. Create 'video_progress' table
    echo "Creating 'video_progress' table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS video_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        video_id INT NOT NULL,
        last_position INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY user_video (user_id, video_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "Success: video_progress table created.\n";
    } else {
        throw new Exception("Error creating table: " . $conn->error);
    }

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage());
}
