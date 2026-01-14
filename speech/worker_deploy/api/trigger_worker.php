<?php
// api/trigger_worker.php
// This script receives a POST request to trigger the background worker asynchronously.

require_once __DIR__ . '/../includes/worker_config.php';

header('Content-Type: application/json');

// Log helper
function trigger_log($msg)
{
    $logFile = __DIR__ . '/trigger_debug.log';
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg" . PHP_EOL, FILE_APPEND);
}

// 1. Authenticate
$token = $_POST['token'] ?? '';
if ($token !== WORKER_SECRET_TOKEN) {
    trigger_log("Unauthorized access attempt. Token: $token");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 2. Identify Paths
$php_exe = defined('WORKER_PHP_PATH') ? WORKER_PHP_PATH : (defined('PHP_BINARY') ? PHP_BINARY : 'php');
$worker_path = realpath(__DIR__ . '/../worker.php');

if (!$worker_path || !file_exists($worker_path)) {
    trigger_log("Worker file not found.");
    http_response_code(500);
    echo json_encode(['success' => false]);
    exit;
}

// 3. Construct Command based on OS
$is_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
$cmd = "\"$php_exe\" \"$worker_path\"";

trigger_log("Spawning background worker (OS: " . PHP_OS . ", CMD: $cmd)...");

if ($is_windows) {
    // Windows Method 1: WScript.Shell
    if (class_exists('COM')) {
        try {
            $WshShell = new COM("WScript.Shell");
            $WshShell->Run("cmd /c $cmd", 0, false);
            trigger_log("Spawned via WScript.Shell");
            echo json_encode(['success' => true, 'method' => 'WScript.Shell']);
            exit;
        } catch (Exception $e) {
            trigger_log("COM failed: " . $e->getMessage());
        }
    }

    // Windows Method 2: popen start /B
    // IMPORTANT: start "Title" "Command"
    $full_cmd = "start /B \"Worker\" $cmd";

    // Use pclose(popen(...))
    $handle = popen($full_cmd, "r");
    if ($handle) {
        $read = fread($handle, 2048); // Read any immediate error
        if ($read)
            trigger_log("popen output: $read");
        pclose($handle);
        trigger_log("Spawned via popen start /B");
        echo json_encode(['success' => true, 'method' => 'popen']);
        exit;
    }
} else {
    // Linux/Unix Method: nohup &
    $full_cmd = "nohup $cmd > /dev/null 2>&1 &";
    exec($full_cmd);
    trigger_log("Spawned via nohup &");
    echo json_encode(['success' => true, 'method' => 'nohup', 'os' => PHP_OS]);
    exit;
}

// Final Fallback Failure
trigger_log("All spawn methods failed.");
http_response_code(500);
echo json_encode(['success' => false, 'os' => PHP_OS]);
?>