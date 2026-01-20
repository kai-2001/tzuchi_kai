<?php
require_once 'includes/config.php';

// Add columns if they don't exist
$alter_sql = "
    ALTER TABLE announcements 
    ADD COLUMN IF NOT EXISTS hero_start_date DATE NULL AFTER is_hero,
    ADD COLUMN IF NOT EXISTS hero_end_date DATE NULL AFTER hero_start_date
";

if ($conn->query($alter_sql)) {
    echo "Migration successful: Added hero_start_date and hero_end_date columns.\n";
} else {
    echo "Migration failed: " . $conn->error . "\n";
}
?>