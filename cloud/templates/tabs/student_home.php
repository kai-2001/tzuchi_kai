<!-- 首頁區塊 -->
<div id="section-home" class="page-section active">

    <!-- Hero 歡迎區（簡化版） -->
    <div class="hero-section hero-compact">
        <i class="fas fa-graduation-cap hero-icon"></i>
        <h1>歡迎回來，<?php echo h($_SESSION['fullname']); ?> 👋</h1>
        <p>開始您的學習之旅，探索更多課程與資源</p>
        <div class="hero-buttons">
            <button class="btn-hero btn-hero-primary" onclick="scrollToSection('progress-hero-card')">
                <i class="fas fa-chart-pie"></i> 查看進度
            </button>
            <button class="btn-hero btn-hero-outline" onclick="scrollToSection('grades-widget')">
                <i class="fas fa-chart-bar"></i> 成績
            </button>
            <button class="btn-hero btn-hero-outline" onclick="scrollToSection('announcement-widget')">
                <i class="fas fa-bullhorn"></i> 公告
            </button>
        </div>
    </div>

    <!-- 修課進度區塊（最重要） -->
    <div id="progress-hero-card" class="progress-hero-card scroll-animate slide-up">
        <div class="progress-hero-header">
            <h3><i class="fas fa-tasks"></i> 修課進度總覽</h3>
            <div class="progress-summary" id="progress-summary">
                <!-- 非同步載入進度統計 -->
            </div>
        </div>
        <div class="progress-hero-body" id="curriculum-progress-widget">
            <!-- 非同步載入，初始顯示 skeleton -->
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px;"></div>
            </div>
        </div>
        <div class="progress-bar-container">
            <div class="overall-progress-bar">
                <div class="progress-fill" id="overall-progress-fill" style="width: 0%"></div>
            </div>
            <span class="progress-text" id="overall-progress-text">計算中...</span>
        </div>
    </div>

    <!-- 兩欄式資訊區 -->
    <div class="row g-4 dashboard-widgets">
        <!-- 成績總覽（垂直長條圖） -->
        <div class="col-lg-5 scroll-animate slide-left">
            <div id="grades-widget" class="widget-card grades-widget h-100">
                <div class="widget-header">
                    <h5><i class="fas fa-chart-bar"></i> 成績總覽</h5>
                </div>
                <div class="widget-body">
                    <div class="grades-chart-container" id="grades-chart-container">
                        <!-- 非同步載入，初始顯示 skeleton -->
                        <div class="loading-skeleton">
                            <div class="skeleton-pulse" style="height: 180px;"></div>
                        </div>
                    </div>
                </div>
                <div class="widget-footer">
                    <button class="btn-widget-action"
                        onclick="goToMoodle('<?php echo $moodle_url; ?>/grade/report/overview/index.php')">
                        <i class="fas fa-external-link-alt me-2"></i>查看更多成績
                    </button>
                </div>
            </div>
        </div>

        <!-- 最新公告 -->
        <div class="col-lg-7 scroll-animate slide-right">
            <div id="announcement-widget" class="widget-card announcement-widget h-100">
                <div class="widget-header">
                    <h5><i class="fas fa-bullhorn"></i> 最新公告</h5>
                </div>
                <div class="widget-body announcement-body">
                    <!-- 非同步載入 -->
                    <div class="loading-skeleton">
                        <div class="skeleton-pulse" style="height: 50px; margin-bottom: 10px;"></div>
                        <div class="skeleton-pulse" style="height: 50px; margin-bottom: 10px;"></div>
                        <div class="skeleton-pulse" style="height: 50px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>