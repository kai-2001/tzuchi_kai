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
            $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
            $conn->set_charset("utf8mb4");

            if ($conn->connect_error) {
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
                            // 2. 取得 Moodle 使用者 ID
                            $moodle_user = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', [
                                'field' => 'username',
                                'values' => [$user['username']]
                            ]);

                            $moodle_success = false;
                            $moodle_error = '';

                            if (!empty($moodle_user) && isset($moodle_user[0]['id'])) {
                                $moodle_user_id = $moodle_user[0]['id'];

                                // 3. 呼叫 Moodle API 更新密碼
                                $update_result = call_moodle($moodle_url, $moodle_token, 'core_user_update_users', [
                                    'users' => [
                                        [
                                            'id' => $moodle_user_id,
                                            'password' => $new_password
                                        ]
                                    ]
                                ]);

                                if (isset($update_result['exception'])) {
                                    $moodle_error = $update_result['message'] ?? '未知錯誤';
                                } else {
                                    $moodle_success = true;
                                }
                            } else {
                                $moodle_error = '找不到 Moodle 使用者';
                            }

                            // 4. 更新 Session 中的 moodle_token
                            $_SESSION['moodle_token'] = base64_encode(
                                openssl_encrypt($new_password, 'AES-256-CBC', $db_pass, 0, substr(md5($db_pass), 0, 16))
                            );

                            if ($moodle_success) {
                                $msg = "密碼修改成功！";
                                $msg_type = "success";
                                // 2秒後跳轉回首頁
                                header("refresh:2;url=index.php");
                            } else {
                                $msg = "入口網密碼已更新，但 Moodle 同步失敗：" . $moodle_error;
                                $msg_type = "warning";
                            }
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
    <style>
        :root {
            --primary: #2563eb;
            --accent: #06b6d4;
            --body-bg: #f0f4f8;
        }

        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #f0f4f8 50%, #ede9fe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            padding: 20px;
        }

        /* 浮游粒子 */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            z-index: 0;
            background:
                radial-gradient(ellipse 600px 400px at 15% 20%, rgba(99, 179, 237, 0.25) 0%, transparent 70%),
                radial-gradient(ellipse 500px 350px at 85% 25%, rgba(167, 139, 250, 0.2) 0%, transparent 70%);
        }

        .password-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 4px 24px rgba(99, 179, 237, 0.12), 0 12px 48px rgba(167, 139, 250, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.8);
            max-width: 450px;
            width: 100%;
            padding: 50px 40px;
            position: relative;
            z-index: 1;
        }

        .password-card h3 {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .password-card .subtitle {
            color: #64748b;
            margin-bottom: 30px;
        }

        .form-label {
            font-weight: 500;
            color: #475569;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            border: none;
            border-radius: 30px;
            padding: 14px 28px;
            font-weight: 600;
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.35);
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(37, 99, 235, 0.45);
        }

        .back-link {
            color: #64748b;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--primary);
        }

        .icon-header {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .icon-header i {
            font-size: 24px;
            color: white;
        }

        .password-rules {
            background: #f1f5f9;
            border-radius: 10px;
            padding: 12px 16px;
            margin-top: 10px;
            font-size: 13px;
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
        }

        .password-rules li {
            margin-bottom: 4px;
        }
    </style>
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