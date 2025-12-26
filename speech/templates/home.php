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
?>

<header>
    <div class="header-top">
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text">學術演講影片平台</h1>
            </a>
        </div>
        <div class="user-nav">
            <?php if (is_logged_in()): ?>
                <?php if (is_manager()): ?>
                    <a href="manage_videos.php" class="btn-admin"><i class="fa-solid fa-list-check"></i> 影片管理</a>
                    <a href="upload.php" class="btn-admin"><i class="fa-solid fa-cloud-arrow-up"></i> 上傳專區</a>
                <?php endif; ?>

                <div class="user-dropdown">
                    <div class="user-info" style="cursor: pointer; margin-right: 0;">
                        <i class="fa-solid fa-circle-user"></i>
                        <span><?= htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']) ?></span>
                        <i class="fa-solid fa-chevron-down" style="font-size: 0.7rem; margin-left: 5px; opacity:0.5;"></i>
                    </div>
                    <div class="dropdown-content">
                        <a href="logout.php" class="dropdown-item text-danger">
                            <i class="fa-solid fa-right-from-bracket"></i> 登出系統
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn-admin"><i class="fa-solid fa-user-lock"></i> 會員登入</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="search-box">
        <form action="index.php" method="GET">
            <input type="text" name="q" placeholder="搜尋標題、講者或單位..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
            <?php if ($campus_id > 0): ?><input type="hidden" name="campus" value="<?= $campus_id ?>"><?php endif; ?>
        </form>
    </div>
</header>

<nav class="tabs">
    <a href="index.php?q=<?= urlencode($search) ?>" class="tab <?= $campus_id == 0 ? 'active' : '' ?>">ALL</a>
    <?php foreach ($campuses as $c): ?>
        <a href="index.php?campus=<?= $c['id'] ?>&q=<?= urlencode($search) ?>"
            class="tab <?= $campus_id == $c['id'] ? 'active' : '' ?>">
            <?= htmlspecialchars($c['name']) ?>
        </a>
    <?php endforeach; ?>
</nav>

<main class="video-grid">
    <?php if (empty($videos)): ?>
        <div
            style="grid-column: 1/-1; text-align: center; padding: 50px; background: var(--glass-bg); border-radius: 20px;">
            <i class="fa-solid fa-magnifying-glass"
                style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 20px; display: block;"></i>
            <p style="font-size: 1.2rem; margin-bottom: 10px;">沒有找到符合的影片。</p>
            <p style="color: var(--text-secondary);">建議您調整搜尋關鍵字，或 <a href="index.php"
                    style="color: var(--primary-color); text-decoration: none;">瀏覽全部影片</a>。</p>
        </div>
    <?php else: ?>
        <?php foreach ($videos as $v): ?>
            <div class="video-card" onclick="checkAuth(<?= $v['id'] ?>)">
                <div class="thumbnail"
                    style="background-image: url('<?= htmlspecialchars($v['thumbnail_path'] ?: 'assets/images/placeholder.jpg') ?>')">
                </div>
                <div class="video-info">
                    <div class="video-title"><?= htmlspecialchars($v['title']) ?></div>
                    <div class="meta">
                        <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($v['speaker_name']) ?></span>
                        <span><i class="fa-solid fa-building"></i> <?= htmlspecialchars($v['affiliation']) ?></span>
                        <span><i class="fa-solid fa-calendar"></i> <?= htmlspecialchars($v['event_date']) ?></span>
                        <span><i class="fa-solid fa-eye"></i> <?= number_format($v['views']) ?></span>
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

<?php include __DIR__ . '/partials/footer.php'; ?>