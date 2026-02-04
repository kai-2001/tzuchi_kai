<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Access Control
// Access Control
if (!is_manager() && !is_campus_admin()) {
    header('Location: index.php?error=unauthorized');
    exit;
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

if (!$video) {
    header('Location: manage_videos.php?error=not_found');
    exit;
}

// Delete video with transaction protection
try {
    $conn->begin_transaction();

    // 1. Delete database record first (with transaction)
    $stmt = $conn->prepare("DELETE FROM videos WHERE id = ?");
    $stmt->bind_param("i", $video_id);
    $stmt->execute();

    $conn->commit();

    // 2. After successful DB deletion, delete files (file deletion failure doesn't affect data consistency)
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
                if (!deleteDirectory($dir_path)) {
                    error_log("Warning: Failed to delete Evercam directory: $dir_path");
                }
            }
        }
        // Handle Single File (MP4) or other formats
        else {
            if (file_exists($full_path) && is_file($full_path)) {
                if (!unlink($full_path)) {
                    error_log("Warning: Failed to delete video file: $full_path");
                }
            }
        }
    }

    // Delete thumbnail
    if (!empty($video['thumbnail_path'])) {
        $thumb_full = __DIR__ . '/' . $video['thumbnail_path'];
        if (file_exists($thumb_full)) {
            if (!unlink($thumb_full)) {
                error_log("Warning: Failed to delete thumbnail: $thumb_full");
            }
        }
    }

    header("Location: manage_videos.php?msg=deleted");
    exit;

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("Video deletion failed for ID $video_id: " . $e->getMessage());
    header('Location: manage_videos.php?error=delete_failed');
    exit;
}

// Helper function deleteDirectory() is now in includes/helpers.php
?>