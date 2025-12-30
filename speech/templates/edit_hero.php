<?php include __DIR__ . '/partials/header.php'; ?>

<header class="static-header">
    <div class="header-container">
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text" style="color: var(--primary-dark);">學術演講影片平台</h1>
            </a>
            <span class="breadcrumb-separator" style="color: #ccc;">/</span>
            <a href="manage_hero.php"
                style="text-decoration:none; color: var(--text-primary); font-size: 1.2rem; font-weight: 500;">公告管理</a>
            <span class="breadcrumb-separator" style="color: #ccc;">/</span>
            <h2 class="page-title" style="color: var(--text-primary); font-size: 1.2rem; font-weight: 500; margin: 0;">
                編輯橫幅</h2>
        </div>
        <div class="user-nav">
            <a href="manage_hero.php" class="btn-admin"><i class="fa-solid fa-arrow-left"></i> 返回列表</a>
        </div>
    </div>
</header>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form" style="max-width: 800px; margin: 0 auto;">
        <form action="edit_hero.php?id=<?= $slide['id'] ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="current_image" value="<?= htmlspecialchars($slide['image_url']) ?>">
            <div class="form-group">
                <label>標題</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($slide['title']) ?>">
            </div>
            <div class="form-group">
                <label>背景圖片 (不變動請留空)</label>
                <?php if ($slide['image_url']): ?>
                    <div style="margin-bottom: 15px;">
                        <img src="<?= $slide['image_url'] ?>"
                            style="max-height: 100px; border-radius: 8px; display: block;">
                    </div>
                <?php endif; ?>
                <input type="file" name="slide_image" accept="image/*">
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>講者名稱</label>
                    <input type="text" name="speaker_name" value="<?= htmlspecialchars($slide['speaker_name']) ?>">
                </div>
                <div class="form-group">
                    <label>活動日期</label>
                    <input type="date" name="event_date" value="<?= $slide['event_date'] ?>">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>所屬院區</label>
                    <select name="campus_id">
                        <option value="0" <?= $slide['campus_id'] == 0 ? 'selected' : '' ?>>全部院區</option>
                        <?php foreach ($campuses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $slide['campus_id'] == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>排序</label>
                    <input type="number" name="sort_order" value="<?= $slide['sort_order'] ?>">
                </div>
            </div>
            <div class="form-group">
                <label>連結 URL</label>
                <input type="url" name="link_url" value="<?= htmlspecialchars($slide['link_url']) ?>">
            </div>
            <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-top: 25px;">
                <input type="checkbox" name="is_hero" id="is_hero" style="width: 20px; height: 20px;"
                    <?= $slide['is_hero'] ? 'checked' : '' ?>>
                <label for="is_hero" style="margin: 0; cursor: pointer;">顯示在首頁橫幅</label>
            </div>
            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="submit" class="btn-submit">儲存變更</button>
                <a href="manage_hero.php" class="btn-admin"
                    style="text-decoration:none; display:inline-flex; align-items: center; padding: 12px 24px;">取消</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>