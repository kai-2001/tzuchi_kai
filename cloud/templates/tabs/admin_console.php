<div class="hero-section">
    <?php
    $is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];
    ?>

    <?php if ($is_hospital_admin): ?>
        <i class="fas fa-hospital-user hero-icon"></i>
        <h1>院區管理控制台</h1>
        <p>歡迎回來！您可以從這裡管理與檢視您所屬院區的課程與學員。</p>
        <div class="hero-buttons">
            <button class="btn-hero btn-hero-primary"
                onclick="goToMoodle('<?php echo $moodle_url; ?>/course/management.php')">
                <i class="fas fa-tasks"></i> 管理院區課程
            </button>
            <button class="btn-hero btn-hero-secondary"
                onclick="goToMoodle('<?php echo $moodle_url; ?>/report/log/index.php')">
                <i class="fas fa-chart-line"></i> 查看學習報表
            </button>
        </div>
    <?php else: ?>
        <i class="fas fa-shield-alt hero-icon"></i>
        <h1>系統管理員控制台</h1>
        <p>歡迎回來，管理員！您可以從這裡管理整個學習平台。</p>
        <div class="hero-buttons">
            <button class="btn-hero btn-hero-primary" onclick="goToMoodle('<?php echo $moodle_url; ?>/admin/search.php')">
                <i class="fas fa-cogs"></i> 進入後台管理
            </button>
        </div>
    <?php endif; ?>
</div>