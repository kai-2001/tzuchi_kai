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
            <!-- 頁面標題 -->
            <div class="page-header-v2">
                <div class="page-header-v2__actions">
                    <a href="index.php?page=student_degree_audit&tab=domains" class="btn-v2 btn-v2--ghost btn-v2--sm">
                        <i class="fas fa-arrow-left"></i>
                        返回修課進度
                    </a>
                    <h1 class="page-header-v2__title m-0" id="domain-page-title">載入中...</h1>
                </div>
                <p class="page-header-v2__subtitle">探索並選擇您的類別課程</p>
            </div>

            <!-- 進度卡片 -->
            <div class="card-v2 card-v2--elevated mb-6" id="domain-progress-container">
                <div class="card-v2__body text-center py-4 text-muted">
                    <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                    <p>載入進度資料中...</p>
                </div>
            </div>

            <!-- 搜尋工具列 -->
            <div class="card-v2"
                style="margin-bottom: var(--space-5); border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                <div class="card-v2__body" style="padding: var(--space-5);">
                    <div style="display: flex; gap: var(--space-3);">
                        <div style="flex: 1; position: relative;">
                            <input type="text" id="domain-course-search" class="input-v2" placeholder="輸入課程名稱關鍵字..."
                                style="width: 100%; padding: 0 20px; height: 50px; font-size: 15px; border-radius: var(--radius-md); border: 1.5px solid var(--border-default); transition: all 0.2s;"
                                onfocus="this.style.borderColor='var(--brand-primary)'; this.style.boxShadow='0 0 0 3px rgba(37, 99, 235, 0.1)'"
                                onblur="this.style.borderColor='var(--border-default)'; this.style.boxShadow='none'"
                                onkeypress="if(event.key === 'Enter') typeof applyDomainSearch === 'function' && applyDomainSearch()">
                        </div>
                        <button class="btn-v2 btn-v2--outline-primary"
                            onclick="typeof applyDomainSearch === 'function' && applyDomainSearch()"
                            style="height: 50px; padding: 0 32px; font-size: 15px; font-weight: 600; border-radius: var(--radius-md); white-space: nowrap;">
                            <i class="fas fa-search" style="margin-right: 8px;"></i>
                            搜尋
                        </button>
                    </div>
                </div>
            </div>

            <!-- 課程清單 -->
            <div style="display: flex; flex-direction: column; gap: var(--space-3);" id="domain-courses-container">
                <div class="text-center py-5 text-muted w-100">
                    <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                    <p>載入課程清單中...</p>
                </div>
            </div>

            <!-- 分頁控制 -->
            <div id="domain-pagination-container" class="mt-4" style="display: none;"></div>

            <script>
                const moodleUrl = window.PortalConfig ? window.PortalConfig.moodleUrl : '';

                // Extract domain from URL
                const urlParams = new URLSearchParams(window.location.search);
                const targetDomainName = urlParams.get('domain') || '';

                let initialDisplay = targetDomainName;
                if (initialDisplay.includes(' - ')) {
                    initialDisplay = initialDisplay.split(' - ').pop().trim();
                }
                document.getElementById('domain-page-title').textContent = initialDisplay ? `必修類別 - ${initialDisplay}` : '必修類別';

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

                let allDomainCoursesData = [];
                let filteredDomainCoursesData = [];
                let currentPage = 1;
                const itemsPerPage = 8;
                let currentDomainName = '';
                let isReqMap = new Set();

                function renderPaginatedDomainCourses() {
                    const container = document.getElementById('domain-courses-container');
                    const paginationContainer = document.getElementById('domain-pagination-container');

                    if (filteredDomainCoursesData.length === 0) {
                        const searchTerm = document.getElementById('domain-course-search').value.trim();
                        if (searchTerm) {
                            container.innerHTML = `
                                <div class="empty-search-msg text-center py-5 text-muted w-100">
                                    <i class="fas fa-search fa-3x mb-3" style="opacity: 0.2;"></i>
                                    <p>找不到符合「<span class="search-term">${searchTerm}</span>」的課程</p>
                                </div>
                            `;
                        } else {
                            container.innerHTML = '<div class="text-center py-5 text-muted w-100">此類別目前沒有設定課程</div>';
                        }
                        paginationContainer.style.display = 'none';
                        return;
                    }

                    const totalPages = Math.ceil(filteredDomainCoursesData.length / itemsPerPage);
                    if (currentPage > totalPages) currentPage = totalPages;
                    if (currentPage < 1) currentPage = 1;

                    const startIndex = (currentPage - 1) * itemsPerPage;
                    const endIndex = Math.min(startIndex + itemsPerPage, filteredDomainCoursesData.length);
                    const currentCourses = filteredDomainCoursesData.slice(startIndex, endIndex);

                    let coursesHtml = '';

                    currentCourses.forEach(item => {
                        const { domainCourse, courseMeta, taken, available, isRequired, isCompleted } = item;

                        let reqTag = isRequired ? '<span class="course-tag course-tag--domain" style="margin-left: 8px;">必修</span> ' : '';
                        let displayDomain = currentDomainName.includes(' - ') ? currentDomainName.split(' - ').pop().trim() : currentDomainName;

                        if (isCompleted || domainCourse.status === 'green') {
                            coursesHtml += `
                                <div class="course-list-item">
                                    <div class="course-list-item__content">
                                        <div class="course-list-item__title">${courseMeta.fullname} ${reqTag}</div>
                                        <div class="course-list-item__meta">${taken && taken.startdate ? new Date(taken.startdate * 1000).getFullYear() + ' 年' : '-'} • <i class="fas fa-folder text-muted mr-1"></i> ${displayDomain}</div>
                                    </div>
                                    <div class="course-list-item__grade"><span class="course-tag" style="background-color: rgba(34, 197, 94, 0.1); color: var(--success);">已完成</span>
                                    <button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${courseMeta.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm" style="margin-left:8px;" ${courseMeta.id ? '' : 'disabled'}>
                                        <i class="fas fa-arrow-right"></i> 進入
                                    </button></div>
                                </div>
                            `;
                        } else if (taken && taken.failed) {
                            coursesHtml += `
                                <div class="course-list-item" style="background: rgba(239, 68, 68, 0.05);">
                                    <div class="course-list-item__content">
                                        <div class="course-list-item__title">${courseMeta.fullname} ${reqTag}</div>
                                        <div class="course-list-item__meta">${taken.startdate ? new Date(taken.startdate * 1000).getFullYear() + ' 年' : '-'} • <i class="fas fa-folder text-muted mr-1"></i> ${displayDomain}</div>
                                    </div>
                                    <div class="course-list-item__grade">
                                        <span class="course-tag" style="background-color: rgba(239, 68, 68, 0.1); color: var(--error);">未通過</span>
                                        <button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${courseMeta.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm" style="margin-left:8px;">
                                            <i class="fas fa-arrow-right"></i> 進入
                                        </button>
                                    </div>
                                </div>
                            `;
                        } else if (taken) {
                            coursesHtml += `
                                <div class="course-list-item">
                                    <div class="course-list-item__content">
                                        <div class="course-list-item__title">${courseMeta.fullname} ${reqTag}</div>
                                        <div class="course-list-item__meta">${taken.startdate ? new Date(taken.startdate * 1000).getFullYear() + ' 年' : '-'} • <i class="fas fa-folder text-muted mr-1"></i> ${displayDomain}</div>
                                    </div>
                                    <div class="course-list-item__grade">
                                        <span class="course-tag course-tag--warning">進行中</span>
                                        <button type="button" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${courseMeta.id}')" class="btn-v2 btn-v2--outline-info btn-v2--sm" style="margin-left:8px;">
                                            <i class="fas fa-arrow-right"></i> 進入
                                        </button>
                                    </div>
                                </div>
                            `;
                        } else if (courseMeta.id) {
                            coursesHtml += `
                                <div class="course-list-item">
                                    <div class="course-list-item__content">
                                        <div class="course-list-item__title">${courseMeta.fullname} ${reqTag}</div>
                                        <div class="course-list-item__meta">- • <i class="fas fa-folder text-muted mr-1"></i> ${displayDomain}</div>
                                    </div>
                                    <div class="course-list-item__grade">
                                        <span class="course-tag" style="background-color: rgba(107, 114, 128, 0.1); color: var(--text-muted);">未選課</span>
                                        <button type="button" onclick="directEnrolCourse(${courseMeta.id})" class="btn-v2 btn-v2--primary btn-v2--sm" style="margin-left:8px;">
                                            <i class="fas fa-plus"></i> 選課
                                        </button>
                                    </div>
                                </div>
                            `;
                        } else {
                            coursesHtml += `
                                <div class="course-list-item" style="opacity: 0.7;">
                                    <div class="course-list-item__content">
                                        <div class="course-list-item__title">${courseMeta.fullname} ${reqTag}</div>
                                        <div class="course-list-item__meta">- • <i class="fas fa-folder text-muted mr-1"></i> ${displayDomain}</div>
                                    </div>
                                    <div class="course-list-item__grade">
                                        <span class="course-tag" style="background-color: rgba(107, 114, 128, 0.1); color: var(--text-muted);">未開課</span>
                                        <button type="button" class="btn-v2 btn-v2--secondary btn-v2--sm" style="margin-left:8px; opacity:0; pointer-events:none;">
                                            <i class="fas fa-plus"></i> 選課
                                        </button>
                                    </div>
                                </div>
                            `;
                        }
                    });

                    container.innerHTML = coursesHtml;

                    // Render pagination
                    if (totalPages > 1) {
                        let pageHtml = `<div class="pagination-v2" style="justify-content: center;">`;
                        pageHtml += `
                            <button class="pagination-v2__btn" onclick="changeDomainPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                        `;

                        // Simple pagination logic (no complex ellipses for now unless > 7 pages)
                        for (let i = 1; i <= totalPages; i++) {
                            if (totalPages <= 7 || i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                                pageHtml += `
                                    <button class="pagination-v2__btn ${i === currentPage ? 'pagination-v2__btn--active' : ''}" onclick="changeDomainPage(${i})">
                                        ${i}
                                    </button>
                                `;
                            } else if (i === currentPage - 2 || i === currentPage + 2) {
                                pageHtml += `<span class="pagination-v2__dots">...</span>`;
                            }
                        }

                        pageHtml += `
                            <button class="pagination-v2__btn" onclick="changeDomainPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>`;
                        paginationContainer.innerHTML = pageHtml;
                        paginationContainer.style.display = 'flex';
                    } else {
                        paginationContainer.style.display = 'none';
                    }
                }

                window.changeDomainPage = function (page) {
                    currentPage = page;
                    renderPaginatedDomainCourses();
                    window.scrollTo({ top: 0, behavior: 'smooth' }); // Scroll back to top
                };

                document.addEventListener('DOMContentLoaded', () => {
                    Promise.all([
                        fetchSubData('courses').catch(e => ({ error: e.message })),
                        fetchSubData('curriculum').catch(e => ({ error: e.message }))
                    ]).then(([coursesResult, curriculumResult]) => {
                        const myCourses = coursesResult.my_courses_raw || [];
                        const availableCourses = coursesResult.available_courses || [];
                        const curriculum = curriculumResult.curriculum_status || null;

                        if (!curriculum) {
                            document.getElementById('domain-progress-container').innerHTML = '<div class="card-v2__body text-center py-4">無法載入類別資料</div>';
                            document.getElementById('domain-courses-container').innerHTML = '';
                            return;
                        }

                        // Get all valid domain names (categories with at least one real course)
                        const curriculumData = curriculum.status || curriculum;
                        const domainNames = Object.keys(curriculumData).filter(cat => Array.isArray(curriculumData[cat]) && curriculumData[cat].some(c => c.status !== 'separator'));

                        let domainName = targetDomainName;
                        if (!curriculumData[domainName] && domainNames.length > 0) {
                            domainName = domainNames[0];
                        }

                        if (!domainName || !curriculumData[domainName]) {
                            document.getElementById('domain-progress-container').innerHTML = '<div class="card-v2__body text-center py-4">查無對應類別設定</div>';
                            document.getElementById('domain-courses-container').innerHTML = '';
                            return;
                        }

                        currentDomainName = domainName;

                        let displayDomainName = domainName;
                        if (displayDomainName.includes(' - ')) {
                            displayDomainName = displayDomainName.split(' - ').pop().trim();
                        }
                        document.getElementById('domain-page-title').textContent = `必修類別 - ${displayDomainName}`;

                        const domainCourses = curriculumData[domainName].filter(c => c.status !== 'separator');

                        // Render Progress Card
                        const requiredCount = (curriculum.quotas && curriculum.quotas[domainName]) ? curriculum.quotas[domainName] : domainCourses.length;
                        const completedCount = domainCourses.filter(c => c.status === 'green').length;
                        const remainingCount = requiredCount - completedCount < 0 ? 0 : requiredCount - completedCount;
                        const progressPercent = requiredCount > 0 ? Math.min(100, Math.round((completedCount / requiredCount) * 100)) : 0;

                        document.getElementById('domain-progress-container').innerHTML = `
                    <div class="card-v2__body">
                        <div class="domain-progress-summary">
                            <div class="flex-1">
                                <h2 style="font-size: 20px; font-weight: 600; margin-bottom: var(--space-2);">${displayDomainName}</h2>
                                <p style="font-size: 14px; color: var(--text-secondary); margin-bottom: var(--space-4);">
                                    <i class="fas fa-info-circle"></i>
                                    此類別包含之必修課程至少需完成 ${requiredCount} 門課程
                                </p>
                                <div class="progress-bar progress-bar--lg mb-2">
                                    <div class="progress-bar__fill" style="width: ${progressPercent}%;"></div>
                                </div>
                                <div class="domain-progress-details">
                                    <span>已完成 ${completedCount} 門</span>
                                    <span>${progressPercent}%</span>
                                </div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-box__value ${remainingCount > 0 ? 'stat-box__value--error' : 'text-success'}">${remainingCount > 0 ? remainingCount : '<i class="fas fa-check"></i>'}</div>
                                <div class="stat-box__label">${remainingCount > 0 ? '還需課程數' : '已達標'}</div>
                            </div>
                        </div>
                    </div>
                `;

                        // Also we need to get mandatory courses from curriculum because they shouldn't count here
                        // We'll collect all mandatory course names across all categories
                        const mandatoryCourseNames = new Set();
                        Object.values(curriculumData).forEach(items => {
                            if (!Array.isArray(items)) return;
                            items.forEach(c => {
                                if (c.is_mandatory_section) mandatoryCourseNames.add(c.fullname);
                            });
                        });
                        isReqMap = mandatoryCourseNames;

                        // 從 API 傳回的 curriculumData 中濾除假補位的「請挑選此領域中...」空資料 (id == 0)
                        const realDomainCourses = domainCourses.filter(c => c.id != 0);

                        // 防呆: 就算 API 的額度分配機制(quotas)造成同一個真實課程出現在陣列多次，在清單頁面只顯示一次(依據課程 ID)
                        const uniqueCoursesMap = new Map();

                        realDomainCourses.forEach(domainCourse => {
                            if (!uniqueCoursesMap.has(domainCourse.id)) {
                                let taken = undefined;
                                let available = undefined;

                                if (domainCourse.id > 0) {
                                    taken = myCourses.find(mc => mc.id == domainCourse.id);
                                    available = availableCourses.find(ac => ac.id == domainCourse.id);
                                }

                                const courseMeta = taken || available || { fullname: domainCourse.fullname, id: domainCourse.id || null };
                                const isCompleted = (taken && taken.progress >= 100) && (!taken.failed);
                                const isRequired = mandatoryCourseNames.has(courseMeta.fullname) || mandatoryCourseNames.has(domainCourse.fullname);

                                uniqueCoursesMap.set(domainCourse.id, {
                                    domainCourse,
                                    courseMeta,
                                    taken,
                                    available,
                                    isRequired,
                                    isCompleted
                                });
                            }
                        });

                        allDomainCoursesData = Array.from(uniqueCoursesMap.values());

                        filteredDomainCoursesData = [...allDomainCoursesData];
                        renderPaginatedDomainCourses();

                        // 榜定搜尋功能
                        window.applyDomainSearch = function () {
                            const searchInput = document.getElementById('domain-course-search');
                            if (!searchInput) return;

                            const term = searchInput.value.toLowerCase().trim();

                            if (term === '') {
                                filteredDomainCoursesData = [...allDomainCoursesData];
                            } else {
                                filteredDomainCoursesData = allDomainCoursesData.filter(item =>
                                    item.courseMeta.fullname.toLowerCase().includes(term)
                                );
                            }

                            currentPage = 1;
                            renderPaginatedDomainCourses();
                        };
                    });
                });
            </script>

            <!-- 全域功能與 SSO 登入處理 -->
            <script src="<?php echo $web_root; ?>/assets/js/student-main.js?v=<?php echo time(); ?>"></script>
</body>

</html>