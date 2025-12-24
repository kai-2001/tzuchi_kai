<!-- templates/login_form.php - 現代化登入表單 -->
<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-graduation-cap"></i>
            <h3>慈濟醫療志業學習網</h3>
        </div>
        <div class="login-body">
            <?php if ($error_msg): ?>
                <div class="alert alert-danger" style="border-radius: 12px;">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo h($error_msg); ?>
                </div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-4">
                    <label class="form-label fw-medium">帳號</label>
                    <input type="text" name="username" class="form-control" placeholder="請輸入帳號" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-medium">密碼</label>
                    <input type="password" name="password" class="form-control" placeholder="請輸入密碼" required>
                </div>
                <div class="mb-4 d-flex justify-content-between align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
                        <label class="form-check-label text-muted" for="rememberMe">保持登入</label>
                    </div>
                    <a href="<?php echo $moodle_url; ?>/login/forgot_password.php" target="_blank"
                        style="color: var(--primary); text-decoration: none; font-size: 14px;">
                        忘記密碼?
                    </a>
                </div>
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>登入
                </button>
            </form>
            <div class="mt-4 text-center">
                <a href="register.php" style="color: var(--primary); text-decoration: none; font-weight: 500;">
                    還沒有帳號？立即註冊 <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>