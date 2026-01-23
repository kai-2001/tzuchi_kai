<?php
require_once __DIR__ . '/../speech/includes/config.php';

echo "Adding branch_code to campuses table...\n";

// 1. Add Column
$check = $conn->query("SHOW COLUMNS FROM campuses LIKE 'branch_code'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE campuses ADD COLUMN branch_code VARCHAR(10) DEFAULT NULL AFTER name";
    if ($conn->query($sql)) {
        echo "Successfully added 'branch_code' column.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column 'branch_code' already exists.\n";
}

// 2. Populate Data
$updates = [
    'dl' => '大林',
    'hl' => '花蓮',
    'tc' => '台中',
    'tp' => '台北',
    'tz' => '法人'
];

foreach ($updates as $code => $name_part) {
    $stmt = $conn->prepare("UPDATE campuses SET branch_code = ? WHERE name LIKE ?");
    $param = "%$name_part%";
    $stmt->bind_param("ss", $code, $param);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo "Updated $name_part to code: $code\n";
    } else {
        echo "No match found/updated for $name_part\n";
    }
}

echo "Done.\n";
?>