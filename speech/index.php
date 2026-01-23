<?php
/**
 * Home Page Controller
 * 
 * Handles: Video listing, search, campus filtering, pagination
 * Template: templates/home.php
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
session_write_close();

// ============================================
// LOGIC: Filter and Pagination settings
// ============================================
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$campus_id = isset($_GET['campus']) ? (int) $_GET['campus'] : 0;
$search = isset($_GET['q']) ? $_GET['q'] : '';
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// ============================================
// LOGIC: Fetch Data via Models
// ============================================
$total_items = video_count_ready($campus_id, $search);
$total_pages = ceil($total_items / $limit);
$videos = video_get_ready($campus_id, $search, $limit, $offset);

$campuses = $conn->query("SELECT * FROM campuses")->fetch_all(MYSQLI_ASSOC);

// Announcements & Hero
$hero_slides = announcement_get_hero();
$upcoming_raw = announcement_get_upcoming();

// Group by Month -> Campus (View-specific logic restored to controller)
$upcoming_grouped = [];
foreach ($upcoming_raw as $lecture) {
    if (!$lecture['event_date'])
        continue;
    $month = date('Y-m', strtotime($lecture['event_date']));
    $campus = $lecture['campus_name'] ?? '全院';
    $upcoming_grouped[$month][$campus][] = $lecture;
}

// ============================================
// TEMPLATE: Pass data to template
// ============================================
$page_title = '首頁';
$page_js = "window.isLoggedIn = " . (is_logged_in() ? 'true' : 'false') . ";";
$show_login_modal = true;

include 'templates/home.php';