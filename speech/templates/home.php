<?php
/**
 * Home Page Template
 * 
 * Variables available from controller:
 * - $videos (array) - List of videos
 * - $campuses (array) - List of campuses
 * - $campus_id (int) - Current campus filter
 * - $search (string) - Current search term
 * - $page (int) - Current page
 * - $total_pages (int) - Total pages
 * - $total_items (int) - Total items
 */
include __DIR__ . '/partials/header.php';

// Logic: Show hero only if no search and no specific campus selected (Initial Load Only)
$display_slides = [];
if (!empty($hero_slides)) {
    $display_slides = $hero_slides;
} elseif ($campus_id == 0 && empty($search) && !empty($upcoming_grouped)) {
    foreach ($upcoming_grouped as $month => $campuses_list) {
        foreach ($campuses_list as $campus_name => $items) {
            foreach ($items as $item) {
                $display_slides[] = [
                    'title' => $item['title'],
                    'speaker_name' => $item['speaker_name'],
                    'event_date' => $item['event_date'],
                    'campus_name' => $campus_name,
                    'link_url' => '#'
                ];
                if (count($display_slides) >= 5)
                    break 3;
            }
        }
    }
}
$show_hero = (!isset($_GET['campus']) && empty($search) && !empty($display_slides));
?>

<header class="<?= !$show_hero ? 'static-header' : '' ?>">
    <div class="header-container">
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text">學術演講影片平台</h1>
            </a>
        </div>

        <div class="search-box">
            <form action="index.php" method="GET">
                <input type="text" name="q" placeholder="搜尋標題、講者或單位..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
                <?php if ($campus_id > 0): ?><input type="hidden" name="campus"
                        value="<?= $campus_id ?>"><?php endif; ?>
            </form>
        </div>

        <div class="user-nav">
            <?php if (!is_manager()): ?>
                <a href="announcements.php" class="btn-admin"><i class="fa-solid fa-bullhorn"></i> <span>公告</span></a>
            <?php endif; ?>
            <?php if (is_logged_in()): ?>
                <?php if (is_manager()): ?>
                    <a href="manage_videos.php" class="btn-admin"><i class="fa-solid fa-list-check"></i> <span>影片</span></a>
                    <a href="manage_announcements.php" class="btn-admin"><i class="fa-solid fa-bullhorn"></i>
                        <span>公告</span></a>
                    <a href="upload.php" class="btn-admin"><i class="fa-solid fa-cloud-arrow-up"></i> <span>上傳</span></a>
                <?php endif; ?>

                <div class="user-dropdown">
                    <div class="user-info">
                        <i class="fa-solid fa-circle-user"></i>
                        <span><?= htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']) ?></span>
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
        </div>
    </div>
</header>



