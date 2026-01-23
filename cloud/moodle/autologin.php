<?php
// 檔案: moodle/autologin.php
// 用途: 強制切換使用者身分並登入

// ★★★ 修正點：移除 AJAX_SCRIPT 定義，否則 Moodle 會禁止跳轉 ★★★
// define('AJAX_SCRIPT', true);  <-- 這行是兇手，刪掉它！

define('REQUIRE_CORRECT_ACCESS', true);
define('NO_MOODLE_COOKIES', false); // 必須允許 Cookie 才能操作 Session

require_once('./config.php');

// 1. 接收資料
$username = optional_param('username', '', PARAM_RAW);
$password = optional_param('password', '', PARAM_RAW);
$wantsurl = optional_param('wantsurl', $CFG->wwwroot . '/my/', PARAM_URL);

// 2. 如果沒傳帳密，直接回首頁
if (empty($username) || empty($password)) {
    redirect($CFG->wwwroot);
}

// 3. 檢查當前狀態 (防止身分錯亂)
if (isloggedin() && !isguestuser()) {
    global $USER;
    
    // 如果「現在登入的人」就是「想要登入的人」，直接放行
    if ($USER->username === $username) {
        redirect($wantsurl);
        exit;
    }
    
    // 如果身分不同 (例如現在是 admin，但要登入 student1)
    // 強制登出當前使用者！
    \core\session\manager::terminate_current();
}

// 4. 驗證帳號密碼
$user = authenticate_user_login($username, $password);

if ($user) {
    // 登入成功，建立 Session
    complete_user_login($user);
    
    // 跳轉 (現在沒有 AJAX_SCRIPT 限制，這裡就會成功跳轉了！)
    redirect($wantsurl);
} else {
    // 登入失敗
    echo "<h1>Auto Login Failed</h1>";
    echo "<p>Moodle 驗證失敗：帳號或密碼錯誤。</p>";
    echo "<p>User: " . htmlspecialchars($username) . "</p>";
    echo "<a href='index.php'>回到登入頁</a>";
}
?>