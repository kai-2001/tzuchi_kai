<?php
/**
 * change_password.php - 修改密碼頁面
 * 同時更新 portal_db 和 Moodle 資料庫
 */
session_set_cookie_params(0);
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 必須登入才能訪問
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

// 產生 CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 驗證 CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = "安全驗證失敗，請重新整理頁面後再試。";
        $msg_type = "danger";
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // 基本驗證
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $msg = "所有欄位都是必填的！";
            $msg_type = "danger";
        } elseif ($new_password !== $confirm_password) {
            $msg = "新密碼與確認密碼不一致！";
            $msg_type = "danger";
        } else {
            // 密碼規則驗證 (符合 Moodle 預設政策)
            $password_errors = [];
            if (strlen($new_password) < 8) {
                $password_errors[] = "至少要有 8 個字元";
            }
            if (!preg_match('/[0-9]/', $new_password)) {
                $password_errors[] = "至少要有 1 個數字";
            }
            if (!preg_match('/[a-z]/', $new_password)) {
                $password_errors[] = "至少要有 1 個小寫字母";
            }
            if (!preg_match('/[A-Z]/', $new_password)) {
                $password_errors[] = "至少要有 1 個大寫字母";
            }
            if (!preg_match('/[^a-zA-Z0-9]/', $new_password)) {
                $password_errors[] = "至少要有 1 個特殊符號 (!@#$%^&* 等)";
            }

            if (!empty($password_errors)) {
                $msg = "密碼不符合規則：<br>• " . implode("<br>• ", $password_errors);
                $msg_type = "danger";
            } else {
                // 連線資料庫驗證當前密碼
                require 'includes/db_connect.php';
                // db_connect.php 會自動處理連線錯誤並中止程式，或建立 $conn 變數

                if (!isset($conn) || $conn->connect_error) {
                    $msg = "系統暫時無法連線，請稍後再試。";
                    $msg_type = "danger";
                } else {
                    // 取得使用者資料
                    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
                    $stmt->bind_param("s", $_SESSION['username']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $stmt->close();

                    if (!$user) {
                        $msg = "使用者不存在！";
                        $msg_type = "danger";
                    } else {
                        // 驗證當前密碼
                        $password_valid = false;
                        if (password_verify($current_password, $user['password'])) {
                            $password_valid = true;
                        } elseif ($user['password'] === $current_password) {
                            // 舊的明碼密碼
                            $password_valid = true;
                        }

                        if (!$password_valid) {
                            $msg = "當前密碼錯誤！";
                            $msg_type = "danger";
                        } else {
                            // ===== 開始更新密碼 =====

                            // 1. 更新 portal_db
                            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $update_stmt->bind_param("si", $new_hash, $user['id']);
                            $portal_success = $update_stmt->execute();
                            $update_stmt->close();

                            if (!$portal_success) {
                                $msg = "更新密碼失敗，請稍後再試。";
                                $msg_type = "danger";
                            } else {
                                // 2. 也是這裡，原本會同步去改 Moodle 密碼，現在不需要了。
                                // 因為使用者走 SSO 登入，且 Moodle 端採用亂數密碼即可。
                                // $moodle_user = call_moodle(...); 
                                // ... (移除同步邏輯)

                                $msg = "密碼修改成功！";
                                $msg_type = "success";
                                // 2秒後跳轉回首頁
                                header("refresh:2;url=index.php");
                            }
                        }
                    }
                    $conn->close();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改密碼 | 雲嘉學習網</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <div class="password-card">
        <div class="icon-header">
            <i class="fas fa-key"></i>
        </div>
        <h3>修改密碼</h3>
        <p class="subtitle">請輸入您的當前密碼和新密碼</p>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8'); ?> mb-4">
                <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="mb-3">
                <label class="form-label">當前密碼</label>
                <input type="password" name="current_password" class="form-control" required placeholder="請輸入目前的密碼">
            </div>

            <div class="mb-3">
                <label class="form-label">新密碼</label>
                <input type="password" name="new_password" class="form-control" required placeholder="請輸入符合規則的新密碼">
                <div class="password-rules">
                    <div class="rules-title"><i class="fas fa-shield-alt me-1"></i> 密碼必須符合以下規則：</div>
                    <ul>
                        <li>至少 8 個字元</li>
                        <li>至少 1 個數字 (0-9)</li>
                        <li>至少 1 個小寫字母 (a-z)</li>
                        <li>至少 1 個大寫字母 (A-Z)</li>
                        <li>至少 1 個特殊符號 (!@#$%^&* 等)</li>
                    </ul>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">確認新密碼</label>
                <input type="password" name="confirm_password" class="form-control" required placeholder="再次輸入新密碼">
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-4">
                <i class="fas fa-check me-2"></i>確認修改
            </button>
        </form>

        <div class="text-center">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> 返回首頁
            </a>
        </div>
    </div>
</body>

</html>