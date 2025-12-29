<?php
require_once 'includes/config.php';
$res = $conn->query('SELECT * FROM video_progress');
$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}
header('Content-Type: application/json');
echo json_encode($data, JSON_PRETTY_PRINT);
