<?php
// create_mapping_table.php
require_once 'includes/config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');

echo "Creating table `category_attribute_mapping`...\n";

$sql = "CREATE TABLE IF NOT EXISTS category_attribute_mapping (
    moodle_category_id BIGINT(10) NOT NULL PRIMARY KEY,
    hospital_attr_id INT(11) NOT NULL,
    department_attr_id INT(11) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_attr_id) REFERENCES attribute_values(id) ON DELETE CASCADE,
    FOREIGN KEY (department_attr_id) REFERENCES attribute_values(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql)) {
    echo "Success: Table created.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

$conn->close();
