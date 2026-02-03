<?php
/**
 * Admin Account Setup Script
 * Create and configure admin accounts (1 system admin + 4 campus admins)
 */

require_once 'includes/config.php';

echo "=================================================\n";
echo "   管理員帳號設定腳本\n";
echo "   Admin Account Setup Utility\n";
echo "=================================================\n\n";

// Define the 5 admin accounts (1 manager + 4 campus admins)
$admin_accounts = [
    [
        'username' => 'admin',
        'role' => 'manager',
        'campus_id' => null,
        'display_name' => '系統管理員',
        'description' => '系統管理員'
    ],
    [
        'username' => 'admin_dl',
        'role' => 'campus_admin',
        'campus_id' => 1, // 大林
        'display_name' => '大林院區管理員',
        'description' => '大林院區管理員'
    ],
    [
        'username' => 'admin_hl',
        'role' => 'campus_admin',
        'campus_id' => 2, // 花蓮
        'display_name' => '花蓮院區管理員',
        'description' => '花蓮院區管理員'
    ],
    [
        'username' => 'admin_tc',
        'role' => 'campus_admin',
        'campus_id' => 3, // 台中
        'display_name' => '台中院區管理員',
        'description' => '台中院區管理員'
    ],
    [
        'username' => 'admin_tp',
        'role' => 'campus_admin',
        'campus_id' => 4, // 台北
        'display_name' => '台北院區管理員',
        'description' => '台北院區管理員'
    ]
];

echo "此腳本將創建或更新以下 5 個管理員帳號：\n";
echo "----------------------------------------\n";
foreach ($admin_accounts as $idx => $account) {
    $num = $idx + 1;
    echo "[$num] {$account['username']} - {$account['description']}\n";
}
echo "----------------------------------------\n\n";

echo "是否繼續？(y/n): ";
$confirm = trim(fgets(STDIN));

if (strtolower($confirm) !== 'y') {
    echo "操作已取消。\n";
    exit(0);
}

echo "\n開始處理...\n\n";

$created = 0;
$updated = 0;
$skipped = 0;

foreach ($admin_accounts as $account) {
    $username = $account['username'];
    $role = $account['role'];
    $campus_id = $account['campus_id'];
    $display_name = $account['display_name'];

    // Check if account exists
    $stmt = $conn->prepare("SELECT id, role, campus_id, password IS NOT NULL as has_password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        echo "• $username - 帳號已存在\n";

        // Check if needs update
        $needs_update = false;
        $updates = [];

        if ($existing['role'] !== $role) {
            $needs_update = true;
            $updates[] = "角色: {$existing['role']} → $role";
        }

        if ($existing['campus_id'] != $campus_id) {
            $needs_update = true;
            $campus_old = $existing['campus_id'] ?? 'NULL';
            $campus_new = $campus_id ?? 'NULL';
            $updates[] = "院區: $campus_old → $campus_new";
        }

        if ($needs_update) {
            echo "  將更新: " . implode(", ", $updates) . "\n";

            if ($campus_id !== null) {
                $stmt = $conn->prepare("UPDATE users SET role = ?, campus_id = ?, display_name = ? WHERE username = ?");
                $stmt->bind_param("siss", $role, $campus_id, $display_name, $username);
            } else {
                $stmt = $conn->prepare("UPDATE users SET role = ?, campus_id = NULL, display_name = ? WHERE username = ?");
                $stmt->bind_param("sss", $role, $display_name, $username);
            }

            if ($stmt->execute()) {
                echo "  ✓ 更新成功\n";
                $updated++;
            } else {
                echo "  ✗ 更新失敗: " . $conn->error . "\n";
            }
        } else {
            echo "  - 無需更新\n";
            $skipped++;
        }

        // Check password status
        if (!$existing['has_password']) {
            echo "  ⚠️  尚未設定本地密碼\n";
        } else {
            echo "  ✓ 已設定本地密碼\n";
        }
    } else {
        echo "• $username - 新建帳號\n";

        // Create new account
        if ($campus_id !== null) {
            $stmt = $conn->prepare("INSERT INTO users (username, role, campus_id, display_name, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->bind_param("ssis", $username, $role, $campus_id, $display_name);
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, role, campus_id, display_name, status) VALUES (?, ?, NULL, ?, 'active')");
            $stmt->bind_param("sss", $username, $role, $display_name);
        }

        if ($stmt->execute()) {
            echo "  ✓ 創建成功 (ID: {$conn->insert_id})\n";
            echo "  ⚠️  請使用 setup_admin_passwords.php 設定密碼\n";
            $created++;
        } else {
            echo "  ✗ 創建失敗: " . $conn->error . "\n";
        }
    }

    echo "\n";
}

// Summary
echo "=================================================\n";
echo "   處理完成\n";
echo "=================================================\n";
echo "新建帳號: $created\n";
echo "更新帳號: $updated\n";
echo "跳過帳號: $skipped\n";
echo "總計: " . count($admin_accounts) . " 個管理員帳號\n\n";

// Check passwords
echo "檢查密碼設定狀態...\n";
echo "----------------------------------------\n";

$sql = "SELECT username, role, password IS NOT NULL as has_password 
        FROM users 
        WHERE role IN ('manager', 'campus_admin') 
        ORDER BY role DESC, id ASC";
$result = $conn->query($sql);

$total = 0;
$with_password = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $total++;
        $status = $row['has_password'] ? '✓' : '✗';
        $roleText = $row['role'] === 'manager' ? '系統管理員' : '院區管理員';
        echo "$status {$row['username']} ($roleText)\n";

        if ($row['has_password']) {
            $with_password++;
        }
    }
}

echo "----------------------------------------\n";
echo "已設定密碼: $with_password / $total\n\n";

if ($with_password < $total) {
    echo "⚠️  還有 " . ($total - $with_password) . " 個帳號未設定密碼\n";
    echo "請執行以下命令設定密碼：\n";
    echo "  php setup_admin_passwords.php\n\n";
} else {
    echo "✅ 所有管理員帳號都已設定密碼\n";
    echo "混合認證模式已就緒！\n\n";
}
