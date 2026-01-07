<?php
require_once 'includes/config.php';
$result = $conn->query("DESCRIBE users");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>