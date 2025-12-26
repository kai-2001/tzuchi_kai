<?php
/**
 * Watch Page Template
 * 
 * Variables available from controller:
 * - $video (array) - Video details
 * - $is_html (bool) - Is HTML content
 */
include __DIR__ . '/partials/header.php';
?>

<header>
    <div class="header-top">
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text">學術演講影片平台</h1>
            </a>
            <span class="breadcrumb-separator">/</span>
            <h2 class="page-title"><?= htmlspecialchars($video['title']) ?></h2>
        </div>
        <div class="user-nav">
            <a href="javascript:history.back()" class="btn-logout">
                <i class="fa-solid fa-rotate-left"></i> 返回上一頁
            </a>
        </div>
    </div>
</header>

<div class="watch-container">
    <div class="video-player-container">
        <div class="player-wrapper">
            <?php if ($is_html): ?>
                <iframe src="<?= htmlspecialchars($video['content_path']) ?>"
                    allow="fullscreen; autoplay; encrypted-media; picture-in-picture" allowfullscreen
                    sandbox="allow-forms allow-scripts allow-same-origin" style="background: white;"></iframe>
            <?php else: ?>
                <video controls src="<?= htmlspecialchars($video['content_path']) ?>" controlsList="nodownload"
                    autoplay></video>
            <?php endif; ?>
        </div>
    </div>

    <div class="video-details-section">
        <div class="campus-badge">
            <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($video['campus_name']) ?>
        </div>
        <h2 class="video-title-main"><?= htmlspecialchars($video['title']) ?></h2>

        <ul class="meta-info-list">
            <li>
                <i class="fa-solid fa-user"></i>
                <span><strong>主講人：</strong><?= htmlspecialchars($video['speaker_name']) ?>
                    <small>(<?= htmlspecialchars($video['affiliation']) ?> -
                        <?= htmlspecialchars($video['position']) ?>)</small></span>
            </li>
            <li>
                <i class="fa-solid fa-calendar"></i>
                <span><strong>演講日期：</strong><?= htmlspecialchars($video['event_date']) ?></span>
            </li>
            <li>
                <i class="fa-solid fa-eye"></i>
                <span><strong>觀看次數：</strong><?= number_format($video['views']) ?> 次</span>
            </li>
            <li>
                <i class="fa-solid fa-file-video"></i>
                <span><strong>內容格式：</strong><?= $is_html ? '互動式 HTML 資料集' : 'MP4 串流影片' ?></span>
            </li>
        </ul>

        <?php if ($is_html): ?>
            <div class="watch-header-actions">
                <a href="<?= htmlspecialchars($video['content_path']) ?>" target="_blank" class="btn-open-popup">
                    <i class="fa-solid fa-up-right-from-square"></i> 在新視窗中開啟
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>