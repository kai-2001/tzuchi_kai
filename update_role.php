<?php
// update_role.php - 快速權限更新工具
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $role = $_POST['role'] ?? 'hospital_admin';

    if (!empty($username)) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE username = ?");
        $stmt->bind_param("ss", $role, $username);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $message = "<div class='alert alert-success'>成功！使用者 <strong>$username</strong> 的權限已更新為 <strong>$role</strong>。</div>";
            } else {
                $message = "<div class='alert alert-warning'>使用者 <strong>$username</strong> 的權限沒有變更（可能已經是該權限或使用者不存在）。</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>更新失敗：" . $stmt->error . "</div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='alert alert-danger'>請輸入使用者名稱。</div>";
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>更新使用者權限</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container mt-5">
    <h2>更新使用者權限工具</h2>
    <hr>
    <?php echo $message; ?>

    <div class="card p-4">
        <form method="post">
            <div class="mb-3">
                <label class="form-label">使用者名稱 (Username)</label>
                <input type="text" name="username" class="form-control" required placeholder="例如：testadmin_taipei">
            </div>

            <div class="mb-3">
                <label class="form-label">設定權限 (Role)</label>
                <select name="role" class="form-select">
                    <option value="hospital_admin">院區管理員 (hospital_admin)</option>
                    <option value="student">學生 (student)</option>
                    <option value="teacherplus">開課教師 (teacherplus)</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">更新權限</button>
        </form>
    </div>

    <div class="mt-4">
        <a href="index.php" class="btn btn-secondary">返回首頁</a>
    </div>
</body>

</html>