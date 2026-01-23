<?php
/**
 * Shared Navbar Component
 * 
 * Logic:
 * - Default Mode: Shows Search Bar (for Home)
 * - Simple Mode ($navbar_mode == 'simple'): Shows Breadcrumbs (for Upload/Manage pages)
 * 
 * Expected Variables in Scope:
 * - $navbar_mode (optional): 'simple' or undefined
 * - $page_title (optional): For breadcrumbs
 * - $search: Current search query (for Home mode)
 * - $campus_id: Current campus ID (for Home mode)
 */
$navbar_mode = $navbar_mode ?? 'default';
?>
<header
    class="<?= (isset($show_hero) && !$show_hero) ? 'static-header' : ($navbar_mode == 'simple' ? 'static-header' : '') ?>">
    <div class="header-container">

        <!-- Left Section: Logo & Breadcrumbs -->
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text" style="<?= $navbar_mode == 'simple' ? 'color: var(--primary-dark);' : '' ?>">
                    學術演講影片平台</h1>
            </a>

            <?php if ($navbar_mode == 'simple'): ?>
                <!-- Simple Mode: Context Breadcrumbs -->
                <span class="breadcrumb-separator" style="color: #ccc;">/</span>
                <a href="manage_videos.php"
                    style="text-decoration:none; color: var(--text-primary); font-size: 1.2rem; font-weight: 500;">
                    影片管理
                </a>
                <?php if (isset($page_title) && $page_title != '影片管理'): ?>
                    <span class="breadcrumb-separator" style="color: #ccc;">/</span>
                    <h2 class="page-title" style="color: var(--text-primary); font-size: 1.2rem; font-weight: 500; margin: 0;">
                        <?= htmlspecialchars($page_title) ?>
                    </h2>
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

        <!-- Right Section: User Nav (Common to All) -->
        <div class="user-nav">
            <?php if ($navbar_mode == 'simple'): ?>
                <!-- Simple Mode: Back Button -->
                <a href="manage_videos.php" class="btn-admin"><i class="fa-solid fa-arrow-left"></i> 回影片列表</a>
            <?php else: ?>
                <!-- Default Mode: Full User Menu -->
                <?php if (!is_manager() && !is_campus_admin()): ?>
                    <a href="announcements.php" class="btn-admin"><i class="fa-solid fa-bullhorn"></i> <span>公告</span></a>
                <?php endif; ?>

                <?php if (is_logged_in()): ?>
                    <?php if (is_manager() || is_campus_admin()): ?>
                        <a href="manage_videos.php" class="btn-admin"><i class="fa-solid fa-list-check"></i> <span>影片</span></a>
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