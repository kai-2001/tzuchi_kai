<?php
/**
 * Add Upcoming Lecture Template
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
            <h2 class="page-title">新增演講預告</h2>
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

    <form action="add_upcoming.php" method="POST">
        <div class="form-grid">
            <div class="form-group full-width">
                <label>演講標題</label>
                <input type="text" name="title" required placeholder="請輸入預定的演講標題">
            </div>

            <div class="form-group">
                <label>講者姓名</label>
                <input type="text" name="speaker_name" required placeholder="例如：林大明 醫師">
            </div>

            <div class="form-group">
                <label>服務單位</label>
                <input type="text" name="affiliation" placeholder="例如：台北慈濟醫院 內科部">
            </div>

            <div class="form-group">
                <label>演講日期</label>
                <input type="date" name="event_date" required>
            </div>

            <div class="form-group">
                <label>院區</label>
                <select name="campus_id" required>
                    <?php foreach ($campuses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>地點</label>
                <input type="text" name="location" placeholder="例如：2樓第一會議室">
            </div>

            <div class="form-group full-width">
                <label>備註/簡介</label>
                <textarea name="description"
                    style="height: 120px; width: 100%; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 1rem;"></textarea>
            </div>
        </div>
        <button type="submit" class="btn-submit">儲存預告</button>
    </form>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>