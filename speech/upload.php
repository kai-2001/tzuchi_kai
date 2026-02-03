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

        // Speaker handling
        $speaker_name = $_POST['speaker_name'];
        $affiliation = $_POST['affiliation'] ?? '';
        $position = $_POST['position'] ?? '';


        $stmt = $conn->prepare("SELECT id FROM speakers WHERE name = ? AND affiliation = ?");
        $stmt->bind_param("ss", $speaker_name, $affiliation);
        $stmt->execute();
        $result = $stmt->get_result();
        $speaker_id = $result ? ($result->fetch_row()[0] ?? null) : null;

        if (!$speaker_id) {
            $stmt = $conn->prepare("INSERT INTO speakers (name, affiliation, position) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $speaker_name, $affiliation, $position);
            $stmt->execute();
            $speaker_id = $conn->insert_id;
        }

        // Handle Thumbnail
        $thumb_path = '';
        if (isset($_FILES['thumbnail'])) {
            if ($_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
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
                $ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
                $temp_name = $_FILES['video_file']['tmp_name'];
                $file_id = uniqid('content_');

                if ($ext === 'mp4') {
                    $filename = $file_id . '.mp4';
                    move_uploaded_file($temp_name, UPLOAD_DIR_VIDEOS . $filename);
                    $content_path = 'uploads/videos/' . $filename;
                    $format = 'mp4';
                } elseif ($ext === 'zip') {
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
            // File uploads: Check compression mode and auto-compression settings
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

            // Determine status based on compression mode
            // Priority: COMPRESSION_MODE (global) > auto_compression (campus-specific)
            if (COMPRESSION_MODE === 'disabled') {
                // Skip compression entirely - go directly to ready
                $status = 'ready';
                $msg = "演講上傳成功！系統設為「無壓縮模式」，影片已可立即觀看。";
                $should_trigger = false;
            } elseif ($auto_compression === '1') {
                // Compression enabled + auto mode
                $status = 'pending';
                $msg = "演講上傳成功！系統設為「自動壓縮」，已通知轉檔主機開始作業。";
                $should_trigger = true;
            } else {
                // Compression enabled + manual mode
                $status = 'waiting';
                $msg = "演講上傳成功！已加入「待處理清單」。請前往佇列管理頁面手動啟動壓縮。";
                $should_trigger = false;
            }
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