<div id="section-home" class="page-section active">
    <div class="hero-section hero-compact">
        <i class="fas fa-chalkboard-teacher hero-icon"></i>
        <h1>歡迎回來，<?php echo h($_SESSION['fullname']); ?> 老師 👨‍🏫</h1>
        <p>管理您的課程，開創更多學習機會</p>
        <div class="hero-buttons">
            <button class="btn-hero btn-hero-primary"
                onclick="goToMoodle('<?php echo $moodle_url; ?>/course/edit.php')">
                <i class="fas fa-plus-circle"></i> 新增課程
            </button>
            <button class="btn-hero btn-hero-outline" onclick="showTab('course-management')">
                <i class="fas fa-tasks"></i> 課程管理
            </button>
        </div>
    </div>
</div>