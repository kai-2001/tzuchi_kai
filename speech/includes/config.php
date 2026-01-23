<?php
/**
 * Speech Portal Configuration
 */

// Database Settings
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'root123';
$db_name = 'speech_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Auth Mode ('soap' or 'local')
define('AUTH_MODE', 'local');

// SOAP Auth Settings
define('SOAP_LOCATION', 'https://nlms.tzuchi.com.tw/tzuchi/webservice/user/svr.php');
define('SOAP_URI', 'https://nlms.tzuchi.com.tw/tzuchi/webservice/user');

// App Settings
define('APP_NAME', '學術演講影片平台');
define('ITEMS_PER_PAGE', 20);
define('UPLOAD_DIR_VIDEOS', __DIR__ . '/../uploads/videos/');
define('UPLOAD_DIR_THUMBS', __DIR__ . '/../uploads/thumbnails/');

session_name('SPEECH_SESSION');
session_start();

define('FFMPEG_PATH', 'D:/ffmpeg-8.0.1-full_build/bin/ffmpeg.exe');
// ============================================
// LOGIC: Distributed Transcoding Config
// ============================================
// 遠端轉檔主機的觸發網址 (目前模擬指向本機)
define('REMOTE_WORKER_URL', 'http://kai/speech/worker_deploy/api/trigger_worker.php');

// 觸發安全金鑰 (需與 api/trigger_worker.php 驗證邏輯一致，目前我先統一用這個)
define('WORKER_SECRET_TOKEN', 'YOUR_SUPER_SECRET_TOKEN_123');

// Load Models & Helpers
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/models/Video.php';
require_once __DIR__ . '/models/Announcement.php';
?>