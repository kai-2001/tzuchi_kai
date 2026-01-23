<?php
// api/get_moodle_data.php - 非同步取得 Moodle 資料的 JSON API

session_set_cookie_params(0);
session_start();

// 載入核心模組
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/moodle_api.php';

// 設定 JSON header
header('Content-Type: application/json; charset=utf-8');

// CORS 設定 (如果需要跨域請求)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// 檢查使用者是否登入
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated',
        'message' => '請先登入'
    ]);
    exit;
}

// 檢查是否為管理員
$is_admin = isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : false;

// 🚀 關鍵優化：檢查快取與 Dirty Flag
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$force_refresh = isset($_COOKIE['moodle_dirty']) || (isset($_GET['refresh']) && $_GET['refresh'] == '1');

// 快取邏輯
$cache_ttl = 600; // 10 分鐘快取
$cached_data = null;
$age = 0;

if (!$force_refresh && isset($_SESSION['moodle_cache'][$type])) {
    $age = time() - (isset($_SESSION['moodle_cache_time'][$type]) ? $_SESSION['moodle_cache_time'][$type] : 0);
    if ($age < $cache_ttl) {
        $cached_data = $_SESSION['moodle_cache'][$type];
    }
}

// 在確定沒有快取、需要呼叫 API 前，先釋放 Session 鎖
if (!$cached_data) {
    session_write_close();
}

if ($cached_data) {
    echo json_encode([
        'success' => true,
        'is_admin' => false,
        'type' => $type,
        'data' => $cached_data,
        'cached' => true,
        'cache_age' => $age,
        'source' => 'session_cache'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // 取得 Moodle 資料 (支援分段載入)
    $type = isset($_GET['type']) ? $_GET['type'] : 'all';
    $moodle_data = fetch_moodle_data($type);

    // 檢查是否有特定錯誤 (例如帳號未啟動)
    if ($moodle_data['error'] === 'MOODLE_USER_NOT_FOUND') {
        echo json_encode([
            'success' => true,
            'is_admin' => false,
            'data_not_found' => true,
            'message' => 'Moodle 帳號尚未建立'
        ]);
        exit;
    }

    // 🚀 關鍵修正：檢查是否有錯誤，如果有錯誤或資料不完整，不要快取 (或只快取極短時間)
    // 這樣可以避免 "查無課程" 的錯誤狀態持續 10 分鐘
    $should_cache = true;

    // 檢查是否有主要錯誤
    if (isset($moodle_data['error']) && !empty($moodle_data['error'])) {
        $should_cache = false;
    }

    // 檢查 my_courses_raw 是否有特定錯誤 (例如 timeout)
    if (isset($moodle_data['my_courses_raw']['error'])) {
        $should_cache = false;
    }

    // 如果是 'courses' 或 'all' 請求，但完全沒抓到課程 (且不是新使用者/管理員)，可能是暫時性錯誤
    // 注意: 我們不能假設每個學生都有課，所以這裡要小心判斷。
    // 但如果 my_courses_raw 是空的 array，通常可以快取。
    // 如果是 NULL 或其他意外狀態則不快取。

    if ($should_cache) {
        // 🚀 寫入快取
        session_start();
        if (!isset($_SESSION['moodle_cache']))
            $_SESSION['moodle_cache'] = [];
        if (!isset($_SESSION['moodle_cache_time']))
            $_SESSION['moodle_cache_time'] = [];

        $_SESSION['moodle_cache'][$type] = $moodle_data;
        $_SESSION['moodle_cache_time'][$type] = time();

        // 如果成功讀取並更新了，就清除 Dirty Flag
        if (isset($_COOKIE['moodle_dirty'])) {
            setcookie('moodle_dirty', '', time() - 3600, '/');
        }
        session_write_close();
    } else {
        // 如果不快取，也要確保 Session 鎖被釋放 (雖然上面 API 呼叫前已經釋放過了，但這裡開啟了新的 session 嗎? 
        // 不，fetch_moodle_data 裡沒有 session_start，但第 84 行有 session_start())
        // 所以如果 $should_cache 為 false，我們還沒開啟 session，或者剛剛開啟了？
        // 修正邏輯：原本第 84 行是 unconditionally session_start()。
        // 我們應該只在要寫入快取時才 session_start()
    }

    // 回傳成功結果
    echo json_encode([
        'success' => true,
        'is_admin' => false,
        'type' => $type,
        'data' => $moodle_data,
        'cached' => false,
        'cache_status' => $should_cache ? 'saved' : 'skipped', // Debug info
        'source' => 'live_api'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // 錯誤處理
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => '無法取得 Moodle 資料',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

    error_log("API Error: " . $e->getMessage());
}
?>