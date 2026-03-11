<?php
/**
 * 院區管理員導覽列元件
 * templates/components/hospital-admin-nav.php
 * 
 * 使用 nav-v2 設計風格，分頁導覽
 * 需要變數：$web_root, $moodle_url, $current_page
 */
$current_page = isset($current_page) ? $current_page : '';
$institution = isset($_SESSION['institution']) ? $_SESSION['institution'] : '未設定';
?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>院區管理 - 雲嘉學習網</title>
    <link rel="icon" href="<?php echo $web_root; ?>/logo/small_logo.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $web_root; ?>/assets/css/design-system.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $web_root; ?>/assets/css/hospital-admin-styles.css?v=<?php echo time(); ?>">

    <!-- Frontend Configuration -->
    <script>
        window.PortalConfig = {
            webRoot: '<?php echo $web_root; ?>',
            baseUrl: '<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>',
            moodleUrl: '<?php echo $moodle_url; ?>',
            user: {
                username: '<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>',
                fullname: '<?php echo isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : ''; ?>',
                institution: '<?php echo isset($_SESSION['institution']) ? htmlspecialchars($_SESSION['institution']) : ''; ?>',
                institutionId: <?php echo isset($_SESSION['institution_id']) ? (int) $_SESSION['institution_id'] : 0; ?>,
                roles: {
                    admin: <?php echo (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) ? 'true' : 'false'; ?>,
                    hospitalAdmin: <?php echo (isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin']) ? 'true' : 'false'; ?>,
                    courseCreator: <?php echo (isset($_SESSION['is_coursecreator']) && $_SESSION['is_coursecreator']) ? 'true' : 'false'; ?>
                },
                managementCategoryId: <?php echo isset($_SESSION['management_category_id']) ? (int) $_SESSION['management_category_id'] : 0; ?>
            },
            api: {
                v1: '<?php echo $web_root; ?>/api/hospital_admin/',
                v2: '<?php echo $web_root; ?>/api/v2/'
            }
        };
    </script>
    <script>
        // === Shared helper functions for all hospital admin pages ===
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showToast(message, type = 'info') {
            const colors = { success: '#10b981', error: '#ef4444', info: '#3b82f6', warning: '#f59e0b' };
            const icons = { success: 'check-circle', error: 'times-circle', info: 'info-circle', warning: 'exclamation-triangle' };
            const toast = document.createElement('div');
            toast.style.cssText = `position:fixed;top:80px;right:24px;z-index:10000;padding:14px 24px;border-radius:10px;color:#fff;font-size:14px;font-weight:500;box-shadow:0 4px 20px rgba(0,0,0,0.15);background:${colors[type] || colors.info};transition:opacity 0.3s;display:flex;align-items:center;gap:8px;`;
            toast.innerHTML = `<i class="fas fa-${icons[type] || icons.info}"></i> ${escapeHtml(message)}`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
        }
    </script>
</head>

<body>
    <nav class="nav-v2">
        <div style="display: flex; align-items: center;">
            <a href="<?php echo $web_root; ?>/index.php" class="nav-v2__brand">
                <img src="<?php echo $web_root; ?>/logo/small_logo.svg" alt="雲嘉e學院" style="height: 48px;">
            </a>
            <div class="nav-v2__menu" id="navMenu">
                <a href="<?php echo $web_root; ?>/index.php"
                    class="nav-v2__link <?php echo $current_page === 'home' ? 'nav-v2__link--active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="<?php echo $web_root; ?>/index.php?page=users"
                    class="nav-v2__link <?php echo $current_page === 'users' ? 'nav-v2__link--active' : ''; ?>">
                    <i class="fas fa-users-cog"></i>
                    成員管理
                </a>
                <a href="<?php echo $web_root; ?>/index.php?page=management"
                    class="nav-v2__link <?php echo $current_page === 'management' ? 'nav-v2__link--active' : ''; ?>">
                    <i class="fas fa-th-large"></i>
                    課程管理
                </a>
                <a href="<?php echo $web_root; ?>/index.php?page=cohorts"
                    class="nav-v2__link <?php echo $current_page === 'cohorts' ? 'nav-v2__link--active' : ''; ?>">
                    <i class="fas fa-layer-group"></i>
                    群組管理
                </a>
                <a href="<?php echo $web_root; ?>/index.php?page=tags"
                    class="nav-v2__link <?php echo $current_page === 'tags' ? 'nav-v2__link--active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    標籤管理
                </a>
            </div>
        </div>
        <div class="nav-v2__right">
            <div class="ha-nav-user" id="haUserMenu">
                <div class="ha-nav-user__trigger">
                    <span class="ha-nav-user__name">
                        <?php echo h($_SESSION['fullname']); ?>
                    </span>
                    <div class="nav-v2__avatar">
                        <?php echo mb_substr($_SESSION['fullname'], 0, 1, "utf-8"); ?>
                    </div>
                </div>
                <div class="ha-nav-user__dropdown" id="haUserDropdown">
                    <div class="ha-nav-user__info">
                        <div class="ha-nav-user__info-name">
                            <?php echo h($_SESSION['fullname']); ?>
                        </div>
                        <div class="ha-nav-user__info-role">
                            <?php echo h($institution); ?> 院區管理員
                        </div>
                    </div>
                    <div class="ha-nav-user__divider"></div>
                    <a href="<?php echo $web_root; ?>/change_password.php" class="ha-nav-user__item">
                        <i class="fas fa-key"></i> 修改密碼
                    </a>
                    <a href="<?php echo $web_root; ?>/logout.php" class="ha-nav-user__item ha-nav-user__item--danger">
                        <i class="fas fa-sign-out-alt"></i> 登出系統
                    </a>
                </div>
            </div>
            <button class="nav-v2__toggle" id="navToggle" aria-label="選單">
                <span class="nav-v2__toggle-bar"></span>
                <span class="nav-v2__toggle-bar"></span>
                <span class="nav-v2__toggle-bar"></span>
            </button>
        </div>
    </nav>

    <script>
        // Hamburger toggle
        (function () {
            const toggle = document.getElementById('navToggle');
            const menu = document.getElementById('navMenu');
            if (toggle && menu) {
                toggle.addEventListener('click', () => {
                    const isOpen = menu.classList.toggle('nav-v2__menu--open');
                    toggle.classList.toggle('nav-v2__toggle--active', isOpen);
                });
            }

            // User dropdown
            const userTrigger = document.querySelector('.ha-nav-user__trigger');
            const userDropdown = document.getElementById('haUserDropdown');
            if (userTrigger && userDropdown) {
                userTrigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userDropdown.classList.toggle('is-open');
                });
                document.addEventListener('click', () => {
                    userDropdown.classList.remove('is-open');
                });
            }
        })();

        // Moodle redirect helper
        function goToMoodle(url) {
            window.open(url, '_blank');
        }
    </script>

    <main class="layout-main">
        <div class="container">