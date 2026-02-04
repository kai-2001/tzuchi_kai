<?php
/**
 * Video Progress Update API
 * 
 * Handles: Saving user's video watch progress
 * Usage: Called by player.js via AJAX
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

// ============================================
// LOGIC: Access Control
// ============================================
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// ============================================
// LOGIC: Validate Input
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$video_id = isset($_POST['video_id']) ? (int) $_POST['video_id'] : 0;
$position = isset($_POST['position']) ? (int) $_POST['position'] : 0;
$user_id = $_SESSION['user_id'];

if ($video_id <= 0 || $position < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// ============================================
// LOGIC: Update or Insert Progress
// ============================================
// Check if video exists
$check_stmt = $conn->prepare("SELECT id FROM videos WHERE id = ?");
$check_stmt->bind_param("i", $video_id);
$check_stmt->execute();
if (!$check_stmt->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'error' => 'Video not found']);
    exit;
}

// Update or insert progress
$stmt = $conn->prepare(
    "INSERT INTO video_progress (user_id, video_id, last_position, updated_at) 
     VALUES (?, ?, ?, NOW()) 
     ON DUPLICATE KEY UPDATE last_position = ?, updated_at = NOW()"
);
$stmt->bind_param("iiii", $user_id, $video_id, $position, $position);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'position' => $position]);
} else {
    // Log detailed error for debugging
    error_log("Video progress update failed for user $user_id, video $video_id: " . $conn->error);
    // Return generic error message (don't expose DB details)
    echo json_encode(['success' => false, 'error' => '儲存進度失敗，請稍後再試']);
}

$stmt->close();
$conn->close();
