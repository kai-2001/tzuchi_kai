<?php
require_once 'includes/config.php';

echo "FETCHING VIDEOS...\n";
$sql = "SELECT id, title, format, user_id, status FROM videos ORDER BY created_at DESC LIMIT 50";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", FMT: " . $row['format'] . ", UID: " . $row['user_id'] . ", TITLE: " . $row['title'] . "\n";
    }
} else {
    echo "Query Error: " . $conn->error . "\n";
}
?>