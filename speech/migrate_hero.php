<?php
require_once 'includes/config.php';

$sql = "CREATE TABLE IF NOT EXISTS hero_slides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    speaker_name VARCHAR(100),
    event_date DATE,
    campus_id INT DEFAULT 0,
    link_url VARCHAR(255),
    image_url VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    echo "Table 'hero_slides' created or already exists.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
?>