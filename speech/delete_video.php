<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Access Control
if (!is_manager()) {
    die("未授權。");
}

$video_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

// Ownership Check
$stmt = $conn->prepare("SELECT content_path, thumbnail_path FROM videos WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $video_id, $user_id);
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if ($video) {
    // Delete files if they exist
    if (!empty($video['content_path']) && file_exists(__DIR__ . '/' . $video['content_path'])) {
        // If it's a directory (ZIP extracted), delete directory
        if (is_dir(__DIR__ . '/' . $video['content_path'])) {
            // Simple recursive delete would be better, but for now just the file
            // Actually content_path points to index.html if ZIP
            $path = __DIR__ . '/' . $video['content_path'];
            if (strpos($path, 'index.html') !== false) {
                $dir = dirname($path);
                // Basic cleanup of dir
                array_map('unlink', glob("$dir/*.*"));
                rmdir($dir);
            } else {
                unlink($path);
            }
        } else {
            unlink(__DIR__ . '/' . $video['content_path']);
        }
    }

    if (!empty($video['thumbnail_path']) && file_exists(__DIR__ . '/' . $video['thumbnail_path'])) {
        unlink(__DIR__ . '/' . $video['thumbnail_path']);
    }

    // Delete record
    $stmt = $conn->prepare("DELETE FROM videos WHERE id = ?");
    $stmt->bind_param("i", $video_id);
    $stmt->execute();

    header("Location: manage_videos.php?msg=deleted");
} else {
    die("找不到影片或權限不足。");
}
?>