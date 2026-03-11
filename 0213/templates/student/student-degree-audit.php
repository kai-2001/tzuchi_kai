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
    <student-nav active="progress"></student-nav>

    <!-- 主內容 -->
    <main class="layout-main">
        <div class="container">
            <!-- 頁面標題 -->
            <div class="page-header-v2">
                <h1 class="page-header-v2__title">修課進度</h1>
                <p class="page-header-v2__subtitle">追蹤學位完成進度並快速選修所需課程</p>
            </div>



            <!-- 頁籤 -->
            <div class="tabs-v2" style="margin-bottom: var(--space-6);">
                <button class="tabs-v2__tab tabs-v2__tab--active" data-tab="required">
                    <i class="fas fa-bookmark"></i>
                    必修課程
                    <span class="badge-v2 badge-v2--sm badge-v2--default" style="margin-left: var(--space-2);"
                        id="badge-required">-/-</span>
                </button>
                <button class="tabs-v2__tab" data-tab="domains">
                    <i class="fas fa-layer-group"></i>
                    必修類別
                    <span class="badge-v2 badge-v2--sm badge-v2--default" style="margin-left: var(--space-2);"
                        id="badge-domains">-/-</span>
                </button>
                <button class="tabs-v2__tab" data-tab="electives">
                    <i class="fas fa-star"></i>
                    自由選修
                    <span class="badge-v2 badge-v2--sm badge-v2--default" style="margin-left: var(--space-2);"
                        id="badge-electives">-</span>
                </button>
            </div>

            <!-- 必修課程區塊 -->
            <div id="required-content" class="tab-content tab-content--active">

                <!-- 必修課程清單 -->
                <div class="card-v2" style="margin-bottom: var(--space-6);">
                    <div class="card-v2__header">
                        <h2 class="card-v2__title">
                            <i class="fas fa-bookmark"></i>
                            必修課程清單
                        </h2>
                        <span class="badge-v2 badge-v2--primary" id="mandatory-progress-badge">- / - 門</span>
                    </div>
                    <div class="card-v2__body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="table-v2">
                                <thead>
                                    <tr>
                                        <th>課程名稱</th>
                                        <th style="width: 100px;">狀態</th>
                                        <th style="width: 120px;">操作</th>
                                    </tr>
                                </thead>
                                <tbody id="required-courses-list">
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fas fa-spinner fa-spin mb-2"></i> 載入中...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 類別必修區塊 -->
            <div id="domains-content" class="tab-content" style="display: none;">
                <!-- 類別必修 -->
                <div class="card-v2">
                    <div class="card-v2__header">
                        <h2 class="card-v2__title">
                            <i class="fas fa-layer-group"></i>
                            必修類別進度
                        </h2>
                    </div>
                    <div class="card-v2__body" id="domains-list-container">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-spinner fa-spin mb-2"></i> 載入類別設定中...
                        </div>
                    </div>
                </div>
            </div>

            <!-- 自由選修區塊 -->
            <div id="electives-content" class="tab-content" style="display: none;">
                <div class="card-v2">
                    <div class="card-v2__header">
                        <h2 class="card-v2__title">
                            <i class="fas fa-star"></i>
                            自由選修課程
                        </h2>
                    </div>
                    <div class="card-v2__body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="table-v2">
                                <thead>
                                    <tr>
                                        <th>課程名稱</th>
                                        <th style="width: 100px;">狀態</th>
                                        <th style="width: 120px;">操作</th>
                                    </tr>
                                </thead>
                                <tbody id="electives-list-container">
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            <i class="fas fa-spinner fa-spin mb-2"></i> 載入中...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
    </main>

    <script>
        // 頁籤切換功能
        document.querySelectorAll('.tabs-v2__tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;

                // 移除所有 active 狀態
                document.querySelectorAll('.tabs-v2__tab').forEach(t => {
                    t.classList.remove('tabs-v2__tab--active');
                });
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('tab-content--active');
                    content.style.display = 'none';
                });

                // 添加當前 active 狀態
                tab.classList.add('tabs-v2__tab--active');
                const targetContent = document.getElementById(targetTab + '-content');
                targetContent.classList.add('tab-content--active');
                targetContent.style.display = 'block';
                targetContent.style.display = 'block';
            });
        });

        // 檢查 URL 參數並自動切換頁籤
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const targetTabBtn = document.querySelector(`.tabs-v2__tab[data-tab="${tabParam}"]`);
                if (targetTabBtn) {
                    targetTabBtn.click();
                }
            }

            // Load Degree Audit Data
            loadDegreeAuditData();
        });

        // ================= API Data Loading and Rendering =================
        const moodleUrl = window.PortalConfig ? window.PortalConfig.moodleUrl : '';

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
        function loadDegreeAuditData() {
            fetchSubData('all')
                .then(result => {
                    const courses = result.my_courses_raw || [];
                    const availableCourses = result.available_courses || [];
                    const curriculum = result.curriculum_status || null;
                    const grades = result.grades || [];

                    if (!curriculum) {
                        console.error("Critical: curriculum data missing", result);
                        document.getElementById('audit-stats-container').innerHTML = '<div class="card-v2__body text-center py-4">無法載入學位資料</div>';
                        return;
                    }

                    try {
                        renderAuditData(courses, availableCourses, curriculum, grades);
                    } catch (e) {
                        console.error("Error rendering audit data:", e);
                        document.getElementById('audit-stats-container').innerHTML = `<div class="card-v2__body text-center py-4 text-danger">渲染資料發生錯誤: ${e.message}</div>`;
                    }
                })
                .catch(e => {
                    console.error("Failed to fetch all data:", e);
                    document.getElementById('audit-stats-container').innerHTML = `<div class="card-v2__body text-center py-4 text-danger">網路連線或伺服器發生錯誤: ${e.message}</div>`;
                });
        }

        function renderAuditData(courses, availableCourses, curriculum, grades) {
            let totalCreditsRequired = 132;
            let creditsEarned = 0;

            let mandatoryCompletedCount = 0;
            let mandatoryTotalCount = 0;

            let domainsCompletedCount = 0;
            let domainsTotalCount = 0;

            let reqHtml = '';
            let domainsHtml = '';

            const usedCourseIds = new Set();

            // Function to find course from courses list
            const findTakenCourse = (courseObj) => {
                if (!courses || !courseObj || !courseObj.fullname) return undefined;
                if (courseObj.id > 0) return courses.find(c => c.id == courseObj.id);
                return courses.find(c => c.fullname && (c.fullname.includes(courseObj.fullname) || courseObj.fullname.includes(c.fullname)));
            };
            const findAvailableCourse = (courseObj) => {
                if (!availableCourses || !courseObj || !courseObj.fullname) return undefined;
                if (courseObj.id > 0) return availableCourses.find(c => c.id == courseObj.id);
                return availableCourses.find(c => c.fullname && (c.fullname.includes(courseObj.fullname) || courseObj.fullname.includes(c.fullname)));
            };

            const curriculumData = curriculum?.status || curriculum || {};
            Object.entries(curriculumData).forEach(([catName, items]) => {
                if (!items || !Array.isArray(items)) return;

                const hasCourses = items && items.length > 0;
                if (!hasCourses) return;

                // 1. Process Required Courses
                const mandatoryItems = items.filter(item => item.is_mandatory_section && item.status !== 'separator');
                mandatoryItems.forEach(reqCourse => {
                    mandatoryTotalCount++;
                    const takenCourse = findTakenCourse(reqCourse);
                    const availableCourse = findAvailableCourse(reqCourse);

                    if (takenCourse && takenCourse.id) usedCourseIds.add(takenCourse.id);

                    if (takenCourse) {
                        const gradeObj = grades.find(g => g.course_id == takenCourse.id);
                        const progress = takenCourse.progress || 0;
                        const isCompleted = (reqCourse.status === 'green' || progress >= 100) && !takenCourse.failed;

                        let displayCatName = catName;
                        if (displayCatName.includes(' - ')) {
                            displayCatName = displayCatName.split(' - ').pop().trim();
                        }

                        let deadlineStr = '';
                        if (takenCourse.enddate && takenCourse.enddate > 0) {
                            const endDate = new Date(takenCourse.enddate * 1000);
                            deadlineStr = ` <span style="margin-left: 8px; font-weight: 500; font-size: 13px; color: var(--text-muted);"><i class="fas fa-clock mr-1"></i> 期限: ${endDate.getFullYear()}/${String(endDate.getMonth() + 1).padStart(2, '0')}/${String(endDate.getDate()).padStart(2, '0')}</span>`;
                        }

                        if (isCompleted) {
                            mandatoryCompletedCount++;
                            creditsEarned += 3;
                            reqHtml += `
                                <tr>
                                    <td>
                                        <strong>${reqCourse.fullname}</strong>
                                        <span class="course-tag course-tag--domain" style="margin-left: 8px;">${displayCatName}</span>
                                        ${deadlineStr}
                                    </td>
                                    <td><span class="course-tag" style="background-color: rgba(34, 197, 94, 0.1); color: var(--success);">已完成</span></td>
                                    <td><button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${takenCourse.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm"><i class="fas fa-arrow-right"></i> 進入</button></td>
                                </tr>
                            `;
                        } else if (takenCourse.failed) {
                            reqHtml += `
                                <tr style="background: rgba(239, 68, 68, 0.05);">
                                    <td>
                                        <strong>${reqCourse.fullname}</strong>
                                        <span class="course-tag course-tag--domain" style="margin-left: 8px;">${displayCatName}</span>
                                        ${deadlineStr}
                                    </td>
                                    <td><span class="course-tag" style="background-color: rgba(239, 68, 68, 0.1); color: var(--error);">未通過</span></td>
                                    <td><button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${takenCourse.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm"><i class="fas fa-arrow-right"></i> 進入</button></td>
                                </tr>
                            `;
                        } else {
                            reqHtml += `
                                <tr style="background: rgba(59, 130, 246, 0.02);">
                                    <td>
                                        <strong>${reqCourse.fullname}</strong>
                                        <span class="course-tag course-tag--domain" style="margin-left: 8px;">${displayCatName}</span>
                                        ${deadlineStr}
                                    </td>
                                    <td><span class="course-tag course-tag--warning">進行中</span></td>
                                    <td><button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${takenCourse.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm"><i class="fas fa-arrow-right"></i> 進入</button></td>
                                </tr>
                            `;
                        }
                    } else if (availableCourse) {
                        let displayCatName = catName;
                        if (displayCatName.includes(' - ')) {
                            displayCatName = displayCatName.split(' - ').pop().trim();
                        }
                        reqHtml += `
                            <tr>
                                <td>
                                    <strong>${reqCourse.fullname}</strong>
                                    <span class="course-tag course-tag--domain" style="margin-left: 8px;">${displayCatName}</span>
                                </td>
                                <td><span class="course-tag" style="background-color: rgba(107, 114, 128, 0.1); color: var(--text-muted);">未選課</span></td>
                                <td><button type="button" onclick="directEnrolCourse(${availableCourse.id})" class="btn-v2 btn-v2--primary btn-v2--sm"><i class="fas fa-plus"></i> 選課</button></td>
                            </tr>
                        `;
                    } else {
                        let displayCatName = catName;
                        if (displayCatName.includes(' - ')) {
                            displayCatName = displayCatName.split(' - ').pop().trim();
                        }
                        reqHtml += `
                            <tr>
                                <td>
                                    <strong>${reqCourse.fullname}</strong>
                                    <span class="course-tag course-tag--domain" style="margin-left: 8px;">${displayCatName}</span>
                                </td>
                                <td><span class="course-tag" style="background-color: rgba(107, 114, 128, 0.1); color: var(--text-muted);">未開課</span></td>
                                <td>-</td>
                            </tr>
                        `;
                    }
                });

                // 2. Process Domains
                if (items.some(item => true)) {
                    domainsTotalCount++;
                    // 包含必修課程以顯示正確的進度比例 (1/3) 而不是被扣除 (0/2)
                    const domainCourses = items.filter(item => item.status !== 'separator');
                    // Ensure the quota correctly overrides the visual length of fallback items
                    const reqCount = (curriculum.quotas && curriculum.quotas[catName]) ? curriculum.quotas[catName] : domainCourses.length;
                    const compCount = domainCourses.filter(item => item.status === 'green').length;

                    if (compCount >= reqCount && reqCount > 0) {
                        domainsCompletedCount++;
                    }

                    const progressPercent = reqCount > 0 ? Math.min(100, Math.round((compCount / reqCount) * 100)) : 0;
                    const isCompleted = compCount >= reqCount && reqCount > 0;
                    const cardClass = isCompleted ? 'domain-summary-card--success' : '';

                    let innerCoursesHtml = '';
                    domainCourses.forEach(catCourse => {
                        let takenCourse = findTakenCourse(catCourse);
                        if (takenCourse) usedCourseIds.add(takenCourse.id);

                        if (takenCourse) {
                            const gradeObj = grades.find(g => g.course_id == takenCourse.id);
                            const gradeVal = gradeObj ? Math.round(gradeObj.grade) : '';
                            const isCourseComp = (catCourse.status === 'green' || takenCourse.progress >= 100) && !takenCourse.failed;

                            // 避免學分重複計算 (如果已經在核心必修區加過，在此處就不加)
                            if (isCourseComp && !catCourse.is_mandatory_section) creditsEarned += 3;

                            let deadlineStr = '';
                            if (takenCourse.enddate && takenCourse.enddate > 0) {
                                const endDate = new Date(takenCourse.enddate * 1000);
                                deadlineStr = ` <span style="margin-left: 8px; font-weight: 500; font-size: 13px; color: var(--text-muted);"><i class="fas fa-clock mr-1"></i> 期限: ${endDate.getFullYear()}/${String(endDate.getMonth() + 1).padStart(2, '0')}/${String(endDate.getDate()).padStart(2, '0')}</span>`;
                            }

                            if (isCourseComp) {
                                innerCoursesHtml += `
                                    <div class="course-list-item">
                                        <div class="course-list-item__content">
                                            <div class="course-list-item__title">${takenCourse.fullname} ${catCourse.is_mandatory_section ? '<span class="course-tag course-tag--domain" style="margin-left:8px;">必修</span>' : ''}</div>
                                            <div class="course-list-item__meta">${takenCourse.startdate ? new Date(takenCourse.startdate * 1000).getFullYear() + ' 年' : '-'} ${deadlineStr}</div>
                                        </div>
                                <div class="course-list-item__grade"><span class="course-tag" style="background-color: rgba(34, 197, 94, 0.1); color: var(--success);">已完成</span>
                                <button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${takenCourse.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm" style="margin-left:8px;">
                                        <i class="fas fa-arrow-right"></i> 進入課程
                                </button></div>
                            </div>
                        `;
                            } else if (takenCourse.failed) {
                                innerCoursesHtml += `
                                    <div class="course-list-item" style="background: rgba(239, 68, 68, 0.05);">
                                    <div class="course-list-item__content">
                                        <div class="course-list-item__title">${takenCourse.fullname} ${catCourse.is_mandatory_section ? '<span class="course-tag course-tag--domain" style="margin-left:8px;">必修</span>' : ''}</div>
                                        <div class="course-list-item__meta">${takenCourse.startdate ? new Date(takenCourse.startdate * 1000).getFullYear() + ' 年' : '-'} ${deadlineStr}</div>
                                    </div>
                                    <div class="course-list-item__grade">
                                        <span class="course-tag" style="background-color: rgba(239, 68, 68, 0.1); color: var(--error);">未通過</span>
                                        <button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${takenCourse.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm" style="margin-left:8px;">
                                            <i class="fas fa-arrow-right"></i> 進入課程
                                        </button>
                                    </div>
                                </div>
                                `;
                            } else {
                                innerCoursesHtml += `
                                    <div class="course-list-item">
                                    <div class="course-list-item__content">
                                        <div class="course-list-item__title">${takenCourse.fullname} ${catCourse.is_mandatory_section ? '<span class="course-tag course-tag--domain" style="margin-left:8px;">必修</span>' : ''}</div>
                                        <div class="course-list-item__meta">${takenCourse.startdate ? new Date(takenCourse.startdate * 1000).getFullYear() + ' 年' : '-'} ${deadlineStr}</div>
                                    </div>
                                    <div class="course-list-item__grade">
                                        <span class="course-tag course-tag--warning">進行中</span>
                                        <button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${takenCourse.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm" style="margin-left:8px;">
                                            <i class="fas fa-arrow-right"></i> 進入課程
                                        </button>
                                    </div>
                                </div>
                                `;
                            }
                        }
                    });

                    let displayCatName = catName;
                    if (displayCatName.includes(' - ')) {
                        displayCatName = displayCatName.split(' - ').pop().trim();
                    }

                    domainsHtml += `
                        <div class="domain-summary-card ${cardClass}" style="margin-bottom: var(--space-4);">
                            <div class="domain-summary-card__header">
                                <div>
                                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 4px;">${displayCatName}</h3>
                                </div>
                                <div class="text-right">
                                    <div style="font-size: 32px; font-weight: 700; color: var(--${isCompleted ? 'success' : 'brand-primary'});">
                                        ${compCount} <span style="font-size: 20px; color: var(--text-muted);">/ ${reqCount} 門</span>
                                    </div>
                                    ${isCompleted ? `<div class="text-sm text-success">✓ 已完成</div>` : ''}
                                </div>
                            </div>
                            <div class="progress-bar progress-bar--lg" style="margin-bottom: var(--space-4);">
                                <div class="progress-bar__fill ${isCompleted ? 'progress-bar__fill--success' : ''}" style="width: ${progressPercent}%;"></div>
                            </div>

                            ${innerCoursesHtml ? `
                            <div style="margin-bottom: var(--space-4);">
                                <div style="font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-2);">已計入課程：</div>
                                <div style="display: flex; flex-direction: column; gap: var(--space-2);">
                                    ${innerCoursesHtml}
                                </div>
                            </div>
                            ` : ''}

                            <a href="index.php?page=student_domain_courses&domain=${encodeURIComponent(catName)}" class="btn-v2 btn-v2--outline-info">
                                <i class="fas fa-search"></i> 搜尋該類別課程
                            </a>
                        </div>
                    `;
                }
            });

            if (!reqHtml) reqHtml = '<tr><td colspan="6" class="text-center py-4 text-muted">無必修課程資料</td></tr>';
            document.getElementById('required-courses-list').innerHTML = reqHtml;

            if (!domainsHtml) domainsHtml = '<div class="text-center py-5 text-muted">無類別設定資料</div>';
            document.getElementById('domains-list-container').innerHTML = domainsHtml;

            // 3. Process Electives
            let electivesCount = 0;
            let electivesHtml = '';

            courses.forEach(course => {
                if (!usedCourseIds.has(course.id)) {
                    electivesCount++;

                    const gradeObj = grades.find(g => g.course_id == course.id);
                    const gradeVal = gradeObj ? Math.round(gradeObj.grade) : '';
                    const progress = course.progress || 0;
                    const isCompleted = progress >= 100 && !course.failed;

                    if (isCompleted) creditsEarned += 3;

                    let elCatName = course.display_category || course.parent_category || '自由選修';
                    if (elCatName.includes(' - ')) {
                        elCatName = elCatName.split(' - ').pop().trim();
                    }
                    // Deadline format
                    let deadlineStr = '';
                    if (course.enddate && course.enddate > 0) {
                        const endDate = new Date(course.enddate * 1000);
                        deadlineStr = ` <span style="margin-left: 8px; font-weight: 500; font-size: 13px; color: var(--text-muted);"><i class="fas fa-clock mr-1"></i> 期限: ${endDate.getFullYear()}/${String(endDate.getMonth() + 1).padStart(2, '0')}/${String(endDate.getDate()).padStart(2, '0')}</span>`;
                    }

                    if (isCompleted) {
                        electivesHtml += `
                            <tr>
                                <td>
                                    <strong>${course.fullname}</strong>
                                    <span class="course-tag course-tag--elective" style="margin-left: 8px;">${elCatName}</span>
                                    ${deadlineStr}
                                </td>
                                <td><span class="course-tag" style="background-color: rgba(34, 197, 94, 0.1); color: var(--success);">已完成</span></td>
                                <td><button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm"><i class="fas fa-arrow-right"></i> 進入</button></td>
                            </tr>
                        `;
                    } else if (course.failed) {
                        electivesHtml += `
                            <tr style="background: rgba(239, 68, 68, 0.05);">
                                <td>
                                    <strong>${course.fullname}</strong>
                                    <span class="course-tag course-tag--elective" style="margin-left: 8px;">${elCatName}</span>
                                    ${deadlineStr}
                                </td>
                                <td><span class="course-tag" style="background-color: rgba(239, 68, 68, 0.1); color: var(--error);">未通過</span></td>
                                <td><button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm"><i class="fas fa-arrow-right"></i> 進入</button></td>
                            </tr>
                        `;
                    } else {
                        electivesHtml += `
                            <tr style="background: rgba(59, 130, 246, 0.02);">
                                <td>
                                    <strong>${course.fullname}</strong>
                                    <span class="course-tag course-tag--elective" style="margin-left: 8px;">${elCatName}</span>
                                    ${deadlineStr}
                                </td>
                                <td><span class="course-tag course-tag--warning">進行中</span></td>
                                <td><button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm"><i class="fas fa-arrow-right"></i> 進入</button></td>
                            </tr>
                        `;
                    }
                }
            });
            if (!electivesHtml) electivesHtml = '<tr><td colspan="3" class="text-center py-4 text-muted">無自由選修資料</td></tr>';
            document.getElementById('electives-list-container').innerHTML = electivesHtml;

            // 4. Update Stats & Badges
            const badgeRequired = document.getElementById('badge-required');
            const badgeDomains = document.getElementById('badge-domains');
            const badgeElectives = document.getElementById('badge-electives');
            const mandatoryProgressBadge = document.getElementById('mandatory-progress-badge');

            if (badgeRequired) badgeRequired.textContent = `${mandatoryCompletedCount}/${mandatoryTotalCount}`;
            if (badgeDomains) badgeDomains.textContent = `${domainsCompletedCount}/${domainsTotalCount}`;
            if (badgeElectives) badgeElectives.textContent = `${electivesCount}`;
            if (mandatoryProgressBadge) mandatoryProgressBadge.textContent = `${mandatoryCompletedCount} / ${mandatoryTotalCount} 門`;

            const totalRequired = mandatoryTotalCount + domainsTotalCount;
            const totalCompleted = mandatoryCompletedCount + domainsCompletedCount;
            const totalCompletionPercent = totalRequired > 0 ? Math.min(100, Math.round((totalCompleted / totalRequired) * 100)) : 0;

        }
    </script>



    <!-- 全域功能與 SSO 登入處理 -->
    <script src="<?php echo $web_root; ?>/assets/js/student-main.js?v=<?php echo time(); ?>"></script>
</body>

</html>