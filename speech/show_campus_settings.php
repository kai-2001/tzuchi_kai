<?php
require_once 'includes/config.php';

echo "=== 院區與自動壓縮設定對照 ===\n\n";

// 查詢所有院區
$campuses = $conn->query("SELECT id, name FROM campuses ORDER BY id");

echo "院區列表：\n";
while ($campus = $campuses->fetch_assoc()) {
    // 查詢該院區的設定
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'auto_compression' AND campus_id = ?");
    $stmt->bind_param("i", $campus['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $setting = $result->fetch_assoc();

    $status = $setting ? ($setting['setting_value'] === '1' ? '✓ 已啟用' : '✗ 未啟用') : '(無設定)';

    echo "ID: {$campus['id']} - {$campus['name']}: {$status}\n";
}

echo "\n=== 全域預設設定 ===\n";
$global = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'auto_compression' AND campus_id = 0")->fetch_assoc();
echo "Campus 0 (全域): " . ($global && $global['setting_value'] === '1' ? '✓ 已啟用' : '✗ 未啟用') . "\n";
?>