<?php
require_once 'includes/config.php';

echo "All Videos (Last 50):\n";
echo sprintf("%-5s | %-30s | %-10s | %-10s | %-10s\n", "ID", "Title", "Format", "User ID", "Status");
echo str_repeat("-", 80) . "\n";

$sql = "SELECT id, title, format, user_id, status FROM videos ORDER BY created_at DESC LIMIT 50";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo sprintf(
            "%-5d | %-30s | %-10s | %-10s | %-10s\n",
            $row['id'],
            substr($row['title'], 0, 30),
            $row['format'],
            $row['user_id'],
            $row['status']
        );
    }
} else {
    echo "Query failed: " . $conn->error . "\n";
}
?>