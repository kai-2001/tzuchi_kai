<?php
/**
 * Shared Navbar Component
 * 
 * Logic:
 * - Default Mode: Shows Search Bar (for Home)
 * - Simple/Admin Mode: Shows Breadcrumbs
 * 
 * Expected Variables in Scope:
 * - $navbar_mode (optional): 'simple' or undefined
 * - $page_title (optional): For breadcrumbs
 * - $custom_breadcrumbs (optional): Array of ['label' => '...', 'url' => '...']
 * - $nav_actions (optional): Array of ['label' => '...', 'url' => '...', 'icon' => '...']
 */
$navbar_mode = $navbar_mode ?? 'default';
?>
<?php
// Determine header class:
// - 'simple' mode (admin pages with breadcrumbs): use 'static-header'
// - Home page without hero (campus filtered): use 'static-header home-filtered' 
// - Home page with hero: no special class
$header_class = '';
if ($navbar_mode == 'simple') {
    $header_class = 'static-header admin-mode';
} elseif (isset($show_hero) && !$show_hero) {
    $header_class = 'static-header home-filtered';
}
?>
<header class="<?= $header_class ?>">
    <div class="header-container">

        <!-- Left Section: Logo & Breadcrumbs -->
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text">學術演講影片平台</h1>
            </a>

            <?php if ($navbar_mode == 'simple'): ?>
                <!-- Breadcrumbs Logic -->
                <?php
                $crumbs = $custom_breadcrumbs ?? [];
                if (empty($crumbs)) {
                    // Default fallback logic
                    if ($page_title != '影片管理' && $page_title != '公告管理') {
                        $crumbs[] = ['label' => '影片管理', 'url' => 'manage_videos.php'];
                    }
                }
                ?>

                <?php foreach ($crumbs as $crumb): ?>
                    <span class="breadcrumb-separator">/</span>
                    <a href="<?= $crumb['url'] ?>" class="breadcrumb-link"><?= htmlspecialchars($crumb['label']) ?></a>
                <?php endforeach; ?>

                <?php if (isset($page_title)): ?>
                    <span class="breadcrumb-separator">/</span>
                    <h2 class="page-title" style="margin: 0;"><?= htmlspecialchars($page_title) ?></h2>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Center Section: Search Bar (Only in Default Mode) -->
        <?php if ($navbar_mode != 'simple'): ?>
            <div class="search-box">
                <form action="index.php" method="GET">
                    <input type="text" name="q" placeholder="搜尋標題、講者或單位..." value="<?= htmlspecialchars($search ?? '') ?>">
                    <button type="submit" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <?php if (isset($campus_id) && $campus_id > 0): ?>
                        <input type="hidden" name="campus" value="<?= $campus_id ?>">
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

        <!-- Right Section: User Nav -->
        <div class="user-nav">
            <?php if (isset($nav_actions) && !empty($nav_actions)): ?>
                <?php foreach ($nav_actions as $action): ?>
                    <a href="<?= $action['url'] ?>" class="btn-admin" title="<?= htmlspecialchars($action['label']) ?>">
                        <i class="<?= $action['icon'] ?>"></i>
                        <span>
                            <?= htmlspecialchars($action['label']) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            <?php elseif ($navbar_mode == 'simple'): ?>
                <!-- Default Back Button for Simple Mode -->
                <a href="manage_videos.php" class="btn-admin" title="回影片列表">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>回影片列表</span>
                </a>
            <?php else: ?>
                <!-- Default Mode: User Menu -->
                <?php if (!is_manager() && !is_campus_admin()): ?>
                    <a href="announcements.php" class="btn-admin"><i class="fa-solid fa-bullhorn"></i> <span>公告</span></a>
                <?php endif; ?>

                <?php if (is_logged_in()): ?>
                    <?php if (is_manager() || is_campus_admin()): ?>
                        <a href="manage_videos.php" class="btn-admin"><i class="fa-solid fa-video"></i> <span>影片</span></a>
                        <a href="manage_announcements.php" class="btn-admin"><i class="fa-solid fa-bullhorn"></i>
                            <span>公告</span></a>
                    <?php endif; ?>

                    <div class="user-dropdown">
                        <div class="user-info">
                            <i class="fa-solid fa-circle-user"></i>
                            <span>
                                <?= htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']) ?>
                            </span>
                        </div>
                        <div class="dropdown-content">
                            <a href="logout.php" class="dropdown-item text-danger">
                                <i class="fa-solid fa-right-from-bracket"></i> 登出
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn-admin"><i class="fa-solid fa-user-lock"></i> <span>登入</span></a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</header>