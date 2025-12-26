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
        $content_path = $video['content_path'];
        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
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
                    $zip->extractTo($extract_dir);
                    $zip->close();
                    $content_path = 'uploads/videos/' . $file_id . '/index.html';
                }
            }
        }

        // Update Video Record
        $stmt = $conn->prepare("UPDATE videos SET title = ?, thumbnail_path = ?, content_path = ?, event_date = ?, campus_id = ?, speaker_id = ? WHERE id = ?");
        $stmt->bind_param("ssssiii", $title, $thumb_path, $content_path, $event_date, $campus_id, $new_speaker_id, $video_id);
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