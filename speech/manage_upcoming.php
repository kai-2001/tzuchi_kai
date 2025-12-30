<?php
/**
 * Manage Upcoming Lectures Page Controller
 * 
 * Handles: Listing, search, pagination for upcoming lectures
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!is_manager()) {
    die("未授權：僅管理員可進入此頁面。");
}

// ============================================
// LOGIC: Parameters
// ============================================
$search = isset($_GET['q']) ? $_GET['q'] : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// ============================================
// LOGIC: handle delete
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $conn->query("DELETE FROM upcoming_lectures WHERE id = $id");
    header("Location: manage_upcoming.php?msg=deleted");
    exit;
}

// ============================================
// LOGIC: Get total count for pagination
// ============================================
$count_query = "SELECT COUNT(*) as total FROM upcoming_lectures WHERE 1=1";
$count_params = [];
$count_types = "";

if (!empty($search)) {
    $count_query .= " AND (title LIKE ? OR speaker_name LIKE ?)";
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
$query = "SELECT u.*, c.name as campus_name 
          FROM upcoming_lectures u
          LEFT JOIN campuses c ON u.campus_id = c.id
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (u.title LIKE ? OR u.speaker_name LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " ORDER BY u.event_date ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$lectures = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================
// TEMPLATE: Pass data to template
// ============================================
$page_title = '近期預告管理';
$page_css_files = ['manage.css'];

include 'templates/manage_upcoming.php';
