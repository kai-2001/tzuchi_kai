<!-- templates/dashboard.php - 現代化儀表板 -->
<?php $is_teacherplus = isset($_SESSION['is_teacherplus']) ? $_SESSION['is_teacherplus'] : false; ?>
<div class="main-content">

    <?php if ($is_admin): ?>
        <div class="hero-section">
            <i class="fas fa-shield-alt hero-icon"></i>
            <h1>管理員控制台</h1>
            <p>歡迎回來，管理員！您可以從這裡管理整個學習平台。</p>
            <div class="hero-buttons">
                <button class="btn-hero btn-hero-primary" onclick="goToMoodle('<?php echo $moodle_url; ?>/admin/search.php')">
                    <i class="fas fa-cogs"></i> 進入後台管理
                </button>
            </div>
        </div>
    <?php elseif ($is_teacherplus): ?>
        <!-- 開課教師介面 -->
        
        <!-- 首頁區塊 -->
        <div id="section-home" class="page-section active">
            <div class="hero-section hero-compact">
                <i class="fas fa-chalkboard-teacher hero-icon"></i>
                <h1>歡迎回來，<?php echo h($_SESSION['fullname']); ?> 老師 👨‍🏫</h1>
                <p>管理您的課程，開創更多學習機會</p>
                <div class="hero-buttons">
                    <button class="btn-hero btn-hero-primary" onclick="goToMoodle('<?php echo $moodle_url; ?>/course/edit.php')">
                        <i class="fas fa-plus-circle"></i> 新增課程
                    </button>
                    <button class="btn-hero btn-hero-outline" onclick="showTab('course-management')">
                        <i class="fas fa-tasks"></i> 課程管理
                    </button>
                </div>
            </div>
        </div>
        
        <!-- 課程管理區塊 -->
        <div id="section-course-management" class="page-section">
            <div class="section-header">
                <h2><i class="fas fa-tasks"></i> 課程管理</h2>
                <p class="section-subtitle">管理您教授的所有課程</p>
            </div>
            
            <!-- 搜尋欄 -->
            <div class="search-bar-container mb-4">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="teacher-course-search" placeholder="搜尋課程名稱..." oninput="filterTeacherCourses(this.value)">
                </div>
                <button onclick="refreshTeacherCourses()" class="btn-refresh-large" title="重新載入">
                    <i class="fas fa-sync-alt"></i> 重新整理
                </button>
            </div>
            
            <!-- 課程列表 -->
            <div class="widget-card">
                <div class="widget-body" id="teacher-courses-list">
                    <!-- 非同步載入教師課程 -->
                    <div class="loading-skeleton">
                        <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                        <div class="skeleton-pulse" style="height: 60px;"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
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
                            <button class="btn-widget-action" onclick="goToMoodle('<?php echo $moodle_url; ?>/grade/report/overview/index.php')">
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

        <!-- 功能頁面區塊 -->
        <div id="section-features" class="page-section">
            <div class="tab-content">

                <!-- 學習歷程 Tab -->
                <div class="tab-pane fade" id="history" role="tabpanel">
                    <h3 class="mb-4 fw-bold" style="color: var(--primary);">
                        <i class="fas fa-history me-2"></i>學習歷程
                    </h3>
                    <?php if (empty($history_by_year)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-folder-open fa-4x mb-3" style="opacity:0.3;"></i>
                            <p>目前沒有學習紀錄</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($history_by_year as $year => $courses): ?>
                            <div class="mb-5">
                                <h5 class="mb-4"><span class="year-badge"><i class="fas fa-calendar-alt me-2"></i><?php echo $year; ?> 年度</span></h5>
                                <div class="row g-4">
                                    <?php foreach ($courses as $course): ?>
                                        <div class="col-md-4">
                                            <div class="card course-card h-100" style="cursor:pointer;" onclick="goToMoodle('<?php echo $moodle_url; ?>/course/view.php?id=<?php echo $course['id']; ?>')">
                                                <div class="card-body">
                                                    <h6 class="card-title fw-bold"><?php echo $course['fullname']; ?></h6>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- 我的課程 Tab -->
                <div class="tab-pane fade" id="my-courses" role="tabpanel">
                    <h3 class="mb-4 fw-bold" style="color: var(--primary);">
                        <i class="fas fa-folder-open me-2"></i>我的課程
                    </h3>
                    <div class="row g-4">
                        <?php if (empty($my_courses_raw)): ?>
                            <div class="col-12 text-center py-5 text-muted">
                                <i class="fas fa-book-open fa-4x mb-3" style="opacity:0.3;"></i>
                                <p>您還沒有選修任何課程</p>
                                <button class="btn btn-primary mt-3" onclick="showTab('quick-enroll')" 
                                        style="background: var(--primary); border:none; padding: 12px 24px; border-radius: 30px;">
                                    <i class="fas fa-search me-2"></i>探索課程
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($my_courses_raw as $course): ?>
                                <div class="col-md-3">
                                    <div class="card course-card h-100" style="cursor:pointer;" onclick="goToMoodle('<?php echo $moodle_url; ?>/course/view.php?id=<?php echo $course['id']; ?>')">
                                        <div class="card-body">
                                            <h6 class="card-title fw-bold"><?php echo $course['fullname']; ?></h6>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 快速選課 Tab -->
                <div class="tab-pane fade" id="quick-enroll" role="tabpanel">
                    <h3 class="mb-4 fw-bold" style="color: var(--primary);">
                        <i class="fas fa-search-plus me-2"></i>探索課程
                    </h3>
                    <div class="filter-control-bar">
                        <div class="d-flex align-items-center gap-3">
                            <span class="fw-bold" style="color: var(--text-secondary);">課程類型</span>
                            <div class="filter-btn-group">
                                <button class="filter-btn active" onclick="filterCourses('all', this)">全部</button>
                                <button class="filter-btn" onclick="filterCourses('physical', this)">實體</button>
                                <button class="filter-btn" onclick="filterCourses('digital', this)">數位</button>
                            </div>
                        </div>
                        <div class="search-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" id="courseSearchInput" class="form-control" placeholder="搜尋課程名稱..." onkeyup="filterCourses()">
                        </div>
                    </div>
                    <div class="row g-4" id="available-courses-container">
                        <?php if (empty($available_courses)): ?>
                            <div class="col-12 text-center py-5 text-muted">
                                <i class="fas fa-box-open fa-4x mb-3" style="opacity:0.3;"></i>
                                <p>目前沒有可選修的新課程</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($available_courses as $course):
                                $catName = isset($course['categoryname']) ? $course['categoryname'] : (isset($course['displayname']) ? $course['displayname'] : '其他');
                                $typeTag = 'other';
                                $typeLabel = '一般';
                                if (strpos($catName, '實體') !== false) {
                                    $typeTag = 'physical';
                                    $typeLabel = '實體';
                                } elseif (strpos($catName, '數位') !== false || strpos($catName, '線上') !== false) {
                                    $typeTag = 'digital';
                                    $typeLabel = '數位';
                                }
                            ?>
                                <div class="col-md-6 course-item" data-type="<?php echo $typeTag; ?>" data-name="<?php echo strtolower($course['fullname']); ?>">
                                    <div class="card course-card h-100 position-relative">
                                        <div class="card-body d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="card-title fw-bold mb-2"><?php echo $course['fullname']; ?></h6>
                                                <small class="text-muted"><i class="fas fa-folder me-1"></i><?php echo $catName; ?></small>
                                            </div>
                                            <button class="btn btn-sm" 
                                                    style="background: var(--primary); color: white; border-radius: 20px; padding: 8px 20px;"
                                                    onclick="goToMoodle('<?php echo $moodle_url; ?>/enrol/index.php?id=<?php echo $course['id']; ?>')">
                                                <i class="fas fa-plus me-1"></i>選課
                                            </button>
                                        </div>
                                        <span class="category-label label-<?php echo $typeTag; ?>"><?php echo $typeLabel; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div id="no-result-msg" class="col-12 text-center py-5 text-muted" style="display:none;">
                            <i class="fas fa-search fa-3x mb-3" style="opacity:0.3;"></i>
                            <p>找不到符合條件的課程</p>
                        </div>
                    </div>
                </div>

                <!-- 必修進度 Tab -->
                <div class="tab-pane fade" id="curriculum" role="tabpanel">
                    <div class="curriculum-section">
                        <h3><i class="fas fa-tasks"></i> 必修課程進度</h3>

                        <div class="card border-0">
                            <div class="card-body p-0">
                                <table class="table curriculum-table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th width="30%">類別</th>
                                            <th>修課情況</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($curriculum_status)): ?>
                                            <?php foreach ($curriculum_status as $cat_name => $items): ?>
                                                <tr>
                                                    <td><strong><?php echo $cat_name; ?></strong></td>
                                                    <td>
                                                        <?php if (empty($items)): ?>
                                                            <span class="text-muted">無課程</span>
                                                        <?php else: ?>
                                                            <?php foreach ($items as $item): ?>
                                                                <?php if ($item['status'] == 'green'): ?>
                                                                    <i class="fas fa-check-circle status-icon icon-green" 
                                                                       title="<?php echo $item['fullname']; ?>: 已完成" data-bs-toggle="tooltip"></i>
                                                                <?php elseif ($item['status'] == 'yellow'): ?>
                                                                    <a href="#" onclick="goToMoodle('<?php echo $moodle_url; ?>/course/view.php?id=<?php echo $item['id']; ?>')" style="text-decoration:none;">
                                                                        <i class="fas fa-exclamation-circle status-icon icon-yellow" 
                                                                           title="<?php echo $item['fullname']; ?>: 未完成" data-bs-toggle="tooltip"></i>
                                                                    </a>
                                                                <?php elseif ($item['status'] == 'black'): ?>
                                                                    <i class="fas fa-times-circle status-icon icon-black" 
                                                                       title="<?php echo $item['fullname']; ?>: 未通過" data-bs-toggle="tooltip"></i>
                                                                <?php else: ?>
                                                                    <a href="#" onclick="goToMoodle('<?php echo $moodle_url; ?>/course/view.php?id=<?php echo $item['id']; ?>')" style="text-decoration:none;">
                                                                        <i class="far fa-play-circle status-icon icon-red" 
                                                                           title="<?php echo $item['fullname']; ?>: 尚未選課" data-bs-toggle="tooltip"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="2" class="text-center text-muted py-4">目前無必修課程設定</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="legend-box">
                            <div><i class="far fa-play-circle status-icon icon-red"></i> 尚未選課</div>
                            <div><i class="fas fa-exclamation-circle status-icon icon-yellow"></i> 已選課，未完成</div>
                            <div><i class="fas fa-check-circle status-icon icon-green"></i> 已完成</div>
                            <div><i class="fas fa-times-circle status-icon icon-black"></i> 未通過</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    <?php endif; ?>
</div>
