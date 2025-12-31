<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/auth.php';

echo "Start Insert Test...<br>";

// Clean up test entry if exists
$conn->query("DELETE FROM videos WHERE title = '__TEST_VIDEO__'");

// Mock data
$title = '__TEST_VIDEO__';
$content_path = 'uploads/videos/test.mp4';
$format = 'mp4';
$metadata = json_encode(['mock' => 'data']);
$duration = 120;
$thumb_path = 'uploads/thumbnails/test.jpg';
$event_date = date('Y-m-d');
$campus_id = 1; // Assuming 1 exists
$speaker_id = 1; // Assuming 1 exists or null? Let's check DB
$user_id = 1; // Assuming 1 exists or current session user
$status = 'pending';

// Verify FK existence
$camp = $conn->query("SELECT id FROM campuses LIMIT 1")->fetch_row();
if (!$camp)
    die("No campuses found");
$campus_id = $camp[0];

$user = $conn->query("SELECT id FROM users LIMIT 1")->fetch_row();
if (!$user)
    die("No users found");
$user_id = $user[0];

// Handle speaker (nullable?)
$speaker = $conn->query("SELECT id FROM speakers LIMIT 1")->fetch_row();
if ($speaker) {
    $speaker_id = $speaker[0];
} else {
    // Insert dummy speaker
    $conn->query("INSERT INTO speakers (name, affiliation) VALUES ('TestSpeaker', 'TestAff')");
    $speaker_id = $conn->insert_id;
}

echo "Data prepared. ID: User=$user_id, Campus=$campus_id, Speaker=$speaker_id<br>";

// Prepare Insert
try {
    $sql = "INSERT INTO videos (title, content_path, format, metadata, duration, thumbnail_path, event_date, campus_id, speaker_id, user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    echo "Prepare success.<br>";

    $bind = $stmt->bind_param("ssssissiiis", $title, $content_path, $format, $metadata, $duration, $thumb_path, $event_date, $campus_id, $speaker_id, $user_id, $status);

    if (!$bind) {
        throw new Exception("Bind failed: " . $stmt->error);
    }

    echo "Bind success.<br>";

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    echo "Execute success. ID: " . $stmt->insert_id . "<br>";

} catch (Exception $e) {
    echo "Exception:Code: " . $e->getMessage() . "<br>";
}

echo "End Test.";
?>