<?php
// worker_config.php
// 專門給背景轉檔程式 (Worker) 使用的設定檔
// 特點：不包含 session_start() 或其他網頁專用的邏輯，確保 CLI 模式下能正常運作。

// ============================================
// Load Environment Variables from .env
// ============================================
function worker_load_env()
{
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) {
        throw new Exception("Worker .env file not found: $envFile");
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0)
            continue; // Skip comments

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}

worker_load_env();

// Helper function to get env variable
function worker_env($key, $default = null)
{
    return $_ENV[$key] ?? $default;
}

// ============================================
// 1. 資料庫設定 (從 .env 載入)
// ============================================
define('WORKER_DB_HOST', worker_env('DB_HOST', 'localhost'));
define('WORKER_DB_USER', worker_env('DB_USER', 'root'));
define('WORKER_DB_PASS', worker_env('DB_PASS', ''));
define('WORKER_DB_NAME', worker_env('DB_NAME', 'speech_db'));

// ============================================
// 2. FFmpeg 路徑設定
// ============================================
define('WORKER_FFMPEG_PATH', worker_env('FFMPEG_PATH', 'ffmpeg'));

// ============================================
// 3. 其他設定
// ============================================
define('WORKER_DEBUG_MODE', worker_env('DEBUG_MODE', 'false') === 'true');

// ============================================
// 4. 安全性設定 (Trigger 驗證用)
// ============================================
define('WORKER_SECRET_TOKEN', worker_env('WORKER_SECRET_TOKEN', ''));

// ============================================
// 5. 檔案路徑設定
// ============================================
define('WORKER_APP_ROOT', realpath(__DIR__ . '/../../'));

?>