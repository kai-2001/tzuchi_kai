<?php
require_once __DIR__ . '/../includes/config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

echo "Migrating course_rules table...\n";

// Create course_rules table if not exists
$sql = "CREATE TABLE IF NOT EXISTS course_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moodle_course_id BIGINT NOT NULL,
    rule_type ENUM('open', 'dept', 'custom') DEFAULT 'custom' COMMENT 'Role type: open (all hospital), dept (specific departments), custom (attributes)',
    rules_json JSON COMMENT 'Detailed rules definition',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_moodle_course (moodle_course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql)) {
    echo "Table 'course_rules' created/checked.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Check if rule_type column exists (in case table existed but old version)
$check = $conn->query("SHOW COLUMNS FROM course_rules LIKE 'rule_type'");
if ($check->num_rows == 0) {
    // Add column if missing
    $sql_alter = "ALTER TABLE course_rules ADD COLUMN rule_type ENUM('open', 'dept', 'custom') DEFAULT 'custom' AFTER moodle_course_id";
    if ($conn->query($sql_alter)) {
        echo "Column 'rule_type' added.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
}

// Check if rules_json column exists
$check_json = $conn->query("SHOW COLUMNS FROM course_rules LIKE 'rules_json'");
if ($check_json->num_rows == 0) {
    // Add column if missing
    $sql_alter_json = "ALTER TABLE course_rules ADD COLUMN rules_json JSON COMMENT 'Detailed rules definition' AFTER rule_type";
    if ($conn->query($sql_alter_json)) {
        echo "Column 'rules_json' added.\n";
    } else {
        echo "Error adding column rules_json: " . $conn->error . "\n";
    }
}

$conn->close();
