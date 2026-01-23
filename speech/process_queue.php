<?php
// process_queue.php
// Controller for the Video Processing Queue (Manual Mode)

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/worker_trigger.php';

if (!is_manager() && !is_campus_admin()) {
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
        if (is_campus_admin()) {
            $sql .= " AND campus_id = " . (int) $_SESSION['campus_id'];
        }
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
$campus_id = is_campus_admin() ? $_SESSION['campus_id'] : 0;
$setting_key = 'auto_compression';

if (isset($_GET['toggle_auto'])) {
    $new_val = $_GET['toggle_auto'] === '1' ? '1' : '0';
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, campus_id, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("siss", $setting_key, $campus_id, $new_val, $new_val);
    $stmt->execute();
    header("Location: process_queue.php"); // Reload page
    exit;
}

// Fetch Current Setting
// Fetch Current Setting
$auto_compression = '0';
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? AND campus_id = ?");
$stmt->bind_param("si", $setting_key, $campus_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $row = $res->fetch_assoc()) {
    $auto_compression = $row['setting_value'];
}

// Fetch Waiting Videos
$waiting_videos = [];
$wait_sql = "SELECT * FROM videos WHERE status = 'waiting'";
if (is_campus_admin()) {
    $wait_sql .= " AND campus_id = " . (int) $_SESSION['campus_id'];
}
$wait_sql .= " ORDER BY created_at ASC";

$res = $conn->query($wait_sql);
if ($res) {
    $waiting_videos = $res->fetch_all(MYSQLI_ASSOC);
}

// Fetch Pending/Processing (Monitor)
$active_jobs = [];
$active_sql = "SELECT id, title, status, process_msg FROM videos WHERE status IN ('pending', 'processing')";
if (is_campus_admin()) {
    $active_sql .= " AND campus_id = " . (int) $_SESSION['campus_id'];
}
$active_sql .= " ORDER BY id ASC";

$res = $conn->query($active_sql);
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