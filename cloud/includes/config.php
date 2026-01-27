<?php
// config.php - 設定檔
// ⚠️ 警告：此檔案包含敏感資訊！
// 1. 請確保此檔案不會被 Git 版本控制追蹤（加入 .gitignore）
// 2. 請勿將此檔案分享給他人
// 3. 建議使用環境變數存放敏感資訊

// 1. 資料庫設定 (連接你的 MariaDB portal_db)
$db_host = 'localhost';
$db_user = 'root';        // 你的 MariaDB 帳號 (通常是 root)
$db_pass = 'root123';            // 你的 MariaDB 密碼 (如果沒設就留空)
$db_name = 'portal_db';

// 2. Moodle 設定
$moodle_url = 'http://kai/cloud/moodle'; // 請改成你 Moodle 的實際網址
$moodle_token = '758a2fbbc57ae5ef9f1724d462cbe7e1'; // Portal API Token
$moodle_sso_secret = '68071733b2b4d6fb8877e07ec5e5b571'; // SSO Login 外掛共享金鑰

// 2b. 網站根路徑設定（專案搬移時只需修改這裡）
define('BASE_URL', '/cloud');        // 網站根 URL 路徑（前端使用）
define('BASE_PATH', __DIR__ . '/..');  // 網站根實體路徑（後端使用）

// 3. 認證模式 ('soap' 或 'local')
define('AUTH_MODE', 'local');

// 4. SOAP 認證設定
define('SOAP_LOCATION', 'https://nlms.tzuchi.com.tw/tzuchi/webservice/user/svr.php');
define('SOAP_URI', 'https://nlms.tzuchi.com.tw/tzuchi/webservice/user');

// 5. 安全設定
define('CSRF_TOKEN_LENGTH', 32);       // CSRF Token 長度
define('CACHE_DURATION', 300);         // 快取時間（秒），5 分鐘
define('CURL_TIMEOUT', 10);            // API 請求超時（秒）
define('CURL_CONNECT_TIMEOUT', 5);     // API 連線超時（秒）

// 6. 系統執行檔路徑 (用於 exec)  <- 已棄用，改用 Web Services
// define('PHP_BIN_PATH', 'c:\\php\\php.exe');
?>