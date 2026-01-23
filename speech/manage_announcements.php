<?php
/**
 * Manage Announcements Page Controller
 * Consolidates Hero Slides and Upcoming Lectures management.
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!is_manager() && !is_campus_admin()) {
    die("未授權：僅管理員或院區管理員可進入此頁面。");
}

// ============================================
// LOGIC: handle actions
// ============================================
if (isset($_GET['action'])) {
    // Delete
    if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id = (int) $_GET['id'];

        $sql = "DELETE FROM announcements WHERE id = $id";
        if (is_campus_admin()) {
            $sql .= " AND campus_id = " . (int) $_SESSION['campus_id'];
        }

        $conn->query($sql);

        // Check if deletion actually happened (to give better feedback if permission denied or not found)
        if ($conn->affected_rows > 0) {
            header("Location: manage_announcements.php?msg=deleted");
            exit;
        } else {
            // Illegal attempt or not found
            die("刪除失敗：權限不足或公告不存在。");
        }
    }
    // Toggle Active Status (optional if needed in future, but not requested in MVP)
    // Toggle Hero Status
    if ($_GET['action'] === 'toggle_hero' && isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $conn->query("UPDATE announcements SET is_hero = 1 - is_hero WHERE id = $id");
        header("Location: manage_announcements.php?msg=updated");
        exit;
    }

    // Update Sort Order (AJAX)
    if ($_GET['action'] === 'update_order' && isset($_GET['id']) && isset($_GET['order'])) {
        $id = (int) $_GET['id'];
        $order = (int) $_GET['order'];
        $stmt = $conn->prepare("UPDATE announcements SET sort_order = ? WHERE id = ?");
        $stmt->bind_param("ii", $order, $id);
        if ($stmt->execute()) {
            http_response_code(200);
            echo 'OK';
        } else {
            http_response_code(500);
            echo 'Error';
        }
        exit;
    }
}

// ============================================
// LOGIC: Fetch Params
// ============================================
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// ============================================
// LOGIC: Build Query
// ============================================
$where_sql = "1=1";

if (is_campus_admin()) {
    $where_sql .= " AND a.campus_id = " . (int) $_SESSION['campus_id'];
}

$params = [];
$types = "";

if (!empty($search)) {
    $where_sql .= " AND (a.title LIKE ? OR a.speaker_name LIKE ? OR a.location LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// Count total
$count_query = "SELECT COUNT(*) as total FROM announcements a WHERE $where_sql";
$stmt_count = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_items = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $limit);

// Fetch items
// Order by event date DESC by default for easy management, or maybe created_at?
// Let's use event_date (Targeting upcoming), but also sort_order for hero relevance?
// User asked for "like upcoming", so commonly Date DESC or ASC.
// Let's sort by event_date DESC (newest first)
$query = "SELECT a.*, c.name as campus_name 
          FROM announcements a
          LEFT JOIN campuses c ON a.campus_id = c.id
          WHERE $where_sql
          ORDER BY a.event_date DESC, a.created_at DESC
          LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================
// TEMPLATE: Pass data to template
// ============================================
$page_title = '公告管理';
$page_css_files = ['manage.css'];

include 'templates/manage_announcements.php';
?>