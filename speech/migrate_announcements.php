<?php
require_once 'includes/config.php';

// 1. Create announcements table
$sql_create = "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    speaker_name VARCHAR(255),
    affiliation VARCHAR(255),
    position VARCHAR(255),
    event_date DATE,
    event_time_start TIME,
    event_time_end TIME,
    campus_id INT,
    location VARCHAR(255),
    image_url VARCHAR(500),
    link_url VARCHAR(500),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    is_hero TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (is_active),
    INDEX (is_hero),
    INDEX (campus_id),
    INDEX (event_date)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

if ($conn->query($sql_create) === TRUE) {
    echo "Table 'announcements' created successfully.\n";
} else {
    die("Error creating table: " . $conn->error);
}

// 2. Migrate hero_slides
// Map: title -> title, image_url -> image_url, link_url -> link_url, sort_order -> sort_order, is_active -> is_active, is_hero -> is_hero
//      speaker_name -> speaker_name, event_date -> event_date, campus_id -> campus_id
// Note: Some fields might not map perfectly or might be overly specific in hero_slides logic, but we do our best.
// Assuming hero_slides has: id, title, image_url, link_url, is_active, sort_order, created_at, speaker_name, event_date, campus_id, is_hero
// We'll select common columns.

$sql_migrate_hero = "INSERT INTO announcements (title, image_url, link_url, sort_order, is_active, is_hero, speaker_name, event_date, campus_id, created_at)
SELECT title, image_url, link_url, sort_order, is_active, is_hero, speaker_name, event_date, campus_id, created_at
FROM hero_slides";

if ($conn->query($sql_migrate_hero) === TRUE) {
    echo "Migrated " . $conn->affected_rows . " rows from hero_slides.\n";
} else {
    echo "Error migrating hero_slides: " . $conn->error . "\n";
}

// 3. Migrate upcoming_lectures
// Map: title -> title, speaker_name -> speaker_name, affiliation -> affiliation, event_date -> event_date, 
//      campus_id -> campus_id, location -> location, description -> description
//      is_active -> 1 (default), is_hero -> 0 (default)

$sql_migrate_upcoming = "INSERT INTO announcements (title, speaker_name, affiliation, event_date, campus_id, location, description, is_active, is_hero)
SELECT title, speaker_name, affiliation, event_date, campus_id, location, description, 1, 0
FROM upcoming_lectures";

if ($conn->query($sql_migrate_upcoming) === TRUE) {
    echo "Migrated " . $conn->affected_rows . " rows from upcoming_lectures.\n";
} else {
    echo "Error migrating upcoming_lectures: " . $conn->error . "\n";
}

echo "Migration complete.";
