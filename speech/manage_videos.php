<?php
/**
 * Manage Videos Page Controller
 * 
 * Handles: Video listing for admin, search, pagination
 * Template: templates/manage.php
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
// LOGIC: Auto-Trigger Check
// ============================================
// If any videos are pending, make sure worker is notified
$res_trigger = $conn->query("SELECT id FROM videos WHERE status = 'pending' LIMIT 1");
if ($res_trigger && $res_trigger->num_rows > 0) {
    trigger_remote_worker();
}

// ============================================
// LOGIC: Get user and search parameters
// ============================================
$user_id = $_SESSION['user_id'];
$search = isset($_GET['q']) ? $_GET['q'] : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// ============================================
// LOGIC: Get total count for pagination
// ============================================
$count_query = "SELECT COUNT(*) as total 
               FROM videos v
               LEFT JOIN speakers s ON v.speaker_id = s.id
               WHERE (1=1)"; // Show all to admin
$count_params = [];
$count_types = "";

if (!empty($search)) {
    $count_query .= " AND (v.title LIKE ? OR s.name LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_types .= "ss";
}

$stmt_count = $conn->prepare($count_query);
if (!empty($count_params)) {
    $stmt_count->bind_param($count_types, ...$count_params);
}
$stmt_count->execute();
$total_items = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $limit);

// ============================================
// LOGIC: Fetch records for current page
// ============================================
$query = "SELECT v.*, s.name as speaker_name, c.name as campus_name, v.status, v.process_msg 
          FROM videos v
          LEFT JOIN speakers s ON v.speaker_id = s.id
          LEFT JOIN campuses c ON v.campus_id = c.id
          WHERE (1=1)";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (v.title LIKE ? OR s.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " ORDER BY v.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$videos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================
// TEMPLATE: Pass data to template
// ============================================
$page_title = '影片管理';
$page_css_files = ['manage.css'];

include 'templates/manage.php';
?>