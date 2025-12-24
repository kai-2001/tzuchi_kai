<?php
require_once 'includes/config.php';

$admin_username = 'admin';

// 1. Ensure admin exists in DB and is a manager
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $admin_username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $stmt = $conn->prepare("INSERT INTO users (username, role, status, display_name) VALUES (?, 'manager', 'active', '系統管理員')");
    $stmt->bind_param("s", $admin_username);
    $stmt->execute();
    $user_id = $conn->insert_id;
} else {
    $user_id = $user['id'];
    // Update to manager just in case
    $conn->query("UPDATE users SET role = 'manager' WHERE id = $user_id");
}

// 2. Set Session
$_SESSION['user_id'] = $user_id;
$_SESSION['username'] = $admin_username;
$_SESSION['role'] = 'manager';

echo "<div style='text-align:center; padding:50px; font-family:sans-serif;'>";
echo "<h2>已成功以「管理員($admin_username)」身分登入！</h2>";
echo "<p>正在導向首頁...</p>";
echo "<script>setTimeout(function(){ window.location.href='index.php'; }, 2000);</script>";
echo "<a href='index.php'>若沒有自動跳轉請點此</a>";
echo "</div>";
?>