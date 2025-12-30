<?php
/**
 * Manage Hero Slides Page Controller
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!is_manager()) {
    die("未授權：僅管理員可進入此頁面。");
}

// ============================================
// LOGIC: handle actions
// ============================================
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $conn->query("DELETE FROM hero_slides WHERE id = $id");
        header("Location: manage_hero.php?msg=deleted");
        exit;
    }
    if ($_GET['action'] === 'toggle' && isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $conn->query("UPDATE hero_slides SET is_active = 1 - is_active WHERE id = $id");
        header("Location: manage_hero.php?msg=updated");
        exit;
    }
    if ($_GET['action'] === 'toggle_hero' && isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $conn->query("UPDATE hero_slides SET is_hero = 1 - is_hero WHERE id = $id");
        header("Location: manage_hero.php?msg=updated");
        exit;
    }
}

// ============================================
// LOGIC: Fetch slides
// ============================================
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$where_sql = "1=1";
if (!empty($search)) {
    $search_esc = $conn->real_escape_string($search);
    $where_sql .= " AND (s.title LIKE '%$search_esc%' OR s.speaker_name LIKE '%$search_esc%')";
}

$query = "SELECT s.*, c.name as campus_name 
          FROM hero_slides s
          LEFT JOIN campuses c ON s.campus_id = c.id
          WHERE $where_sql
          ORDER BY s.sort_order ASC, s.created_at DESC";
$slides = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// ============================================
// TEMPLATE: Pass data to template
// ============================================
$page_title = '橫幅管理';
$page_css_files = ['manage.css'];

include 'templates/manage_hero.php';
?>