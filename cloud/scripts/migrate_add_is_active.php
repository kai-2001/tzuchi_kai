<?php
/**
 * 補充遷移 - 新增 users.is_active 欄位
 */
require_once __DIR__ . '/../includes/config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

// 檢查欄位是否存在
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1";
    if ($conn->query($sql)) {
        echo "已新增 users.is_active 欄位\n";
    } else {
        echo "新增失敗: " . $conn->error . "\n";
    }
} else {
    echo "users.is_active 欄位已存在\n";
}

$conn->close();
?>