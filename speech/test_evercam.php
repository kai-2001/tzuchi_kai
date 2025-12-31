<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'includes/config.php';
require_once 'includes/auth.php';

echo "Start EverCam Test...\n";

// Mock variables that usually come from ZIP processing
$title = '__TEST_EVERCAM__';
$content_path = 'uploads/videos/test_evercam/media.mp4';
$format = 'evercam'; // This triggers the condition
$metadata = json_encode(['index' => []]);
$duration = 300;
$thumb_path = '';
$event_date = date('Y-m-d');
$campus_id = 1;
$speaker_id = 1;
$user_id = 1;
$status = 'pending'; // Queue it

// DB Conn check
if (!$conn)
    die("No DB Conn");

// Prepare
$sql = "INSERT INTO videos (title, content_path, format, metadata, duration, thumbnail_path, event_date, campus_id, speaker_id, user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt)
    die("Prepare failed: " . $conn->error);

// Bind
// ssssissiiis (11)
$bind = $stmt->bind_param("ssssissiiis", $title, $content_path, $format, $metadata, $duration, $thumb_path, $event_date, $campus_id, $speaker_id, $user_id, $status);
if (!$bind)
    die("Bind failed: " . $stmt->error);

// Execute
if ($stmt->execute()) {
    echo "EverCam Insert Success. ID: " . $stmt->insert_id . "\n";
} else {
    echo "EverCam Insert Failed: " . $stmt->error . "\n";
}
?>