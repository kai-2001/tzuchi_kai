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

// ============================================
// LOGIC: Access Control
// ============================================
if (!is_manager()) {
    die("未授權：僅管理員可進入此頁面。");
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

        // Handle Content (MP4 or ZIP)
        $content_path = '';
        $format = 'mp4';
        $metadata = null;
        $duration = 0;

        if (isset($_FILES['video_file'])) {
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
                    $zip = new ZipArchive;
                    if ($zip->open($temp_name) === TRUE) {
                        $extract_dir = UPLOAD_DIR_VIDEOS . $file_id . '/';
                        mkdir($extract_dir, 0777, true);

                        // Locate config.js and identify video file
                        $config_content = '';
                        $video_filename = '';
                        $has_config = false;
                        $zip_prefix = ''; // Store the folder prefix (e.g., "Folder/") if config.js is nested

                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $zf = $zip->getNameIndex($i);
                            if (basename($zf) === 'config.js') {
                                $config_content = $zip->getFromIndex($i);
                                $has_config = true;
                                // Capture the directory path of config.js as prefix
                                $dir = dirname($zf);
                                $zip_prefix = ($dir === '.') ? '' : $dir . '/';
                                break; // Found config, stop searching
                            }
                        }

                        if (!$has_config) {
                            $zip->close();
                            deleteDir($extract_dir);
                            throw new Exception("ZIP 檔案中未找到 config.js，這可能不是標準的 EverCam 網頁匯出檔。");
                        }

                        // Parse config.js for video filename and chapters
                        // Format: var config = { ... };
                        if (preg_match('/var\s+config\s*=\s*(\{.*\})/s', $config_content, $matches)) {
                            $json_text = trim($matches[1]);
                            // Remove trailing semicolon if captured inside the brace block by mistake (though unlikely with {.*})
                            $json_text = rtrim($json_text, ';');

                            $config_data = json_decode($json_text, true);
                            if ($config_data) {
                                if (isset($config_data['src'][0]['src'])) {
                                    $video_filename = $config_data['src'][0]['src'];
                                }
                                if (isset($config_data['index'])) {
                                    // Store with indentation if present
                                    $metadata = json_encode($config_data['index']);
                                }
                                if (isset($config_data['duration'])) {
                                    $duration = (int) ($config_data['duration'] / 1000); // ms to s
                                }
                            }
                        }

                        if (empty($video_filename)) {
                            // Try fallback to media.mp4 - Must look inside the same prefix
                            $search_target = $zip_prefix . 'media.mp4';
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                if ($zip->getNameIndex($i) === $search_target) {
                                    $video_filename = 'media.mp4'; // Internal logic uses simple name
                                    break;
                                }
                            }
                        }

                        if (empty($video_filename)) {
                            $zip->close();
                            deleteDir($extract_dir);
                            throw new Exception("無法從 ZIP 中識別影片檔案。");
                        }

                        // Prepare full paths for extraction
                        $config_path_in_zip = $zip_prefix . 'config.js';
                        $video_path_in_zip = $zip_prefix . $video_filename;

                        // Check if video file actually exists in zip at expected path
                        if ($zip->locateName($video_path_in_zip) === false) {
                            $zip->close();
                            deleteDir($extract_dir);
                            throw new Exception("找不到影片檔：$video_path_in_zip (設定檔指定為 $video_filename)");
                        }

                        // Extract ONLY the video file and config.js
                        // extractTo expects exact internal paths
                        if (!$zip->extractTo($extract_dir, [$config_path_in_zip, $video_path_in_zip])) {
                            $zip->close();
                            deleteDir($extract_dir);
                            throw new Exception("解壓縮失敗：無法將檔案從 ZIP 中取出。");
                        }
                        $zip->close();

                        // Flatten structure if nested
                        if (!empty($zip_prefix)) {
                            // Files are now at $extract_dir . $zip_prefix . $filename
                            // We need to move them to $extract_dir . $filename
                            $full_config_path = $extract_dir . $config_path_in_zip;
                            $full_video_path = $extract_dir . $video_path_in_zip;

                            if (file_exists($full_config_path)) {
                                rename($full_config_path, $extract_dir . 'config.js');
                            }
                            if (file_exists($full_video_path)) {
                                rename($full_video_path, $extract_dir . $video_filename);
                            }

                            // Clean up empty directory structure
                            // dirname($config_path_in_zip) is e.g. "Folder/Sub"
                            // We need to remove $extract_dir/Folder/Sub, then $extract_dir/Folder...
                            // Simple approach: deleteDir($extract_dir . explode('/', $zip_prefix)[0]);
                            // Assuming prefix "Folder/" -> delete "Folder"
                            $first_dir = explode('/', $zip_prefix)[0];
                            deleteDir($extract_dir . $first_dir);
                        }

                        // Final Sanity Check: Did we actually get the files?
                        if (!file_exists($extract_dir . 'config.js') || !file_exists($extract_dir . $video_filename)) {
                            // Cleanup and fail
                            deleteDir($extract_dir);
                            throw new Exception("檔案寫入失敗：解壓縮顯示成功，但目標資料夾中找不到檔案。");
                        }

                        $content_path = 'uploads/videos/' . $file_id . '/' . $video_filename;
                        $format = 'evercam';
                    } else {
                        throw new Exception("無法開啟 ZIP 檔案。");
                    }
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

        // Check Auto-Compression Setting
        $auto_compression = '0';
        $res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'auto_compression'");
        if ($res && $row = $res->fetch_assoc()) {
            $auto_compression = $row['setting_value'];
        }

        // Save Video with ownership and new metadata
        $user_id = $_SESSION['user_id'];

        // Determine status based on auto-compression setting
        if ($auto_compression === '1') {
            $status = 'pending';
            $msg = "演講上傳成功！系統設為「自動壓縮」，已通知轉檔主機開始作業。";
            $should_trigger = true;
        } else {
            $status = 'waiting';
            $msg = "演講上傳成功！已加入「待處理清單」。請前往佇列管理頁面手動啟動壓縮。";
            $should_trigger = false;
        }

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
// HELPER: Delete directory function
// ============================================
function deleteDir($dirPath)
{
    if (!is_dir($dirPath))
        return;
    $files = array_diff(scandir($dirPath), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dirPath/$file")) ? deleteDir("$dirPath/$file") : unlink("$dirPath/$file");
    }
    return rmdir($dirPath);
}

// ============================================
// TEMPLATE: Pass data to template
// ============================================
$page_title = '上傳演講';
$page_css_files = ['forms.css'];

include 'templates/upload.php';