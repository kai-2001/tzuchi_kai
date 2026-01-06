<?php
/**
 * Upload Page Template
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
            <h2 class="page-title" style="color: var(--text-primary); font-size: 1.2rem; font-weight: 500; margin: 0;">
                上傳新演講</h2>
        </div>
        <div class="user-nav">
            <a href="index.php" class="btn-admin"><i class="fa-solid fa-house"></i> <span>返回首頁</span></a>
        </div>
    </div>
</header>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form">
        <?php if ($msg): ?>
            <div style="color: #4ade80; margin-bottom: 20px;"><?= $msg ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="color: #f87171; margin-bottom: 20px;"><?= $error ?></div>
        <?php endif; ?>

        <form action="upload.php" method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>演講標題</label>
                    <input type="text" name="title" required>
                </div>

                <div class="form-group">
                    <label>所屬院區</label>
                    <select name="campus_id" required>
                        <?php foreach ($campuses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>演講日期</label>
                    <input type="date" name="event_date" required>
                </div>

                <div class="form-group">
                    <label>講者姓名</label>
                    <input type="text" name="speaker_name" required>
                </div>

                <div class="form-group">
                    <label>服務單位</label>
                    <input type="text" name="affiliation" required>
                </div>

                <div class="form-group">
                    <label>職務 (如醫師、護理師)</label>
                    <input type="text" name="position" required>
                </div>

                <div class="form-group">
                    <label>上傳縮圖 (JPG/PNG)</label>
                    <input type="file" name="thumbnail" accept="image/*" required>
                </div>

                <div class="form-group full-width">
                    <label>上傳 mp4 或 evercam zip 檔</label>
                    <input type="file" name="video_file" accept=".mp4,.zip" required>
                </div>
            </div>
            <style>
                @keyframes progress-stripe {
                    0% {
                        background-position: 1rem 0;
                    }

                    100% {
                        background-position: 0 0;
                    }
                }

                .progress-bar-animated {
                    background-image: linear-gradient(45deg, rgba(255, 255, 255, .15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, .15) 50%, rgba(255, 255, 255, .15) 75%, transparent 75%, transparent);
                    background-size: 1rem 1rem;
                    animation: progress-stripe 1s linear infinite;
                }
            </style>
            <div id="progress-container" style="display:none; margin-top: 20px;">
                <div style="background: #e5e7eb; border-radius: 8px; height: 14px; overflow: hidden;">
                    <div id="progress-bar" class="progress-bar-animated"
                        style="background-color: var(--primary-color, #008491); width: 0%; height: 100%; transition: width 0.2s;">
                    </div>
                </div>
                <div id="progress-text"
                    style="text-align: center; margin-top: 5px; font-size: 0.9rem; color: #666; font-weight: 600;">
                    準備上傳...</div>
            </div>

            <button type="submit" class="btn-submit" id="btn-submit">開始上傳</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('form');
        const btnSubmit = document.getElementById('btn-submit');
        const progressContainer = document.getElementById('progress-container');
        const progressBar = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // Disable button
            btnSubmit.disabled = true;
            btnSubmit.innerText = '上傳中...';
            progressContainer.style.display = 'block';

            const formData = new FormData(form);
            const xhr = new XMLHttpRequest();

            xhr.open('POST', form.action, true);

            // Upload Progress
            xhr.upload.onprogress = function (e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                    progressText.innerText = percent + '%';

                    if (percent >= 100) {
                        progressText.innerText = '上傳完成，正在處理資料...';
                    }
                }
            };

            // Complete
            xhr.onload = function () {
                if (xhr.status === 200) {
                    // If the response is a redirect (which PHP default does) or success content
                    // Since we expect a redirect header usually, AJAX handles redirection automatically if 302/301?
                    // Actually XMLHttpRequest follows redirects automatically and returns the FINAL page content.
                    // So checking xhr.responseURL is better.
                    if (xhr.responseURL && !xhr.responseURL.includes('upload.php')) {
                        // Success: Redirected to manage page or others
                        window.location.href = xhr.responseURL;
                    } else {
                        // It stayed on upload.php, might be error or success msg
                        // We can just reload or parse output. 
                        // Simplest: Replace document body with response
                        document.documentElement.innerHTML = xhr.responseText;
                        // Re-run scripts? No. 
                        // Better: Check if response text contains "Success" or specific marker?
                        // Given our PHP adds ?msg=...
                        window.location.reload(); // Fallback
                    }
                } else {
                    alert('上傳失敗: ' + xhr.statusText);
                    btnSubmit.disabled = false;
                    btnSubmit.innerText = '開始上傳';
                }
            };

            xhr.onerror = function () {
                alert('網路錯誤，上傳失敗。');
                btnSubmit.disabled = false;
                btnSubmit.innerText = '開始上傳';
            };

            xhr.send(formData);
        });
    });
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>