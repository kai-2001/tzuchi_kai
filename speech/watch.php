<?php
/**
 * Watch Page Controller
 * 
 * Handles: Video viewing, access control, view count increment
 * Template: templates/watch.php
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

// ============================================
// LOGIC: Access Control
// ============================================
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

// ============================================
// LOGIC: Get video ID and increment views
// ============================================
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $conn->query("UPDATE videos SET views = views + 1 WHERE id = $id");
}

// ============================================
// LOGIC: Fetch video data
// ============================================
$stmt = $conn->prepare("SELECT v.*, s.name as speaker_name, s.affiliation, s.position, c.name as campus_name 
                      FROM videos v
                      LEFT JOIN speakers s ON v.speaker_id = s.id
                      LEFT JOIN campuses c ON v.campus_id = c.id
                      WHERE v.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if (!$video) {
    die("未找到該演講。");
}

// ============================================
// LOGIC: Fetch user progress
// ============================================
$user_id = $_SESSION['user_id'];
$last_position = 0;
$stmt = $conn->prepare("SELECT last_position FROM video_progress WHERE user_id = ? AND video_id = ?");
$stmt->bind_param("ii", $user_id, $id);
$stmt->execute();
$progress_res = $stmt->get_result()->fetch_assoc();
if ($progress_res) {
    $last_position = $progress_res['last_position'];
}

// ============================================
// TEMPLATE: Pass data to template
// ============================================
$page_title = $video['title'];
$page_css_files = ['watch.css'];

include 'templates/watch.php';