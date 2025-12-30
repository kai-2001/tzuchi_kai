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

<header class="static-header">
    <div class="header-container">
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text" style="color: var(--primary-dark);">學術演講影片平台</h1>
            </a>
            <span class="breadcrumb-separator" style="color: #ccc;">/</span>
            <a href="manage_videos.php"
                style="text-decoration:none; color: var(--text-primary); font-size: 1.2rem; font-weight: 500;">影片管理</a>
            <span class="breadcrumb-separator" style="color: #ccc;">/</span>
            <h2 class="page-title" style="color: var(--text-primary); font-size: 1.2rem; font-weight: 500; margin: 0;">
                編輯影片資料</h2>
        </div>
        <div class="user-nav">
            <a href="manage_videos.php" class="btn-admin"><i class="fa-solid fa-arrow-left"></i> 返回列表</a>
        </div>
    </div>
</header>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form">
        <?php if ($error): ?>
            <div style="color: #f87171; margin-bottom: 20px;"><?= $error ?></div>
        <?php endif; ?>

        <form action="edit_video.php?id=<?= $video_id ?>" method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>演講標題</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($video['title']) ?>" required>
                </div>

                <div class="form-group">
                    <label>所屬院區</label>
                    <select name="campus_id" required>
                        <?php foreach ($campuses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($c['id'] == $video['campus_id']) ? 'selected' : '' ?>>
                                <?= $c['name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>演講日期</label>
                    <input type="date" name="event_date" value="<?= $video['event_date'] ?>" required>
                </div>

                <div class="form-group">
                    <label>講者姓名</label>
                    <input type="text" name="speaker_name" value="<?= htmlspecialchars($video['speaker_name']) ?>"
                        required>
                </div>

                <div class="form-group">
                    <label>服務單位</label>
                    <input type="text" name="affiliation" value="<?= htmlspecialchars($video['affiliation']) ?>"
                        required>
                </div>

                <div class="form-group">
                    <label>職務</label>
                    <input type="text" name="position" value="<?= htmlspecialchars($video['position']) ?>" required>
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
                    <label>更新影片或 Zip 檔 (留空則保持不變)</label>
                    <input type="file" name="video_file" accept=".mp4,.zip">
                </div>
            </div>
            <button type="submit" class="btn-submit">儲存修改</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>