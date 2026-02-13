<?php
/**
 * Manage Accounts Controller
 * 
 * Handles: Admin account listing and deletion
 * Access: Manager only
 * Template: templates/manage_accounts.php
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

// ============================================
// LOGIC: Access Control - Manager Only
// ============================================
if (!is_manager()) {
    die("未授權：僅系統管理員可進入此頁面。");
}

// ============================================
// LOGIC: Handle Actions
// ============================================
$msg = '';
$error = '';
$action = $_GET['action'] ?? '';

// --- Delete Account ---
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    // Prevent deleting self
    if ($id === (int) $_SESSION['user_id']) {
        $error = '無法刪除自己的帳號。';
    } else {
        // Delete associated tokens first
        $stmt = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // Delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role IN ('manager', 'campus_admin')");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($conn->affected_rows > 0) {
            header("Location: manage_accounts.php?msg=deleted");
            exit;
        } else {
            $error = '刪除失敗，帳號不存在或非管理員帳號。';
        }
    }
}

// ============================================
// LOGIC: URL messages
// ============================================
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'created':
            $msg = '帳號已成功建立。';
            break;
        case 'updated':
            $msg = '帳號已成功更新。';
            break;
        case 'deleted':
            $msg = '帳號已成功刪除。';
            break;
    }
}

// ============================================
// LOGIC: Fetch admin accounts
// ============================================
$query = "SELECT u.*, c.name as campus_name, 
                 u.password IS NOT NULL AND u.password != '' as has_password
          FROM users u
          LEFT JOIN campuses c ON u.campus_id = c.id
          WHERE u.role IN ('manager', 'campus_admin')
          ORDER BY u.role DESC, u.id ASC";
$accounts = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// ============================================
// TEMPLATE: Pass data to template
// ============================================
$page_title = '帳號管理';
$page_css_files = ['manage.css'];

include 'templates/manage_accounts.php';
?>