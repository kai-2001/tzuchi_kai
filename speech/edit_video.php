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
require_once 'includes/compression_helper.php';
require_once 'includes/models/Speaker.php';

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
    $stmt = $conn->prepare("SELECT v.* FROM videos v WHERE v.id = ?");
    $stmt->bind_param("i", $video_id);
} else {
    // Campus Admin: Must match campus
    $stmt = $conn->prepare("SELECT v.* FROM videos v WHERE v.id = ? AND v.campus_id = ?");
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

        // Speaker handling - 處理多位講者
        $speakers_data = $_POST['speakers'] ?? [];
        if (empty($speakers_data)) {
            throw new Exception("至少需要一位講者。");
        }

        // 處理每位講者，建立或找到 speaker_id
        $speaker_ids = [];
        foreach ($speakers_data as $speaker) {
            $name = trim($speaker['name'] ?? '');
            $affiliation = trim($speaker['affiliation'] ?? '');
            $position = trim($speaker['position'] ?? '');

            if (empty($name)) {
                throw new Exception("講者姓名不能為空。");
            }

            $speaker_id = speaker_find_or_create($name, $affiliation, $position);
            $speaker_ids[] = $speaker_id;
        }

        // Handle Thumbnail Update
        $thumb_path = $video['thumbnail_path'];
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            // Check thumbnail file size
            if ($_FILES['thumbnail']['size'] > MAX_IMAGE_SIZE) {
                throw new Exception("縮圖檔案大小超過限制 (" . MAX_IMAGE_SIZE_MB . "MB)。");
            }

            // Validate MIME type (security: prevent fake extensions)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['thumbnail']['tmp_name']);
            finfo_close($finfo);

            $allowed_image_mimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime_type, $allowed_image_mimes)) {
                throw new Exception("縮圖檔案類型無效，僅支援 JPG、PNG、GIF 或 WebP 格式。");
            }

            if (file_exists(__DIR__ . '/' . $thumb_path))
                unlink(__DIR__ . '/' . $thumb_path);

            $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('thumb_') . '.' . $ext;
            move_uploaded_file($_FILES['thumbnail']['tmp_name'], UPLOAD_DIR_THUMBS . $filename);
            $thumb_path = 'uploads/thumbnails/' . $filename;
        }

        // Handle Video/Content Update (File or Link)
        $content_path = $video['content_path']; // Default to old
        $format = $video['format'];
        $metadata = $video['metadata'];
        $duration = $video['duration'];
        $status_update_sql = ""; // Only update status if file changed
        $is_link_upload = false;

        // Check if using link or file upload
        $using_link = !empty($_POST['video_link']);
        $using_file = isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK;

        if ($using_link && $using_file) {
            throw new Exception("請選擇檔案上傳或連結輸入其中一種方式。");
        }

        if ($using_link) {
            // Handle link-based update
            $video_link = trim($_POST['video_link']);

            // Validate URL format
            if (!filter_var($video_link, FILTER_VALIDATE_URL)) {
                throw new Exception("影片連結格式無效。");
            }

            // Ensure it's http or https
            $scheme = parse_url($video_link, PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'])) {
                throw new Exception("影片連結必須使用 http:// 或 https:// 協定。");
            }

            // Determine format based on URL
            $url_path = parse_url($video_link, PHP_URL_PATH);
            $basename = basename($url_path);

            if (strtolower($basename) === 'index.html') {
                // EverCam format with index.html
                $directory = dirname($video_link);
                $content_path = $directory . '/media.mp4';
                $format = 'evercam';
                $metadata = null;
                $duration = 0;
            } else {
                // Check file extension
                $ext = strtolower(pathinfo($url_path, PATHINFO_EXTENSION));

                if ($ext === 'mp4') {
                    $content_path = $video_link;
                    $format = 'mp4';
                    $metadata = null;
                    $duration = 0;
                } else {
                    // Assume EverCam if not .mp4 and not index.html
                    $content_path = $video_link;
                    $format = 'evercam';
                    $metadata = null;
                    $duration = 0;
                }
            }

            $is_link_upload = true;

            // Note: Old file cleanup is skipped for link uploads since old content might be a link too
            // If old content was a file, it won't be deleted automatically
            // This is acceptable as the path is being replaced

        } elseif ($using_file) {
            // Check video/ZIP file size
            if ($_FILES['video_file']['size'] > MAX_UPLOAD_SIZE) {
                throw new Exception("影片檔案大小超過限制 (" . MAX_UPLOAD_SIZE_MB . "MB)。");
            }

            // 1. Cleanup Old File/Directory logic (only for file replacements)
            $old_path_rel = $video['content_path'];
            $old_full_path = __DIR__ . '/' . $old_path_rel;

            if (!empty($old_path_rel) && file_exists($old_full_path)) {
                $path_info = pathinfo($old_full_path);
                $parent_dir = dirname($old_full_path);

                $upload_root_norm = str_replace('\\', '/', realpath(UPLOAD_DIR_VIDEOS));
                $parent_dir_norm = str_replace('\\', '/', realpath($parent_dir));

                if ($parent_dir_norm !== $upload_root_norm) {
                    // It's a subfolder (EverCam), delete the whole folder
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

            // Validate MIME type (security: prevent fake extensions)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $temp_name);
            finfo_close($finfo);

            if ($ext === 'mp4') {
                // Validate MP4 MIME type
                $allowed_video_mimes = ['video/mp4', 'video/x-m4v', 'application/mp4'];
                if (!in_array($mime_type, $allowed_video_mimes)) {
                    throw new Exception("檔案類型驗證失敗，請上傳有效的 MP4 影片檔案。");
                }

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
                // Validate ZIP MIME type
                $allowed_zip_mimes = ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'];
                if (!in_array($mime_type, $allowed_zip_mimes)) {
                    throw new Exception("檔案類型驗證失敗，請上傳有效的 ZIP 壓縮檔案。");
                }

                // Use centralized EverCam ZIP processing helper
                $result = process_evercam_zip($temp_name, $file_id);
                $content_path = $result['content_path'];
                $format = $result['format'];
                $metadata = $result['metadata'];
                $duration = $result['duration'];
            }

            // Determine status based on upload type
            if ($is_link_upload) {
                // Link-based uploads skip compression entirely
                $status = 'ready';
                $should_trigger = false;
            } else {
                // File uploads: Use compression helper to determine status
                $result = determine_video_status($campus_id, $conn);
                $status = $result['status'];
                $should_trigger = $result['trigger'];
            }
        } else {
            $status = $video['status']; // Keep old status
            $should_trigger = false;
        }

        // Update Video Record (移除 speaker_id)
        $stmt = $conn->prepare("UPDATE videos SET title = ?, thumbnail_path = ?, content_path = ?, format = ?, metadata = ?, duration = ?, event_date = ?, campus_id = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssssisisi", $title, $thumb_path, $content_path, $format, $metadata, $duration, $event_date, $campus_id, $status, $video_id);
        $stmt->execute();

        // 更新所有講者關聯（多對多）
        require_once 'includes/models/VideoSpeaker.php';
        video_speaker_remove_all($video_id);
        foreach ($speaker_ids as $index => $speaker_id) {
            video_speaker_add($video_id, $speaker_id, 'speaker', $index);
        }

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