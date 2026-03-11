<!-- 非同步資料載入腳本 -->
<script>
    (function () {
        'use strict';

        const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
        const isCourseCreator = <?php echo (isset($_SESSION['is_coursecreator']) && $_SESSION['is_coursecreator']) ? 'true' : 'false'; ?>;

        if (isAdmin) {
            return; // 管理員不執行
        }

        const loading3dHtml = `
            <div class="loader-3d-portal">
                <div class="portal-ring ring-outer"></div>
                <div class="portal-ring ring-inner"></div>
                <div class="portal-core"></div>
                <div class="portal-text">Loading...</div>
            </div>
        `;

        // 3D Loading CSS has been moved to assets/css/style.css

        /**
         * 建立課程卡片 HTML (共用函式)
         * @param {Object} course 課程物件
         * @param {string} moodleUrl Moodle 基礎網址
         * @param {string} type 'my_courses'(學習中/已完成) 或 'available'(選課)
         */
        function createCourseCard(course, moodleUrl, type) {
            const mainCat = course.parent_category || course.categoryname || '其他';
            const subCat = (course.child_category && course.child_category !== mainCat) ? course.child_category : '';
            const progress = course.progress || 0;

            // 處理分類 key (用於篩選)
            let typeKey = mainCat.replace(/[^a-zA-Z0-9\u4e00-\u9fa5]/g, '');
            if (!typeKey) typeKey = 'cat-' + Math.abs(mainCat.split('').reduce((a, b) => { a = ((a << 5) - a) + b.charCodeAt(0); return a & a }, 0));

            // 狀態與按鈕邏輯
            let statusHtml = '';
            let buttonHtml = '';

            if (type === 'my_courses' || course.is_enrolled) {
                // 已選修/我的課程
                statusHtml = `<span class="badge ${progress >= 100 ? 'bg-success' : 'bg-warning'} ms-2" style="font-size: 10px;">
                                ${progress >= 100 ? '已完成' : '學習中 (' + progress.toFixed(2) + '%)'}
                              </span>`;
                buttonHtml = `<button class="btn btn-sm" 
                                      style="background: #f1f5f9; color: var(--primary); border: 1px solid var(--primary); border-radius: 20px; padding: 8px 20px;"
                                      onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')">
                                  <i class="fas fa-sign-in-alt me-1"></i>進入課程
                              </button>`;
            } else {
                // 可選修課程
                statusHtml = `<span class="badge bg-secondary ms-2" style="font-size: 10px; opacity: 0.7;">未選課</span>`;
                buttonHtml = `<button class="btn btn-sm" 
                                      style="background: var(--primary); color: white; border-radius: 20px; padding: 8px 20px;"
                                      onclick="directEnrolCourse(${course.id})">
                                  <i class="fas fa-plus me-1"></i>選課
                              </button>`;
            }

            return `
                <div class="col-md-6 course-item" data-type="${typeKey}" data-name="${course.fullname.toLowerCase()}">
                    <div class="card course-card h-100 position-relative">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title fw-bold mb-1">${course.fullname}${statusHtml}</h6>
                                <small class="text-muted">
                                    <i class="fas fa-folder-open me-1"></i>${mainCat}
                                    ${subCat ? `<i class="fas fa-chevron-right mx-1" style="font-size: 8px; vertical-align: middle; opacity: 0.5;"></i>${subCat}` : ''}
                                </small>
                            </div>
                            ${buttonHtml}
                        </div>
                        <span class="category-label">${mainCat}</span>
                    </div>
                </div>
            `;
        }

        if (isCourseCreator) {
            const moodleUrl = '<?php echo $moodle_url; ?>';
            const teacherMgmtCatId = <?php echo (isset($_SESSION['management_category_id']) && $_SESSION['management_category_id'] > 0) ? $_SESSION['management_category_id'] : 0; ?>;
            const addCourseUrl = moodleUrl + '/course/edit.php' + (teacherMgmtCatId > 0 ? '?category=' + teacherMgmtCatId : '');

            function loadTeacherCourses() {
                const container = document.getElementById('teacher-courses-list');
                if (!container) return;

                // 顯示載入動畫（3D 旋轉環）
                container.innerHTML = loading3dHtml;

                fetch('api/get_moodle_data.php?type=courses')
                    .then(response => response.json())
                    .then(result => {
                        if (!result.success) throw new Error(result.message || 'Unknown error');
                        const courses = result.data?.my_courses_raw || [];
                        renderTeacherCourses(courses);
                    })
                    .catch(error => {
                        console.error('載入教師課程失敗:', error);
                        container.innerHTML = `
                            <div class="text-center py-5 text-danger">
                                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                                <p>課程載入失敗，請重新整理頁面</p>
                            </div>
                        `;
                    });
            }

            function renderTeacherCourses(courses) {
                const container = document.getElementById('teacher-courses-list');
                if (!container) return;

                if (!courses || courses.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-chalkboard fa-4x mb-3" style="opacity:0.3;"></i>
                            <p>您目前沒有教授任何課程</p>
                            <a href="#" onclick="goToMoodle('${addCourseUrl}')" class="btn btn-primary mt-3">
                                <i class="fas fa-plus-circle me-2"></i>建立第一門課程
                            </a>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div class="teacher-courses-grid">
                        ${courses.map(course => `
                            <div class="teacher-course-card" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')">
                                <div class="course-icon">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="course-info">
                                    <h5>${course.fullname}</h5>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-users me-1"></i> 學生數: ${course.enrolledusercount || 0}
                                    </p>
                                </div>
                                <div class="course-actions">
                                    <span class="btn-icon" onclick="event.stopPropagation(); goToMoodle('${moodleUrl}/course/edit.php?id=${course.id}')" title="編輯課程">
                                        <i class="fas fa-edit"></i>
                                    </span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
                container.classList.add('fade-in');
            }

            // 定義全域重新整理函式
            window.refreshTeacherCourses = function () {
                const container = document.getElementById('teacher-courses-list');
                if (container) {
                    container.innerHTML = loading3dHtml;
                }
                // 清除搜尋欄
                const searchInput = document.getElementById('teacher-course-search');
                if (searchInput) searchInput.value = '';

                fetch('index.php?clear_cache=1')
                    .then(() => loadTeacherCourses())
                    .catch(() => loadTeacherCourses());
            };

            // 定義全域搜尋函式
            window.filterTeacherCourses = function (keyword) {
                const cards = document.querySelectorAll('.teacher-course-card');
                const searchTerm = keyword.toLowerCase().trim();

                cards.forEach(card => {
                    const courseName = card.querySelector('h5')?.textContent?.toLowerCase() || '';
                    if (searchTerm === '' || courseName.includes(searchTerm)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            };

            // 標記是否已載入過課程
            let teacherCoursesLoaded = false;

            // 覆寫 showTab 來處理延遲載入
            const originalShowTab = window.showTab;
            window.showTab = function (tabId) {
                originalShowTab(tabId);

                // 當切換到課程管理時，首次載入課程
                if (tabId === 'course-management' && !teacherCoursesLoaded) {
                    teacherCoursesLoaded = true;
                    loadTeacherCourses();
                }
            };

            // 不在頁面載入時自動載入課程
            // return; // 移除這個 return 以便繼續執行下方的學生/通用邏輯
        }

        // 使用 3D Loader 替換原本的 loading logic

        // 顯示 loading 狀態
        function showLoading() {
            const sections = [
                '#dashboard-stats-container',
                '#mandatory-courses-container',
                '#domain-progress-container',
                '#in-progress-courses-container',
                '#announcements-container'
            ];

            sections.forEach(selector => {
                const el = document.querySelector(selector);
                if (el) {
                    el.innerHTML = loading3dHtml;
                }
            });
        }

        // 渲染公告 (區塊 E: 最新公告)
        function renderAnnouncements(announcements) {
            const container = document.getElementById('announcements-container');
            if (!container) return;

            if (!announcements || announcements.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="far fa-clipboard fa-2x mb-2" style="opacity:0.5;"></i>
                        <p class="mb-0 text-sm">目前沒有新公告</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = announcements.slice(0, 3).map(ann => {
                const dateObj = new Date(ann.time);
                const diffDays = Math.floor((new Date() - dateObj) / (1000 * 60 * 60 * 24));
                const badgeHtml = diffDays <= 3 ? '<span class="badge-v2 badge-v2--primary" style="margin-left: 8px; font-size: 0.7rem;">New</span>' : '';

                return `
                <div class="notification-item" style="cursor: pointer;" onclick="goToMoodle('${ann.url}')">
                    <div class="notification-item__icon notification-item__icon--info">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="notification-item__content">
                        <div class="notification-item__title">${ann.title}${badgeHtml}</div>
                        <div class="notification-item__desc">${ann.course_name}</div>
                        <div class="notification-item__time">${ann.time}</div>
                        <div class="notification-item__time">${dateObj.toLocaleDateString('zh-TW', { month: '2-digit', day: '2-digit' })}</div>
                    </div>
                </div>
                `;
            }).join('');

            container.classList.add('fade-in');
        }

        // 渲染可選修課程
        function renderAvailableCourses(courses) {
            const container = document.getElementById('available-courses-container');
            if (!container) return;

            if (!courses || courses.length === 0) {
                container.innerHTML = `
                <div class="col-12 text-center py-5 text-muted">
                    <i class="fas fa-box-open fa-4x mb-3" style="opacity:0.3;"></i>
                    <p>目前沒有可選課的新課程</p>
                </div>
            `;
                return;
            }

            const moodleUrl = '<?php echo $moodle_url; ?>';

            // 收集所有不重複的分類名稱 (以父類別為主，用於動態建立篩選器)
            const categorySet = new Set();
            courses.forEach(course => {
                const catName = course.parent_category || course.categoryname || '其他';
                categorySet.add(catName);
            });

            // 更新篩選按鈕（如果有篩選器容器）
            const filterContainer = document.getElementById('course-type-filters');
            if (filterContainer && categorySet.size > 0) {
                // 保存可選修按鈕（如果存在）
                const optionalBtn = document.getElementById('optionalCoursesBtn');
                const optionalBtnHtml = optionalBtn ? optionalBtn.outerHTML : '';

                let filterHtml = '<button class="filter-btn active" data-type="all">全部</button>';
                // 如果有可選修按鈕，加回去
                if (optionalBtnHtml) {
                    filterHtml += optionalBtnHtml;
                }
                categorySet.forEach(catName => {
                    // 使用分類名稱的 hash 作為 data-type（避免特殊字元問題）
                    let typeKey = catName.replace(/[^a-zA-Z0-9\u4e00-\u9fa5]/g, '');
                    if (!typeKey) typeKey = 'cat-' + Math.abs(catName.split('').reduce((a, b) => { a = ((a << 5) - a) + b.charCodeAt(0); return a & a }, 0));
                    filterHtml += `<button class="filter-btn" data-type="${typeKey}">${catName}</button>`;
                });
                filterContainer.innerHTML = filterHtml;

                // 重新綁定篩選事件
                filterContainer.querySelectorAll('.filter-btn:not(.optional-filter-btn)').forEach(btn => {
                    btn.addEventListener('click', function () {
                        // 隱藏可選修區塊，顯示一般課程
                        const optSection = document.getElementById('optional-courses-section');
                        if (optSection) optSection.style.display = 'none';
                        const availContainer = document.getElementById('available-courses-container');
                        if (availContainer) availContainer.style.display = 'flex';

                        // 不需要手動切換 active，由 filterCourses 統一處理
                        filterCoursesByType(this.dataset.type, this);
                    });
                });
            }

            container.innerHTML = courses.map(course => createCourseCard(course, moodleUrl, 'available')).join('');

            container.classList.add('fade-in');
        }

        // 渲染必修進度
        function renderCurriculumStatus(status) {
            const tbody = document.querySelector('.curriculum-section table tbody');
            if (!tbody) return;

            if (!status || Object.keys(status).length === 0) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="2" class="text-center text-muted py-4">目前無必修課程設定</td>
                </tr>
            `;
                return;
            }

            const moodleUrl = '<?php echo $moodle_url; ?>';

            if (typeof status !== 'object') return;
            tbody.innerHTML = Object.entries(status).map(([catName, items]) => {
                if (!Array.isArray(items)) return '';
                let icons = '';
                if (!items || items.length === 0) {
                    icons = '<span class="text-muted">無課程</span>';
                } else {
                    // 分離必修和一般課程
                    const mandatoryItems = [];
                    const regularItems = [];
                    let hasSeparator = false;

                    items.forEach(item => {
                        if (item.status === 'separator') {
                            hasSeparator = true;
                        } else if (item.is_mandatory_section) {
                            mandatoryItems.push(item);
                        } else {
                            regularItems.push(item);
                        }
                    });

                    const sortByStatus = (a, b) => {
                        const order = { green: 0, yellow: 1, red: 2 };
                        return (order[a.status] ?? 2) - (order[b.status] ?? 2);
                    };
                    mandatoryItems.sort(sortByStatus);
                    regularItems.sort(sortByStatus);

                    const renderItem = (item, isMandatory) => {
                        const fullCatName = item.category_name || catName;
                        const suffix = isMandatory ? ' (必修)' : '';

                        let deadlineStr = '';
                        if (item.enddate && item.enddate > 0) {
                            const endDate = new Date(item.enddate * 1000);
                            deadlineStr = ` [期限: ${endDate.getFullYear()}/${String(endDate.getMonth() + 1).padStart(2, '0')}/${String(endDate.getDate()).padStart(2, '0')}]`;
                        }

                        const title = `${item.fullname} (${fullCatName})${deadlineStr}: ${item.status === 'green' ? '已完成' : item.status === 'yellow' ? '未完成' : '尚未選課'}${suffix}`;
                        const iconClass = item.status === 'green' ? 'fas fa-check-circle icon-green' :
                            item.status === 'yellow' ? 'fas fa-exclamation-circle icon-yellow' :
                                'far fa-play-circle icon-red';
                        if (item.status === 'green') {
                            return `<i class="${iconClass} status-icon" title="${title}" data-bs-toggle="tooltip" data-bs-html="true"></i>`;
                        } else {
                            return `<a href="#" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${item.id}'); return false;" style="text-decoration:none;">
                                    <i class="${iconClass} status-icon" title="${title}" data-bs-toggle="tooltip" data-bs-html="true"></i>
                                </a>`;
                        }
                    };

                    if (mandatoryItems.length > 0) {
                        icons += '<span style="display:inline;background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;padding:1px 6px;margin-right:3px;" title="必修">';
                        icons += '<i class="fas fa-star" style="color:#f59e0b;font-size:0.6rem;margin-right:2px;"></i>';
                        icons += mandatoryItems.map(item => renderItem(item, true)).join('');
                        icons += '</span>';
                    }
                    if (hasSeparator && regularItems.length > 0) {
                        icons += '<span style="margin:0 4px;color:#cbd5e1;font-weight:300;">|</span>';
                    }
                    icons += regularItems.map(item => renderItem(item, false)).join('');
                }

                return `
                <tr>
                    <td><strong>${catName}</strong></td>
                    <td>${icons}</td>
                </tr>
            `;
            }).join('');

            tbody.classList.add('fade-in');

            // 重新初始化 Bootstrap tooltips
            if (typeof bootstrap !== 'undefined') {
                const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltips.forEach(el => new bootstrap.Tooltip(el));
            }
        }

        // 渲染進行中課程 (區塊 D: 進行中課程)
        function renderInProgressCourses(courses) {
            const container = document.getElementById('in-progress-courses-container');
            if (!container) return;

            if (!courses || courses.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-box-open fa-2x mb-2" style="opacity:0.3;"></i>
                        <p class="mb-0 text-sm">目前沒有進行中的課程</p>
                    </div>
                `;
                return;
            }

            const moodleUrl = '<?php echo $moodle_url; ?>';

            // 過濾並只顯示 progress < 100 且未失敗(failed) 的課程，最多顯示 3 筆
            const inProgressCourses = courses.filter(course => (course.progress || 0) < 100 && !course.failed).slice(0, 3);

            if (inProgressCourses.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-check-circle fa-2x mb-2" style="color: var(--success); opacity:0.7;"></i>
                        <p class="mb-0 text-sm">所有選修課程皆已完成！</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = inProgressCourses.map(course => {
                const progress = course.progress || 0;

                // Extract the child category from the display_category (e.g. "台北類別 - 營養子1" -> "營養子1")
                let domainName = course.display_category || '無分類';
                if (domainName.includes(' - ')) {
                    domainName = domainName.split(' - ').pop().trim();
                }

                let deadlineStr = '';
                if (course.enddate && course.enddate > 0) {
                    const endDate = new Date(course.enddate * 1000);
                    deadlineStr = ` <span style="color: var(--text-muted); font-weight: 500; font-size: 13px;"><i class="fas fa-clock mr-1"></i> 期限: ${endDate.getFullYear()}/${String(endDate.getMonth() + 1).padStart(2, '0')}/${String(endDate.getDate()).padStart(2, '0')}</span>`;
                }

                return `
                <div class="course-card" style="cursor: pointer;" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')">
                    <div class="course-card__header">
                        <div style="flex: 1;">
                            <div class="course-card__tags" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                <span class="course-tag course-tag--elective">${domainName}</span>
                                ${deadlineStr}
                            </div>
                            <h3 class="course-card__title" style="margin-bottom: 0;">${course.fullname}</h3>
                        </div>
                    </div>
                    <div class="course-card__progress">
                        <div class="course-card__progress-label">
                            <span>進度</span>
                            <span>${progress.toFixed(0)}%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-bar__fill" style="width: ${progress}%;"></div>
                        </div>
                    </div>
                </div>
                `;
            }).join('');

            container.classList.add('fade-in');
        }

        // 渲染學習歷程
        function renderLearningHistory(historyByYear) {
            const container = document.querySelector('#history');
            if (!container) return;

            // 先清空容器，保留標題
            const title = container.querySelector('h3');
            container.innerHTML = '';
            if (title) {
                container.appendChild(title);
            } else {
                container.innerHTML = '<h3 class="mb-4 fw-bold" style="color: var(--primary);"><i class="fas fa-history me-2"></i>學習歷程</h3>';
            }

            if (!historyByYear || Object.keys(historyByYear).length === 0) {
                container.innerHTML += `
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-folder-open fa-4x mb-3" style="opacity:0.3;"></i>
                    <p>目前沒有學習紀錄</p>
                </div>
            `;
                return;
            }

            const moodleUrl = '<?php echo $moodle_url; ?>';
            const historyHtml = Object.entries(historyByYear).map(([year, courses]) => `
                <div class="mb-5">
                    <h5 class="mb-4"><span class="year-badge"><i class="fas fa-calendar-alt me-2"></i>${year} 年度</span></h5>
                    <div class="row g-4">
                        ${courses.map(course => `
                            <div class="col-md-4">
                                <div class="card course-card h-100" style="cursor:pointer;" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')">
                                    <div class="card-body">
                                        <h6 class="card-title fw-bold">${course.fullname}</h6>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `).join('');

            container.innerHTML += historyHtml;
            container.classList.add('fade-in');
        }

        // 渲染首頁修課進度 Widget (區塊 A: 學位完成進度 / 區塊 B: 必修進度卡片 / 區塊 C: 領域必修進度)
        function renderDashboardStatsAndCurriculum(status, courses) {
            const statsContainer = document.getElementById('dashboard-stats-container');
            const mandatoryContainer = document.getElementById('mandatory-courses-container');
            const domainContainer = document.getElementById('domain-progress-container');
            const mandatoryBadge = document.getElementById('mandatory-course-badge');

            if (!status || Object.keys(status).length === 0) {
                if (statsContainer) statsContainer.innerHTML = '<div class="stat-card-large text-center py-4"><div class="text-muted">暫無資料</div></div>';
                if (mandatoryContainer) mandatoryContainer.innerHTML = '<div class="text-center py-4 text-muted">目前無必修課程設定</div>';
                if (domainContainer) domainContainer.innerHTML = '<div class="text-center py-4 text-muted">目前無領域設定</div>';
                return;
            }

            const moodleUrl = '<?php echo $moodle_url; ?>';
            let totalMandatory = 0;
            let completedMandatory = 0;
            let inProgressMandatory = 0;
            let failedMandatory = 0;

            let totalCategories = 0;
            let completedCategories = 0;

            let mandatoryHtmlComplete = '';
            let mandatoryHtmlInProgress = '';
            let mandatoryHtmlNotStarted = '';
            let mandatoryHtmlFailed = '';

            let domainHtml = '';
            let totalCredits = 0; // 暫時以課程數代替，後續可從 API 擴充
            let requiredCredits = 120; // 預設畢業學分

            if (typeof status !== 'object') return;
            const categoryQuotas = status.quotas || {};
            const statusData = status.status || status;

            Object.entries(statusData).forEach(([catName, items]) => {
                if (!Array.isArray(items)) return;
                const hasCourses = items && items.some(item =>
                    item.status !== 'separator' && (item.id > 0 || item.is_mandatory_section)
                );

                if (!hasCourses) return;

                // 處理必修課程 (區塊 B)
                const mandatoryItems = items.filter(item => item.is_mandatory_section && item.status !== 'separator');

                mandatoryItems.forEach(item => {
                    totalMandatory++;

                    let statusIcon = '';
                    let statusClass = '';
                    let clickAttr = item.id > 0 ? `onclick="goToMoodle('${moodleUrl}/course/view.php?id=${item.id}')" style="cursor:pointer;"` : '';

                    const actualCourse = (courses || []).find(c => c.id == item.id) || item;
                    let deadlineStr = '';
                    if (actualCourse.enddate && actualCourse.enddate > 0) {
                        const endDate = new Date(actualCourse.enddate * 1000);
                        deadlineStr = ` <span style="margin-left: 8px; font-weight: 500; font-size: 13px; color: var(--text-muted);"><i class="fas fa-clock mr-1"></i> 期限: ${endDate.getFullYear()}/${String(endDate.getMonth() + 1).padStart(2, '0')}/${String(endDate.getDate()).padStart(2, '0')}</span>`;
                    }

                    if (item.status === 'green') {
                        completedMandatory++;
                        totalCredits += 3; // 假設施 3 學分

                        mandatoryHtmlComplete += `
                            <div class="course-list-item" ${clickAttr}>
                                <div class="course-list-item__content">
                                    <div class="course-list-item__title">${item.fullname}</div>
                                    <div class="course-list-item__meta">${catName}${deadlineStr}</div>
                                </div>
                            </div>`;
                    } else if (item.status === 'yellow') {
                        inProgressMandatory++;

                        mandatoryHtmlInProgress += `
                            <div class="course-list-item" ${clickAttr}>
                                <div class="course-list-item__content">
                                    <div class="course-list-item__title">${item.fullname}</div>
                                    <div class="course-list-item__meta">${catName}${deadlineStr}</div>
                                </div>
                            </div>`;
                    } else if (item.status === 'red') {
                        failedMandatory++;

                        mandatoryHtmlFailed += `
                            <div class="course-list-item" ${clickAttr}>
                                <div class="course-list-item__content">
                                    <div class="course-list-item__title">${item.fullname}</div>
                                    <div class="course-list-item__meta">${catName}${deadlineStr}</div>
                                </div>
                            </div>`;
                    } else {
                        mandatoryHtmlNotStarted += `
                            <div class="course-list-item" ${clickAttr}>
                                <div class="course-list-item__content">
                                    <div class="course-list-item__title">${item.fullname || '未選課'}</div>
                                    <div class="course-list-item__meta">${catName}${deadlineStr}</div>
                                </div>
                            </div>`;
                    }
                });

                // 處理領域進度 (區塊 C)
                // 每個分類視為一個類別
                totalCategories++;
                const actualItemsCount = items.filter(item => item.status !== 'separator').length;
                const catTotal = categoryQuotas[catName] || actualItemsCount;
                const catCompleted = items.filter(item => item.status === 'green').length;
                const catProgress = catTotal > 0 ? Math.min((catCompleted / catTotal) * 100, 100) : 0;

                let noteHtml = '';
                const remaining = catTotal - catCompleted;

                if (remaining <= 0 && catTotal > 0) {
                    completedCategories++;
                    noteHtml = `<div class="domain-progress-card__note text-muted">已達成規定門檻</div>`;
                } else {
                    noteHtml = `<div class="domain-progress-card__note text-muted">還需 ${remaining > 0 ? remaining : 0} 門課程</div>`;
                }

                domainHtml += `
                    <div class="domain-progress-card">
                        <div class="domain-progress-card__header">
                            <div class="domain-progress-card__title">${catName}</div>
                            <div>
                                <span class="domain-progress-card__count">${catCompleted}</span>
                                <span class="domain-progress-card__count-label">/ ${catTotal} 門</span>
                            </div>
                        </div>
                        <div class="progress-bar progress-bar--sm">
                            <div class="progress-bar__fill" style="width: ${catProgress}%;"></div>
                        </div>
                        ${noteHtml}
                    </div>
                `;
            });

            // 總結統計資料更新 (區塊 A - 改為 2 欄: 必修課程 與 必修類別)
            const overallPercentage = totalMandatory > 0 ? Math.round((completedMandatory / totalMandatory) * 100) : 0;
            const categoryPercentage = totalCategories > 0 ? Math.round((completedCategories / totalCategories) * 100) : 0;

            if (statsContainer) {
                const r = window.innerWidth <= 480 ? 35 : 70;
                const circumference = 2 * Math.PI * r;
                const offset1 = circumference - (overallPercentage / 100) * circumference;
                const offset2 = circumference - (categoryPercentage / 100) * circumference;

                statsContainer.innerHTML = `
                    <div class="stat-card-large" style="position: relative;">
                        <div class="progress-circle">
                            <svg class="progress-circle__svg" width="${r * 2 + 20}" height="${r * 2 + 20}">
                                <defs>
                                    <linearGradient id="progress-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:#2563eb;stop-opacity:1" />
                                        <stop offset="100%" style="stop-color:#06b6d4;stop-opacity:1" />
                                    </linearGradient>
                                </defs>
                                <circle class="progress-circle__bg" cx="${r + 10}" cy="${r + 10}" r="${r}"></circle>
                                <circle class="progress-circle__fill" cx="${r + 10}" cy="${r + 10}" r="${r}" 
                                    stroke="url(#progress-gradient)"
                                    stroke-dasharray="${circumference} ${circumference}" 
                                    stroke-dashoffset="${offset1}"
                                    style="transition: stroke-dashoffset 1s ease-out;"></circle>
                            </svg>
                            <div class="progress-circle__text">
                                <span class="progress-circle__percentage">${overallPercentage}%</span>
                                <span class="progress-circle__label">必修完成度</span>
                            </div>
                        </div>
                        <div style="position: absolute; bottom: 15px; right: 20px; font-size: 0.9rem; color: #64748b; font-weight: 500;">
                            ${completedMandatory} / ${totalMandatory} 門
                        </div>
                    </div>

                    <div class="stat-card-large" style="position: relative;">
                        <div class="progress-circle">
                            <svg class="progress-circle__svg" width="${r * 2 + 20}" height="${r * 2 + 20}">
                                <defs>
                                    <linearGradient id="progress-gradient-2" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:#10b981;stop-opacity:1" />
                                        <stop offset="100%" style="stop-color:#34d399;stop-opacity:1" />
                                    </linearGradient>
                                </defs>
                                <circle class="progress-circle__bg" cx="${r + 10}" cy="${r + 10}" r="${r}"></circle>
                                <circle class="progress-circle__fill" cx="${r + 10}" cy="${r + 10}" r="${r}" 
                                    stroke="url(#progress-gradient-2)"
                                    stroke-dasharray="${circumference} ${circumference}" 
                                    stroke-dashoffset="${offset2}"
                                    style="transition: stroke-dashoffset 1s ease-out;"></circle>
                            </svg>
                            <div class="progress-circle__text">
                                <span class="progress-circle__percentage">${categoryPercentage}%</span>
                                <span class="progress-circle__label">類別完成度</span>
                            </div>
                        </div>
                        <div style="position: absolute; bottom: 15px; right: 20px; font-size: 0.9rem; color: #64748b; font-weight: 500;">
                            ${completedCategories} / ${totalCategories} 類
                        </div>
                    </div>
                `;
            }

            // 更新必修課程 (區塊 B)
            if (mandatoryBadge) mandatoryBadge.textContent = `${completedMandatory} / ${totalMandatory} 門`;

            if (mandatoryContainer) {
                let mHtml = '';
                const mPercent = totalMandatory > 0 ? (completedMandatory / totalMandatory) * 100 : 0;

                mHtml += `
                    <div class="progress-bar mb-4">
                        <div class="progress-bar__fill" style="width: ${mPercent}%;"></div>
                    </div>
                `;

                if (completedMandatory > 0) {
                    mHtml += `
                        <div class="mb-4">
                            <div class="d-flex align-center gap-2 mb-2">
                                <span class="course-status course-status--completed">已完成</span>
                                <span class="text-sm text-muted">(${completedMandatory} 門)</span>
                            </div>
                            <div class="d-flex flex-col gap-2 pl-4">${mandatoryHtmlComplete}</div>
                        </div>`;
                }

                if (inProgressMandatory > 0) {
                    mHtml += `
                        <div class="mb-4">
                            <div class="d-flex align-center gap-2 mb-2">
                                <span class="course-tag course-tag--warning">進行中</span>
                                <span class="text-sm text-muted">(${inProgressMandatory} 門)</span>
                            </div>
                            <div class="d-flex flex-col gap-2 pl-4">${mandatoryHtmlInProgress}</div>
                        </div>`;
                }

                if (failedMandatory > 0) {
                    mHtml += `
                        <div class="mb-4">
                            <div class="d-flex align-center gap-2 mb-2">
                                <span class="course-tag" style="background: rgba(239, 68, 68, 0.1); color: var(--error);">未通過</span>
                                <span class="text-sm text-muted">(${failedMandatory} 門)</span>
                            </div>
                            <div class="d-flex flex-col gap-2 pl-4">${mandatoryHtmlFailed}</div>
                        </div>`;
                }

                const notStartedMandatory = totalMandatory - completedMandatory - inProgressMandatory - failedMandatory;
                if (notStartedMandatory > 0) {
                    mHtml += `
                        <div>
                            <div class="d-flex align-center gap-2 mb-2">
                                <span class="course-status course-status--not-started">未選課</span>
                                <span class="text-sm text-muted">(${notStartedMandatory} 門)</span>
                            </div>
                            <div class="d-flex flex-col gap-2 pl-4">${mandatoryHtmlNotStarted}</div>
                        </div>`;
                }

                if (totalMandatory === 0) {
                    mHtml += '<div class="text-center py-4 text-muted">完全沒有必修課程！</div>';
                }

                mandatoryContainer.innerHTML = mHtml;
            }

            // 更新領域進度 (區塊 C)
            if (domainContainer) {
                domainContainer.innerHTML = domainHtml || '<div class="text-center py-4 text-muted">目前無領域設定</div>';
            }
        }

        // 渲染成績垂直長條圖
        function renderGradesChart(grades) {
            const container = document.getElementById('grades-chart-container');
            if (!container) return;

            if (!grades || grades.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-chart-bar fa-3x mb-3" style="opacity:0.3;"></i>
                        <p class="mb-0">目前沒有成績資料</p>
                    </div>
                `;
                return;
            }

            // 建立垂直長條圖 HTML
            let html = '<div class="vertical-bar-chart">';

            grades.forEach(grade => {
                const percentage = grade.grade_max > 0 ? (grade.grade / grade.grade_max) * 100 : 0;
                const shortName = grade.course_name.length > 8 ?
                    grade.course_name.substring(0, 8) + '...' : grade.course_name;

                // 根據成績設定顏色
                let barColor = '#10b981'; // 綠色 (>=80)
                if (percentage < 60) {
                    barColor = '#ef4444'; // 紅色
                } else if (percentage < 80) {
                    barColor = '#f59e0b'; // 黃色
                }

                html += `
                    <div class="bar-column" title="${grade.course_name}: ${grade.grade_formatted}">
                        <div class="bar-value">${Math.round(grade.grade)}</div>
                        <div class="bar-track">
                            <div class="bar-fill" style="height: ${percentage}%; background: ${barColor};"></div>
                        </div>
                        <div class="bar-label">${shortName}</div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
            container.classList.add('fade-in');
        }

        // 階段性載入資料 (含 Retry 機制 - 保留函式名但移除未使用的常數)
        function fetchSubData(type, renderer) {

            fetch(`api/get_moodle_data.php?type=${type}`, {
                method: 'GET',
                credentials: 'same-origin'
            })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    return response.json();
                })
                .then(result => {
                    if (!result.success) throw new Error(result.message || 'Unknown error');

                    // 處理 Moodle 帳號未建立的情況
                    if (result.data_not_found) {
                        handleUserNotFound();
                        return;
                    }

                    const data = result.data;

                    // 🚀 關鍵優化：檢查是否有連線逾時
                    if (data && data.error === 'MOODLE_TIMEOUT') {
                        throw new Error('MOODLE_TIMEOUT');
                    }
                    if (type === 'courses' && data.my_courses_raw && data.my_courses_raw.error === 'MOODLE_TIMEOUT') {
                        throw new Error('MOODLE_TIMEOUT');
                    }
                    if (type === 'announcements' && data.latest_announcements && data.latest_announcements.error === 'MOODLE_TIMEOUT') {
                        throw new Error('MOODLE_TIMEOUT');
                    }

                    // 驗證關鍵資料是否遺失（例如應該要有課程卻回傳空陣列，可能是暫時性 API 失敗）
                    // 這裡只針對 known flaky APIs 做檢查
                    if (type === 'courses' && (!data.my_courses_raw || (Array.isArray(data.my_courses_raw) && data.my_courses_raw.length === 0))) {
                        // 雖然空課程是合法的，但如果是 API 故障導致的，我們希望能重試
                        // 這裡可以透過檢查 result.cache_status 來決定要不要重試，或者先假設使用者真的沒課
                        // 暫時不對 "空課程" 強制重試，避免無限迴圈 (除非我們能區分 "真沒課" vs "API 壞掉")
                    }

                    renderer(data);
                    // console.log(`✅ ${type} 載入完成`);
                })
                .catch(error => {
                    console.error(`❌ 載入 ${type} 失敗:`, error);
                    const isTimeout = error.message.includes('TIMEOUT') || error.message.includes('逾時');
                    handlePartialError(type, isTimeout);
                });
        }

        function handleUserNotFound() {
            const message = `
                <div class="text-center py-4">
                    <i class="fas fa-info-circle fa-2x mb-2" style="opacity:0.2;"></i>
                    <p class="text-muted text-sm">目前的帳號尚未有關聯的資料</p>
                </div>
            `;
            const widgets = document.querySelectorAll('#announcements-container, #dashboard-stats-container, #mandatory-courses-container, #domain-progress-container, #in-progress-courses-container');
            widgets.forEach(el => {
                if (el) el.innerHTML = message;
            });
        }

        function handlePartialError(type, isTimeout = false) {
            const msg = isTimeout ? '連線逾時，請重新整理' : '載入失敗';
            const icon = isTimeout ? 'fa-clock' : 'fa-exclamation-triangle';
            const errorHtml = `<div class="text-center py-4 text-danger"><i class="fas ${icon} fa-2x mb-2"></i><p class="text-sm m-0">${msg}</p></div>`;

            let selector = '';
            switch (type) {
                case 'courses': selector = '#in-progress-courses-container'; break;
                case 'announcements': selector = '#announcements-container'; break;
                case 'curriculum': selector = '#dashboard-stats-container, #mandatory-courses-container, #domain-progress-container'; break;
            }
            if (selector) {
                document.querySelectorAll(selector).forEach(el => { if (el) el.innerHTML = errorHtml; });
            }
        }

        function loadMoodleData() {
            showLoading();

            // 一次性載入所有儀表板所需資料 (降低 Moodle API 併發負擔)
            fetchSubData('all', data => {
                // 1. 處理課程
                const coursesDataCache = data.my_courses_raw || [];
                if (data.my_courses_raw) renderInProgressCourses(data.my_courses_raw);

                // 2. 處理必修進度
                if (data.curriculum_status) {
                    window.curriculumDataCache = data.curriculum_status;
                    renderDashboardStatsAndCurriculum(data.curriculum_status, coursesDataCache);
                }

                // 3. 處理最新公告
                if (data.latest_announcements) {
                    renderAnnouncements(data.latest_announcements);
                }
            });
        }

        // 頁面載入完成後立即開始載入資料
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadMoodleData);
        } else {
            loadMoodleData();
        }
    })();
</script>