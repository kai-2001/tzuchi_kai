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