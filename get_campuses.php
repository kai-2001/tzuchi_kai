<?php
require_once __DIR__ . '/speech/includes/config.php';

$res = $conn->query("SELECT id, name FROM campuses");
while ($row = $res->fetch_assoc()) {
    echo $row['id'] . ":" . $row['name'] . "\n";
}
?>