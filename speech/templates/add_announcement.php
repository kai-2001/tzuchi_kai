<?php include __DIR__ . '/partials/header.php'; ?>

<?php
$navbar_mode = 'simple';
$page_title = '新增公告';
$custom_breadcrumbs = [
    ['label' => '公告管理', 'url' => 'manage_announcements.php']
];
$nav_actions = [
    ['label' => '返回列表', 'url' => 'manage_announcements.php', 'icon' => 'fa-solid fa-arrow-left']
];
include __DIR__ . '/partials/navbar.php';
?>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form">
        <?php if ($error): ?>
            <div class="alert alert-danger" style="color: #f87171; margin-bottom: 20px;"><?= $error ?></div>
        <?php endif; ?>

        <form action="add_announcement.php" method="POST" enctype="multipart/form-data" id="announcementForm">
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
                    <input type="date" name="event_date" id="event_date">
                </div>
                <div class="form-group">
                    <label>地點</label>
                    <input type="text" name="location" placeholder="例如：國際會議廳">
                </div>
            </div>

            <div class="form-group">
                <label>所屬院區</label>
                <select name="campus_id" <?= is_campus_admin() ? 'style="pointer-events: none; background: #f1f5f9;"' : '' ?>>
                    <?php if (!is_campus_admin()): ?>
                        <option value="0">全部院區</option>
                    <?php endif; ?>
                    <?php foreach ($campuses as $c): ?>
                        <?php if (is_campus_admin() && $c['id'] != $_SESSION['campus_id'])
                            continue; ?>
                        <option value="<?= $c['id'] ?>" <?= (is_campus_admin() && $c['id'] == $_SESSION['campus_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (is_campus_admin()): ?>
                    <input type="hidden" name="campus_id" value="<?= $_SESSION['campus_id'] ?>">
                <?php endif; ?>
            </div>

            <div class="form-group full-width">
                <label>詳細內容/備註</label>
                <textarea name="description" style="height: 120px; width: 100%;"></textarea>
            </div>

            <div
                style="background: rgba(248, 250, 252, 0.5); padding: 25px; border-radius: 15px; margin-top: 10px; border: 1px solid #e2e8f0;">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label
                        style="font-weight: 600; color: var(--primary-color); display: block; margin-bottom: 15px;">顯示在首頁橫幅</label>
                    <div style="display: flex; gap: 20px;">
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <input type="radio" name="is_hero" value="1" id="hero_show"> 顯示
                        </label>
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <input type="radio" name="is_hero" value="0" id="hero_hide" checked> 不顯示
                        </label>
                    </div>
                </div>

                <div id="hero-fields">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>上架日期</label>
                            <input type="date" name="hero_start_date" id="hero_start_date">
                        </div>
                        <div class="form-group">
                            <label>下架日期</label>
                            <input type="date" name="hero_end_date" id="hero_end_date">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>橫幅背景圖片 (推薦尺寸 1920x450)</label>
                        <input type="file" name="slide_image" accept="image/*">
                    </div>
                    <div class="form-grid">

                        <input type="hidden" name="sort_order" value="0">
                    </div>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 40px; display: flex; justify-content: center; gap: 20px;">
                <button type="submit" class="btn-submit"
                    style="padding: 12px 40px; font-size: 1rem; border-radius: 50px; min-width: 160px;">
                    確定新增
                </button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/validators.js"></script>
<script src="assets/js/announcements.js"></script>
<script>
    <?php require_once __DIR__ . '/../includes/Validator.php'; ?>
    const announcementRules = <?= Validator::getRulesJson('add_announcement') ?>;
    FormValidator.init('announcementForm', announcementRules);
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>