<?php
// scripts/migrate_phase2_learning_paths.php
require_once __DIR__ . '/../includes/config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

echo "Starting Phase 2 Migration: Learning Paths tables...\n";

// 1. learning_paths
$sql_lp = "CREATE TABLE IF NOT EXISTS learning_paths (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT, -- NULL for Global paths, Value for Hospital-specific
    name VARCHAR(255) NOT NULL,
    description TEXT,
    target_rule JSON COMMENT 'JSON rule definition for target audience',
    is_required TINYINT(1) DEFAULT 0 COMMENT 'Is this path mandatory for target audience?',
    created_by INT COMMENT 'User ID of creator',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql_lp)) {
    echo "Table 'learning_paths' created or exists.\n";
} else {
    echo "Error creating 'learning_paths': " . $conn->error . "\n";
}

// 2. learning_path_courses
$sql_lpc = "CREATE TABLE IF NOT EXISTS learning_path_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    path_id INT NOT NULL,
    moodle_course_id BIGINT NOT NULL COMMENT 'Moodle Course ID',
    display_order INT DEFAULT 0,
    is_required TINYINT(1) DEFAULT 1 COMMENT 'Is this specific course required in the path?',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (path_id) REFERENCES learning_paths(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql_lpc)) {
    echo "Table 'learning_path_courses' created or exists.\n";
} else {
    echo "Error creating 'learning_path_courses': " . $conn->error . "\n";
}

// 3. learning_path_managers (Optional: for assigning other managers to a path)
$sql_lpm = "CREATE TABLE IF NOT EXISTS learning_path_managers (
    path_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (path_id, user_id),
    FOREIGN KEY (path_id) REFERENCES learning_paths(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql_lpm)) {
    echo "Table 'learning_path_managers' created or exists.\n";
} else {
    echo "Error creating 'learning_path_managers': " . $conn->error . "\n";
}

$conn->close();
echo "Migration Phase 2 Complete.\n";
