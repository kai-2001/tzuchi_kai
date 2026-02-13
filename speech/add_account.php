<?php
/**
 * Add Admin Account Controller
 * 
 * Handles: Creating new admin accounts
 * Access: Manager only
 * Template: templates/add_account.php
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
// LOGIC: Initialize
// ============================================
$error = '';

// ============================================
// LOGIC: Handle form submission
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $role = 'campus_admin'; // Only campus_admin can be created
    $campus_id = !empty($_POST['campus_id']) ? (int) $_POST['campus_id'] : null;
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($username)) {
        $error = '帳號名稱不可為空。';
    } elseif (strlen($username) < 3) {
        $error = '帳號名稱至少 3 個字元。';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = '帳號只能包含英文字母、數字和底線。';
    } elseif (empty($password) || strlen($password) < 6) {
        $error = '密碼至少 6 個字元。';
    } elseif ($role === 'campus_admin' && empty($campus_id)) {
        $error = '院區管理員必須選擇所屬院區。';
    } else {
        // Check duplicate
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "帳號「$username」已存在。";
        } else {
            // Create account
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            if ($role === 'manager') {
                $campus_id = null;
            }

            $stmt = $conn->prepare("INSERT INTO users (username, password, display_name, role, campus_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("ssssi", $username, $hashed, $display_name, $role, $campus_id);

            if ($stmt->execute()) {
                header("Location: manage_accounts.php?msg=created");
                exit;
            } else {
                $error = '建立帳號失敗：' . $conn->error;
            }
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
$page_title = '新增帳號';
$page_css_files = ['forms.css', 'manage.css'];

include 'templates/add_account.php';
?>