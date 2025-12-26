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
        if (isset($_FILES['video_file'])) {
            if ($_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
                $temp_name = $_FILES['video_file']['tmp_name'];
                $file_id = uniqid('content_');

                if ($ext === 'mp4') {
                    $filename = $file_id . '.mp4';
                    move_uploaded_file($temp_name, UPLOAD_DIR_VIDEOS . $filename);
                    $content_path = 'uploads/videos/' . $filename;
                } elseif ($ext === 'zip') {
                    $zip = new ZipArchive;
                    if ($zip->open($temp_name) === TRUE) {
                        $extract_dir = UPLOAD_DIR_VIDEOS . $file_id . '/';
                        mkdir($extract_dir, 0777, true);

                        // Allowed extensions whitelist
                        $allowed_inner_exts = ['html', 'htm', 'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'mp4', 'json'];
                        $index_found = false;
                        $total_uncompressed_size = 0;
                        $max_uncompressed_size = 512 * 1024 * 1024; // 512MB

                        // Pre-check total size
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $stat = $zip->statIndex($i);
                            $total_uncompressed_size += $stat['size'];
                        }

                        if ($total_uncompressed_size > $max_uncompressed_size) {
                            $zip->close();
                            deleteDir($extract_dir);
                            throw new Exception("上傳失敗：ZIP 內容解壓縮後預計佔用 " . round($total_uncompressed_size / 1024 / 1024, 2) . "MB，超過系統限制 (100MB)。");
                        }

                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);

                            // Path Traversal Protection
                            if (strpos($filename, '..') !== false || strpos($filename, '/') === 0 || strpos($filename, '\\') === 0) {
                                continue;
                            }

                            // Extension Whitelisting
                            $inner_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            if (empty($inner_ext) || !in_array($inner_ext, $allowed_inner_exts)) {
                                if (substr($filename, -1) !== '/') {
                                    continue;
                                }
                            }

                            $zip->extractTo($extract_dir, $filename);

                            if (basename($filename) === 'index.html') {
                                $index_found = true;
                            }
                        }
                        $zip->close();

                        if ($index_found) {
                            $content_path = 'uploads/videos/' . $file_id . '/index.html';
                        } else {
                            deleteDir($extract_dir);
                            throw new Exception("ZIP 檔案中未找到 index.html 檔案，或所有檔案皆因安全限制被攔截。");
                        }
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

        // Save Video with ownership
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO videos (title, content_path, thumbnail_path, event_date, campus_id, speaker_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiii", $title, $content_path, $thumb_path, $event_date, $campus_id, $speaker_id, $user_id);
        $stmt->execute();

        $conn->commit();
        $msg = "演講上傳成功！";
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