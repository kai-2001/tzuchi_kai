<?php include __DIR__ . '/partials/header.php'; ?>

<header class="static-header">
    <div class="header-container">
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text" style="color: var(--primary-dark);">學術演講影片平台</h1>
            </a>
            <span class="breadcrumb-separator" style="color: #ccc;">/</span>
            <a href="manage_announcements.php"
                style="text-decoration:none; color: var(--text-primary); font-size: 1.2rem; font-weight: 500;">公告管理</a>
            <span class="breadcrumb-separator" style="color: #ccc;">/</span>
            <h2 class="page-title" style="color: var(--text-primary); font-size: 1.2rem; font-weight: 500; margin: 0;">
                新增公告</h2>
        </div>
        <div class="user-nav">
            <a href="manage_announcements.php" class="btn-admin"><i class="fa-solid fa-arrow-left"></i> 返回列表</a>
        </div>
    </div>
</header>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form" style="max-width: 800px; margin: 0 auto;">
        <?php if ($error): ?>
            <div class="alert alert-danger" style="color: #f87171; margin-bottom: 20px;"><?= $error ?></div>
        <?php endif; ?>

        <form action="add_announcement.php" method="POST" enctype="multipart/form-data">
            <div class="form-group full-width">
                <label>公告標題 <span style="color:red;">*</span></label>
                <input type="text" name="title" required placeholder="例如：12月全院演講公告">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>講者姓名</label>
                    <input type="text" name="speaker_name" placeholder="非必填">
                </div>
                <div class="form-group">
                    <label>單位/職稱</label>
                    <input type="text" name="affiliation" placeholder="例如：某某醫院 醫師">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>活動日期</label>
                    <input type="date" name="event_date">
                </div>
                <div class="form-group">
                    <label>地點</label>
                    <input type="text" name="location" placeholder="例如：國際會議廳">
                </div>
            </div>

            <div class="form-group">
                <label>所屬院區</label>
                <select name="campus_id">
                    <option value="0">全部院區</option>
                    <?php foreach ($campuses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group full-width">
                <label>詳細內容/備註</label>
                <textarea name="description"
                    style="height: 120px; width: 100%; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 1rem;"></textarea>
            </div>

            <div
                style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-top: 10px; border: 1px solid #e2e8f0;">
                <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <input type="checkbox" name="is_hero" id="is_hero" style="width: 20px; height: 20px;">
                    <label for="is_hero" style="margin: 0; cursor: pointer; font-weight: 600; color: #0ea5e9;">顯示在首頁橫幅
                        (Hero Carousel)</label>
                </div>

                <div id="hero-fields" style="display: none;">
                    <div class="form-group">
                        <label>橫幅背景圖片 (推薦尺寸 1920x800)</label>
                        <input type="file" name="slide_image" accept="image/*">
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>跳轉連結 URL (可選)</label>
                            <input type="url" name="link_url" placeholder="點擊橫幅後要跳轉的網址">
                        </div>
                        <div class="form-group">
                            <label>排序 (越小越前面)</label>
                            <input type="number" name="sort_order" value="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="submit" class="btn-submit">確定新增</button>
                <a href="manage_announcements.php" class="btn-admin"
                    style="text-decoration:none; display:inline-flex; align-items: center; padding: 12px 24px;">取消</a>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('is_hero').addEventListener('change', function () {
        document.getElementById('hero-fields').style.display = this.checked ? 'block' : 'none';
    });
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>