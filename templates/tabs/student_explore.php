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
                <button class="filter-btn optional-filter-btn" data-type="optional" id="optionalCoursesBtn"
                    style="display: none;" onclick="showOptionalCourses()">
                    <i class="fas fa-star me-1"></i>可選修 <span class="optional-count-badge"
                        id="optionalCountBadge">0</span>
                </button>
            </div>
        </div>
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="courseSearchInput" class="form-control" placeholder="搜尋課程名稱..."
                onkeyup="filterCourses()">
        </div>
    </div>

    <!-- 可選修課程區塊（預設隱藏） -->
    <div id="optional-courses-section" class="optional-courses-section" style="display: none;">
        <div class="optional-courses-header">
            <h5><i class="fas fa-star text-warning me-2"></i>專屬可選修課程</h5>
            <p class="text-muted mb-0">以下課程已為您開放選修，點擊報名即可加入</p>
        </div>
        <div class="row g-4" id="optional-courses-container">
            <!-- 非同步載入 -->
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

<style>
    /* 可選修課程篩選按鈕 */
    .optional-filter-btn {
        background: linear-gradient(135deg, #fbbf24, #f59e0b) !important;
        color: #451a03 !important;
        border: none !important;
    }

    .optional-filter-btn:hover,
    .optional-filter-btn.active {
        background: linear-gradient(135deg, #f59e0b, #d97706) !important;
    }

    .optional-count-badge {
        background: rgba(255, 255, 255, 0.3);
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.75rem;
        margin-left: 4px;
    }

    /* 可選修課程區塊 */
    .optional-courses-section {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border: 2px solid #fbbf24;
        border-radius: 16px;
        padding: 20px 24px;
        margin-bottom: 24px;
    }

    .optional-courses-header {
        margin-bottom: 16px;
    }

    .optional-courses-header h5 {
        margin: 0 0 4px 0;
        font-weight: 600;
        color: #92400e;
    }

    /* 可選修課程卡片 */
    .optional-course-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        transition: all 0.2s;
        border: 2px solid transparent;
    }

    .optional-course-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        border-color: #fbbf24;
    }

    .optional-course-card h6 {
        margin: 0 0 8px 0;
        font-weight: 600;
        color: #1e293b;
    }

    .optional-course-card p {
        margin: 0 0 12px 0;
        font-size: 0.85rem;
        color: #64748b;
    }

    .btn-enrol-optional {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: #451a03;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
    }

    .btn-enrol-optional:hover {
        transform: scale(1.02);
        box-shadow: 0 4px 12px rgba(251, 191, 36, 0.4);
    }
</style>

<script>
    // 載入可選修課程
    (function () {
        async function loadOptionalCourses() {
            try {
                const res = await fetch(PortalConfig.webRoot + '/api/student/get_optional_courses.php');
                const data = await res.json();

                if (data.success && data.courses && data.courses.length > 0) {
                    const section = document.getElementById('optional-courses-section');
                    const container = document.getElementById('optional-courses-container');
                    const filterBtn = document.getElementById('optionalCoursesBtn');
                    const countBadge = document.getElementById('optionalCountBadge');

                    // 顯示篩選按鈕
                    filterBtn.style.display = 'inline-flex';
                    countBadge.textContent = data.courses.length;

                    // 渲染課程卡片
                    container.innerHTML = data.courses.map(course => `
                    <div class="col-md-6 col-lg-4">
                        <div class="optional-course-card">
                            <h6>${course.fullname}</h6>
                            <p>${course.shortname}</p>
                            <button class="btn-enrol-optional" onclick="enrolOptionalCourse(${course.id})">
                                <i class="fas fa-plus-circle me-1"></i> 報名參加
                            </button>
                        </div>
                    </div>
                `).join('');

                    // 設定「全部」按鈕點擊恢復
                    document.querySelector('[data-type="all"]').onclick = function () {
                        const allBtns = document.querySelectorAll('#course-type-filters .filter-btn');
                        allBtns.forEach(btn => btn.classList.remove('active'));
                        this.classList.add('active');

                        section.style.display = 'none';
                        document.getElementById('available-courses-container').style.display = 'flex';
                    };

                    // 把 section 和 container 存成全域變數供 showOptionalCourses 使用
                    window._optionalSection = section;
                    window._optionalFilterBtn = filterBtn;
                }
            } catch (e) {
                console.error('載入可選修課程失敗', e);
            }
        }

        // 頁面載入後執行
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadOptionalCourses);
        } else {
            setTimeout(loadOptionalCourses, 500);
        }
    })();

    // 顯示可選修課程區塊
    function showOptionalCourses() {
        const allBtns = document.querySelectorAll('#course-type-filters .filter-btn');
        allBtns.forEach(btn => btn.classList.remove('active'));

        const filterBtn = document.getElementById('optionalCoursesBtn');
        if (filterBtn) filterBtn.classList.add('active');

        // 顯示可選修區塊，隱藏一般課程
        const section = document.getElementById('optional-courses-section');
        if (section) section.style.display = 'block';
        const availContainer = document.getElementById('available-courses-container');
        if (availContainer) availContainer.style.display = 'none';
    }

    // 報名可選修課程
    async function enrolOptionalCourse(courseId) {

        try {
            const res = await fetch(PortalConfig.webRoot + '/api/student/enrol_course.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `course_id=${courseId}`
            });
            const data = await res.json();

            if (data.success) {
                alert('報名成功！');
                location.reload();
            } else {
                alert('報名失敗：' + (data.error || '未知錯誤'));
            }
        } catch (e) {
            alert('報名發生錯誤');
        }
    }
</script>