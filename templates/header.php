<!-- templates/header.php - 頁首區塊 -->
<?php
// 設定 cookie 傳遞 admin 狀態給 Moodle JavaScript
$is_admin = isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : false;
setcookie('portal_is_admin', $is_admin ? '1' : '0', 0, '/');
?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>雲嘉學習網 | 大林慈濟教學部</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>

<body>
    <?php if (isset($_SESSION['username'])): ?>
        <?php
        // SSO Login 外掛：產生加密的 SSO URL
        function generate_sso_url($username, $target_url)
        {
            global $moodle_url, $moodle_sso_secret;

            // 建立 payload
            $payload = json_encode([
                'username' => $username,
                'timestamp' => time()
            ]);

            // AES-256-CBC 加密
            $iv = openssl_random_pseudo_bytes(16);
            $ciphertext = openssl_encrypt($payload, 'aes-256-cbc', $moodle_sso_secret, 0, $iv);
            $encrypted = base64_encode($ciphertext . '::' . $iv);

            // HMAC 簽名
            $sig = hash_hmac('sha256', $payload, $moodle_sso_secret);

            // 建立 SSO URL
            $sso_url = $moodle_url . '/local/ssologin/login.php?data=' . urlencode($encrypted) . '&sig=' . $sig;

            // 如果有目標 URL，加入 wantsurl 參數
            if (!empty($target_url)) {
                $sso_url .= '&wantsurl=' . urlencode($target_url);
            }

            return $sso_url;
        }
        ?>

        <nav id="portal-global-nav">
            <div style="display:flex; align-items:center;">
                <a onclick="showHome()" class="pg-brand">
                    <i class="fas fa-cloud"></i>
                    <span>雲嘉學習網</span>
                    <?php // if ($is_admin) echo '<span class="admin-badge">Admin</span>'; // 已停用 ?>
                </a>

                <div class="pg-menu">
                    <?php if ($is_admin): ?>
                        <a href="#" onclick="goToMoodle('<?php echo $moodle_url; ?>/course/index.php')" class="pg-link">
                            <i class="fas fa-list"></i> 課程列表
                        </a>
                        <a href="#" onclick="goToMoodle('<?php echo $moodle_url; ?>/course/edit.php?category=2')"
                            class="pg-link">
                            <i class="fas fa-plus-circle"></i> 新增課程
                        </a>
                        <a href="#" onclick="goToMoodle('<?php echo $moodle_url; ?>/admin/user.php')" class="pg-link">
                            <i class="fas fa-users"></i> 使用者
                        </a>
                        <a href="#" onclick="goToMoodle('<?php echo $moodle_url; ?>/admin/search.php')" class="pg-link">
                            <i class="fas fa-cogs"></i> 網站管理
                        </a>
                    <?php else: ?>
                        <!-- 學生導覽列 -->
                        <?php
                        $is_teacherplus = isset($_SESSION['is_teacherplus']) ? $_SESSION['is_teacherplus'] : false;
                        if ($is_teacherplus):
                            ?>
                            <!-- 開課教師導覽列 -->
                            <a onclick="showHome()" class="pg-link">
                                <i class="fas fa-home"></i> 個人主頁
                            </a>
                            <a href="#" onclick="goToMoodle('<?php echo $moodle_url; ?>/course/edit.php')" class="pg-link">
                                <i class="fas fa-plus-circle"></i> 新增課程
                            </a>
                            <a onclick="showTab('course-management')" class="pg-link">
                                <i class="fas fa-tasks"></i> 課程管理
                            </a>
                        <?php else: ?>
                            <a onclick="showHome()" class="pg-link">
                                <i class="fas fa-home"></i> 個人主頁
                            </a>
                            <a onclick="showTab('quick-enroll')" class="pg-link">
                                <i class="fas fa-compass"></i> 探索課程
                            </a>
                            <a onclick="showTab('my-courses')" class="pg-link">
                                <i class="fas fa-book-open"></i> 我的課程
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="pg-right-area">
                <div class="pg-dropdown" id="portal-user-menu">
                    <div class="pg-link" style="display:flex; align-items:center; gap:12px;">
                        <span><?php echo h($_SESSION['fullname']); ?></span>
                        <div class="user-avatar-circle"><?php echo mb_substr($_SESSION['fullname'], 0, 1, "utf-8"); ?></div>
                    </div>
                    <div class="pg-dropdown-content" style="right:0; left:auto;">
                        <a href="change_password.php">
                            <i class="fas fa-key"></i> 修改密碼
                        </a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> 登出系統</a>
                    </div>
                </div>
            </div>
        </nav>
    <?php endif; ?>