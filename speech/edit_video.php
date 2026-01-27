<?php
/**
 * Edit Video Page Controller
 * 
 * Handles: Video editing, file replacement, speaker update
 * Template: templates/edit.php
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/worker_trigger.php';

// ============================================
// LOGIC: Access Control
// ============================================
if (!is_manager() && !is_campus_admin()) {
    header('Location: index.php?error=unauthorized');
    exit;
}

// ============================================
// LOGIC: Get video ID and verify ownership
// ============================================
$video_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

if (is_manager()) {
    $stmt = $conn->prepare("SELECT v.*, s.name as speaker_name, s.affiliation, s.position 
                           FROM videos v 
                           LEFT JOIN speakers s ON v.speaker_id = s.id 
                           WHERE v.id = ?");
    $stmt->bind_param("i", $video_id);
} else {
    // Campus Admin: Must match campus
    $stmt = $conn->prepare("SELECT v.*, s.name as speaker_name, s.affiliation, s.position 
                           FROM videos v 
                           LEFT JOIN speakers s ON v.speaker_id = s.id 
                           WHERE v.id = ? AND v.campus_id = ?");
    $stmt->bind_param("ii", $video_id, $_SESSION['campus_id']);
}
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if (!$video) {
    header('Location: manage_videos.php?error=not_found');
    exit;
}

// ============================================
// LOGIC: Initialize messages
// ============================================
$msg = '';
$error = '';

// ============================================
// LOGIC: Handle form submission
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        $title = $_POST['title'];

        if (is_campus_admin()) {
            $campus_id = $_SESSION['campus_id'];
        } else {
            $campus_id = $_POST['campus_id'];
        }
        $event_date = $_POST['event_date'];
        $speaker_name = $_POST['speaker_name'];
        $affiliation = $_POST['affiliation'] ?? '';
        $position = $_POST['position'] ?? '';

        // Update Speaker (or create new)
        $stmt = $conn->prepare("SELECT id FROM speakers WHERE name = ? AND affiliation = ?");
        $stmt->bind_param("ss", $speaker_name, $affiliation);
        $stmt->execute();
        $speaker_result = $stmt->get_result();
        $new_speaker_id = $speaker_result ? ($speaker_result->fetch_row()[0] ?? null) : null;

        if (!$new_speaker_id) {
            $stmt = $conn->prepare("INSERT INTO speakers (name, affiliation, position) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $speaker_name, $affiliation, $position);
            $stmt->execute();
            $new_speaker_id = $conn->insert_id;
        } else {
            $stmt = $conn->prepare("UPDATE speakers SET position = ? WHERE id = ?");
            $stmt->bind_param("si", $position, $new_speaker_id);
            $stmt->execute();
        }

        // Handle Thumbnail Update
        $thumb_path = $video['thumbnail_path'];
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            if (file_exists(__DIR__ . '/' . $thumb_path))
                unlink(__DIR__ . '/' . $thumb_path);

            $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('thumb_') . '.' . $ext;
            move_uploaded_file($_FILES['thumbnail']['tmp_name'], UPLOAD_DIR_THUMBS . $filename);
            $thumb_path = 'uploads/thumbnails/' . $filename;
        }

        // Handle Video/Content Update
        $content_path = $video['content_path']; // Default to old
        $format = $video['format'];
        $metadata = $video['metadata'];
        $duration = $video['duration'];
        $status_update_sql = ""; // Only update status if file changed

        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {

            // 1. Cleanup Old File/Directory logic
            $old_path_rel = $video['content_path'];
            $old_full_path = __DIR__ . '/' . $old_path_rel; // Absolute path

            if (!empty($old_path_rel) && file_exists($old_full_path)) {
                // Check if it's a standalone file (MP4 in root of uploads/videos)
                // or a file inside a subdir (EverCam folder)
                $path_info = pathinfo($old_full_path);
                $parent_dir = dirname($old_full_path);

                // If the parent dir is NOT the main videos folder, it implies it's a specific subfolder (EverCam)
                // UPLOAD_DIR_VIDEOS ends with slash, e.g. .../uploads/videos/
                // parent_dir would be .../uploads/videos/content_xyz
                $upload_root_norm = str_replace('\\', '/', realpath(UPLOAD_DIR_VIDEOS));
                $parent_dir_norm = str_replace('\\', '/', realpath($parent_dir));

                if ($parent_dir_norm !== $upload_root_norm) {
                    // It's a subfolder (EverCam), delete the whole folder
                    // Quick recursive delete
                    $files = glob($parent_dir_norm . '/*');
                    foreach ($files as $file) {
                        if (is_file($file))
                            unlink($file);
                    }
                    rmdir($parent_dir_norm);
                } else {
                    // It's a single file in root (Standard MP4)
                    unlink($old_full_path);
                }
            }

            // 2. Proceed with New Upload
            $ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
            $temp_name = $_FILES['video_file']['tmp_name'];
            $file_id = uniqid('content_');

            if ($ext === 'mp4') {
                $filename = $file_id . '.mp4';
                move_uploaded_file($temp_name, UPLOAD_DIR_VIDEOS . $filename);
                $content_path = 'uploads/videos/' . $filename;
                $format = 'mp4';
                $metadata = null; // Clear metadata for plain MP4
                // Duration? We might keep old or set to 0 to let worker fix it? 
                // Worker doesn't update duration currently. 
                // But upload.php sets duration to 0 if not found? 
                // Let's assume duration update is handled or 0.
            } elseif ($ext === 'zip') {
                // Use centralized EverCam ZIP processing helper
                $result = process_evercam_zip($temp_name, $file_id);
                $content_path = $result['content_path'];
                $format = $result['format'];
                $metadata = $result['metadata'];
                $duration = $result['duration'];
            }

            // Check auto_compression setting to determine initial status
            $auto_compression = '0';
            // Check specific campus setting first
            $sql = "SELECT campus_id, setting_value FROM system_settings WHERE setting_key = 'auto_compression' AND campus_id IN (?, 0)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $campus_id);
            $stmt->execute();
            $res = $stmt->get_result();

            $settings = [];
            while ($row = $res->fetch_assoc()) {
                $settings[$row['campus_id']] = $row['setting_value'];
            }

            if (isset($settings[$campus_id])) {
                $auto_compression = $settings[$campus_id];
            } elseif (isset($settings[0])) {
                $auto_compression = $settings[0];
            }

            // Set status and trigger flag based on auto-compression setting
            if ($auto_compression === '1') {
                $status = 'pending';
                $should_trigger = true;
            } else {
                $status = 'waiting';
                $should_trigger = false;
            }
        } else {
            $status = $video['status']; // Keep old status
            $should_trigger = false;
        }

        // Update Video Record
        // We update format, metadata, duration, status as well
        $stmt = $conn->prepare("UPDATE videos SET title = ?, thumbnail_path = ?, content_path = ?, format = ?, metadata = ?, duration = ?, event_date = ?, campus_id = ?, speaker_id = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssssisiisi", $title, $thumb_path, $content_path, $format, $metadata, $duration, $event_date, $campus_id, $new_speaker_id, $status, $video_id);
        $stmt->execute();

        $conn->commit();

        // Trigger Worker AFTER commit if file was replaced and auto mode enabled
        if (isset($should_trigger) && $should_trigger) {
            trigger_remote_worker();
        }

        header("Location: manage_videos.php?msg=updated");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// ============================================
// LOGIC: Get campuses for form
// ============================================
$campuses = $conn->query("SELECT * FROM campuses")->fetch_all(MYSQLI_ASSOC);

// ============================================
// TEMPLATE: Pass data to template
// ============================================
$page_title = '編輯影片';
$page_css_files = ['forms.css'];

include 'templates/edit.php';