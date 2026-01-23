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

<header class="static-header">
    <div class="header-container">
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text" style="color: var(--primary-dark);">學術演講影片平台</h1>
            </a>
            <span class="breadcrumb-separator" style="color: #ccc;">/</span>
            <h2 class="page-title"
                style="color: var(--text-primary); font-size: 1.2rem; font-weight: 500; margin: 0; max-width: 600px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= htmlspecialchars($video['title']) ?>
            </h2>
        </div>
        <div class="user-nav">
            <a href="javascript:history.back()" class="btn-admin">
                <i class="fa-solid fa-rotate-left"></i> <span>返回上一頁</span>
            </a>
        </div>
    </div>
</header>

<div class="watch-container" style="padding-top: 120px;">
    <?php /* [BACKUP] Resume Prompt Logic
<?php if ($last_position > 0): ?>
<?php
$m = floor($last_position / 60);
$s = $last_position % 60;
$time_fmt = sprintf('%02d:%02d', $m, $s);
?>
<div id="resumePrompt" class="resume-prompt-overlay" style="display: none;">
  <div class="prompt-content">
      <i class="fa-solid fa-clock-rotate-left"></i>
      <span>上次觀看到 <strong><?= $time_fmt ?></strong></span>
      <button id="btnResume" class="btn-resume-action">繼續觀看</button>
      <button id="btnSkipResume" class="btn-resume-close"><i class="fa-solid fa-xmark"></i></button>
  </div>
</div>
<?php endif; ?>
*/ ?>

    <?php if (isset($video['status']) && $video['status'] === 'ready'): ?>
        <div class="video-player-section">
            <div
                class="player-layout <?= ($video['format'] === 'evercam' && $video['metadata']) ? 'dual-view' : 'single-view' ?>">
                <div class="main-player">
                    <div class="player-wrapper">
                        <video id="mainVideo" controls src="<?= htmlspecialchars($video['content_path']) ?>"
                            controlsList="nodownload" autoplay></video>

                    </div>
                </div>

                <?php if ($video['format'] === 'evercam' && $video['metadata']): ?>
                    <?php $chapters = json_decode($video['metadata'], true); ?>
                    <div class="chapters-sidebar">
                        <div class="sidebar-header" onclick="toggleChapters()"
                            style="cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                            <span><i class="fa-solid fa-list-ul"></i> 演講章節</span>
                            <i class="fa-solid fa-chevron-down" id="chapterChevron"></i>
                        </div>
                        <div class="chapter-list" id="chapterList">
                            <?php foreach ($chapters as $index => $chapter): ?>
                                <?php
                                $time_sec = (int) ($chapter['time'] / 1000);
                                $mins = floor($time_sec / 60);
                                $secs = $time_sec % 60;
                                $time_str = sprintf('%02d:%02d', $mins, $secs);
                                ?>
                                <div class="chapter-item <?= (isset($chapter['indent']) && $chapter['indent'] == '1') ? 'indent' : '' ?>"
                                    data-time="<?= $time_sec ?>">
                                    <span class="chap-num"><?= $index + 1 ?>.</span>
                                    <span class="chap-title"><?= htmlspecialchars($chapter['title']) ?></span>
                                    <span class="chap-time"><?= $time_str ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <script>
                        function toggleChapters() {
                            const list = document.getElementById('chapterList');
                            const chevron = document.getElementById('chapterChevron');

                            // Check computed style because it might be hidden by CSS (mobile)
                            const currentDisplay = window.getComputedStyle(list).display;

                            if (currentDisplay === 'none') {
                                list.style.display = 'block';
                                chevron.style.transform = 'rotate(180deg)'; // Point up to indicate "can collapse"
                            } else {
                                list.style.display = 'none';
                                chevron.style.transform = 'rotate(0deg)'; // Point down to indicate "can expand"
                            }
                        }
                    </script>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="container"
            style="text-align: center; padding: 80px 20px; background: rgba(255,255,255,0.6); border-radius: 16px; margin: 20px auto; max-width: 800px; backdrop-filter: blur(5px);">
            <div style="margin-bottom: 30px;">
                <i class="fa-solid fa-gear fa-spin"
                    style="font-size: 4rem; color: var(--primary); opacity: 0.8; --fa-animation-duration: 3s;"></i>
            </div>
            <h3 style="color: var(--primary-dark); font-weight: 700; margin-bottom: 15px;">影片正在處理中</h3>
            <p style="color: #666; font-size: 1.1rem; line-height: 1.6; max-width: 600px; margin: 0 auto 30px;">
                為了提供最佳的觀看體驗與節省頻寬，系統正在對影片進行轉檔與壓縮優化。<br>
                請稍後再回來觀看。
            </p>
            <div
                style="display: inline-block; padding: 10px 20px; background: #f0f0f0; border-radius: 50px; color: #888; font-size: 0.9rem;">
                目前狀態：<span class="badge bg-secondary"><?= htmlspecialchars($video['status'] ?? 'processing') ?></span>
                <?php if (!empty($video['process_msg'])): ?>
                    <span style="border-left: 1px solid #ccc; margin-left: 10px; padding-left: 10px;">
                        <?= htmlspecialchars(mb_strimwidth($video['process_msg'], 0, 50, "...")) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div style="margin-top: 40px;">
                <a href="index.php" class="btn-admin btn-primary-gradient"
                    style="text-decoration: none; padding: 10px 30px; border-radius: 50px;">
                    <i class="fa-solid fa-arrow-left me-2"></i> 返回影片列表
                </a>
            </div>
        </div>
    <?php endif; ?>

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
                <span><strong>內容格式：</strong><?= $video['format'] === 'evercam' ? 'EverCam 互動課程' : 'MP4 影片' ?></span>
            </li>
        </ul>

        <?php if ($video['format'] === 'evercam'): ?>
            <!-- Config button removed -->
        <?php endif; ?>
    </div>
</div>

<script src="assets/js/player.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        initPlayer(<?= (int)$video['id'] ?>, <?= (int)$last_position ?>);
    });
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>