<?php
// worker_config.php
// 專門給背景轉檔程式 (Worker) 使用的設定檔
// 特點：不包含 session_start() 或其他網頁專用的邏輯，確保 CLI 模式下能正常運作。

// ============================================
// 1. 資料庫設定 (Worker 專用)
// ============================================
define('WORKER_DB_HOST', 'localhost');
define('WORKER_DB_USER', 'root');
define('WORKER_DB_PASS', 'root123'); // 您的資料庫密碼
define('WORKER_DB_NAME', 'speech_db');

// ============================================
// 2. FFmpeg 路徑設定
// ============================================
// 優先使用的 FFmpeg 執行檔路徑
define('WORKER_FFMPEG_PATH', 'D:/ffmpeg-8.0.1-full_build/bin/ffmpeg.exe');

// ============================================
// 3. 其他設定
// ============================================
// 發生錯誤時是否要停止 (Debug 用)
define('WORKER_DEBUG_MODE', true);

// ============================================
// 4. 安全性設定 (Trigger 驗證用)
// ============================================
// 用於 api/trigger_worker.php 驗證來源合法性
define('WORKER_SECRET_TOKEN', 'YOUR_SUPER_SECRET_TOKEN_123');

// ============================================
// 5. 檔案路徑設定
// ============================================
// 專案根目錄 (預設為此設定檔的上一層目錄，即 speech/ 資料夾)
// [模擬部署] 由於目前放在 speech/worker_deploy/includes，故往上兩層回到 speech/
// 如果您的影片檔案放在其他磁碟 (例如 E:/videos)，請修改此處。
define('WORKER_APP_ROOT', realpath(__DIR__ . '/../../'));

// PHP 執行檔路徑 (選填)
// 若自動偵測失敗，或需指定特定 PHP 版本，請取消註解並修改。
// define('WORKER_PHP_PATH', 'C:/php/php.exe');

?>