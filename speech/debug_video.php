<?php
require_once 'includes/config.php';
$id = 9; // The ID from the user's screenshot
$res = $conn->query("SELECT id, title, format, metadata, content_path FROM videos WHERE id = $id");
$video = $res->fetch_assoc();
header('Content-Type: application/json');
echo json_encode($video, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
