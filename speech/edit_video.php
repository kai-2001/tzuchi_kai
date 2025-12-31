<?php
/**
 * Edit Video Page Controller
 * 
 * Handles: Video editing, file replacement, speaker update
 * Template: templates/edit.php
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

// ============================================
// LOGIC: Access Control
// ============================================
if (!is_manager()) {
    die("未授權。");
}

// ============================================
// LOGIC: Get video ID and verify ownership
// ============================================
$video_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT v.*, s.name as speaker_name, s.affiliation, s.position 
                       FROM videos v 
                       LEFT JOIN speakers s ON v.speaker_id = s.id 
                       WHERE v.id = ? AND v.user_id = ?");
$stmt->bind_param("ii", $video_id, $user_id);
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if (!$video) {
    die("找不到影片或權限不足。");
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
        $campus_id = $_POST['campus_id'];
        $event_date = $_POST['event_date'];
        $speaker_name = $_POST['speaker_name'];
        $affiliation = $_POST['affiliation'];
        $position = $_POST['position'];

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
                $zip = new ZipArchive;
                if ($zip->open($temp_name) === TRUE) {
                    $extract_dir = UPLOAD_DIR_VIDEOS . $file_id . '/';
                    mkdir($extract_dir, 0777, true);

                    // Locate config.js and identify video file
                    $config_content = '';
                    $video_filename = '';
                    $has_config = false;

                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $zf = $zip->getNameIndex($i);
                        if (basename($zf) === 'config.js') {
                            $config_content = $zip->getFromIndex($i);
                            $has_config = true;
                        }
                    }

                    if (!$has_config) {
                        $zip->close();
                        // deleteDir($extract_dir); // Should add helper
                        throw new Exception("ZIP 檔案中未找到 config.js");
                    }

                    // Parse config.js (Simplified logic match upload.php)
                    if (preg_match('/var\s+config\s*=\s*(\{.*\})/s', $config_content, $matches)) {
                        $json_text = rtrim(trim($matches[1]), ';');
                        $config_data = json_decode($json_text, true);
                        if ($config_data) {
                            if (isset($config_data['src'][0]['src']))
                                $video_filename = $config_data['src'][0]['src'];
                            if (isset($config_data['index']))
                                $metadata = json_encode($config_data['index']);
                            if (isset($config_data['duration']))
                                $duration = (int) ($config_data['duration'] / 1000);
                        }
                    }

                    if (empty($video_filename)) {
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            if (basename($zip->getNameIndex($i)) === 'media.mp4') {
                                $video_filename = 'media.mp4';
                                break;
                            }
                        }
                    }

                    if (empty($video_filename)) {
                        $zip->close();
                        throw new Exception("無法從 ZIP 中識別影片檔案。");
                    }

                    $zip->extractTo($extract_dir, ['config.js', $video_filename]);
                    $zip->close();

                    $content_path = 'uploads/videos/' . $file_id . '/' . $video_filename;
                    $format = 'evercam';
                }
            }

            // If file replaced, triggers pending status
            $status = 'pending';
        } else {
            $status = $video['status']; // Keep old status
        }

        // Update Video Record
        // We update format, metadata, duration, status as well
        $stmt = $conn->prepare("UPDATE videos SET title = ?, thumbnail_path = ?, content_path = ?, format = ?, metadata = ?, duration = ?, event_date = ?, campus_id = ?, speaker_id = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssssisiisi", $title, $thumb_path, $content_path, $format, $metadata, $duration, $event_date, $campus_id, $new_speaker_id, $status, $video_id);
        $stmt->execute();

        $conn->commit();
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