<?php if ($show_hero): ?>
    <div class="swiper hero-swiper">
        <div class="swiper-wrapper">
            <?php foreach ($display_slides as $slide): ?>
                <div class="swiper-slide">
                    <div class="hero-full-slide"
                        style="<?= !empty($slide['image_url']) ? "background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.7)), url('" . $slide['image_url'] . "');" : "" ?> background-size: cover; background-position: center;">
                        <?php if (empty($slide['image_url'])): ?>
                            <div class="hero-slide-bg"></div>
                        <?php endif; ?>
                        <div class="hero-slide-container">
                            <div class="hero-slide-content">

                                <div class="hero-meta-top">
                                    <div class="hero-pill">
                                        <?= htmlspecialchars($slide['campus_name'] ?? '全部') ?>
                                    </div>
                                    <?php if (!empty($slide['event_date'])): ?>
                                        <div class="hero-date-pill">
                                            <i class="fa-regular fa-calendar"></i>
                                            <?= htmlspecialchars($slide['event_date']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <h2 class="hero-main-title">
                                    <?= htmlspecialchars($slide['title']) ?>
                                </h2>

                                <?php if (!empty($slide['speaker_name'])): ?>
                                    <div class="hero-speaker-row">
                                        <i class="fa-solid fa-user-tie"></i>
                                        <span><?= htmlspecialchars($slide['speaker_name']) ?></span>
                                        <?php if (!empty($slide['affiliation'])): ?>
                                            <span class="affiliation">|
                                                <?= htmlspecialchars($slide['affiliation']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="hero-spacer"></div>
                                <?php endif; ?>

                                <div class="hero-btns">
                                    <a href="announcements.php" class="btn-hero-primary"
                                        style="background: rgba(255,255,255,0.9);">
                                        查看公告 <i class="fa-solid fa-file-lines"></i>
                                    </a>

                                    <?php if (is_manager()): ?>
                                        <a href="manage_announcements.php" class="btn-hero-glass">
                                            管理公告
                                        </a>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($display_slides) > 1): ?>
            <div class="swiper-pagination"></div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="home-content-layout" style="<?= !$show_hero ? 'margin-top: 120px;' : '' ?>">
    <div class="main-column">
        <!-- Minimal Hero (Fallback when no slides) - Adjust logic to hide if searching/filtering too? -->
        <?php if (!$show_hero): ?>
            <!-- If hero is hidden due to filter, we don't need fallback text usually. The videos are the focus. -->
            <!-- But if we are in 'No Slides' mode on homepage, default existing logic applies? -->
            <!-- Original logic: if (empty($display_slides) && $campus_id == 0 && empty($search)) -->
            <!-- My new Logic $show_hero covers (campus==0 && search empty). -->
            <!-- So if !$show_hero, we are searching/filtering OR empty slides. -->
            <!-- But if we are filtering, we probably don't want "Minimal Hero". -->
        <?php else: ?>
            <!-- Hero shown. -->
        <?php endif; ?>
        <?php if (empty($display_slides) && $campus_id == 0 && empty($search)): ?>
            <div class="hero-minimal">
                <div class="hero-minimal-content">
                    <h1>精彩演講，盡在眼底</h1>
                    <p>探索來自各院區的最佳學術分享與演講紀錄</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Campus Tabs -->
        <nav class="tabs" style="background: transparent; border: none; margin-bottom: 25px; padding: 0;">
            <a href="index.php?campus=0&q=<?= urlencode($search) ?>"
                class="tab <?= $campus_id == 0 ? 'active' : '' ?>">全部專區</a>
            <?php foreach ($campuses as $c): ?>
                <a href="index.php?campus=<?= $c['id'] ?>&q=<?= urlencode($search) ?>"
                    class="tab <?= $campus_id == $c['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($c['name']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Video Grid -->
        <section class="horizontal-section">
            <div class="section-head">
                <h2><i class="fa-solid fa-clapperboard"></i> <?= $search ? '搜尋結果' : '精選演講影片' ?></h2>
            </div>
            <main class="video-grid">
                <?php if (empty($videos)): ?>
                    <div
                        style="grid-column: 1/-1; text-align: center; padding: 100px 50px; background: white; border-radius: 24px; border: 1px dashed #ccc;">
                        <i class="fa-solid fa-magnifying-glass"
                            style="font-size: 3rem; color: #ddd; margin-bottom: 20px; display: block;"></i>
                        <p style="font-size: 1.2rem; color: #666;">沒有找到符合的影片</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($videos as $v): ?>
                        <div class="video-card" onclick="checkAuth(<?= $v['id'] ?>)">
                            <div class="thumbnail"
                                style="background-image: url('<?= htmlspecialchars($v['thumbnail_path'] ?: 'assets/images/placeholder.jpg') ?>'); background-size: cover; background-position: center;">
                            </div>
                            <div class="video-card-body">
                                <div class="speaker-avatar">
                                    <?= mb_substr($v['speaker_name'], 0, 1) ?>
                                </div>
                                <div class="video-info">
                                    <div class="video-title" title="<?= htmlspecialchars($v['title']) ?>">
                                        <?= htmlspecialchars($v['title']) ?>
                                    </div>
                                    <div class="video-meta-yt">
                                        <div class="speaker-name"><?= htmlspecialchars($v['speaker_name']) ?></div>
                                        <div class="stats">
                                            <span><?= number_format($v['views']) ?> 次觀看</span>
                                            <span class="dot">•</span>
                                            <span><?= htmlspecialchars($v['event_date']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>

            <?php if ($total_items > 0): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>&campus=<?= $campus_id ?>&q=<?= urlencode($search) ?>"
                            class="page-link <?= $page == $i ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>