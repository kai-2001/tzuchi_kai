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
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>☁️</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>

<body>
    <?php if (isset($_SESSION['username'])): ?>

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

            <div class="pg-right-group">
                <div id="pg-right-area">
                    <div class="pg-dropdown" id="portal-user-menu">
                        <div class="pg-link" style="display:flex; align-items:center; gap:12px;">
                            <span><?php echo h($_SESSION['fullname']); ?></span>
                            <div class="user-avatar-circle"><?php echo mb_substr($_SESSION['fullname'], 0, 1, "utf-8"); ?>
                            </div>
                        </div>
                        <div class="pg-dropdown-content" style="right:0; left:auto;">
                            <a href="change_password.php">
                                <i class="fas fa-key"></i> 修改密碼
                            </a>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> 登出系統</a>
                        </div>
                    </div>
                </div>

                <button class="mobile-menu-btn" style="display:none;">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </nav>

        <script>
            // Dashboard Menu Logic
            document.addEventListener('DOMContentLoaded', function () {
                var mobileMenuBtn = document.querySelector('.mobile-menu-btn');
                var mobileMenu = document.querySelector('.pg-menu');
                var userMenu = document.getElementById('portal-user-menu');

                // 1. Mobile Menu Toggle
                if (mobileMenuBtn) {
                    mobileMenuBtn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        // Close user menu if open
                        if (userMenu && userMenu.classList.contains('active')) {
                            userMenu.classList.remove('active');
                        }
                        if (mobileMenu) mobileMenu.classList.toggle('active');
                    });
                }

                // 2. User Menu Toggle (Click)
                if (userMenu) {
                    var trigger = userMenu.querySelector('.pg-link'); // Avatar area
                    if (trigger) {
                        trigger.addEventListener('click', function (e) {
                            e.preventDefault();
                            e.stopPropagation();

                            // Close mobile menu if open
                            if (mobileMenu && mobileMenu.classList.contains('active')) {
                                mobileMenu.classList.remove('active');
                            }

                            userMenu.classList.toggle('active');
                        });
                    }
                }

                // 3. Global Click Outside
                document.addEventListener('click', function (e) {
                    // Close Mobile Menu
                    if (mobileMenu && mobileMenu.classList.contains('active')) {
                        if (!mobileMenu.contains(e.target) && (!mobileMenuBtn || !mobileMenuBtn.contains(e.target))) {
                            mobileMenu.classList.remove('active');
                        }
                    }

                    // Close User Menu
                    if (userMenu && userMenu.classList.contains('active')) {
                        if (!userMenu.contains(e.target)) {
                            userMenu.classList.remove('active');
                        }
                    }
                });
            });

            // Keep original function name for backward compatibility if called inline
            function toggleDashboardMenu() {
                // Now handled by event listener, but keep empty or redirect just in case
                var btn = document.querySelector('.mobile-menu-btn');
                if (btn) btn.click();
            }
        </script>
        </nav>
    <?php endif; ?>