<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入 -
        <?php echo APP_NAME; ?>
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body class="login-page">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="container">
        <div class="login-card">
            <h1>會員登入</h1>

            <?php if (!empty($error)): ?>
                <div class="error-msg">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="off" id="loginForm">
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

                <div class="form-group" style="margin-top: 15px; margin-bottom: 20px; margin-left: 5px;">
                    <label class="checkbox-container"
                        style="display: flex; align-items: center; cursor: pointer; user-select: none;">
                        <input type="checkbox" name="remember_me"
                            style="width: 18px; height: 18px; accent-color: var(--primary-color); cursor: pointer;">
                        <span
                            style="margin-left: 10px; font-weight: 500; font-size: 0.95rem; color: var(--text-secondary);">保持登入狀態</span>
                    </label>
                </div>

                <button type="submit" class="btn-login-submit">
                    立即登入 <i class="fa-solid fa-arrow-right-to-bracket" style="margin-left: 8px; font-size: 0.9rem;"></i>
                </button>
            </form>

            <a href="index.php" class="back-link">
                <i class="fa-solid fa-chevron-left" style="font-size: 0.8rem; margin-right: 5px;"></i> 返回首頁
            </a>
        </div>
    </div>

    <script src="assets/js/validators.js"></script>
    <script>
        <?php require_once __DIR__ . '/../includes/Validator.php'; ?>
        const loginRules = <?= Validator::getRulesJson('login') ?>;
        FormValidator.init('loginForm', loginRules);
    </script>
</body>

</html>