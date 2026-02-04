<?php
/**
 * Upload Page Controller
 * 
 * Handles: Video upload processing, file handling, speaker management
 * Template: templates/upload.php
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/worker_trigger.php';
require_once 'includes/helpers.php';
require_once 'includes/Validator.php';
require_once 'includes/compression_helper.php';
require_once 'includes/models/Speaker.php';

// ============================================
// LOGIC: Access Control
// ============================================
if (!is_manager() && !is_campus_admin()) {
    die("未授權：僅管理員或院區管理員可進入此頁面。");
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
        // Backend validation using Validator class
        $validation = Validator::validate($_POST, 'upload');
        if (!$validation['valid']) {
            throw new Exception(Validator::getFirstError($validation['errors']));
        }

        // Detect if post_max_size was exceeded
        if (empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
            $maxPostSize = ini_get('post_max_size');
            throw new Exception("上傳失敗：檔案總大小超過了伺服器限制 ($maxPostSize)。請縮小檔案或聯絡管理員調整 php.ini。");
        }

        $conn->begin_transaction();

        $title = $_POST['title'];
        $campus_id = $_POST['campus_id'];
        $event_date = $_POST['event_date'];

        // Speaker handling (using Speaker Model)
        $speaker_name = $_POST['speaker_name'];
        $affiliation = $_POST['affiliation'] ?? '';
        $position = $_POST['position'] ?? '';

        $speaker_id = speaker_find_or_create($speaker_name, $affiliation, $position);

        // Handle Thumbnail
        $thumb_path = '';
        if (isset($_FILES['thumbnail'])) {
            if ($_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
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

                $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('thumb_') . '.' . $ext;
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], UPLOAD_DIR_THUMBS . $filename);
                $thumb_path = 'uploads/thumbnails/' . $filename;
            } elseif ($_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
                throw new Exception("縮圖上傳錯誤，錯誤代碼：" . $_FILES['thumbnail']['error']);
            }
        }

        // Handle Content (File Upload or Link)
        $content_path = '';
        $format = 'mp4';
        $metadata = null;
        $duration = 0;
        $is_link_upload = false; // Track if using link

        // Check if using link or file upload
        $using_link = !empty($_POST['video_link']);
        $using_file = isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK;

        if ($using_link && $using_file) {
            throw new Exception("請選擇檔案上傳或連結輸入其中一種方式。");
        }

        if (!$using_link && !$using_file) {
            throw new Exception("請上傳影片檔案或提供影片連結。");
        }

        if ($using_link) {
            // Handle link-based upload
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
                // Example: http://example.com/videos/abc123/index.html
                // We store as: http://example.com/videos/abc123/media.mp4
                // The actual filename will be determined by config.js at runtime
                $directory = dirname($video_link);
                $content_path = $directory . '/media.mp4'; // Placeholder
                $format = 'evercam';
                $metadata = null; // Will be loaded dynamically by watch.php from config.js
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
                    // Direct link to media file (e.g., media.mp4, player.mp4)
                    $content_path = $video_link;
                    $format = 'evercam';
                    $metadata = null; // Will be loaded dynamically by watch.php
                    $duration = 0;
                }
            }

            $is_link_upload = true;

        } elseif ($using_file) {
            // Handle file upload (existing logic)
            if ($_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                // Check video/ZIP file size
                if ($_FILES['video_file']['size'] > MAX_UPLOAD_SIZE) {
                    throw new Exception("影片檔案大小超過限制 (" . MAX_UPLOAD_SIZE_MB . "MB)。");
                }

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
                } else {
                    throw new Exception("不支援的檔案格式，僅限 MP4 或 ZIP。");
                }
            } else {
                $errorCode = $_FILES['video_file']['error'];
                $errorMsg = "影片上傳失敗。";
                if ($errorCode === UPLOAD_ERR_INI_SIZE)
                    $errorMsg = "影片大小超過伺服器 php.ini 的限制 (upload_max_filesize)。";
                if ($errorCode === UPLOAD_ERR_PARTIAL)
                    $errorMsg = "影片僅部分上傳完成。";
                throw new Exception($errorMsg . " (代碼: $errorCode)");
            }
        }

        // Determine Status: Link uploads are ALWAYS ready
        if ($is_link_upload) {
            // Link-based uploads skip compression entirely
            $status = 'ready';
            $should_trigger = false;
            $msg = "演講上傳成功！使用外部連結的影片已可立即觀看。";
        } else {
            // File uploads: Use compression helper to determine status
            $result = determine_video_status($campus_id, $conn);
            $status = $result['status'];
            $should_trigger = $result['trigger'];
            $msg = $result['message'];
        }

        // Save Video with ownership and new metadata
        $user_id = $_SESSION['user_id'];

        $stmt = $conn->prepare("INSERT INTO videos (title, content_path, format, metadata, duration, thumbnail_path, event_date, campus_id, speaker_id, user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssissiiis", $title, $content_path, $format, $metadata, $duration, $thumb_path, $event_date, $campus_id, $speaker_id, $user_id, $status);
        $stmt->execute();

        // Capture ID
        $video_id = $conn->insert_id;

        $conn->commit();

        // Trigger Worker AFTER commit to avoid transaction race condition
        if (isset($should_trigger) && $should_trigger) {
            trigger_remote_worker();
        }

        // Redirect to manage page to avoid re-submission and provide clear feedback
        header("Location: manage_videos.php?msg=" . urlencode($msg));
        exit;
    } catch (Exception $e) {
        if (isset($conn))
            $conn->rollback();
        $error = $e->getMessage();
    }
}

// (Trigger function now in includes/worker_trigger.php)

// ============================================
// LOGIC: Get campuses for form
// ============================================
$campuses = $conn->query("SELECT * FROM campuses")->fetch_all(MYSQLI_ASSOC);

// ============================================
// TEMPLATE: Pass data to template
// ============================================
$page_title = '上傳演講';
$page_css_files = ['forms.css'];

include 'templates/upload.php';