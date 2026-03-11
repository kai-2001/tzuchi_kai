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
    <student-nav active="courses"></student-nav>

    <!-- 主內容 -->
    <main class="layout-main">
        <div class="container">
            <!-- 頁面標題 -->
            <div class="page-header-v2">
                <h1 class="page-header-v2__title">我的課程</h1>
                <p class="page-header-v2__subtitle">管理您的修課清單和查看學習進度</p>
            </div>

            <!-- 搜尋和篩選 -->
            <div class="card-v2 mb-5 search-card">
                <div class="card-v2__body p-5">
                    <div class="d-flex gap-4 align-stretch flex-wrap">
                        <!-- 搜尋框 -->
                        <div class="flex-1 d-flex gap-2" style="min-width: 320px;">
                            <div style="flex: 1; position: relative;">
                                <input type="text" class="input-v2 search-input" placeholder="搜尋課程名稱、代碼或教授..."
                                    id="courseSearch">
                            </div>
                            <button class="btn-v2 btn-v2--outline-primary search-btn">
                                <i class="fas fa-search" style="margin-right: 6px;"></i>
                                搜尋
                            </button>
                        </div>

                        <!-- 篩選器容器 -->
                        <div class="d-flex gap-3 align-center">
                            <!-- 類型篩選 -->
                            <div style="min-width: 120px;">
                                <select class="input-v2 search-select" id="typeFilter">
                                    <option value="">全部類型</option>
                                    <option value="required">必修</option>
                                    <option value="domain">類別</option>
                                    <option value="elective">選修</option>
                                </select>
                            </div>

                            <!-- 分隔線 -->
                            <div style="width: 1px; height: 24px; background: var(--border-default);"></div>

                            <!-- 清除篩選 -->
                            <button class="btn-v2 btn-v2--ghost search-btn" onclick="clearFilters()">
                                <i class="fas fa-times" style="margin-right: 6px;"></i>
                                清除
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 分頁 -->
            <div class="tabs-v2">
                <ul class="tabs-v2__list">
                    <li class="tabs-v2__item">
                        <button class="tabs-v2__button tabs-v2__button--active" data-tab="in-progress">
                            進行中
                            <span class="badge-v2 badge-v2--primary" style="margin-left: 6px;"
                                id="badge-in-progress">0</span>
                        </button>
                    </li>
                    <li class="tabs-v2__item">
                        <button class="tabs-v2__button" data-tab="completed">
                            已完成
                            <span class="badge-v2 badge-v2--success" style="margin-left: 6px;"
                                id="badge-completed">0</span>
                        </button>
                    </li>
                    <li class="tabs-v2__item">
                        <button class="tabs-v2__button" data-tab="failed">
                            未通過
                            <span class="badge-v2"
                                style="margin-left: 6px; background-color: rgba(239, 68, 68, 0.1); color: var(--error);"
                                id="badge-failed">0</span>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- 分頁內容 -->

            <!-- 修習中 -->
            <div class="tabs-v2__panel tabs-v2__panel--active" id="tab-in-progress">
                <div style="display: flex; flex-direction: column; gap: var(--space-3);" id="in-progress-container">
                    <div class="text-center py-5 text-muted w-100">
                        <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                        <p>載入課程資料中...</p>
                    </div>
                </div>
            </div>

            <!-- 已完成 -->
            <div class="tabs-v2__panel" id="tab-completed">
                <div style="display: flex; flex-direction: column; gap: var(--space-3);" id="completed-container">
                    <div class="text-center py-5 text-muted w-100">
                        <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                        <p>載入課程資料中...</p>
                    </div>
                </div>
            </div>

            <!-- 未通過 -->
            <div class="tabs-v2__panel" id="tab-failed">
                <div style="display: flex; flex-direction: column; gap: var(--space-3);" id="failed-container">
                    <div class="text-center py-5 text-muted w-100">
                        <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                        <p>載入課程資料中...</p>
                    </div>
                </div>
            </div>
    </main>

    <script>
        // ======= 資料取得與渲染邏輯 =======
        const moodleUrl = window.PortalConfig ? window.PortalConfig.moodleUrl : '';
        let globalCoursesData = []; // 保存所有課程供搜尋/篩選使用
        let curriculumDataCache = null;

        function fetchSubData(type) {
            const urlParams = new URLSearchParams(window.location.search);
            const refreshParam = urlParams.has('refresh') ? '&refresh=1' : '';
            return fetch(`api/get_moodle_data.php?type=${type}${refreshParam}`, { method: 'GET', credentials: 'same-origin' })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    return response.json();
                })
                .then(result => {
                    if (!result.success) throw new Error(result.message || 'Unknown error');
                    return result.data;
                });
        }

        // 啟動 API 請求 (轉換為單一請求模式，避開本機伺服器的連線序列化佔用)
        function loadCoursesData() {
            fetchSubData('all')
                .then(data => {
                    const courses = data.my_courses_raw || [];
                    const curriculum = data.curriculum_status || null;
                    const grades = data.grades || [];

                    globalCoursesData = courses;
                    curriculumDataCache = curriculum;

                    renderCoursesData(courses, curriculum, grades);
                    filterCourses(); // 初始化過濾
                })
                .catch(e => {
                    console.error("Failed to fetch all data:", e);
                    document.getElementById('in-progress-container').innerHTML = `<div class="text-danger w-100 p-4">載入失敗: ${e.message}</div>`;
                    document.getElementById('completed-container').innerHTML = `<div class="text-danger w-100 p-4">載入失敗: ${e.message}</div>`;
                    document.getElementById('failed-container').innerHTML = `<div class="text-danger w-100 p-4">載入失敗: ${e.message}</div>`;
                });
        }

        function renderCoursesData(courses, curriculum, grades) {
            const completed = courses.filter(c => c.progress >= 100);
            const inProgress = courses.filter(c => c.progress < 100 && !c.failed);
            const failed = courses.filter(c => c.failed === true);

            // 更新 Badge
            document.getElementById('badge-in-progress').textContent = inProgress.length;
            document.getElementById('badge-completed').textContent = completed.length;
            document.getElementById('badge-failed').textContent = failed.length;



            // 渲染修習中
            const inProgressContainer = document.getElementById('in-progress-container');
            if (inProgress.length === 0) {
                inProgressContainer.innerHTML = '<div class="text-center py-5 text-muted w-100">目前沒有修習中的課程</div>';
            } else {
                inProgressContainer.innerHTML = inProgress.map(c => renderCourseListItem(c, grades)).join('');
            }

            // 渲染已完成
            const completedContainer = document.getElementById('completed-container');
            if (completed.length === 0) {
                completedContainer.innerHTML = '<div class="text-center py-5 text-muted w-100">目前沒有已完成的課程</div>';
            } else {
                completedContainer.innerHTML = completed.map(c => renderCourseListItem(c, grades)).join('');
            }

            // 渲染未通過
            const failedContainer = document.getElementById('failed-container');
            if (failed.length === 0) {
                failedContainer.innerHTML = '<div class="text-center py-5 text-muted w-100">目前沒有未通過的課程</div>';
            } else {
                // 未通過的卡片可以使用與修習中相同的 render 方式，或者套用不同的樣式
                failedContainer.innerHTML = failed.map(c => renderCourseListItem(c, grades)).join('');
            }
        }

        function getCourseTags(courseName, categoryName) {
            let tagsHtml = '';

            // Extract the child category from the display_category (e.g. "台北類別 - 營養子1" -> "營養子1")
            let domainName = categoryName || '選擇課程';
            if (domainName.includes(' - ')) {
                domainName = domainName.split(' - ').pop().trim();
            }

            // Return a single tag using the primary blue style
            tagsHtml = `<span class="course-tag course-tag--elective">${domainName}</span>`;

            return tagsHtml;
        }

        function renderCourseCard(course) {
            const courseUrl = `${moodleUrl}/course/view.php?id=${course.id}`;
            const tags = getCourseTags(course.fullname, course.display_category);
            const dateStr = course.startdate ? new Date(course.startdate * 1000).toLocaleDateString('zh-TW') : '未定';
            const progress = course.progress ? parseFloat(course.progress).toFixed(0) : 0;
            const progressColor = progress >= 80 ? 'progress-bar__fill--success' : (progress < 30 ? 'bg-danger' : '');

            return `
                <div class="course-card js-searchable-item" data-search="${course.fullname.toLowerCase()}" data-tags="${tags.replace(/<[^>]*>?/gm, ' ')}">
                    <div class="course-card__header"><div class="course-card__tags">${tags}</div></div>
                    <h3 class="course-card__title">${course.fullname}</h3>
                    <div class="course-card__meta">
                        <span class="course-card__meta-item"><i class="fas fa-folder"></i> ${course.display_category || '無分類'}</span>
                        <span class="course-card__meta-item"><i class="fas fa-calendar"></i> 開始: ${dateStr}</span>
                    </div>
                    <div class="course-card__progress">
                        <div class="course-card__progress-label"><span>課程進度</span><span>${progress}%</span></div>
                        <div class="progress-bar"><div class="progress-bar__fill ${progressColor}" style="width: ${progress}%;"></div></div>
                    </div>
                    <div class="course-card__actions mt-3">
                        <button type="button" onclick="goToMoodle('${courseUrl}')" class="btn-v2 btn-v2--outline-info" style="flex: 1; text-align: center;">
                            <i class="fas fa-arrow-right"></i> 進入課程
                        </button>
                    </div>
                </div>
            `;
        }

        function renderCourseListItem(course, grades) {
            const courseUrl = `${moodleUrl}/course/view.php?id=${course.id}`;
            const tagsHtml = getCourseTags(course.fullname, course.display_category);
            const dateStr = course.startdate ? new Date(course.startdate * 1000).getFullYear() + ' 年' : '-';

            const isCompleted = course.progress >= 100 && !course.failed;
            const isFailed = course.failed === true;

            let statusTag = '';
            let bgStyle = '';

            if (isCompleted) {
                statusTag = '<span class="course-tag" style="background-color: rgba(34, 197, 94, 0.1); color: var(--success);">已完成</span>';
            } else if (isFailed) {
                statusTag = '<span class="course-tag" style="background-color: rgba(239, 68, 68, 0.1); color: var(--error);">未通過</span>';
                bgStyle = 'background: rgba(239, 68, 68, 0.05);';
            } else {
                statusTag = '<span class="course-tag course-tag--warning">進行中</span>';
            }

            // Deadline format
            let deadlineStr = '';
            if (course.enddate && course.enddate > 0) {
                const endDate = new Date(course.enddate * 1000);
                deadlineStr = ` <span style="margin-left: 8px; font-weight: 500; font-size: 13px; color: var(--text-muted);"><i class="fas fa-clock mr-1"></i> 期限: ${endDate.getFullYear()}/${String(endDate.getMonth() + 1).padStart(2, '0')}/${String(endDate.getDate()).padStart(2, '0')}</span>`;
            }

            return `
                <div class="course-list-item js-searchable-item" style="${bgStyle}" data-id="${course.id}" data-search="${course.fullname.toLowerCase()}" data-tags="${tagsHtml.replace(/<[^>]*>?/gm, ' ')}">
                    <div class="course-list-item__content">
                        <div class="course-list-item__title">${course.fullname} ${tagsHtml}</div>
                        <div class="course-list-item__meta">${dateStr} • <i class="fas fa-folder text-muted mr-1"></i> ${course.display_category || '無分類'} ${deadlineStr}</div>
                    </div>
                    <div class="course-list-item__grade">
                        ${statusTag}
                        <button type="button" onclick="goToMoodle('${courseUrl}')" class="btn-v2 btn-v2--outline-info btn-v2--sm" style="margin-left:8px;">
                            <i class="fas fa-arrow-right"></i> 進入
                        </button>
                    </div>
                </div>
            `;
        }

        // 分頁切換功能
        document.addEventListener('DOMContentLoaded', function () {
            // Load Date
            loadCoursesData();

            const tabButtons = document.querySelectorAll('.tabs-v2__button');
            const tabPanels = document.querySelectorAll('.tabs-v2__panel');

            tabButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const tabId = this.dataset.tab;

                    // 移除所有 active class
                    tabButtons.forEach(btn => btn.classList.remove('tabs-v2__button--active'));
                    tabPanels.forEach(panel => panel.classList.remove('tabs-v2__panel--active'));

                    // 加上 active class
                    this.classList.add('tabs-v2__button--active');
                    document.getElementById('tab-' + tabId).classList.add('tabs-v2__panel--active');
                });
            });

            // 搜索功能
            const searchInput = document.getElementById('courseSearch');
            const typeFilter = document.getElementById('typeFilter');

            function filterCourses() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedType = typeFilter.value;

                const items = document.querySelectorAll('.js-searchable-item');

                items.forEach(item => {
                    const searchData = item.getAttribute('data-search') || '';
                    const courseId = item.getAttribute('data-id');

                    let actualType = 'elective';

                    if (curriculumDataCache) {
                        const curData = curriculumDataCache.status || curriculumDataCache;
                        Object.entries(curData).forEach(([catName, itemsArray]) => {
                            if (!Array.isArray(itemsArray)) return;
                            itemsArray.forEach(c => {
                                if (c.id == courseId || searchData.includes(c.fullname.toLowerCase())) {
                                    actualType = c.is_mandatory_section ? 'required' : 'domain';
                                }
                            });
                        });
                    }

                    const matchesSearch = searchData.includes(searchTerm);
                    const matchesType = !selectedType || actualType === selectedType;

                    if (matchesSearch && matchesType) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }

            // Make filter globally accessible
            window.filterCourses = filterCourses;

            // 綁定事件監聽器
            const searchBtn = document.querySelector('.search-btn'); // 取得搜尋按鈕

            searchBtn.addEventListener('click', filterCourses);
            searchInput.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    filterCourses();
                }
            });

            typeFilter.addEventListener('change', filterCourses);
        });

        // 清除篩選
        function clearFilters() {
            document.getElementById('courseSearch').value = '';
            document.getElementById('typeFilter').value = '';
            if (window.filterCourses) window.filterCourses();
        }
    </script>



    <!-- 全域功能與 SSO 登入處理 -->
    <script src="<?php echo $web_root; ?>/assets/js/student-main.js?v=<?php echo time(); ?>"></script>
</body>

</html>