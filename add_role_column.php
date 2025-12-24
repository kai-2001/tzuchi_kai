<?php
// 臨時腳本：新增 role 欄位到 users 資料表
require_once 'includes/config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

// 檢查欄位是否已存在
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($result->num_rows > 0) {
    echo "role 欄位已存在<br>";
} else {
    // 新增 role 欄位
    $sql = "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'student' AFTER email";
    if ($conn->query($sql)) {
        echo "✅ role 欄位新增成功！<br>";
    } else {
        echo "❌ 新增失敗: " . $conn->error . "<br>";
    }
}

// 顯示目前的使用者和角色
echo "<h3>目前使用者清單：</h3>";
$users = $conn->query("SELECT id, username, fullname, role FROM users");
echo "<table border='1' cellpadding='8'><tr><th>ID</th><th>帳號</th><th>姓名</th><th>角色</th></tr>";
while ($row = $users->fetch_assoc()) {
    echo "<tr><td>{$row['id']}</td><td>{$row['username']}</td><td>{$row['fullname']}</td><td>{$row['role']}</td></tr>";
}
echo "</table>";

echo "<br><p>要設定開課教師，請執行: UPDATE users SET role = 'teacherplus' WHERE username = '帳號名稱';</p>";

$conn->close();
?>