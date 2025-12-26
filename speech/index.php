<?php
/**
 * Home Page Controller
 * 
 * Handles: Video listing, search, campus filtering, pagination
 * Template: templates/home.php
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

// ============================================
// LOGIC: Pagination settings
// ============================================
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * ITEMS_PER_PAGE;

// ============================================
// LOGIC: Filter settings
// ============================================
$campus_id = isset($_GET['campus']) ? (int) $_GET['campus'] : 0;
$search = isset($_GET['q']) ? $_GET['q'] : '';

// ============================================
// LOGIC: Build Query
// ============================================
$query = "SELECT v.*, s.name as speaker_name, s.affiliation, c.name as campus_name 
          FROM videos v
          LEFT JOIN speakers s ON v.speaker_id = s.id
          LEFT JOIN campuses c ON v.campus_id = c.id
          WHERE 1=1";
$params = [];
$types = "";

if ($campus_id > 0) {
    $query .= " AND v.campus_id = ?";
    $params[] = $campus_id;
    $types .= "i";
}

if (!empty($search)) {
    $query .= " AND (v.title LIKE ? OR s.name LIKE ? OR s.affiliation LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// ============================================
// LOGIC: Get Total Count for Pagination
// ============================================
$count_query = str_replace("SELECT v.*, s.name as speaker_name, s.affiliation, c.name as campus_name", "SELECT COUNT(*)", $query);
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$total_items = $result->fetch_row()[0];
$total_pages = ceil($total_items / ITEMS_PER_PAGE);

// ============================================
// LOGIC: Get Results
// ============================================
$query .= " ORDER BY v.created_at DESC LIMIT ? OFFSET ?";
$params[] = ITEMS_PER_PAGE;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$videos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================
// LOGIC: Get Campuses for tabs
// ============================================
$campuses = $conn->query("SELECT * FROM campuses")->fetch_all(MYSQLI_ASSOC);

// ============================================
// TEMPLATE: Pass data to template
// ============================================
$page_title = APP_NAME;
$show_login_modal = true;
$page_js = "window.isLoggedIn = " . (is_logged_in() ? 'true' : 'false') . ";";

include 'templates/home.php';