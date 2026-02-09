<?php
/**
 * Edit Video Page Template
 * 
 * Variables available from controller:
 * - $video (array) - Video details
 * - $video_id (int) - Video ID
 * - $campuses (array) - List of campuses
 * - $error (string) - Error message
 */
include __DIR__ . '/partials/header.php';
?>

<?php
$navbar_mode = 'simple';
$page_title = '編輯影片資料';
include __DIR__ . '/partials/navbar.php';
?>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form">
        <?php if ($error): ?>
            <div style="color: #f87171; margin-bottom: 20px;"><?= $error ?></div>
        <?php endif; ?>

        <form id="uploadForm" action="edit_video.php?id=<?= $video_id ?>" method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>演講標題</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($video['title']) ?>" required>
                </div>

                <div class="form-group">
                    <label>所屬院區</label>
                    <select name="campus_id" required <?= is_campus_admin() ? 'style="pointer-events: none; background: #f1f5f9;"' : '' ?>>
                        <?php foreach ($campuses as $c): ?>
                            <?php if (is_campus_admin() && $c['id'] != $video['campus_id'])
                                continue; ?>
                            <option value="<?= $c['id'] ?>" <?= ($c['id'] == $video['campus_id']) ? 'selected' : '' ?>>
                                <?= $c['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (is_campus_admin()): ?>
                        <input type="hidden" name="campus_id" value="<?= $video['campus_id'] ?>">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>演講日期</label>
                    <input type="date" name="event_date" value="<?= $video['event_date'] ?>" required>
                </div>

                <div class="form-group full-width">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-weight: 500; color: #334155;">講者資訊</span>
                        <button type="button" class="btn-add-speaker" id="addSpeaker">
                            <i class="fas fa-plus"></i> 新增講者
                        </button>
                    </div>

                    <div id="speakers-container" class="speakers-container">
                        <!-- 講者項目將由 JavaScript 插入 -->
                    </div>
                </div>

                <div class="form-group">
                    <label>目前縮圖</label>
                    <div class="preview-thumb"
                        style="background-image: url('<?= htmlspecialchars($video['thumbnail_path'] ?: 'assets/images/placeholder.jpg') ?>')">
                    </div>
                    <label>更新縮圖 (留空則保持不變)</label>
                    <input type="file" name="thumbnail" accept="image/*">
                </div>

                <div class="form-group full-width">
                    <label>更新 mp4 或 evercam zip 檔 (留空則保持不變)</label>
                    <input type="file" name="video_file" accept=".mp4,.zip" id="video_file">
                </div>

                <div class="form-group full-width">
                    <label>或 貼上影片連結 (留空則保持不變)</label>
                    <input type="url" name="video_link" id="video_link"
                        placeholder="http://example.com/videos/abc123/index.html">
                    <small class="form-hint">
                        <i class="fas fa-info-circle"></i> 檔案上傳與連結輸入擇一即可。使用連結的影片將直接設為可播放狀態，不進行壓縮。
                    </small>
                </div>
            </div>
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

            <button type="submit" class="btn-submit" id="btn-submit">儲存修改</button>
        </form>
    </div>
</div>

<script src="assets/js/validators.js?v=<?= time() ?>"></script>
<script src="assets/js/upload.js?v=<?= time() ?>"></script>
<script>
    // ==========================================
    // Load existing speakers for edit mode
    // ==========================================
    <?php
    require_once __DIR__ . '/../includes/models/VideoSpeaker.php';
    $existing_speakers = video_speaker_get_by_video($video_id);
    ?>

    const existingSpeakers = <?= json_encode($existing_speakers, JSON_UNESCAPED_UNICODE) ?>;

    // Wait for upload.js to define addSpeaker function
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            if (typeof window.addSpeaker === 'function' && existingSpeakers && existingSpeakers.length > 0) {
                existingSpeakers.forEach(speaker => {
                    window.addSpeaker({
                        name: speaker.name,
                        affiliation: speaker.affiliation || '',
                        position: speaker.position || ''
                    });
                });
            }
        }, 100);
    });

    // ==========================================
    // Mutual exclusion and conditional required validation
    // ==========================================
    document.addEventListener('DOMContentLoaded', function () {
        const videoFile = document.getElementById('video_file');
        const videoLink = document.getElementById('video_link');
        const form = document.querySelector('form');

        function updateFileInput() {
            if (videoLink.value.trim()) {
                // Link has value - disable and clear file
                videoFile.value = '';
                videoFile.disabled = true;
                videoFile.style.backgroundColor = '#f1f5f9';
            } else {
                // Link is empty - enable file
                videoFile.disabled = false;
                videoFile.style.backgroundColor = '';
            }
        }

        function updateLinkInput() {
            if (videoFile.files.length > 0) {
                // File selected - disable and clear link
                videoLink.value = '';
                videoLink.disabled = true;
                videoLink.style.backgroundColor = '#f1f5f9';
            } else {
                // No file - enable link
                videoLink.disabled = false;
                videoLink.style.backgroundColor = '';
            }
        }

        // File format validation with visual feedback
        function validateVideoFile(fileInput) {
            if (fileInput.files.length === 0) return true;

            const file = fileInput.files[0];
            const fileName = file.name.toLowerCase();
            const allowedExtensions = ['.mp4', '.zip'];
            const isValid = allowedExtensions.some(ext => fileName.endsWith(ext));

            // Remove any existing error message
            const oldError = fileInput.parentElement.querySelector('.format-error');
            if (oldError) oldError.remove();

            if (!isValid) {
                // Create visual error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'format-error';
                errorDiv.style.cssText = `
                    background: #fee2e2;
                    border-left: 4px solid #ef4444;
                    color: #b91c1c;
                    padding: 12px 16px;
                    border-radius: 6px;
                    margin-top: 10px;
                    font-size: 0.9rem;
                    animation: slideIn 0.3s ease-out;
                    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.1);
                `;
                errorDiv.innerHTML = `
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 1.1rem; margin-top: 2px;"></i>
                        <div>
                            <strong style="display: block; margin-bottom: 4px;">檔案格式錯誤</strong>
                            <span>只接受 <code style="background: rgba(239,68,68,0.1); padding: 2px 6px; border-radius: 3px; font-size: 0.85rem;">MP4</code> 或 <code style="background: rgba(239,68,68,0.1); padding: 2px 6px; border-radius: 3px; font-size: 0.85rem;">ZIP</code> 格式</span>
                            <br><span style="font-size: 0.85rem; opacity: 0.8;">您選擇的檔案：${file.name}</span>
                        </div>
                    </div>
                `;

                // Insert error message after the file input
                fileInput.parentElement.appendChild(errorDiv);

                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (errorDiv.parentElement) {
                        errorDiv.style.animation = 'slideOut 0.3s ease-in';
                        setTimeout(() => errorDiv.remove(), 300);
                    }
                }, 5000);

                // Clear the file input
                fileInput.value = '';
                return false;
            }

            return true;
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            @keyframes slideOut {
                from {
                    opacity: 1;
                    transform: translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateY(-10px);
                }
            }
        `;
        document.head.appendChild(style);

        // Add event listeners with validation
        videoFile.addEventListener('change', function () {
            if (validateVideoFile(this)) {
                updateLinkInput();
            }
        });
        videoLink.addEventListener('input', updateFileInput);

        // Form submit validation
        form.addEventListener('submit', function (e) {
            const hasFile = videoFile.files.length > 0;
            const hasLink = videoLink.value.trim() !== '';

            if (hasFile && hasLink) {
                e.preventDefault();
                alert('請只選擇一種上傳方式：檔案上傳或影片連結。');
                return false;
            }
        });

        // ==========================================
        // Initialize Form Validator
        // ==========================================
        <?php require_once __DIR__ . '/../includes/Validator.php'; ?>
        const editVideoRules = <?= Validator::getRulesJson('edit_video') ?>;
        FormValidator.init('uploadForm', editVideoRules);
    });
</script>


<?php include __DIR__ . '/partials/footer.php'; ?>