<?php
/**
 * Upload Page Template
 * 
 * Variables available from controller:
 * - $campuses (array) - List of campuses
 * - $msg (string) - Success message
 * - $error (string) - Error message
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
            <h2 class="page-title">上傳新演講</h2>
        </div>
        <div class="user-nav">
            <a href="index.php" class="btn-logout"><i class="fa-solid fa-house"></i> 返回首頁</a>
        </div>
    </div>
</header>

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
                <label>上傳影片或 Zip 檔 (包含 index.html)</label>
                <input type="file" name="video_file" accept=".mp4,.zip" required>
            </div>
        </div>
        <button type="submit" class="btn-submit">開始上傳</button>
    </form>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>