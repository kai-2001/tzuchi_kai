<?php
// process_queue.php
// Controller for the Video Processing Queue (Manual Mode)

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/worker_trigger.php';

if (!is_manager()) {
    die("未授權。");
}

// Handle Form Submission (Start Processing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['video_ids'])) {
    $ids = $_POST['video_ids'];
    if (!empty($ids) && is_array($ids)) {
        // Sanitize IDs
        $ids = array_map('intval', $ids);
        $id_list = implode(',', $ids);

        // Update Status to 'pending'
        $sql = "UPDATE videos SET status = 'pending' WHERE id IN ($id_list) AND status = 'waiting'";
        if ($conn->query($sql)) {
            $affected_rows = $conn->affected_rows;

            // Trigger Remote Worker
            trigger_remote_worker();

            header("Location: process_queue.php?msg=started&count=$affected_rows");
            exit;
        } else {
            $error = "更新狀態失敗：" . $conn->error;
        }
    }
}

// Toggle Auto-Compression Setting
if (isset($_GET['toggle_auto'])) {
    $new_val = $_GET['toggle_auto'] === '1' ? '1' : '0';
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('auto_compression', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("ss", $new_val, $new_val);
    $stmt->execute();
    header("Location: process_queue.php"); // Reload page
    exit;
}

// Fetch Current Setting
$auto_compression = '0';
$res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'auto_compression'");
if ($res && $row = $res->fetch_assoc()) {
    $auto_compression = $row['setting_value'];
}

// Fetch Waiting Videos
$waiting_videos = [];
$res = $conn->query("SELECT * FROM videos WHERE status = 'waiting' ORDER BY created_at ASC");
if ($res) {
    $waiting_videos = $res->fetch_all(MYSQLI_ASSOC);
}

// Fetch Pending/Processing (Monitor)
$active_jobs = [];
$res = $conn->query("SELECT id, title, status, process_msg FROM videos WHERE status IN ('pending', 'processing') ORDER BY id ASC");
if ($res) {
    $active_jobs = $res->fetch_all(MYSQLI_ASSOC);
}

// AUTO-TRIGGER LOGIC for Queue Page:
// If there are pending jobs, ensure the worker is triggered.
foreach ($active_jobs as $job) {
    if ($job['status'] === 'pending') {
        trigger_remote_worker();
        break;
    }
}

$page_title = '轉檔排程管理';
$page_css_files = ['forms.css']; // Reuse form styles

include 'templates/process_queue.php';
?>