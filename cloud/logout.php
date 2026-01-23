<?php
// logout.php - 強力登出處理程式

session_start();
require_once 'includes/config.php'; // 引入設定檔以取得 $moodle_url

// 防止快取
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// 1. 清空並銷毀 Session
$_SESSION = array();

// 清除 Session Cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// 2. 清除入口網的「保持登入」Cookie
if (isset($_COOKIE['portal_remember'])) {
    setcookie('portal_remember', '', time() - 3600, '/');
}

// 3. ★★★ 強力清除 Moodle Cookie (針對路徑) ★★★
// Moodle 的 Cookie 名稱通常是 "MoodleSession"，如果這招沒效，請看下方的「檢查步驟」
$moodle_cookie_name = 'MoodleSession';

// 策略 A: 嘗試刪除「根目錄 /」的 Moodle Cookie
setcookie($moodle_cookie_name, '', time() - 3600, '/');

// 策略 B: 嘗試解析 $moodle_url，找出 Moodle 的安裝路徑並刪除
// 例如: http://localhost/moodle -> 路徑是 /moodle/
if (isset($moodle_url)) {
    $path = parse_url($moodle_url, PHP_URL_PATH);

    // 如果路徑不是空的，且不是根目錄，就針對該路徑刪除
    if ($path && $path != '/') {
        // 刪除 /moodle
        setcookie($moodle_cookie_name, '', time() - 3600, $path);
        // 刪除 /moodle/ (多加斜線比較保險)
        setcookie($moodle_cookie_name, '', time() - 3600, rtrim($path, '/') . '/');
    }
}

// 4. 回到首頁
header("Location: index.php");
exit;
?>