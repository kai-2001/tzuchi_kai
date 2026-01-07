<?php
require_once 'includes/config.php';
session_start();

$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT_LOGGED_IN';
$is_manager = isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager');

echo "<h1>Debug Info</h1>";
echo "<p>Current User ID: <strong>" . htmlspecialchars($current_user_id) . "</strong></p>";
echo "<p>Is Manager (Session): <strong>" . ($is_manager ? 'Yes' : 'No') . "</strong></p>";

echo "<h2>All Videos (Last 50)</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Title</th><th>Format</th><th>User ID</th><th>Status</th><th>Created At</th></tr>";

$sql = "SELECT id, title, format, user_id, status, created_at FROM videos ORDER BY created_at DESC LIMIT 50";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row_user_id = $row['user_id'];
        $style = ($row_user_id == $current_user_id) ? "background-color: #e6fffa;" : "background-color: #fff5f5;";

        echo "<tr style='$style'>";
        echo "<td>{$row['id']}</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>{$row['format']}</td>";
        echo "<td>{$row['user_id']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6'>Query failed: " . $conn->error . "</td></tr>";
}
echo "</table>";
?>