<?php
/**
 * forgot_password.php - 忘記密碼請求頁面
 * 允許使用者輸入 Email 來請求重置密碼
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

$msg = '';
$msg_type = '';
$simulated_url = ''; // 測試階段用來顯示模擬的重置網址

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = "安全驗證失敗，請重新整理頁面後再試。";
        $msg_type = "danger";
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "請輸入有效的 Email 地址！";
            $msg_type = "danger";
        } else {
            require 'includes/db_connect.php';

            if (!isset($conn) || $conn->connect_error) {
                $msg = "系統暫時無法連線，請稍後再試。";
                $msg_type = "danger";
            } else {
                // 尋找出是否有這組 Email (確保只更新存在的活躍帳號)
                $stmt = $conn->prepare("SELECT id, username, fullname FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();

                if ($user) {
                    // 有此使用者，產生 Token 並寫入資料庫
                    $token = bin2hex(random_bytes(32)); // 產生長度 64 的隨機字串
                    $created_at = date('Y-m-d H:i:s');
                    $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes')); // 30 分鐘後過期

                    // 記錄到獨立的密碼重置表中
                    $insert_stmt = $conn->prepare("INSERT INTO password_resets (email, token, created_at, expires_at) VALUES (?, ?, ?, ?)");
                    $insert_stmt->bind_param("ssss", $email, $token, $created_at, $expires_at);

                    if ($insert_stmt->execute()) {
                        // TODO: 未來這裡要換成真的 SMTP 寄信程式，例如 PHPMailer。
                        // send_reset_email($email, $token);

                        // 開發環境：直接在畫面上顯示網址以供測試
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                        $domainName = $_SERVER['HTTP_HOST'];
                        $simulated_url = $protocol . $domainName . $web_root . "/reset_password.php?token=" . $token;

                        $msg = "密碼重置信件已成功發送至您的信箱。請於 30 分鐘內點擊信中的連結完成密碼變更。";
                        $msg_type = "success";
                    } else {
                        $msg = "系統發生錯誤，無法處理您的請求，請聯絡系統服務員。";
                        $msg_type = "danger";
                    }
                    $insert_stmt->close();
                } else {
                    // 為了防止有心人士測試我們系統有哪些 Email，不管存不存在，我們都顯示成功訊息，這是業界標準資安做法。
                    $msg = "如果這組 Email 存在於我們的系統中，重置信件已發送至您的信箱。請於 30 分鐘內點擊信件連結完成密碼變更。";
                    $msg_type = "success";
                }
                $conn->close();
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
    <title>忘記密碼 | 雲嘉學習網</title>
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
            line-height: 1.6;
        }

        .form-label {
            font-weight: 600;
            color: #334155;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .input-icon-wrapper {
            position: relative;
            margin-bottom: 24px;
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
            line-height: 1.6;
        }

        .simulation-box {
            background: #fffbeb;
            border: 1px dashed #f59e0b;
            border-radius: 12px;
            padding: 16px;
            margin-top: 20px;
            font-size: 13px;
        }
    </style>
</head>

<body>
    <div class="login-form-card">
        <div class="login-header">
            <i class="fas fa-paper-plane"></i>
            <h3>忘記密碼</h3>
        </div>

        <div class="login-body">
            <p class="subtitle">請輸入您在系統中註冊的 Email 信箱，<br>系統將會傳送重置密碼的連結給您。</p>

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

                <?php if ($simulated_url): ?>
                    <!-- [開發測試] 模擬寄信模式 -->
                    <div class="simulation-box">
                        <strong style="color: #b45309;"><i class="fas fa-bug"></i> 【測試模式】模擬信件已成功「寄出」</strong><br>
                        等未來掛載 SMTP 之後，這裡會是真的信件寄送到使用者的信箱。現在作為測試，您可以直接點擊以下網址來設定新密碼：<br><br>
                        <a href="<?php echo htmlspecialchars($simulated_url, ENT_QUOTES, 'UTF-8'); ?>"
                            style="color: #2563eb; word-break: break-all; text-decoration: underline;" target="_blank">
                            <?php echo htmlspecialchars($simulated_url, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            <form method="post" <?php if ($msg_type === 'success')
                echo 'style="display:none;"'; ?>>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <label class="form-label">電子信箱 (Email)</label>
                <div class="input-icon-wrapper">
                    <input type="email" name="email" class="form-control" required placeholder="請輸入您的 Email 地址">
                    <i class="fas fa-envelope"></i>
                </div>

                <button type="submit" class="btn-login">
                    發送重置信件 <i class="fas fa-paper-plane"></i>
                </button>
            </form>

            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> 返回登入頁面
            </a>
        </div>
    </div>
</body>

</html>