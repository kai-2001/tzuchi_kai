<div class="tab-pane fade" id="quick-enroll" role="tabpanel">
    <h3 class="mb-4 fw-bold" style="color: var(--primary);">
        <i class="fas fa-search-plus me-2"></i>探索課程
    </h3>
    <div class="filter-control-bar">
        <div class="d-flex align-items-center gap-3">
            <span class="fw-bold" style="color: var(--text-secondary);">課程類型</span>
            <div class="filter-btn-group" id="course-type-filters">
                <!-- 動態載入分類按鈕 -->
                <button class="filter-btn active" data-type="all">全部</button>
            </div>
        </div>
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="courseSearchInput" class="form-control" placeholder="搜尋課程名稱..."
                onkeyup="filterCourses()">
        </div>
    </div>
    <div class="row g-4" id="available-courses-container">
        <!-- 非同步載入 -->
        <div class="col-12">
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 150px;"></div>
            </div>
        </div>
    </div>
</div>