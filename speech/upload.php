<?php
/**
 * Upload Page Controller
 * 
 * Handles: Video upload processing, file handling, speaker management
 * Template: templates/upload.php
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

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
        $affiliation = $_POST['affiliation'];
        $position = $_POST['position'];

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

                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $zf = $zip->getNameIndex($i);
                            if (basename($zf) === 'config.js') {
                                $config_content = $zip->getFromIndex($i);
                                $has_config = true;
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
                            // Try fallback to media.mp4
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                if (basename($zip->getNameIndex($i)) === 'media.mp4') {
                                    $video_filename = 'media.mp4';
                                    break;
                                }
                            }
                        }

                        if (empty($video_filename)) {
                            $zip->close();
                            deleteDir($extract_dir);
                            throw new Exception("無法從 ZIP 中識別影片檔案。");
                        }

                        // Extract ONLY the video file and config.js
                        $zip->extractTo($extract_dir, ['config.js', $video_filename]);
                        $zip->close();

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

        // Save Video with ownership and new metadata
        $user_id = $_SESSION['user_id'];
        $status = 'pending'; // ALL videos (MP4 and EverCam extracted MP4) go to queue

        // Formerly EverCam was set to 'ready', but user wants compression for them too.
        // if ($format === 'evercam') { $status = 'ready'; } 

        $stmt = $conn->prepare("INSERT INTO videos (title, content_path, format, metadata, duration, thumbnail_path, event_date, campus_id, speaker_id, user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssissiiis", $title, $content_path, $format, $metadata, $duration, $thumb_path, $event_date, $campus_id, $speaker_id, $user_id, $status);
        $stmt->execute();

        $video_id = $conn->insert_id; // Capture ID for potential immediate job trigger

        $conn->commit();

        if ($status === 'pending') {
            $msg = "演講上傳成功！影片已排入轉檔佇列，稍後將自動處理。";
        } else {
            $msg = "演講上傳成功！";
        }
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