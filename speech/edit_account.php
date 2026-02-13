<?php
/**
 * Edit Admin Account Controller
 * 
 * Handles: Editing existing admin accounts (info + password reset)
 * Access: Manager only
 * Template: templates/edit_account.php
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

// ============================================
// LOGIC: Access Control - Manager Only
// ============================================
if (!is_manager()) {
    header('Location: index.php?error=unauthorized');
    exit;
}

// ============================================
// LOGIC: Get account by ID
// ============================================
$account_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $conn->prepare("SELECT u.*, c.name as campus_name FROM users u LEFT JOIN campuses c ON u.campus_id = c.id WHERE u.id = ? AND u.role IN ('manager', 'campus_admin')");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();

if (!$account) {
    header('Location: manage_accounts.php?error=not_found');
    exit;
}

// ============================================
// LOGIC: Initialize
// ============================================
$error = '';

// ============================================
// LOGIC: Handle form submission
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_name = trim($_POST['display_name'] ?? '');
    $role = $_POST['role'] ?? 'campus_admin';
    $campus_id = !empty($_POST['campus_id']) ? (int) $_POST['campus_id'] : null;
    $new_password = $_POST['new_password'] ?? '';

    if ($role === 'campus_admin' && empty($campus_id)) {
        $error = '院區管理員必須選擇所屬院區。';
    } elseif ($role === 'manager') {
        $campus_id = null;
    }

    if (empty($error)) {
        // Update basic info
        $stmt = $conn->prepare("UPDATE users SET display_name = ?, role = ?, campus_id = ? WHERE id = ?");
        $stmt->bind_param("ssii", $display_name, $role, $campus_id, $account_id);
        $stmt->execute();

        // Update password if provided
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                $error = '密碼至少 6 個字元。';
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed, $account_id);
                $stmt->execute();
            }
        }

        if (empty($error)) {
            header("Location: manage_accounts.php?msg=updated");
            exit;
        }
    }
}

// ============================================
// LOGIC: Fetch campuses for dropdown
// ============================================
$campuses = $conn->query("SELECT id, name FROM campuses ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// ============================================
// TEMPLATE
// ============================================
$page_title = '編輯帳號';
$page_css_files = ['forms.css', 'manage.css'];

include 'templates/edit_account.php';
?>