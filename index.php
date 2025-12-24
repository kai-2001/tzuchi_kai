<?php
// index.php - 主入口 (模組化版本)

session_set_cookie_params(0);
session_start();

// 載入核心模組
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/moodle_api.php';

// 產生 CSRF Token
generate_csrf_token();

// 自動登入檢查
check_auto_login();

// 處理登出
process_logout();

// 處理登入
$error_msg = process_login();

// 取得 Moodle 資料
$is_admin = isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : false;

// 刷新快取（如果 URL 有 ?refresh=1）
if (isset($_GET['refresh']) && $_GET['refresh'] == '1') {
    unset($_SESSION['moodle_cache']);
    unset($_SESSION['moodle_cache_time']);
    header("Location: index.php");
    exit;
}

// AJAX 清除快取（不重導向，供 JavaScript 呼叫）
if (isset($_GET['clear_cache']) && $_GET['clear_cache'] == '1') {
    unset($_SESSION['moodle_cache']);
    unset($_SESSION['moodle_cache_time']);
    echo 'OK';
    exit;
}

// 🚀 非同步模式: 不在伺服器端等待資料,改由前端 AJAX 載入
$async_mode = true; // 啟用非同步載入

// 準備空資料結構 (將由前端填充)
$my_courses_raw = [];
$history_by_year = [];
$available_courses = [];
$latest_announcements = [];
$curriculum_status = [];
?>
<?php if (!isset($_SESSION['username'])): ?>
    <?php include 'templates/landing.php'; ?>
<?php else: ?>
    <?php include 'templates/header.php'; ?>
    <?php include 'templates/dashboard.php'; ?>
    <?php include 'templates/footer.php'; ?>
<?php endif; ?>