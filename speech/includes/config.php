<?php
/**
 * Speech Portal Configuration
 * Loads environment-specific settings from .env file
 */

// ============================================
// LOGIC: Load Environment Variables
// ============================================
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments and empty lines
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0)
            continue;

        // Parse KEY=VALUE
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Helper function to get environment variable with fallback
function env($key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    // Convert string booleans
    if (strtolower($value) === 'true')
        return true;
    if (strtolower($value) === 'false')
        return false;
    return $value;
}

// ============================================
// Database Settings
// ============================================
$db_host = env('DB_HOST', 'localhost');
$db_user = env('DB_USER', 'root');
$db_pass = env('DB_PASS', 'root123');
$db_name = env('DB_NAME', 'speech_db');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ============================================
// Environment Settings
// ============================================
define('APP_ENV', env('APP_ENV', 'development'));
define('IS_PRODUCTION', APP_ENV === 'production');

// ============================================
// Auth Settings
// ============================================
// Auth Mode ('soap' or 'local')
define('AUTH_MODE', env('AUTH_MODE', 'local'));

// SOAP Auth Settings
define('SOAP_LOCATION', env('SOAP_LOCATION', 'https://nlms.tzuchi.com.tw/tzuchi/webservice/user/svr.php'));
define('SOAP_URI', env('SOAP_URI', 'https://nlms.tzuchi.com.tw/tzuchi/webservice/user'));
define('SOAP_VERIFY_SSL', env('SOAP_VERIFY_SSL', false));

// ============================================
// App Settings
// ============================================
define('APP_NAME', env('APP_NAME', '學術演講影片平台'));
define('ITEMS_PER_PAGE', env('ITEMS_PER_PAGE', 20));
define('UPLOAD_DIR_VIDEOS', __DIR__ . '/../uploads/videos/');
define('UPLOAD_DIR_THUMBS', __DIR__ . '/../uploads/thumbnails/');

session_name('SPEECH_SESSION');
session_start();

// ============================================
// FFmpeg & Worker Settings
// ============================================
define('FFMPEG_PATH', env('FFMPEG_PATH', 'D:/ffmpeg-8.0.1-full_build/bin/ffmpeg.exe'));

// Distributed Transcoding Config
define('REMOTE_WORKER_URL', env('REMOTE_WORKER_URL', 'http://kai/speech/worker_deploy/api/trigger_worker.php'));
define('WORKER_SECRET_TOKEN', env('WORKER_SECRET_TOKEN', 'YOUR_SUPER_SECRET_TOKEN_123'));

// ============================================
// Load Models & Helpers
// ============================================
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/models/Video.php';
require_once __DIR__ . '/models/Announcement.php';
?>