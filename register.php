<?php
session_start();
require_once 'includes/config.php';

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
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $institution = trim($_POST['institution']);

        // 1. 基本檢查
        if (empty($username) || empty($password) || empty($fullname) || empty($email) || empty($institution)) {
            $msg = "所有欄位都是必填的！";
            $msg_type = "danger";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            // 帳號格式驗證：只允許英文、數字、底線
            $msg = "帳號只能包含英文字母、數字和底線！";
            $msg_type = "danger";
        } elseif (strlen($username) < 3 || strlen($username) > 20) {
            $msg = "帳號長度需在 3-20 個字元之間！";
            $msg_type = "danger";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Email 格式驗證
            $msg = "請輸入有效的電子信箱格式！";
            $msg_type = "danger";
        } elseif (strlen($password) < 8) {
            $msg = "密碼長度至少需要 8 個字元！";
            $msg_type = "danger";
        } else {
            // 連線資料庫
            require_once 'includes/db_connect.php';

            // 2. 檢查帳號是否已經存在 (外層)
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $msg = "這個帳號 ($username) 已經有人使用了，請換一個。";
                $msg_type = "warning";
            } else {
                // ★★★ 關鍵修改：加密密碼存入本地 DB ★★★
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // 3. 寫入外層資料庫 (portal_db) - 存入加密後的亂碼 + 機構
                $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, institution) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $hashed_password, $fullname, $email, $institution);

                if ($stmt->execute()) {
                    // 外層建立成功，接著同步到 Moodle
                    $last_name = mb_substr($fullname, 0, 1, "utf-8"); // 取第一個字當姓
                    $first_name = mb_substr($fullname, 1, null, "utf-8"); // 剩下的字當名

                    // --- 4. 呼叫 Moodle API 建立使用者 ---
                    $moodle_user_data = [
                        'users' => [
                            [
                                'username' => $username,
                                'password' => $password, // 傳給 Moodle 必須是「明碼」，Moodle 會自己加密
                                'firstname' => $first_name,
                                'lastname' => $last_name,
                                'email' => $email,
                                'institution' => $institution, // 🚀 新增機構欄位同步
                                'auth' => 'manual',
                            ]
                        ]
                    ];

                    $serverurl = $moodle_url . '/webservice/rest/server.php' . '?wstoken=' . $moodle_token . '&wsfunction=core_user_create_users&moodlewsrestformat=json';

                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_URL, $serverurl);
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($moodle_user_data));
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_TIMEOUT, 10);        // 最多等待 10 秒
                    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);  // 連線最多等 5 秒
                    $resp = curl_exec($curl);
                    curl_close($curl);

                    $moodle_result = json_decode($resp, true);

                    // 檢查 Moodle 是否建立成功
                    if (isset($moodle_result['exception'])) {
                        // Moodle 報錯 (通常是密碼太簡單)
                        $msg = "外層註冊成功，但同步 Moodle 失敗：" . $moodle_result['message'];
                        $msg_type = "warning";
                    } else {
                        // 🚀 關鍵新增: 自動將使用者加入對應院區的群組 (Cohort)
                        $cohort_map = [
                            '台北' => 'cohort_taipei',
                            '嘉義' => 'cohort_chiayi',
                            '大林' => 'cohort_dalin',
                            '花蓮' => 'cohort_hualien'
                        ];

                        if (array_key_exists($institution, $cohort_map)) {
                            $cohort_id = $cohort_map[$institution];
                            $script_path = __DIR__ . '/scripts/sync_cohort.php';
                            // 呼叫 CLI 指令: php scripts/sync_cohort.php [username] [cohort_id]
                            $cmd = "php " . escapeshellarg($script_path) . " " . escapeshellarg($username) . " " . escapeshellarg($cohort_id);
                            // 背景執行或同步執行皆可，這裡同步執行以確保狀態
                            exec($cmd);
                        }

                        $msg = "註冊成功！請使用新帳號登入。";
                        $msg_type = "success";
                        // 2秒後跳轉回登入頁
                        header("refresh:2;url=index.php");
                    }

                } else {
                    $msg = "資料庫錯誤：" . $conn->error;
                    $msg_type = "danger";
                }
            }
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊新帳號 | 雲嘉學習網</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --accent: #06b6d4;
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

        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 4px 24px rgba(99, 179, 237, 0.12), 0 12px 48px rgba(167, 139, 250, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.8);
            max-width: 480px;
            width: 100%;
            padding: 50px 40px;
            position: relative;
            z-index: 1;
        }

        .register-card h3 {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .register-card .subtitle {
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
    <div class="register-card">
        <div class="icon-header">
            <i class="fas fa-user-plus"></i>
        </div>
        <h3>註冊學員帳號</h3>
        <p class="subtitle">建立帳號開始您的學習之旅</p>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8'); ?> mb-4">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="mb-3">
                <label class="form-label">帳號</label>
                <input type="text" name="username" class="form-control" required placeholder="英文、數字或底線，3-20 字元">
            </div>

            <div class="mb-3">
                <label class="form-label">密碼</label>
                <input type="password" name="password" class="form-control" required placeholder="請輸入符合規則的密碼">
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

            <div class="mb-3">
                <label class="form-label">真實姓名</label>
                <input type="text" name="fullname" class="form-control" required placeholder="例如：王小明">
            </div>

            <div class="mb-3">
                <label class="form-label">所屬院區</label>
                <select name="institution" class="form-select" required>
                    <option value="" disabled selected>請選擇院區</option>
                    <option value="台北">台北院區</option>
                    <option value="嘉義">嘉義院區</option>
                    <option value="大林">大林院區</option>
                    <option value="花蓮">花蓮院區</option>
                    <option value="其他">其他</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label">電子信箱</label>
                <input type="email" name="email" class="form-control" required placeholder="name@example.com">
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-4">
                <i class="fas fa-check me-2"></i>立即註冊
            </button>
        </form>

        <div class="text-center">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> 已有帳號？返回登入
            </a>
        </div>
    </div>
</body>

</html>