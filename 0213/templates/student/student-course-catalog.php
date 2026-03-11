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
    <student-nav active="catalog"></student-nav>

    <!-- 主內容 -->
    <main class="layout-main">
        <div class="container">
            <!-- 頁面標題與搜尋 -->
            <div class="page-header-v2" style="margin-bottom: var(--space-5);">
                <div>
                    <h1 class="page-header-v2__title">選課中心</h1>
                </div>
            </div>

            <!-- 搜尋和篩選區域 -->
            <div class="card-v2"
                style="margin-bottom: var(--space-5); border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                <div class="card-v2__body" style="padding: var(--space-5);">
                    <!-- 搜尋框和按鈕 -->
                    <div style="display: flex; gap: var(--space-3); margin-bottom: var(--space-4);">
                        <div style="flex: 1; position: relative;">
                            <input type="text" id="courseSearch" class="input-v2" placeholder="搜尋課程名稱、代碼或教授"
                                style="width: 100%; padding: 0 20px; height: 50px; font-size: 15px; border-radius: var(--radius-md); border: 1.5px solid var(--border-default); transition: all 0.2s;"
                                onfocus="this.style.borderColor='var(--brand-primary)'; this.style.boxShadow='0 0 0 3px rgba(37, 99, 235, 0.1)'"
                                onblur="this.style.borderColor='var(--border-default)'; this.style.boxShadow='none'"
                                onkeypress="if(event.key === 'Enter') applyFilters()">
                        </div>
                        <button class="btn-v2 btn-v2--outline-primary" onclick="applyFilters()"
                            style="height: 50px; padding: 0 32px; font-size: 15px; font-weight: 600; border-radius: var(--radius-md); white-space: nowrap;">
                            <i class="fas fa-search" style="margin-right: 8px;"></i>
                            搜尋
                        </button>
                    </div>

                    <div id="advanced-filters" style="padding-top: var(--space-4);">
                        <!-- 篩選器排列 -->
                        <div
                            style="display: flex; flex-wrap: wrap; gap: var(--space-4); align-items: flex-end; margin-bottom: var(--space-4);">
                            <div style="flex: 1; min-width: 160px; max-width: 240px;">
                                <label
                                    style="display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">院區</label>
                                <select id="campusSelect" class="input-v2"
                                    style="width: 100%; height: 44px; font-size: 14px; padding: 0 12px; padding-right: 36px; border-radius: var(--radius-md); border: 1.5px solid var(--border-default); cursor: pointer; background-position: right 12px center;">
                                    <option value="">全部院區</option>
                                </select>
                            </div>

                            <div style="flex: 1; min-width: 160px; max-width: 240px;">
                                <label
                                    style="display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">課程類別</label>
                                <select id="categorySelect" class="input-v2"
                                    style="width: 100%; height: 44px; font-size: 14px; padding: 0 12px; padding-right: 36px; border-radius: var(--radius-md); border: 1.5px solid var(--border-default); cursor: pointer; background-position: right 12px center;">
                                    <option value="">全部類別</option>
                                </select>
                            </div>
                        </div>

                        <!-- 選項和清除按鈕 -->
                        <div
                            style="display: flex; gap: var(--space-4); align-items: center; justify-content: space-between; flex-wrap: wrap;">
                            <label class="form-checkbox-v2"
                                style="margin: 0; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" id="chkExcluded">
                                <span style="font-size: 13px;">排除已修課程</span>
                            </label>

                            <button class="btn-v2 btn-v2--ghost" onclick="clearFilters()"
                                style="font-size: 13px; height: 36px; padding: 0 16px;">
                                <i class="fas fa-redo" style="margin-right: 6px; font-size: 12px;"></i>
                                清除篩選
                            </button>
                        </div>
                    </div>
                </div>
            </div>


            <!-- 結果統計和排序 -->
            <div
                style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--space-3); margin-bottom: var(--space-3); padding: 0 var(--space-1);">
                <!-- 結果統計 -->
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <i class="fas fa-list" style="color: var(--text-muted); font-size: 12px;"></i>
                    <span style="font-size: 13px; color: var(--text-secondary);">找到</span>
                    <span id="courseCount"
                        style="font-size: 16px; font-weight: 700; color: var(--brand-primary);">...</span>
                    <span style="font-size: 13px; color: var(--text-secondary);">門課程</span>
                </div>

                <!-- 排序選項 -->
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <i class="fas fa-sort-amount-down" style="color: var(--text-muted); font-size: 12px;"></i>
                    <span style="font-size: 13px; color: var(--text-secondary);">排序：</span>
                    <select id="sortSelect" class="input-v2" onchange="applyFilters()"
                        style="min-width: 130px; height: 34px; font-size: 13px; padding: 0 10px; padding-right: 28px; border-radius: var(--radius-md); border: 1.5px solid var(--border-default); cursor: pointer; background-position: right 10px center;">
                        <option value="name_asc">課程名稱 (正序)</option>
                        <option value="name_desc">課程名稱 (倒序)</option>
                        <option value="time_new">最新加入</option>
                    </select>
                </div>
            </div>

            <!-- 課程清單 -->
            <div id="catalog-courses-container">
                <div class="text-center py-5 text-muted col-12" style="grid-column: 1 / -1;">
                    <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                    <p>載入課程中...</p>
                </div>
            </div>

            <!-- 分頁導航 -->
            <div style="display: flex; justify-content: center; margin-top: var(--space-6);">
                <div style="display: flex; gap: var(--space-2);">
                    <button class="btn-v2 btn-v2--ghost btn-v2--icon" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="btn-v2 btn-v2--primary">1</button>
                    <button class="btn-v2 btn-v2--ghost">2</button>
                    <button class="btn-v2 btn-v2--ghost">3</button>
                    <button class="btn-v2 btn-v2--ghost">4</button>
                    <button class="btn-v2 btn-v2--ghost btn-v2--icon">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        const moodleUrl = window.PortalConfig ? window.PortalConfig.moodleUrl : '';
        let curriculum = null;
        let requiredCoursesDict = {};

        let currentPage = 1;
        const itemsPerPage = 12;
        let searchTimeout = null;

        document.addEventListener('DOMContentLoaded', function () {
            const advancedFilters = document.getElementById('advanced-filters');
            if (advancedFilters) {
                advancedFilters.classList.remove('hidden');
            }

            // Bind events for Server-Side Search
            const searchEl = document.getElementById('courseSearch');
            if (searchEl) {
                // Remove debounce, rely strictly on Enter key due to user feedback
                searchEl.addEventListener('keypress', (event) => {
                    if (event.key === 'Enter') {
                        applyFilters();
                    }
                });
            }

            const campusEl = document.getElementById('campusSelect');
            if (campusEl) campusEl.addEventListener('change', () => { currentPage = 1; loadCatalogData(); });
            const catEl = document.getElementById('categorySelect');
            if (catEl) catEl.addEventListener('change', () => { currentPage = 1; loadCatalogData(); });
            const chkExcludedEl = document.getElementById('chkExcluded');
            if (chkExcludedEl) chkExcludedEl.addEventListener('change', () => { currentPage = 1; loadCatalogData(); });
            const sortEl = document.getElementById('sortSelect');
            if (sortEl) sortEl.addEventListener('change', () => { currentPage = 1; loadCatalogData(); });

            // Initial load requires fetching curriculum first to build options, then catalog
            initPage();
        });

        function fetchSubData(type) {
            return fetch(`api/get_moodle_data.php?type=${type}`, { method: 'GET', credentials: 'same-origin' })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    return response.json();
                })
                .then(result => {
                    if (!result.success) throw new Error(result.message || 'Unknown error');
                    return result.data;
                });
        }

        // Initialize Option Dropdowns once
        function initPage() {
            fetchSubData('curriculum').then(curriculumResult => {
                curriculum = curriculumResult.curriculum_status || null;
                if (curriculum) {
                    const curriculumData = curriculum.status || curriculum;
                    Object.values(curriculumData).forEach(items => {
                        if (!Array.isArray(items)) return;
                        items.forEach(c => {
                            if (c.is_mandatory_section) {
                                requiredCoursesDict[c.fullname] = true;
                            }
                        });
                    });
                }

                // Initial fetch to populate filters (using a large limit just for category structure)
                fetch(`api/get_catalog_courses.php?limit=2000`)
                    .then(res => res.json())
                    .then(res => {
                        if (res.success && res.data) {
                            populateFilters(res.data);
                        }
                        // Now load the actual first page
                        loadCatalogData();
                    }).catch(() => loadCatalogData());
            }).catch(e => {
                console.error("Failed to load curriculum", e);
                loadCatalogData();
            });
        }

        function populateFilters(courses) {
            const categorySelect = document.getElementById('categorySelect');
            const campusSelect = document.getElementById('campusSelect');
            if (categorySelect && campusSelect) {
                const categories = new Set();
                const campuses = new Set();
                courses.forEach(c => {
                    const parentCatName = c.parent_category;
                    const childCatName = c.child_category || parentCatName;

                    if (parentCatName && parentCatName !== '其他') campuses.add(parentCatName);
                    if (childCatName && childCatName !== '選修' && childCatName !== '其他') categories.add(childCatName);
                });

                let campusHtml = '<option value="">全部院區</option>';
                campuses.forEach(campusName => {
                    let displayName = campusName.includes('類別') ? campusName.replace('類別', '') : campusName;
                    campusHtml += `<option value="${campusName}">${displayName}</option>`;
                });
                campusSelect.innerHTML = campusHtml;

                let optionsHtml = '<option value="">全部類別</option>';
                categories.forEach(catName => {
                    optionsHtml += `<option value="${catName}">${catName}</option>`;
                });
                categorySelect.innerHTML = optionsHtml;

                // Auto-select from URL
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('tab') === 'domain' || urlParams.get('category') === 'domain') {
                    categorySelect.value = '__REQUIRED__';
                }
            }
        }

        function applyFilters() {
            currentPage = 1;
            loadCatalogData();
        }

        function loadCatalogData() {
            const container = document.getElementById('catalog-courses-container');
            container.innerHTML = `
                <div class="text-center py-5 text-muted col-12" style="grid-column: 1 / -1;">
                    <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                    <p>載入課程中...</p>
                </div>
            `;

            // Build query parameters
            const searchEl = document.getElementById('courseSearch');
            const campusEl = document.getElementById('campusSelect');
            const catEl = document.getElementById('categorySelect');
            const chkExcludedEl = document.getElementById('chkExcluded');
            const sortEl = document.getElementById('sortSelect');

            const params = new URLSearchParams();
            params.append('page', currentPage);
            params.append('limit', itemsPerPage);

            if (searchEl && searchEl.value) params.append('search', searchEl.value);
            if (campusEl && campusEl.value) params.append('campus', campusEl.value);
            if (catEl && catEl.value) params.append('category', catEl.value);
            if (sortEl && sortEl.value) params.append('sort', sortEl.value);
            if (chkExcludedEl && chkExcludedEl.checked) params.append('exclude_enrolled', 'true');

            fetch(`api/get_catalog_courses.php?${params.toString()}`)
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        updateStats(result.total);
                        renderCourses(result.data, result.total);
                    } else {
                        container.innerHTML = '<div class="text-center py-5 text-muted col-12">無法載入課程</div>';
                    }
                })
                .catch(err => {
                    console.error("API Error", err);
                    container.innerHTML = '<div class="text-center py-5 text-muted col-12">發生錯誤，請稍後再試</div>';
                });
        }

        function updateStats(total) {
            const statsContainer = document.querySelector('.fa-list')?.parentElement;
            if (statsContainer) {
                statsContainer.innerHTML = `
                    <i class="fas fa-list" style="color: var(--text-muted); font-size: 12px;"></i>
                    <span style="font-size: 13px; color: var(--text-secondary);">找到</span>
                    <span style="font-size: 16px; font-weight: 700; color: var(--brand-primary);">${total}</span>
                    <span style="font-size: 13px; color: var(--text-secondary);">門課程</span>
                `;
            }
        }

        function renderCourses(paginatedCourses, totalRecords) {
            const container = document.getElementById('catalog-courses-container');

            if (paginatedCourses.length === 0) {
                container.innerHTML = '<div class="text-center py-5 text-muted col-12" style="grid-column: 1 / -1;">沒有符合條件的課程</div>';
                renderPagination(totalRecords);
                return;
            }

            let html = '<div class="d-flex flex-col gap-3">';
            paginatedCourses.forEach(course => {
                const isEnrolled = course.is_enrolled;
                const isCompleted = course.completed;
                const isFailed = course.is_failed;
                const catName = course.child_category || course.parent_category || '無分類';

                let tagsHtml = `<span class="course-tag course-tag--elective">${catName}</span>`;
                let statusTag = '';
                let actionHtml = '';

                if (isCompleted) {
                    statusTag = '<span class="course-tag" style="background-color: rgba(34, 197, 94, 0.1); color: var(--success);">已完成</span>';
                    actionHtml = `
                        <button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm" style="margin-left:8px;">
                            <i class="fas fa-arrow-right"></i> 進入
                        </button>
                    `;
                } else if (isFailed) {
                    statusTag = '<span class="course-tag" style="background-color: #ef44441a; color: #ef4444;">未通過</span>';
                    actionHtml = `
                        <button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm" style="margin-left:8px;">
                            <i class="fas fa-arrow-right"></i> 進入
                        </button>
                    `;
                } else if (isEnrolled) {
                    statusTag = '<span class="course-tag course-tag--warning">進行中</span>';
                    actionHtml = `
                        <button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm" style="margin-left:8px;">
                            <i class="fas fa-arrow-right"></i> 進入
                        </button>
                    `;
                } else {
                    statusTag = '<span class="course-tag" style="background-color: rgba(107, 114, 128, 0.1); color: var(--text-muted);">未選課</span>';
                    actionHtml = `
                        <button type="button" onclick="directEnrolCourse(${course.id})" class="btn-v2 btn-v2--primary btn-v2--sm" style="margin-left:8px;">
                            <i class="fas fa-plus"></i> 選課
                        </button>
                    `;
                }

                html += `
                    <div class="course-list-item">
                        <div class="course-list-item__content">
                            <div class="course-list-item__title">${course.fullname} ${tagsHtml}</div>
                            <div class="course-list-item__meta"><i class="fas fa-folder text-muted mr-1"></i> ${course.parent_category}</div>
                        </div>
                        <div class="course-list-item__grade">
                            ${statusTag}
                            ${actionHtml}
                        </div>
                    </div>
                `;
            });
            html += '</div>';

            container.innerHTML = html;
            renderPagination(totalRecords);
        }

        function renderPagination(totalRecords) {
            const totalPages = Math.ceil(totalRecords / itemsPerPage) || 1;
            const paginationEl = document.querySelector('.fa-chevron-left');
            if (!paginationEl) return;
            const paginationContainer = paginationEl.closest('div').parentElement;

            let pHtml = `
                <div style="display: flex; gap: var(--space-2);">
                    <button class="btn-v2 btn-v2--ghost btn-v2--icon" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                        <i class="fas fa-chevron-left"></i>
                    </button>
            `;

            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, startPage + 4);
            startPage = Math.max(1, endPage - 4);

            for (let i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    pHtml += `<button class="btn-v2 btn-v2--primary">${i}</button>`;
                } else {
                    pHtml += `<button class="btn-v2 btn-v2--ghost" onclick="changePage(${i})">${i}</button>`;
                }
            }

            pHtml += `
                    <button class="btn-v2 btn-v2--ghost btn-v2--icon" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            `;

            paginationContainer.innerHTML = pHtml;
        }

        function changePage(page) {
            const totalPages = Math.ceil(parseInt(document.querySelector('.fa-list')?.parentElement.children[2].textContent || 0) / itemsPerPage) || 1;
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                loadCatalogData();
                document.getElementById('catalog-courses-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // 清除篩選功能
        function clearFilters() {
            const searchInput = document.getElementById('courseSearch');
            if (searchInput) searchInput.value = '';

            const selects = ['campusSelect', 'categorySelect'];
            selects.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.selectedIndex = 0;
            });

            const checkboxes = ['chkExcluded'];
            checkboxes.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.checked = false;
            });

            currentPage = 1;
            loadCatalogData();
        }
    </script>



    <!-- 全域功能與 SSO 登入處理 -->
    <script src="<?php echo $web_root; ?>/assets/js/student-main.js?v=<?php echo time(); ?>"></script>
</body>

</html>