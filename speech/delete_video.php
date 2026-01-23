<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Access Control
// Access Control
if (!is_manager() && !is_campus_admin()) {
    die("未授權。");
}

$video_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

// Ownership Check
if (is_manager()) {
    $stmt = $conn->prepare("SELECT content_path, thumbnail_path, format FROM videos WHERE id = ?");
    $stmt->bind_param("i", $video_id);
} else {
    // Campus Admin: Must match campus
    $stmt = $conn->prepare("SELECT content_path, thumbnail_path, format FROM videos WHERE id = ? AND campus_id = ?");
    $stmt->bind_param("ii", $video_id, $_SESSION['campus_id']);
}
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if ($video) {
    // Delete files if they exist
    if (!empty($video['content_path'])) {
        $full_path = __DIR__ . '/' . $video['content_path'];

        // Handle Evercam Directory
        if ($video['format'] === 'evercam') {
            // For Evercam, content_path points to the MP4 file INSIDE the folder. 
            // We want to delete the CONTAINER folder.
            $dir_path = dirname($full_path);

            // Safety check: ensure we are inside uploads directory and not deleting system root
            // This prevents deleting outside of 'uploads'
            if (strpos(realpath($dir_path), realpath(__DIR__ . '/uploads')) === 0 && is_dir($dir_path)) {
                deleteDirectory($dir_path);
            }
        }
        // Handle Single File (MP4) or other formats
        else {
            if (file_exists($full_path) && is_file($full_path)) {
                unlink($full_path);
            }
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

// Helper function for recursive delete
function deleteDirectory($dir)
{
    if (!file_exists($dir)) {
        return true;
    }
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($dir);
}
?>