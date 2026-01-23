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
$video = video_get_by_id($id);

if (!$video) {
    die("未找到該演講。");
}

// ============================================
// LOGIC: Dynamic Chapter Loading (EverCam)
// ============================================
// ============================================
// LOGIC: Dynamic Chapter Loading (EverCam)
// ============================================
if ($video['format'] === 'evercam') {
    // Unified Logic: Replace "filename.mp4" with "config.js"
    // Works for both local paths (uploads/videos/xyz/media.mp4) and URLs (http://.../media.mp4)
    // Regex matches the last segment after / or \
    $config_path = preg_replace('/[\\\\\/][^\\\\\/]+$/', '/config.js', $video['content_path']);

    // Create connection context (Timeout 3s, Ignore SSL errors for compatibility)
    $ctx = stream_context_create([
        'http' => ['timeout' => 3, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);

    // Attempt to fetch content (suppress warnings with @)
    $config_content = @file_get_contents($config_path, false, $ctx);

    if ($config_content) {
        // Parse config.js: var config = { ... };
        if (preg_match('/var\s+config\s*=\s*(\{.*\})/s', $config_content, $matches)) {
            $json_text = trim($matches[1]);
            // Remove trailing semicolon if present
            $json_text = rtrim($json_text, ';');

            $config_data = json_decode($json_text, true);

            if ($config_data && isset($config_data['index'])) {
                // Override database metadata with fresh file data
                $video['metadata'] = json_encode($config_data['index']);
            }
        }
    }
}

// ============================================
// LOGIC: Fetch user progress
// ============================================
$user_id = $_SESSION['user_id'];
session_write_close();
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