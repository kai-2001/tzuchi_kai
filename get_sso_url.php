<?php
/**
 * SSO URL 產生器
 * 用於在 JavaScript 中動態產生 Moodle SSO 登入連結
 */

session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// 檢查是否已登入
if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// 取得目標 URL
$target_url = isset($_GET['url']) ? $_GET['url'] : $moodle_url . '/my/';

// 產生 SSO URL
$username = $_SESSION['username'];
$is_coursecreator = !empty($_SESSION['is_coursecreator']);

// 🚀 教師自動招生：如果是開課教師且目標是課程頁面，自動 enrol 為 editingteacher
if ($is_coursecreator && !empty($_GET['url'])) {
    $parsed_url = $_GET['url'];
    // 從 URL 提取 course_id (支援 course/view.php?id=XX)
    if (preg_match('/course\/view\.php\?id=(\d+)/', $parsed_url, $matches)) {
        $course_id = (int) $matches[1];
        $moodle_uid = $_SESSION['moodle_uid'] ?? null;
        if ($course_id > 0 && $moodle_uid) {
            // 直接用 curl（不用 call_moodle，因為 enrol API 成功回傳 null 會讓 call_moodle crash）
            $api_url = $moodle_url . '/webservice/rest/server.php';
            $params = [
                'wstoken' => $moodle_token,
                'wsfunction' => 'enrol_manual_enrol_users',
                'moodlewsrestformat' => 'json',
                'enrolments[0][roleid]' => 3,
                'enrolments[0][userid]' => $moodle_uid,
                'enrolments[0][courseid]' => $course_id,
            ];
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $api_url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $resp = curl_exec($ch);
            if (curl_errno($ch)) {
                error_log("[SSO] Teacher auto-enrol curl error: " . curl_error($ch));
            }
            curl_close($ch);
        }
    }
}

session_write_close(); // 🚀 關鍵優化：讀完後立即釋放 Session 鎖

$timestamp = time();

// 建立 payload
$payload = json_encode([
    'username' => $username,
    'timestamp' => $timestamp
]);

// AES-256-CBC 加密
$iv = openssl_random_pseudo_bytes(16);
$ciphertext = openssl_encrypt($payload, 'aes-256-cbc', $moodle_sso_secret, 0, $iv);
$encrypted = base64_encode($ciphertext . '::' . $iv);

// HMAC 簽名
$sig = hash_hmac('sha256', $payload, $moodle_sso_secret);

// 建立 SSO URL
$sso_url = $moodle_url . '/local/ssologin/login.php?data=' . urlencode($encrypted) . '&sig=' . $sig;

// 如果有目標 URL，加入 wantsurl 參數
if (!empty($target_url)) {
    $sso_url .= '&wantsurl=' . urlencode($target_url);
}

echo json_encode([
    'success' => true,
    'sso_url' => $sso_url
]);
?>