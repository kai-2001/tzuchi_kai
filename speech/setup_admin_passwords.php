<?php
/**
 * Admin Password Setup Utility
 * 
 * This script helps set up local passwords for privileged accounts
 * (system admin and campus admins) to enable hybrid authentication.
 * 
 * Usage: php setup_admin_passwords.php
 */

require_once 'includes/config.php';

echo "=================================================\n";
echo "   管理員密碼設定工具\n";
echo "   Admin Password Setup Utility\n";
echo "=================================================\n\n";

// Fetch all privileged accounts
$sql = "SELECT id, username, role, campus_id, password IS NOT NULL as has_password 
        FROM users 
        WHERE role IN ('manager', 'campus_admin') 
        ORDER BY role DESC, id ASC";

$result = $conn->query($sql);
$admins = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
}

if (empty($admins)) {
    echo "❌ 找不到管理員帳號！\n";
    echo "   請先在資料庫中創建管理員帳號。\n\n";
    exit(1);
}

// Display current status
echo "目前的特權帳號列表：\n";
echo "----------------------------------------\n";
foreach ($admins as $idx => $admin) {
    $num = $idx + 1;
    $status = $admin['has_password'] ? '✓ 已設定' : '✗ 未設定';
    $roleText = $admin['role'] === 'manager' ? '系統管理員' : '院區管理員';
    $campusInfo = $admin['campus_id'] ? " (院區 ID: {$admin['campus_id']})" : '';

    echo "[$num] {$admin['username']} - $roleText$campusInfo - 密碼: $status\n";
}
echo "----------------------------------------\n\n";

// Interactive password setup
while (true) {
    echo "請選擇要設定密碼的帳號 [1-" . count($admins) . "] (輸入 'q' 結束): ";
    $input = trim(fgets(STDIN));

    if (strtolower($input) === 'q') {
        echo "\n程式結束。\n";
        break;
    }

    $choice = intval($input);
    if ($choice < 1 || $choice > count($admins)) {
        echo "❌ 無效的選項，請重新輸入。\n\n";
        continue;
    }

    $selectedAdmin = $admins[$choice - 1];
    echo "\n選擇的帳號: {$selectedAdmin['username']}\n";

    // Password input
    echo "請輸入新密碼 (至少 6 個字元): ";
    $password = trim(fgets(STDIN));

    if (strlen($password) < 6) {
        echo "❌ 密碼太短，至少需要 6 個字元。\n\n";
        continue;
    }

    // Confirm password
    echo "請再次輸入新密碼: ";
    $password_confirm = trim(fgets(STDIN));

    if ($password !== $password_confirm) {
        echo "❌ 兩次輸入的密碼不一致，請重新操作。\n\n";
        continue;
    }

    // Hash and update
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $selectedAdmin['id']);

    if ($stmt->execute()) {
        echo "✅ 密碼設定成功！\n\n";
        // Update local array
        $admins[$choice - 1]['has_password'] = 1;
    } else {
        echo "❌ 密碼設定失敗：" . $conn->error . "\n\n";
    }
}

// Final summary
echo "\n=================================================\n";
echo "   最終狀態\n";
echo "=================================================\n";

$configured = 0;
$total = count($admins);

foreach ($admins as $admin) {
    $status = $admin['has_password'] ? '✓' : '✗';
    $roleText = $admin['role'] === 'manager' ? '系統管理員' : '院區管理員';
    echo "$status {$admin['username']} ($roleText)\n";
    if ($admin['has_password']) {
        $configured++;
    }
}

echo "----------------------------------------\n";
echo "已設定: $configured / $total\n";

if ($configured === $total) {
    echo "\n🎉 所有管理員帳號都已設定密碼！\n";
    echo "   混合認證模式已就緒。\n";
} else {
    echo "\n⚠️  尚有 " . ($total - $configured) . " 個帳號未設定密碼。\n";
    echo "   請稍後再次執行此腳本完成設定。\n";
}

echo "\n";
