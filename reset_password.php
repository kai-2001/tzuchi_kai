<?php
/**
 * reset_password.php - 重設密碼執行頁面
 * 接收 token 並允許使用者設定新密碼
 */
session_set_cookie_params(0);
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 如果已經登入，導向首頁
if (isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

// 產生 CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$token = $_GET['token'] ?? '';
$msg = '';
$msg_type = '';
$email = '';
$is_token_valid = false;

// 1. 驗證 Token 是否有效
if (empty($token) || strlen($token) !== 64) {
    $msg = "無效的密碼重置連結。";
    $msg_type = "danger";
} else {
    require 'includes/db_connect.php';
    if (!isset($conn) || $conn->connect_error) {
        $msg = "系統暫時無法連線，請稍後再試。";
        $msg_type = "danger";
    } else {
        $current_time = date('Y-m-d H:i:s');
        // 去資料庫比對 token，並確認是否過期 (統一使用 PHP 時間比對)
        $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > ? LIMIT 1");
        $stmt->bind_param("ss", $token, $current_time);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $email = $row['email'];
            $is_token_valid = true;
        } else {
            $msg = "您的密碼重置連結已失效或輸入錯誤。請重新申請。";
            $msg_type = "warning";
        }
        $stmt->close();
    }
}

// 2. 處理表單送出
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_token_valid) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = "安全驗證失敗，請重新整理頁面後再試。";
        $msg_type = "danger";
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($new_password) || empty($confirm_password)) {
            $msg = "所有欄位都是必填的！";
            $msg_type = "danger";
        } elseif ($new_password !== $confirm_password) {
            $msg = "新密碼與確認密碼不一致！";
            $msg_type = "danger";
        } else {
            // 密碼規則驗證 (與 change_password.php 一致)
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
                // 更新密碼
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

                // 1. 更新 users 表中的密碼
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $update_stmt->bind_param("ss", $new_hash, $email);

                if ($update_stmt->execute()) {
                    // 2. 刪除使用過的 token，避免重複使用
                    $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                    $del_stmt->bind_param("s", $email);
                    $del_stmt->execute();
                    $del_stmt->close();

                    $msg = "密碼修改成功！即將跳轉至登入頁面...";
                    $msg_type = "success";
                    $is_token_valid = false; // 成功後關閉表單顯示
                    header("refresh:3;url=index.php");
                } else {
                    $msg = "更新密碼失敗，請稍後再試。";
                    $msg_type = "danger";
                }
                $update_stmt->close();
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
    <title>重設密碼 | 雲嘉學習網</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Serif+TC:wght@500;600;700&display=swap"
        rel="stylesheet">
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/design-system.css">

    <style>
        body {
            background-color: #f8fafc;
            background-image: radial-gradient(circle at 15% 50%, rgba(44, 119, 104, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(37, 99, 235, 0.08), transparent 25%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            padding: 20px;
        }

        .login-form-card {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.06);
            width: 100%;
            max-width: 460px;
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
            padding: 35px 40px 30px;
            text-align: center;
            color: white;
            position: relative;
        }

        .login-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 40px;
            background: #ffffff;
            transform: skewY(-3deg);
            z-index: 0;
        }

        .login-header i {
            font-size: 2.8rem;
            margin-bottom: 16px;
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 16px;
            border-radius: 50%;
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 1;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .login-header h3 {
            margin: 0;
            font-family: 'Noto Serif TC', serif;
            font-weight: 700;
            font-size: 24px;
            position: relative;
            z-index: 1;
            letter-spacing: 1px;
        }

        .login-body {
            padding: 20px 40px 40px;
            position: relative;
            z-index: 1;
        }

        .login-body .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .form-label {
            font-weight: 600;
            color: #334155;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .input-icon-wrapper {
            position: relative;
            margin-bottom: 20px;
        }

        .input-icon-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            transition: color 0.3s ease;
        }

        .input-icon-wrapper .form-control {
            padding-left: 44px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            height: 48px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .input-icon-wrapper .form-control:focus {
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .input-icon-wrapper .form-control:focus+i,
        .input-icon-wrapper .form-control:focus~i {
            color: #2563eb;
        }

        .password-rules {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 13px;
            border: 1px solid #e2e8f0;
        }

        .password-rules .rules-title {
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }

        .password-rules ul {
            margin: 0;
            padding-left: 20px;
            color: #64748b;
            line-height: 1.6;
        }

        .btn-login {
            background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
            color: white;
            border: none;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
            margin-bottom: 24px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
            color: white;
        }

        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #2563eb;
        }

        .alert {
            border-radius: 12px;
            border: none;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 16px;
        }
    </style>
</head>

<body>
    <div class="login-form-card">
        <div class="login-header">
            <i class="fas fa-shield-alt"></i>
            <h3>建立新密碼</h3>
        </div>

        <div class="login-body">

            <?php if ($msg): ?>
                <div class="alert alert-<?php echo htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8'); ?> mb-4">
                    <?php if ($msg_type === 'success'): ?>
                        <i class="fas fa-check-circle fs-5 mt-1"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-circle fs-5 mt-1"></i>
                    <?php endif; ?>
                    <div>
                        <?php echo $msg; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($is_token_valid): ?>
                <p class="subtitle">為帳號
                    <?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?> 設定一組安全的新密碼。
                </p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <label class="form-label">新密碼</label>
                    <div class="input-icon-wrapper">
                        <input type="password" name="new_password" class="form-control" required placeholder="請輸入新密碼">
                        <i class="fas fa-lock"></i>
                    </div>

                    <label class="form-label">確認新密碼</label>
                    <div class="input-icon-wrapper mb-4">
                        <input type="password" name="confirm_password" class="form-control" required placeholder="再次輸入新密碼">
                        <i class="fas fa-check-circle"></i>
                    </div>

                    <div class="password-rules">
                        <div class="rules-title"><i class="fas fa-shield-alt me-1"></i> 密碼必須符合以下規則：</div>
                        <ul>
                            <li>至少 8 個字元</li>
                            <li>包含至少 1 個 <b>數字 (0-9)</b></li>
                            <li>包含至少 1 個 <b>小寫字母 (a-z)</b></li>
                            <li>包含至少 1 個 <b>大寫字母 (A-Z)</b></li>
                            <li>包含至少 1 個 <b>特殊符號</b> (!@#$%^&* 等)</li>
                        </ul>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="fas fa-save"></i> 儲存並登入
                    </button>
                </form>
            <?php else: ?>
                <a href="forgot_password.php" class="btn-login" style="margin-top:20px;">
                    重新申請密碼重置
                </a>
            <?php endif; ?>

            <?php if ($msg_type !== 'success'): ?>
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> 返回登入頁面
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>