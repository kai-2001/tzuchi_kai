<!-- templates/header.php - 頁首區塊 -->
<?php
// 設定 cookie 傳遞 admin 狀態給 Moodle JavaScript
$is_admin = isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : false;
$is_hospital_admin = isset($_SESSION['is_hospital_admin']) ? $_SESSION['is_hospital_admin'] : false;
setcookie('portal_is_admin', $is_admin ? '1' : '0', 0, '/');
setcookie('portal_is_hospital_admin', $is_hospital_admin ? '1' : '0', 0, '/');
setcookie('portal_is_coursecreator', (isset($_SESSION['is_coursecreator']) && $_SESSION['is_coursecreator']) ? '1' : '0', 0, '/');
if (isset($_SESSION['management_category_id'])) {
    setcookie('portal_manage_cat_id', $_SESSION['management_category_id'], 0, '/');
}
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
    <link rel="stylesheet" href="<?php echo $web_root; ?>/assets/css/design-system.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $web_root; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $web_root; ?>/assets/css/ui-modules.css?v=<?php echo time(); ?>">

    <?php if (!$is_admin && !$is_hospital_admin && !(isset($_SESSION['is_coursecreator']) && $_SESSION['is_coursecreator'])): ?>
        <link rel="stylesheet" href="<?php echo $web_root; ?>/assets/css/student-styles.css?v=<?php echo time(); ?>">
    <?php endif; ?>

    <!-- Frontend Configuration (Injected by PHP) -->
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
                    admin: <?php echo $is_admin ? 'true' : 'false'; ?>,
                    hospitalAdmin: <?php echo $is_hospital_admin ? 'true' : 'false'; ?>,
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
</head>

<body>
    <?php if (isset($_SESSION['username'])): ?>

        <nav id="portal-global-nav">
            <div style="display:flex; align-items:center;">
                <a onclick="showHome()" class="pg-brand">
                    <img src="logo/small_logo.svg" alt="雲嘉學習網" class="nav-logo" style="height: 50px; margin-top: -8px;">
                </a>

                <div class="pg-menu">
                    <?php if ($is_hospital_admin): ?>
                        <!-- 院區管理員專屬連結 -->
                        <a onclick="showTab('users-management')" class="pg-link">
                            <i class="fas fa-users-cog"></i> 成員管理
                        </a>
                        <a onclick="showTab('category-management')" class="pg-link">
                            <i class="fas fa-folder-tree"></i> 類別管理
                        </a>
                        <a onclick="showTab('course-management')" class="pg-link">
                            <i class="fas fa-chalkboard"></i> 課程管理
                        </a>
                        <a onclick="showTab('enrollment-management')" class="pg-link">
                            <i class="fas fa-user-plus"></i> 招生管理
                        </a>
                        <a onclick="showTab('cohort-management')" class="pg-link">
                            <i class="fas fa-layer-group"></i> 群組管理
                        </a>
                        <a onclick="showTab('tag-management')" class="pg-link">
                            <i class="fas fa-tags"></i> 標籤管理
                        </a>
                        <a href="#" onclick="goToMoodle('<?php echo $moodle_url; ?>/report/log/index.php')" class="pg-link">
                            <i class="fas fa-chart-line"></i> 報表
                        </a>
                    <?php elseif ($is_admin): ?>
                        <!-- 系統管理員連結 -->
                        <a onclick="showTab('admin-categories')" class="pg-link">
                            <i class="fas fa-folder-tree"></i> 類別管理
                        </a>
                        <a onclick="showTab('dimensions')" class="pg-link">
                            <i class="fas fa-layer-group"></i> 維度管理
                        </a>
                        <a href="<?php echo $web_root; ?>/manage_template_tags.php" class="pg-link">
                            <i class="fas fa-bookmark"></i> 模板標籤
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
                        $is_coursecreator = isset($_SESSION['is_coursecreator']) ? $_SESSION['is_coursecreator'] : false;
                        $mgmt_cat_id = isset($_SESSION['management_category_id']) ? $_SESSION['management_category_id'] : 0;
                        if ($is_coursecreator):
                            $add_course_url = $moodle_url . '/course/edit.php';
                            if ($mgmt_cat_id > 0) {
                                $add_course_url .= '?category=' . $mgmt_cat_id;
                            }
                            ?>
                            <!-- 開課教師導覽列 -->
                            <a onclick="showHome()" class="pg-link">
                                <i class="fas fa-home"></i> 個人主頁
                            </a>
                            <a href="#" onclick="goToMoodle('<?php echo $add_course_url; ?>')" class="pg-link">
                                <i class="fas fa-plus-circle"></i> 新增課程
                            </a>
                            <a onclick="showTab('course-management')" class="pg-link">
                                <i class="fas fa-tasks"></i> 課程管理
                            </a>
                        <?php else: ?>
                            <?php $current_page = $_GET['page'] ?? 'student_dashboard'; ?>
                            <a href="index.php?page=student_dashboard"
                                class="pg-link <?php echo $current_page === 'student_dashboard' ? 'active' : ''; ?>">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                            <a href="index.php?page=student_courses"
                                class="pg-link <?php echo $current_page === 'student_courses' ? 'active' : ''; ?>">
                                <i class="fas fa-book"></i> 我的課程
                            </a>
                            <a href="index.php?page=student_degree_audit"
                                class="pg-link <?php echo $current_page === 'student_degree_audit' ? 'active' : ''; ?>">
                                <i class="fas fa-chart-line"></i> 修課進度
                            </a>
                            <a href="index.php?page=student_course_catalog"
                                class="pg-link <?php echo $current_page === 'student_course_catalog' ? 'active' : ''; ?>">
                                <i class="fas fa-search"></i> 選課中心
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