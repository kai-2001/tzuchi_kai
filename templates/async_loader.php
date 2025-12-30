<!-- 非同步資料載入腳本 -->
<script>
    (function () {
        'use strict';

        // 檢查是否為非同步模式
        const asyncMode = <?php echo isset($async_mode) && $async_mode ? 'true' : 'false'; ?>;
        const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
        const isTeacherPlus = <?php echo (isset($_SESSION['is_teacherplus']) && $_SESSION['is_teacherplus']) ? 'true' : 'false'; ?>;

        if (!asyncMode || isAdmin) {
            return; // 非非同步模式或管理員,不執行
        }

        // 如果是開課教師，定義並載入教師課程資料
        // 3D Loading Animation HTML (Enhanced Original Neon Portal with Energy Core)
        const loading3dHtml = `
            <div class="loader-3d-portal">
                <div class="portal-ring ring-outer"></div>
                <div class="portal-ring ring-inner"></div>
                <div class="portal-core"></div>
                <div class="portal-text">Loading...</div>
            </div>
        `;

        // 3D Loading CSS
        const loader3dStyle = document.createElement('style');
        loader3dStyle.textContent = `
            .loader-3d-portal {
                display: flex;
                justify-content: center;
                align-items: center;
                perspective: 1000px;
                height: 200px;
                width: 100%;
                position: relative;
                transform-style: preserve-3d;
            }
            .portal-ring {
                position: absolute;
                border-radius: 50%;
                border: 4px solid transparent;
                transform-style: preserve-3d;
            }
            .ring-outer {
                width: 100px;
                height: 100px;
                border-top-color: #2563eb;
                border-bottom-color: #2563eb;
                box-shadow: 0 0 25px rgba(37, 99, 235, 0.5);
                animation: portalRotateX 2s linear infinite;
            }
            .ring-inner {
                width: 70px;
                height: 70px;
                border-left-color: #06b6d4;
                border-right-color: #06b6d4;
                box-shadow: 0 0 20px rgba(6, 182, 212, 0.5);
                animation: portalRotateY 1.5s linear infinite;
            }
            .portal-core {
                position: absolute;
                width: 45px;
                height: 45px;
                background: radial-gradient(circle, rgba(255, 255, 255, 0.9) 0%, rgba(37, 99, 235, 0.3) 60%, transparent 100%);
                border-radius: 50%;
                box-shadow: 0 0 30px rgba(37, 99, 235, 0.6), inset 0 0 15px rgba(255, 255, 255, 0.8);
                filter: blur(2px);
                animation: corePulse 1.5s ease-in-out infinite;
                z-index: -1;
            }
            .portal-text {
                position: absolute;
                font-family: inherit;
                font-weight: 700;
                font-size: 13px;
                color: #1e3a8a;
                text-shadow: 0 0 10px rgba(255, 255, 255, 0.8);
                letter-spacing: 1px;
                animation: portalPulse 1.5s ease-in-out infinite;
            }
            @keyframes portalRotateX {
                0% { transform: rotateX(0deg) rotateY(30deg) rotateZ(0deg); }
                100% { transform: rotateX(360deg) rotateY(30deg) rotateZ(360deg); }
            }
            @keyframes portalRotateY {
                0% { transform: rotateX(60deg) rotateY(0deg) rotateZ(0deg); }
                100% { transform: rotateX(60deg) rotateY(360deg) rotateZ(360deg); }
            }
            @keyframes corePulse {
                0%, 100% { transform: scale(1); opacity: 0.7; }
                50% { transform: scale(1.2); opacity: 1; filter: blur(4px); }
            }
            @keyframes portalPulse {
                0%, 100% { opacity: 0.6; transform: scale(0.95); }
                50% { opacity: 1; transform: scale(1.05); }
            }
            .fade-in {
                animation: fadeIn 0.5s;
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
        `;
        document.head.appendChild(loader3dStyle);

        if (isTeacherPlus) {
            const moodleUrl = '<?php echo $moodle_url; ?>';

            function loadTeacherCourses() {
                const container = document.getElementById('teacher-courses-list');
                if (!container) return;

                // 顯示載入動畫（3D 旋轉環）
                container.innerHTML = loading3dHtml;

                fetch('api/get_moodle_data.php')
                    .then(response => response.json())
                    .then(data => {
                        const courses = data.data?.my_courses_raw || data.my_courses_raw || [];
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
                            <a href="#" onclick="goToMoodle('${moodleUrl}/course/edit.php')" class="btn btn-primary mt-3">
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
                '.announcement-body',
                '#available-courses-container',
                '.curriculum-section table tbody',
                '#my-courses .row',
                '#history',
                '#curriculum-progress-widget',
                '#grades-chart-container'
            ];

            sections.forEach(selector => {
                const el = document.querySelector(selector);
                if (el) {
                    el.innerHTML = loading3dHtml;
                }
            });
        }

        // 渲染公告
        function renderAnnouncements(announcements) {
            const container = document.querySelector('.announcement-body');
            if (!container) return;

            if (!announcements || announcements.length === 0) {
                container.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="far fa-clipboard fa-3x mb-3" style="opacity:0.5;"></i>
                    <p class="mb-0">目前沒有新公告</p>
                </div>
            `;
                return;
            }

            container.innerHTML = announcements.map(ann => `
            <div class="news-item" onclick="goToMoodle('${ann.link}')">
                <div class="news-date">
                    <i class="far fa-calendar-alt"></i>
                    ${new Date(ann.date * 1000).toLocaleDateString('zh-TW', { month: '2-digit', day: '2-digit' })}
                </div>
                <div class="flex-grow-1">
                    <span class="news-badge">${ann.course_name}</span>
                    <span class="fw-medium">${ann.subject}</span>
                </div>
                <i class="fas fa-chevron-right text-muted"></i>
            </div>
        `).join('');

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
                    <p>目前沒有可選修的新課程</p>
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
                let filterHtml = '<button class="filter-btn active" data-type="all">全部</button>';
                categorySet.forEach(catName => {
                    // 使用分類名稱的 hash 作為 data-type（避免特殊字元問題）
                    let typeKey = catName.replace(/[^a-zA-Z0-9\u4e00-\u9fa5]/g, '');
                    if (!typeKey) typeKey = 'cat-' + Math.abs(catName.split('').reduce((a, b) => { a = ((a << 5) - a) + b.charCodeAt(0); return a & a }, 0));
                    filterHtml += `<button class="filter-btn" data-type="${typeKey}">${catName}</button>`;
                });
                filterContainer.innerHTML = filterHtml;

                // 重新綁定篩選事件
                filterContainer.querySelectorAll('.filter-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        // 不需要手動切換 active，由 filterCourses 統一處理
                        filterCoursesByType(this.dataset.type, this);
                    });
                });
            }

            container.innerHTML = courses.map(course => {
                const mainCat = course.parent_category || course.categoryname || '其他';
                const subCat = (course.child_category && course.child_category !== mainCat) ? course.child_category : '';

                // 使用父類別名稱的 hash 作為 data-type (篩選器以大類為主)
                let typeKey = mainCat.replace(/[^a-zA-Z0-9\u4e00-\u9fa5]/g, '');
                if (!typeKey) typeKey = 'cat-' + Math.abs(mainCat.split('').reduce((a, b) => { a = ((a << 5) - a) + b.charCodeAt(0); return a & a }, 0));

                const moodleUrl = '<?php echo $moodle_url; ?>';

                // 狀態標示邏輯
                let statusHtml = '';
                let buttonHtml = '';

                if (course.is_enrolled) {
                    const progress = course.progress || 0;
                    statusHtml = `<span class="badge ${progress >= 100 ? 'bg-success' : 'bg-warning'} ms-2" style="font-size: 10px;">
                                    ${progress >= 100 ? '已完成' : '學習中 (' + progress + '%)'}
                                  </span>`;
                    buttonHtml = `<button class="btn btn-sm" 
                                          style="background: #f1f5f9; color: var(--primary); border: 1px solid var(--primary); border-radius: 20px; padding: 8px 20px;"
                                          onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')">
                                      <i class="fas fa-sign-in-alt me-1"></i>進入課程
                                  </button>`;
                } else {
                    statusHtml = `<span class="badge bg-secondary ms-2" style="font-size: 10px; opacity: 0.7;">未選課</span>`;
                    buttonHtml = `<button class="btn btn-sm" 
                                          style="background: var(--primary); color: white; border-radius: 20px; padding: 8px 20px;"
                                          onclick="goToMoodle('${moodleUrl}/enrol/index.php?id=${course.id}')">
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
            }).join('');

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
            tbody.innerHTML = Object.entries(status).map(([catName, items]) => {
                let icons = '';
                if (!items || items.length === 0) {
                    icons = '<span class="text-muted">無課程</span>';
                } else {
                    // 排序: 綠色 (green) > 黃色 (yellow) > 其他 (red/gray)
                    const sortedItems = [...items].sort((a, b) => {
                        const order = { green: 0, yellow: 1, red: 2 };
                        const aOrder = order[a.status] ?? 2;
                        const bOrder = order[b.status] ?? 2;
                        return aOrder - bOrder;
                    });
                    icons = sortedItems.map(item => {
                        const fullCatName = item.category_name || catName;
                        const title = `${item.fullname} (${fullCatName}): ${item.status === 'green' ? '已完成' : item.status === 'yellow' ? '未完成' : '尚未選課'}`;
                        const iconClass = item.status === 'green' ? 'fas fa-check-circle icon-green' :
                            item.status === 'yellow' ? 'fas fa-exclamation-circle icon-yellow' :
                                'far fa-play-circle icon-red';

                        if (item.status === 'green') {
                            return `<i class="${iconClass} status-icon" title="${title}" data-bs-toggle="tooltip"></i>`;
                        } else {
                            return `<a href="#" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${item.id}')" style="text-decoration:none;">
                                    <i class="${iconClass} status-icon" title="${title}" data-bs-toggle="tooltip"></i>
                                </a>`;
                        }
                    }).join('');
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

        // 渲染我的課程
        function renderMyCourses(courses) {
            const container = document.querySelector('#my-courses .row');
            if (!container) return;

            if (!courses || courses.length === 0) {
                container.innerHTML = `
                <div class="col-12 text-center py-5 text-muted">
                    <i class="fas fa-book-open fa-4x mb-3" style="opacity:0.3;"></i>
                    <p>您還沒有選修任何課程</p>
                    <button class="btn btn-primary mt-3" onclick="showTab('quick-enroll')" 
                            style="background: var(--primary); border:none; padding: 12px 24px; border-radius: 30px;">
                        <i class="fas fa-search me-2"></i>探索課程
                    </button>
                </div>
            `;
                return;
            }

            const moodleUrl = '<?php echo $moodle_url; ?>';
            container.innerHTML = courses.map(course => `
                <div class="col-md-3">
                    <div class="card course-card h-100" style="cursor:pointer;" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')">
                        <div class="card-body">
                            <h6 class="card-title fw-bold">${course.fullname}</h6>
                        </div>
                    </div>
                </div>
            `).join('');

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

        // 渲染首頁修課進度 Widget
        function renderCurriculumProgressWidget(status) {
            const container = document.getElementById('curriculum-progress-widget');
            const summaryEl = document.getElementById('progress-summary');
            const progressFill = document.getElementById('overall-progress-fill');
            const progressText = document.getElementById('overall-progress-text');

            if (!container) return;

            if (!status || Object.keys(status).length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-info-circle me-2"></i>目前無必修課程設定
                    </div>
                `;
                if (progressText) progressText.textContent = '無資料';
                return;
            }

            const moodleUrl = '<?php echo $moodle_url; ?>';
            let totalCourses = 0;
            let completedCourses = 0;

            // 建立進度 HTML
            let html = '<div class="progress-categories">';

            Object.entries(status).forEach(([catName, items]) => {
                html += `<div class="progress-category-row">
                    <div class="category-name"><strong>${catName}</strong></div>
                    <div class="category-items">`;

                if (!items || items.length === 0) {
                    html += '<span class="text-muted">無課程</span>';
                } else {
                    // 排序: 綠色 (green) > 黃色 (yellow) > 其他 (red/gray)
                    const sortedItems = [...items].sort((a, b) => {
                        const order = { green: 0, yellow: 1, red: 2 };
                        const aOrder = order[a.status] ?? 2;
                        const bOrder = order[b.status] ?? 2;
                        return aOrder - bOrder;
                    });
                    sortedItems.forEach(item => {
                        totalCourses++;
                        let iconClass, iconColor, title;

                        if (item.status === 'green') {
                            completedCourses++;
                            iconClass = 'fas fa-check-circle';
                            iconColor = '#10b981';
                            title = `${item.fullname}: 已完成`;
                        } else if (item.status === 'yellow') {
                            iconClass = 'fas fa-spinner';
                            iconColor = '#f59e0b';
                            title = `${item.fullname}: 進行中`;
                        } else {
                            iconClass = 'far fa-circle';
                            iconColor = '#94a3b8';
                            title = `${item.fullname}: 尚未選課`;
                        }

                        const clickable = item.status !== 'green' ?
                            `onclick="goToMoodle('${moodleUrl}/course/view.php?id=${item.id}')" style="cursor:pointer;"` : '';

                        html += `<span class="progress-item" ${clickable} title="${title}" data-bs-toggle="tooltip">
                            <i class="${iconClass}" style="color: ${iconColor}; font-size: 1.5rem;"></i>
                        </span>`;
                    });
                }

                html += '</div></div>';
            });

            html += '</div>';
            container.innerHTML = html;
            container.classList.add('fade-in');

            // 更新整體進度
            const percentage = totalCourses > 0 ? Math.round((completedCourses / totalCourses) * 100) : 0;
            if (progressFill) {
                progressFill.style.width = percentage + '%';
            }
            if (progressText) {
                progressText.textContent = `${percentage}% 完成 (${completedCourses}/${totalCourses} 門課程)`;
            }
            if (summaryEl) {
                summaryEl.innerHTML = `<span class="badge bg-primary">${completedCourses}/${totalCourses} 門完成</span>`;
            }

            // 初始化 tooltips
            if (typeof bootstrap !== 'undefined') {
                const tooltips = container.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltips.forEach(el => new bootstrap.Tooltip(el));
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

        // 階段性載入資料
        function fetchSubData(type, renderer) {
            return fetch(`api/get_moodle_data.php?type=${type}`, {
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
                    renderer(data);
                    console.log(`✅ ${type} 載入完成`);
                })
                .catch(error => {
                    console.error(`❌ 載入 ${type} 失敗:`, error);
                    handlePartialError(type);
                });
        }

        function handleUserNotFound() {
            const message = `
                <div class="text-center py-5">
                    <i class="fas fa-info-circle fa-3x mb-3" style="opacity:0.2;"></i>
                    <p class="text-muted">目前的帳號尚未有關聯的 Moodle 課程資料</p>
                </div>
            `;
            // 替換各區塊內容
            const widgets = document.querySelectorAll('.announcement-body, #available-courses-container, #curriculum-progress-widget, #grades-chart-container, #my-courses .row, #history');
            widgets.forEach(el => {
                if (el) el.innerHTML = message;
            });
        }

        function handlePartialError(type) {
            const errorHtml = `<div class="text-center p-3 text-danger"><small>載入失敗</small></div>`;
            // 根據類型找到對應容器並顯示錯誤
            let selector = '';
            switch (type) {
                case 'courses': selector = '#available-courses-container, #my-courses .row, #history'; break;
                case 'announcements': selector = '.announcement-body'; break;
                case 'curriculum': selector = '#curriculum-progress-widget'; break;
                case 'grades': selector = '#grades-chart-container'; break;
            }
            if (selector) {
                document.querySelectorAll(selector).forEach(el => { if (el) el.innerHTML = errorHtml; });
            }
        }

        function loadMoodleData() {
            showLoading();

            // 🚀 改為發送單一請求取得所有資料，減少連線數與 Session 鎖定競爭
            fetch(`api/get_moodle_data.php?type=all`, {
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

                    // 同步渲染所有區塊
                    if (data.available_courses) renderAvailableCourses(data.available_courses);
                    if (data.my_courses_raw) renderMyCourses(data.my_courses_raw);
                    if (data.history_by_year) renderLearningHistory(data.history_by_year);
                    if (data.latest_announcements) renderAnnouncements(data.latest_announcements);
                    if (data.curriculum_status) {
                        renderCurriculumStatus(data.curriculum_status);
                        renderCurriculumProgressWidget(data.curriculum_status);
                    }
                    if (data.grades) renderGradesChart(data.grades);

                    console.log('🚀 Moodle 資料統一載入完成');
                })
                .catch(error => {
                    console.error(`❌ 載入 Moodle 資料失敗:`, error);
                    // 顯示各區塊錯誤
                    ['courses', 'announcements', 'curriculum', 'grades'].forEach(type => handlePartialError(type));
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