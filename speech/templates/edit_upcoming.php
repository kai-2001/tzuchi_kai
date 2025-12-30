<?php
/**
 * Edit Upcoming Lecture Template
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
            <h2 class="page-title">編輯演講預告</h2>
        </div>
        <div class="user-nav">
            <a href="manage_upcoming.php" class="btn-logout"><i class="fa-solid fa-arrow-left"></i> 返回管理</a>
        </div>
    </div>
</header>

<div class="upload-form">
    <?php if ($error): ?>
        <div class="alert alert-danger" style="color: #f87171; margin-bottom: 20px;"><?= $error ?></div>
    <?php endif; ?>

    <form action="edit_upcoming.php?id=<?= $lecture['id'] ?>" method="POST">
        <div class="form-grid">
            <div class="form-group full-width">
                <label>演講標題</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($lecture['title']) ?>">
            </div>

            <div class="form-group">
                <label>講者姓名</label>
                <input type="text" name="speaker_name" required
                    value="<?= htmlspecialchars($lecture['speaker_name']) ?>">
            </div>

            <div class="form-group">
                <label>服務單位</label>
                <input type="text" name="affiliation" value="<?= htmlspecialchars($lecture['affiliation']) ?>">
            </div>

            <div class="form-group">
                <label>演講日期</label>
                <input type="date" name="event_date" required value="<?= htmlspecialchars($lecture['event_date']) ?>">
            </div>

            <div class="form-group">
                <label>院區</label>
                <select name="campus_id" required>
                    <?php foreach ($campuses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $lecture['campus_id'] == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>地點</label>
                <input type="text" name="location" value="<?= htmlspecialchars($lecture['location']) ?>">
            </div>

            <div class="form-group full-width">
                <label>備註/簡介</label>
                <textarea name="description"
                    style="height: 120px; width: 100%; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 1rem;"><?= htmlspecialchars($lecture['description']) ?></textarea>
            </div>
        </div>
        <button type="submit" class="btn-submit">更新預告</button>
    </form>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>