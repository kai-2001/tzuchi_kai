<?php
// fix_unit_types.php
require_once 'includes/config.php';
header('Content-Type: text/plain; charset=utf-8');

// Enable error reporting
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    echo "Connecting to DB...\n";
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset('utf8mb4');

    // Goals:
    // 1. Find all attributes that are currently type_id = 1 (Department) BUT have a parent_id (meaning they are sub-units).
    // 2. Update them to type_id = 3 (Unit).

    echo "Scanning for child units with incorrect type...\n";

    // Select candidates
    $sql = "SELECT id, name, parent_id, type_id FROM attribute_values WHERE type_id = 1 AND parent_id IS NOT NULL";
    $res = $conn->query($sql);

    $candidates = [];
    while ($row = $res->fetch_assoc()) {
        $candidates[] = $row;
    }

    if (empty($candidates)) {
        echo "No child units found with type_id=1. Migration might have already run or no data needs fixing.\n";
    } else {
        echo "Found " . count($candidates) . " records to fix:\n";
        foreach ($candidates as $c) {
            echo " - ID {$c['id']}: {$c['name']} (Parent {$c['parent_id']})\n";
        }

        // Execute update
        $updateSql = "UPDATE attribute_values SET type_id = 3 WHERE type_id = 1 AND parent_id IS NOT NULL";
        $conn->query($updateSql);

        echo "Migration completed. Updated rows: " . $conn->affected_rows . "\n";
    }

    $conn->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
