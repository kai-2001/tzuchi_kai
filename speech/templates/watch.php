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
                    <div class="sidebar-header" onclick="toggleChapters()" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
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
            <div class="watch-header-actions">
                <a href="<?= htmlspecialchars(dirname($video['content_path'])) ?>/config.js" target="_blank"
                    class="btn-open-popup">
                    <i class="fa-solid fa-code"></i> 查看設定檔
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const video = document.getElementById('mainVideo');
        const chapters = document.querySelectorAll('.chapter-item');
        const videoId = <?= $video['id'] ?>;
        let lastSavedPosition = <?= (int) $last_position ?>;
        let initialSeekDone = false;

        // 1. Resume playback position prompt
        const resumePrompt = document.getElementById('resumePrompt');
        const btnResume = document.getElementById('btnResume');
        const btnSkipResume = document.getElementById('btnSkipResume');
        let promptHideTimer = null;

        video.addEventListener('loadedmetadata', function () {
            // Show prompt if we have a position deeper than 1s
            if (lastSavedPosition > 1 && !initialSeekDone) {
                resumePrompt.style.display = 'flex';
                setTimeout(() => resumePrompt.style.opacity = '1', 50);
            }
            initialSeekDone = true;
        });

        // Start auto-hide timer ONLY when video starts playing
        video.addEventListener('play', function () {
            if (resumePrompt && resumePrompt.style.display === 'flex' && !promptHideTimer) {
                promptHideTimer = setTimeout(() => {
                    resumePrompt.style.opacity = '0';
                    setTimeout(() => {
                        resumePrompt.style.display = 'none';
                        promptHideTimer = null;
                    }, 500);
                }, 8000); // 8 seconds after play starts
            }
        });

        if (btnResume) {
            btnResume.addEventListener('click', function () {
                video.currentTime = lastSavedPosition;
                resumePrompt.style.display = 'none';
                if (promptHideTimer) clearTimeout(promptHideTimer);
                video.play();
            });
        }

        if (btnSkipResume) {
            btnSkipResume.addEventListener('click', function () {
                resumePrompt.style.display = 'none';
                if (promptHideTimer) clearTimeout(promptHideTimer);
            });
        }

        // 2. Progress Tracking (Throttled)
        let lastUpdateTime = Date.now();
        video.addEventListener('timeupdate', function () {
            if (!initialSeekDone) return;

            const now = Date.now();
            const currentPos = Math.floor(video.currentTime);

            // Save progress every 5 seconds for better responsiveness
            if (now - lastUpdateTime > 5000 && currentPos !== lastSavedPosition) {
                saveProgress(currentPos);
                lastUpdateTime = now;
                lastSavedPosition = currentPos;
            }

            // Highlight current chapter
            highlightChapter(currentPos);
        });

        function saveProgress(pos) {
            const formData = new FormData();
            formData.append('video_id', videoId);
            formData.append('position', pos);

            fetch('update_progress.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) console.error('Progress save error:', data.error);
                })
                .catch(err => console.error('Progress sync error:', err));
        }

        // 3. Chapter Navigation
        chapters.forEach(item => {
            item.addEventListener('click', function () {
                const time = parseInt(this.dataset.time);
                video.currentTime = time;
                video.play();
            });
        });

        function highlightChapter(currentTime) {
            let activeIndex = -1;
            chapters.forEach((item, index) => {
                const startTime = parseInt(item.dataset.time);
                const nextItem = chapters[index + 1];
                const endTime = nextItem ? parseInt(nextItem.dataset.time) : Infinity;

                if (currentTime >= startTime && currentTime < endTime) {
                    activeIndex = index;
                }
            });

            chapters.forEach((item, index) => {
                if (index === activeIndex) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        }
    });
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>