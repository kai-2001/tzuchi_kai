<?php
/**
 * 修正法人院區的自動壓縮設定
 */
require_once 'includes/config.php';

echo "=== 修正法人院區自動壓縮設定 ===\n\n";

$campus_id = 5; // 法人院區

// 檢查當前設定
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'auto_compression' AND campus_id = ?");
$stmt->bind_param("i", $campus_id);
$stmt->execute();
$result = $stmt->get_result();
$current = $result->fetch_assoc();

echo "當前設定：";
if ($current) {
    echo $current['setting_value'] === '1' ? "已啟用\n" : "未啟用\n";
} else {
    echo "無設定\n";
}

// 更新設定
$stmt = $conn->prepare("UPDATE system_settings SET setting_value = '1' WHERE setting_key = 'auto_compression' AND campus_id = ?");
$stmt->bind_param("i", $campus_id);

if ($stmt->execute()) {
    echo "✓ 已更新為：已啟用\n";

    // 驗證更新
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'auto_compression' AND campus_id = ?");
    $stmt->bind_param("i", $campus_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $updated = $result->fetch_assoc();

    echo "\n驗證結果：";
    echo $updated['setting_value'] === '1' ? "✓ 確認已啟用\n" : "✗ 更新失敗\n";
} else {
    echo "✗ 更新失敗：" . $stmt->error . "\n";
}

echo "\n現在上傳到法人院區的影片將自動進入壓縮排程。\n";
?>