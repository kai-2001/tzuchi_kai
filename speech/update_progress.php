<?php
/**
 * Update Progress API
 * 
 * Receives video_id and position via POST and saves it to the database.
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Ensure user is logged in
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $video_id = isset($_POST['video_id']) ? (int) $_POST['video_id'] : 0;
    $position = isset($_POST['position']) ? (int) $_POST['position'] : 0;
    $user_id = $_SESSION['user_id'];

    if ($video_id > 0) {
        // Use UPSERT (INSERT ... ON DUPLICATE KEY UPDATE)
        $stmt = $conn->prepare("INSERT INTO video_progress (user_id, video_id, last_position) 
                               VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE last_position = VALUES(last_position)");
        $stmt->bind_param("iii", $user_id, $video_id, $position);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
