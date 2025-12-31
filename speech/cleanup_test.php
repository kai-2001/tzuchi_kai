<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$ids = [];
$check = $conn->query("SELECT id, title FROM videos WHERE title LIKE '__TEST%'");
while ($row = $check->fetch_assoc()) {
    $ids[] = $row['id'];
    echo "Found test video: " . $row['title'] . " (ID: " . $row['id'] . ")\n";
}

if (!empty($ids)) {
    $id_str = implode(',', $ids);
    $conn->query("DELETE FROM videos WHERE id IN ($id_str)");
    echo "Deleted " . count($ids) . " test videos.\n";
} else {
    echo "No test videos found.\n";
}
?>