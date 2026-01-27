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

// Handle Auto-Compression Setting Toggle
if (isset($_GET['toggle_auto']) && isset($_GET['campus_id'])) {
    $target_campus_id = (int) $_GET['campus_id'];
    $new_val = $_GET['toggle_auto'] === '1' ? '1' : '0';

    // Permission check
    if (is_campus_admin() && $target_campus_id !== $_SESSION['campus_id']) {
        die("未授權：院區管理員只能修改自己院區的設定。");
    }

    $setting_key = 'auto_compression';
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, campus_id, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("siss", $setting_key, $target_campus_id, $new_val, $new_val);
    $stmt->execute();
    header("Location: process_queue.php");
    exit;
}

// Fetch Auto-Compression Settings for Display
$campus_settings = [];

if (is_manager()) {
    // Manager: Fetch all campuses (not global default)
    // Note: Global setting (campus_id=0) remains in DB as fallback but is not shown in UI
    $campuses = $conn->query("SELECT id, name FROM campuses ORDER BY id");

    while ($campus = $campuses->fetch_assoc()) {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'auto_compression' AND campus_id = ?");
        $stmt->bind_param("i", $campus['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $setting = $result->fetch_assoc();

        $campus_settings[] = [
            'campus_id' => $campus['id'],
            'campus_name' => $campus['name'],
            'auto_compression' => $setting ? $setting['setting_value'] : '0',
            'is_global' => false
        ];
    }
} else {
    // Campus Admin: Only their campus
    $my_campus_id = $_SESSION['campus_id'];
    $stmt = $conn->prepare("SELECT name FROM campuses WHERE id = ?");
    $stmt->bind_param("i", $my_campus_id);
    $stmt->execute();
    $campus_name = $stmt->get_result()->fetch_assoc()['name'];

    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'auto_compression' AND campus_id = ?");
    $stmt->bind_param("i", $my_campus_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $setting = $result->fetch_assoc();

    $campus_settings[] = [
        'campus_id' => $my_campus_id,
        'campus_name' => $campus_name,
        'auto_compression' => $setting ? $setting['setting_value'] : '0',
        'is_global' => false
    ];
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