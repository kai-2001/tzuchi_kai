<?php
/**
 * 檢查自動壓縮設定
 */
require_once 'includes/config.php';

echo "=== 檢查自動壓縮設定 ===\n\n";

// 查詢所有院區的自動壓縮設定
$sql = "SELECT campus_id, setting_key, setting_value FROM system_settings WHERE setting_key = 'auto_compression' ORDER BY campus_id";
$result = $conn->query($sql);

echo "當前設定：\n";
echo str_repeat('-', 50) . "\n";
printf("%-15s %-25s %-15s\n", "Campus ID", "Setting Key", "Value");
echo str_repeat('-', 50) . "\n";

$found = false;
while ($row = $result->fetch_assoc()) {
    $found = true;
    printf(
        "%-15s %-25s %-15s\n",
        $row['campus_id'] == 0 ? '0 (全域預設)' : $row['campus_id'],
        $row['setting_key'],
        $row['setting_value'] === '1' ? '✓ 已啟用' : '✗ 未啟用'
    );
}

if (!$found) {
    echo "⚠️  資料庫中沒有任何 auto_compression 設定！\n";
}

echo str_repeat('-', 50) . "\n\n";

// 查詢所有院區
echo "所有院區：\n";
echo str_repeat('-', 50) . "\n";
$campuses = $conn->query("SELECT id, name FROM campuses ORDER BY id");
while ($campus = $campuses->fetch_assoc()) {
    echo "ID: {$campus['id']} - {$campus['name']}\n";
}
echo str_repeat('-', 50) . "\n\n";

// 測試查詢邏輯（模擬 upload.php 的查詢）
echo "測試查詢邏輯：\n";
echo str_repeat('-', 50) . "\n";

$test_campus_id = 1; // 假設測試院區 ID 1
$sql = "SELECT campus_id, setting_value FROM system_settings WHERE setting_key = 'auto_compression' AND campus_id IN (?, 0)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $test_campus_id);
$stmt->execute();
$res = $stmt->get_result();

$settings = [];
while ($row = $res->fetch_assoc()) {
    $settings[$row['campus_id']] = $row['setting_value'];
}

echo "院區 ID {$test_campus_id} 的查詢結果：\n";
var_dump($settings);

$auto_compression = '0';
if (isset($settings[$test_campus_id])) {
    $auto_compression = $settings[$test_campus_id];
    echo "→ 使用院區特定設定: {$auto_compression}\n";
} elseif (isset($settings[0])) {
    $auto_compression = $settings[0];
    echo "→ 使用全域預設設定: {$auto_compression}\n";
} else {
    echo "→ 使用程式碼預設值: {$auto_compression}\n";
}

echo "\n最終判定：";
if ($auto_compression === '1') {
    echo "✓ 自動壓縮已啟用 (status = 'pending')\n";
} else {
    echo "✗ 自動壓縮未啟用 (status = 'waiting')\n";
}

echo str_repeat('-', 50) . "\n";
?>