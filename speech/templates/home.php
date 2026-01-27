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
}
// Removed fallback logic ensuring if no hero slides are explicitly set, nothing is shown.
$show_hero = (!isset($_GET['campus']) && empty($search) && !empty($display_slides));
?>

<?php include __DIR__ . '/partials/navbar.php'; ?>



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
                                        <?php if (!empty($slide['speaker_name'])): ?>
                                            <span class="speaker"><?= htmlspecialchars($slide['speaker_name']) ?></span>
                                        <?php endif; ?>
                                        <?php
                                        $info_parts = [];
                                        if (!empty($slide['affiliation']))
                                            $info_parts[] = htmlspecialchars($slide['affiliation']);
                                        if (!empty($slide['position']))
                                            $info_parts[] = htmlspecialchars($slide['position']);
                                        if (!empty($info_parts)): ?>
                                            <span class="affiliation">|
                                                <?= implode(' - ', $info_parts) ?></span>
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

                                    <?php if (is_manager() || is_campus_admin()): ?>
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

<div class="home-content-layout" style="<?= !$show_hero ? 'margin-top: 100px;' : '' ?>">
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
        <?php // Hero minimal removed as requested ?>

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
            <!-- Header removed as requested -->
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