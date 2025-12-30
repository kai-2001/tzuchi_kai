<div id="section-course-management" class="page-section">
    <div class="section-header">
        <h2><i class="fas fa-tasks"></i> 課程管理</h2>
        <p class="section-subtitle">管理您教授的所有課程</p>
    </div>

    <!-- 搜尋欄 -->
    <div class="search-bar-container mb-4">
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="teacher-course-search" placeholder="搜尋課程名稱..."
                oninput="filterTeacherCourses(this.value)">
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