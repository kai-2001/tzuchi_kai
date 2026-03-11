<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>雲嘉學習網</title>
    <!-- 引入絕對路徑的 CSS 與圖示庫 -->
    <link rel="stylesheet" href="<?php echo $web_root; ?>/assets/css/design-system.css">
    <link rel="stylesheet" href="<?php echo $web_root; ?>/assets/css/student-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body style="background-color: var(--bg-body); margin: 0; padding: 0;">
    <!-- 掛載 Web Component 與導覽列 -->
    <script src="<?php echo $web_root; ?>/assets/js/components/student-nav.js"></script>
    <student-nav active="dashboard"></student-nav>

    <!-- templates/student/student-dashboard.php -->
    <?php
    // 此頁面由 index.php 載入，故已在完整的 HTML 架構內 (已有 header.php)
    ?>
    <main class="layout-main">
        <div class="container">
            <!-- 頁面標題 -->
            <div class="page-header-v2">
                <h1 class="page-header-v2__title">個人主頁</h1>
                <p class="page-header-v2__subtitle">查看您的學位進度</p>
            </div>

            <!-- 區塊 A: 學位完成進度 -->
            <div class="grid-2 mb-6" id="dashboard-stats-container">
                <div class="stat-card-large text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted mb-2"></i>
                    <div class="text-muted text-sm">載入統計資料中...</div>
                </div>
            </div>

            <!-- 兩欄佈局 -->
            <div class="grid-2">
                <!-- 左欄 -->
                <div>
                    <!-- 區塊 B: 必修進度卡片 -->
                    <div class="card-v2 mb-5">
                        <div class="card-v2__header">
                            <h2 class="card-v2__title">
                                <i class="fas fa-bookmark"></i>
                                必修課程進度
                            </h2>
                            <span class="badge-v2 badge-v2--primary" id="mandatory-course-badge">載入中</span>
                        </div>
                        <div class="card-v2__body" id="mandatory-courses-container">
                            <div class="text-center py-4">
                                <i class="fas fa-spinner fa-spin fa-2x text-muted mb-2"></i>
                                <div class="text-muted text-sm">載入必修課程中...</div>
                            </div>
                        </div>
                        <div class="card-v2__footer">
                            <a href="index.php?page=student_degree_audit"
                                class="btn-v2 btn-v2--outline-info btn-v2--sm">
                                查看修課進度
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- 近期系統公告 -->
                    <div class="card-v2">
                        <div class="card-v2__header">
                            <h2 class="card-v2__title">
                                <i class="fas fa-bullhorn"></i>
                                最新公告
                            </h2>
                        </div>
                        <div class="card-v2__body">
                            <div class="d-flex flex-col gap-3" id="announcements-container">
                                <div class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin fa-2x text-muted mb-2"></i>
                                    <div class="text-muted text-sm">載入最新公告中...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 右欄 -->
                <div>
                    <!-- 區塊 C: 領域必修進度 -->
                    <div class="card-v2 mb-5">
                        <div class="card-v2__header">
                            <h2 class="card-v2__title">
                                <i class="fas fa-layer-group"></i>
                                必修類別進度
                            </h2>
                        </div>
                        <div class="card-v2__body">
                            <div class="d-grid gap-4" id="domain-progress-container">
                                <div class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin fa-2x text-muted mb-2"></i>
                                    <div class="text-muted text-sm">載入類別設定中...</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-v2__footer">
                            <a href="index.php?page=student_degree_audit&tab=domains"
                                class="btn-v2 btn-v2--outline-info btn-v2--sm">
                                選擇類別課程
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- 區塊 D: 進行中課程 -->
                    <div class="card-v2" style="margin-bottom: var(--space-5);">
                        <div class="card-v2__header">
                            <h2 class="card-v2__title">
                                <i class="fas fa-layer-group"></i>
                                進行中課程
                            </h2>
                        </div>
                        <div class="card-v2__body">
                            <div class="d-flex flex-col gap-4" id="in-progress-courses-container">
                                <div class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin fa-2x text-muted mb-2"></i>
                                    <div class="text-muted text-sm">載入課程中...</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-v2__footer">
                            <a href="index.php?page=student_courses" class="btn-v2 btn-v2--outline-info btn-v2--sm">
                                查看所有課程
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>


                </div>
            </div>
    </main>

    <!-- 載入原本負責處理資料提取的 script，並修改以適用於多頁面 -->
    <?php include 'templates/student/student_js_loader.php'; ?>
    </div>
    <!-- 全域功能與 SSO 登入處理 -->
    <script src="<?php echo $web_root; ?>/assets/js/student-main.js?v=<?php echo time(); ?>"></script>
</body>

</html>