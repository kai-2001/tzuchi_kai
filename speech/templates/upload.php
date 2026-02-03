<?php
/**
 * Upload Page Template
 */
include __DIR__ . '/partials/header.php';
?>

<?php
$navbar_mode = 'simple';
// Controller sets $page_title = '上傳演講', but breadcrumb expects '上傳新演講' to match exactly? 
// Actually navbar.php uses $page_title directly. 
// Let's ensure it matches the visual. 
$page_title = '上傳新演講';
include __DIR__ . '/partials/navbar.php';
?>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form">
        <?php if ($msg): ?>
            <div style="color: #4ade80; margin-bottom: 20px;"><?= $msg ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="color: #f87171; margin-bottom: 20px;"><?= $error ?></div>
        <?php endif; ?>

        <form action="upload.php" method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>演講標題</label>
                    <input type="text" name="title" required>
                </div>

                <div class="form-group">
                    <label>所屬院區</label>
                    <select name="campus_id" required <?= is_campus_admin() ? 'style="pointer-events: none; background: #f1f5f9;"' : '' ?>>
                        <?php foreach ($campuses as $c): ?>
                            <?php if (is_campus_admin() && $c['id'] != $_SESSION['campus_id'])
                                continue; ?>
                            <option value="<?= $c['id'] ?>" <?= (is_campus_admin() && $c['id'] == $_SESSION['campus_id']) ? 'selected' : '' ?>>
                                <?= $c['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (is_campus_admin()): ?>
                        <input type="hidden" name="campus_id" value="<?= $_SESSION['campus_id'] ?>">
                    <?php endif; ?>
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
                    <input type="text" name="affiliation">
                </div>

                <div class="form-group">
                    <label>職務 (如醫師、護理師)</label>
                    <input type="text" name="position">
                </div>

                <div class="form-group">
                    <label>上傳縮圖 (JPG/PNG)</label>
                    <input type="file" name="thumbnail" accept="image/*">
                </div>

                <div class="form-group full-width">
                    <label>上傳 mp4 或 evercam zip 檔</label>
                    <input type="file" name="video_file" accept=".mp4,.zip" id="video_file">
                </div>

                <div class="form-group full-width">
                    <label>或 貼上影片連結</label>
                    <input type="url" name="video_link" id="video_link"
                        placeholder="http://example.com/videos/abc123/index.html">
                    <small class="form-hint">
                        <i class="fas fa-info-circle"></i> 檔案上傳與連結輸入擇一即可。使用連結的影片將直接設為可播放狀態，不進行壓縮。
                    </small>
                </div>
            </div>
            <style>
                .form-hint {
                    display: block;
                    color: #64748b;
                    font-size: 0.875rem;
                    margin-top: 8px;
                    line-height: 1.5;
                    padding-left: 5px;
                }

                .form-hint i {
                    color: var(--primary-color);
                    margin-right: 4px;
                    font-size: 0.85rem;
                }
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

<script src="assets/js/validators.js"></script>
<script src="assets/js/upload.js"></script>
<script>
    // Mutual exclusion and conditional required validation
    const videoFile = document.getElementById('video_file');
    const videoLink = document.getElementById('video_link');
    const form = document.getElementById('uploadForm');

    function updateFileInput() {
        if (videoLink.value.trim()) {
            // Link has value - disable and clear file
            videoFile.value = '';
            videoFile.disabled = true;
            videoFile.removeAttribute('required');
            videoFile.style.backgroundColor = '#f1f5f9';
            videoLink.setAttribute('required', 'required');
        } else {
            // Link is empty - enable file
            videoFile.disabled = false;
            videoFile.setAttribute('required', 'required');
            videoFile.style.backgroundColor = '';
            videoLink.removeAttribute('required');
        }
    }

    function updateLinkInput() {
        if (videoFile.files.length > 0) {
            // File selected - disable and clear link
            videoLink.value = '';
            videoLink.disabled = true;
            videoLink.removeAttribute('required');
            videoLink.style.backgroundColor = '#f1f5f9';
            videoFile.setAttribute('required', 'required');
        } else {
            // No file - enable link
            videoLink.disabled = false;
            videoLink.setAttribute('required', 'required');
            videoLink.style.backgroundColor = '';
            videoFile.removeAttribute('required');
        }
    }

    videoFile.addEventListener('change', updateLinkInput);
    videoLink.addEventListener('input', updateFileInput);

    // Form submit validation
    form.addEventListener('submit', function (e) {
        const hasFile = videoFile.files.length > 0;
        const hasLink = videoLink.value.trim() !== '';

        if (!hasFile && !hasLink) {
            e.preventDefault();
            alert('請選擇上傳檔案或提供影片連結。');
            return false;
        }

        if (hasFile && hasLink) {
            e.preventDefault();
            alert('請只選擇一種上傳方式：檔案上傳或影片連結。');
            return false;
        }
    });

    // Initialize form validation with rules from backend
    <?php require_once __DIR__ . '/../includes/Validator.php'; ?>
    const uploadRules = <?= Validator::getRulesJson('upload') ?>;
    FormValidator.init('uploadForm', uploadRules);
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>