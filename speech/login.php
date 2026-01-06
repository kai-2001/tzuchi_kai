<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'includes/config.php';
require_once 'includes/auth.php';

$error = '';

// --- SSO Receiver Logic ---
if (isset($_GET['data']) && isset($_GET['sig'])) {
    require_once '../includes/config.php'; // Get $moodle_sso_secret from main portal

    $encdata = $_GET['data'];
    $signature = $_GET['sig'];
    $secret = $moodle_sso_secret;

    // Decrypt (AES-256-CBC)
    $decoded = base64_decode($encdata);
    if ($decoded !== false && strpos($decoded, '::') !== false) {
        list($ciphertext, $iv) = explode('::', $decoded, 2);
        $data = openssl_decrypt($ciphertext, 'aes-256-cbc', $secret, 0, $iv);

        if ($data !== false) {
            // Verify HMAC
            $expected_sig = hash_hmac('sha256', $data, $secret);
            if (hash_equals($expected_sig, $signature)) {
                $payload = json_decode($data, true);
                if ($payload && isset($payload['username'])) {
                    $username = $payload['username'];

                    // Successful SSO - Login User
                    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();

                    if (!$user) {
                        $stmt = $conn->prepare("INSERT INTO users (username, role) VALUES (?, 'member')");
                        $stmt->bind_param("s", $username);
                        $stmt->execute();
                        $user_id = $conn->insert_id;
                        $role = 'member';
                    } else {
                        $user_id = $user['id'];
                        $role = $user['role'];
                    }

                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;

                    header("Location: index.php");
                    exit;
                }
            }
        }
    }
    $error = 'SSO 驗證失敗。';
}
// --- End SSO Logic ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $auth_result = user_login($username, $password);

    if ($auth_result) {
        // Success - Check or create local user
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            // First time login, create as member
            $role = ($username === 'admin') ? 'manager' : 'member';
            $stmt = $conn->prepare("INSERT INTO users (username, role) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $role);
            $stmt->execute();
            $user_id = $conn->insert_id;
        } else {
            $user_id = $user['id'];
            $role = $user['role'];
        }

        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;

        // If SOAP returned an array with a name, store it in session
        if (is_array($auth_result) && isset($auth_result['sn'])) {
            $_SESSION['display_name'] = $auth_result['sn'];
        } elseif (is_object($auth_result) && isset($auth_result->sn)) {
            $_SESSION['display_name'] = $auth_result->sn;
        }

        header("Location: index.php");
        exit;
    } elseif ($auth_result === 'error') {
        $error = '登入服務暫時無法使用，請稍後再試。';
    } else {
        $error = '帳號或密碼錯誤。';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <title>登入 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #f0f9ff 0%, #e2eef4 100%);
            min-height: 100vh;
            margin: 0;
            position: relative;
        }

        /* Animated Blobs */
        .blob {
            position: fixed;
            width: 800px;
            height: 800px;
            background: linear-gradient(135deg, rgba(0, 132, 145, 0.12) 0%, rgba(242, 49, 131, 0.08) 100%);
            filter: blur(120px);
            border-radius: 50%;
            z-index: -1;
            animation: move 35s infinite alternate cubic-bezier(0.45, 0, 0.55, 1);
            pointer-events: none;
        }

        .blob-1 {
            top: -300px;
            left: -300px;
        }

        .blob-2 {
            bottom: -300px;
            right: -300px;
            animation-delay: -15s;
        }

        @keyframes move {
            from {
                transform: translate(0, 0) rotate(0deg) scale(1);
            }

            to {
                transform: translate(200px, 200px) rotate(180deg) scale(1.4);
            }
        }

        .login-card {
            max-width: 440px;
            width: 100%;
            padding: 50px 40px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 40px;
            text-align: center;
            box-shadow: 0 30px 60px -15px rgba(0, 132, 145, 0.15);
            animation: cardFadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
            margin: auto;
            /* Allow safe centering even if viewport is small */
        }

        @keyframes cardFadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 35px;
            background: linear-gradient(135deg, var(--primary-color), #00a8b9);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -1px;
        }

        .form-group {
            margin-bottom: 25px;
            text-align: left;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            margin-left: 5px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            opacity: 0.6;
            font-size: 1.1rem;
        }

        /* Override global !important styles from style.css */
        input[type="text"].glass-input,
        input[type="password"].glass-input {
            width: 100%;
            padding: 15px 20px 15px 50px !important;
            border-radius: 16px !important;
            border: 1px solid rgba(0, 132, 145, 0.2) !important;
            background: rgba(255, 255, 255, 0.6) !important;
            color: var(--text-primary) !important;
            font-size: 1rem !important;
            outline: none !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02) !important;
        }

        input[type="text"].glass-input:focus,
        input[type="password"].glass-input:focus {
            background: white !important;
            border-color: var(--primary-color) !important;
            box-shadow: 0 10px 20px rgba(0, 132, 145, 0.08) !important;
            transform: translateY(-2px);
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: var(--primary-color);
            background: linear-gradient(135deg, var(--primary-color) 0%, #00a8b9 100%);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 15px;
            box-shadow: 0 10px 20px rgba(0, 132, 145, 0.2);
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.2s ease, filter 0.2s ease;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 132, 145, 0.3);
            filter: brightness(1.08);
            /* Reduced brightness change to prevent flash */
        }

        .error-msg {
            background: rgba(255, 107, 107, 0.1);
            color: #e63946;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 0.9rem;
            border: 1px solid rgba(255, 107, 107, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .back-link {
            display: inline-block;
            margin-top: 30px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--primary-color);
        }
    </style>
</head>

<body>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="container">
        <div class="login-card">
            <h1>會員登入</h1>

            <?php if ($error): ?>
                <div class="error-msg">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="off">
                <div class="form-group">
                    <label>管理帳號</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user-shield"></i>
                        <input type="text" name="username" class="glass-input" placeholder="請輸入帳號" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>登入密碼</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-key"></i>
                        <input type="password" name="password" class="glass-input" placeholder="請輸入密碼" required>
                    </div>
                </div>
                <button type="submit" class="btn-submit">
                    立即登入 <i class="fa-solid fa-arrow-right-to-bracket" style="margin-left: 8px; font-size: 0.9rem;"></i>
                </button>
            </form>

            <a href="index.php" class="back-link">
                <i class="fa-solid fa-chevron-left" style="font-size: 0.8rem; margin-right: 5px;"></i> 返回首頁
            </a>
        </div>
    </div>
</body>

</html>