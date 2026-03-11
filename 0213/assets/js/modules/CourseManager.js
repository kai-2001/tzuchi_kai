/**
 * CourseManager.js
 * Handles course rendering and interactions
 */

import { api } from './ApiClient.js';

class CourseManager {
    constructor() {
        this.config = window.PortalConfig;
        this.isLoading = false;
        this.loading3dHtml = `
            <div class="loader-3d-portal">
                <div class="portal-ring ring-outer"></div>
                <div class="portal-ring ring-inner"></div>
                <div class="portal-core"></div>
                <div class="portal-text">Loading...</div>
            </div>
        `;
    }

    /**
     * Initialize course management
     */
    init() {
        // Expose critical functions globally for backward compatibility with onclick handlers
        window.goToMoodle = (url) => this.goToMoodle(url);
        window.refreshTeacherCourses = () => this.loadTeacherCourses();
        window.filterTeacherCourses = (keyword) => this.filterTeacherCourses(keyword);
    }

    /**
     * Go to Moodle (SSO or Direct)
     * @param {string} targetUrl 
     */
    goToMoodle(targetUrl) {
        // Ensure URL is complete
        if (!targetUrl.startsWith('http')) {
            targetUrl = this.config.moodleUrl + (targetUrl.startsWith('/') ? targetUrl : '/' + targetUrl);
        }

        // Logic from original main.js (simplified for now, ideally call main.js function)
        // Check if main.js functions are available
        if (typeof window.redirectWithSSO === 'function') {
            // Use existing global function for now to minimize breakage
            // In Phase 2, we should move redirectWithSSO to a module
            const moodleBase = this.config.moodleUrl;
            // Remove base if present for the legacy handler? 
            // Actually original main.js expects relative or full? 
            // Let's stick to the original behavior: just redirect
            window.redirectWithSSO(targetUrl);
        } else {
            window.location.href = targetUrl;
        }
    }

    /**
     * Create Course Card HTML
     */
    createCourseCard(course, type) {
        const moodleUrl = this.config.moodleUrl;
        const mainCat = course.parent_category || course.categoryname || '其他';
        const subCat = (course.child_category && course.child_category !== mainCat) ? course.child_category : '';
        const progress = course.progress || 0;

        // Generate ID key for filtering
        let typeKey = mainCat.replace(/[^a-zA-Z0-9\u4e00-\u9fa5]/g, '');
        if (!typeKey) {
            typeKey = 'cat-' + Math.abs(mainCat.split('').reduce((a, b) => {
                a = ((a << 5) - a) + b.charCodeAt(0); return a & a
            }, 0));
        }

        let statusHtml = '';
        let buttonHtml = '';

        if (type === 'my_courses' || course.is_enrolled) {
            // Enrolled
            statusHtml = `<span class="badge ${progress >= 100 ? 'bg-success' : 'bg-warning'} ms-2" style="font-size: 10px;">
                            ${progress >= 100 ? '已完成' : '學習中 (' + progress.toFixed(2) + '%)'}
                          </span>`;
            buttonHtml = `<button class="btn btn-sm" 
                                  style="background: #f1f5f9; color: var(--primary); border: 1px solid var(--primary); border-radius: 20px; padding: 8px 20px;"
                                  onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')">
                              <i class="fas fa-sign-in-alt me-1"></i>進入課程
                          </button>`;
        } else {
            // Available
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
    }

    /**
     * Render Available Courses
     */
    renderAvailableCourses(courses) {
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

        // Generate Filter Buttons
        this.renderFilters(courses);

        // Render Cards
        container.innerHTML = courses.map(course => this.createCourseCard(course, 'available')).join('');
        container.classList.add('fade-in');
    }

    /**
     * Render Filters
     */
    renderFilters(courses) {
        const filterContainer = document.getElementById('course-type-filters');
        // Only render if container exists and not empty
        if (!filterContainer) return;

        const categorySet = new Set();
        courses.forEach(course => {
            const catName = course.parent_category || course.categoryname || '其他';
            categorySet.add(catName);
        });

        if (categorySet.size === 0) return;

        // Keep optional button if exists
        const optionalBtn = document.getElementById('optionalCoursesBtn');
        const optionalBtnHtml = optionalBtn ? optionalBtn.outerHTML : '';

        let filterHtml = '<button class="filter-btn active" data-type="all">全部</button>';
        if (optionalBtnHtml) filterHtml += optionalBtnHtml;

        categorySet.forEach(catName => {
            let typeKey = catName.replace(/[^a-zA-Z0-9\u4e00-\u9fa5]/g, '');
            if (!typeKey) {
                typeKey = 'cat-' + Math.abs(catName.split('').reduce((a, b) => {
                    a = ((a << 5) - a) + b.charCodeAt(0); return a & a
                }, 0));
            }
            filterHtml += `<button class="filter-btn" data-type="${typeKey}">${catName}</button>`;
        });

        filterContainer.innerHTML = filterHtml;

        // Re-bind events
        filterContainer.querySelectorAll('.filter-btn:not(.optional-filter-btn)').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const optSection = document.getElementById('optional-courses-section');
                if (optSection) optSection.style.display = 'none';

                const availContainer = document.getElementById('available-courses-container');
                if (availContainer) availContainer.style.display = 'flex';

                // Assuming filterCoursesByType is global or we implement it here
                // For now, let's assume global compatibility
                if (typeof window.filterCoursesByType === 'function') {
                    window.filterCoursesByType(e.target.dataset.type, e.target);
                }
            });
        });
    }

    /**
     * Render My Courses
     */
    renderMyCourses(courses) {
        const container = document.getElementById('my-courses-container');
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

        container.innerHTML = courses.map(course => this.createCourseCard(course, 'my_courses')).join('');
        container.classList.add('fade-in');
    }

    /**
     * Load Teacher Courses
     */
    async loadTeacherCourses() {
        if (!this.config.user.roles.courseCreator) return;

        const container = document.getElementById('teacher-courses-list');
        if (!container) return;

        container.innerHTML = this.loading3dHtml;

        try {
            // Using logic from original file: api/get_moodle_data.php?type=courses
            const result = await api.get('api/get_moodle_data.php', { type: 'courses' });

            if (!result.success) throw new Error(result.message || 'Unknown error');
            const courses = result.data?.my_courses_raw || [];
            this.renderTeacherCourses(courses);
        } catch (error) {
            console.error('Failed to load teacher courses:', error);
            container.innerHTML = `
                <div class="text-center py-5 text-danger">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <p>課程載入失敗，請重新整理頁面</p>
                </div>
            `;
        }
    }

    renderTeacherCourses(courses) {
        const container = document.getElementById('teacher-courses-list');
        if (!container) return;

        const moodleUrl = this.config.moodleUrl;
        const mgmtCatId = this.config.user.managementCategoryId;
        const addCourseUrl = `${moodleUrl}/course/edit.php` + (mgmtCatId > 0 ? `?category=${mgmtCatId}` : '');

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

    filterTeacherCourses(keyword) {
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
    }
    /**
     * Load all dashboard data (Atomic Concurrent Loading)
     */
    loadAllData() {
        if (this.config.user.roles.admin) return; // Admin doesn't need this

        // Show loading state
        this.showLoading();

        // 1. Load Courses (My Courses, Available, History)
        this.fetchSubData('courses', (data) => {
            if (data.my_courses_raw) this.renderMyCourses(data.my_courses_raw);
            if (data.available_courses) this.renderAvailableCourses(data.available_courses);
            if (data.history_by_year) this.renderLearningHistory(data.history_by_year);
        });

        // 2. Load Curriculum Status
        this.fetchSubData('curriculum', (data) => {
            if (data.curriculum_status) {
                this.renderCurriculumStatus(data.curriculum_status);
                this.renderCurriculumProgressWidget(data.curriculum_status);
            }
        });

        // 3. Load Announcements
        this.fetchSubData('announcements', (data) => {
            if (data.latest_announcements) this.renderAnnouncements(data.latest_announcements);
        });

        // 4. Load Grades
        this.fetchSubData('grades', (data) => {
            if (data.grades) this.renderGradesChart(data.grades);
        });
    }

    /**
     * Show Loading Indicators
     */
    showLoading() {
        const sections = [
            '.announcement-body',
            '#available-courses-container',
            '.curriculum-section table tbody',
            '#my-courses-container',
            '#history',
            '#curriculum-progress-widget',
            '#grades-chart-container'
        ];

        sections.forEach(selector => {
            const el = document.querySelector(selector);
            if (el) {
                el.innerHTML = this.loading3dHtml;
            }
        });
    }

    /**
     * Fetch Sub Data Helper
     */
    async fetchSubData(type, renderer) {
        try {
            // Using legacy API for now, will migrate to v2 later
            const result = await api.get('api/get_moodle_data.php', { type });

            // Handle Moodle User Not Found
            if (result.data_not_found) {
                this.handleUserNotFound();
                return;
            }

            const data = result.data;

            // Handle Timeout Errors
            if (data && data.error === 'MOODLE_TIMEOUT') throw new Error('MOODLE_TIMEOUT');
            if (type === 'courses' && data.my_courses_raw?.error === 'MOODLE_TIMEOUT') throw new Error('MOODLE_TIMEOUT');
            if (type === 'announcements' && data.latest_announcements?.error === 'MOODLE_TIMEOUT') throw new Error('MOODLE_TIMEOUT');

            renderer(data);

        } catch (error) {
            console.error(`Failed to load ${type}:`, error);
            const isTimeout = error.message.includes('TIMEOUT') || error.message.includes('逾時');
            this.handlePartialError(type, isTimeout);
        }
    }

    /**
     * Handle User Not Found
     */
    handleUserNotFound() {
        const message = `
            <div class="text-center py-5">
                <i class="fas fa-info-circle fa-3x mb-3" style="opacity:0.2;"></i>
                <p class="text-muted">目前的帳號尚未有關聯的 Moodle 課程資料</p>
            </div>
        `;
        const widgets = document.querySelectorAll('.announcement-body, #available-courses-container, #curriculum-progress-widget, #grades-chart-container, #my-courses-container, #history');
        widgets.forEach(el => { if (el) el.innerHTML = message; });
    }

    /**
     * Handle Partial Error
     */
    handlePartialError(type, isTimeout = false) {
        const msg = isTimeout ? '連線逾時，請重新整理頁面' : '載入失敗';
        const icon = isTimeout ? 'fa-clock' : 'fa-exclamation-triangle';
        const errorHtml = `<div class="text-center p-3 text-danger"><i class="fas ${icon} me-1"></i><small>${msg}</small></div>`;

        let selector = '';
        switch (type) {
            case 'courses': selector = '#available-courses-container, #my-courses-container, #history'; break;
            case 'announcements': selector = '.announcement-body'; break;
            case 'curriculum': selector = '#curriculum-progress-widget'; break;
            case 'grades': selector = '#grades-chart-container'; break;
        }
        if (selector) {
            document.querySelectorAll(selector).forEach(el => { if (el) el.innerHTML = errorHtml; });
        }
    }

    /**
     * Render Announcements
     */
    renderAnnouncements(announcements) {
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

        const moodleUrl = this.config.moodleUrl;
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

    /**
     * Render Curriculum Status (Table)
     */
    renderCurriculumStatus(status) {
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

        const moodleUrl = this.config.moodleUrl;
        tbody.innerHTML = Object.entries(status).map(([catName, items]) => {
            let icons = '';
            if (!items || items.length === 0) {
                icons = '<span class="text-muted">無課程</span>';
            } else {
                const mandatoryItems = items.filter(i => i.is_mandatory_section);
                const regularItems = items.filter(i => !i.is_mandatory_section && i.status !== 'separator');
                const hasSeparator = items.some(i => i.status === 'separator');

                const sortByStatus = (a, b) => {
                    const order = { green: 0, yellow: 1, red: 2 };
                    return (order[a.status] ?? 2) - (order[b.status] ?? 2);
                };
                mandatoryItems.sort(sortByStatus);
                regularItems.sort(sortByStatus);

                const renderItem = (item, isMandatory) => {
                    const fullCatName = item.category_name || catName;
                    const suffix = isMandatory ? ' (必修)' : '';
                    const title = `${item.fullname} (${fullCatName}): ${item.status === 'green' ? '已完成' : item.status === 'yellow' ? '未完成' : '尚未選課'}${suffix}`;
                    const iconClass = item.status === 'green' ? 'fas fa-check-circle icon-green' :
                        item.status === 'yellow' ? 'fas fa-exclamation-circle icon-yellow' :
                            'far fa-play-circle icon-red';

                    const content = `<i class="${iconClass} status-icon" title="${title}" data-bs-toggle="tooltip"></i>`;

                    if (item.status === 'green') return content;
                    return `<a href="#" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${item.id}'); return false;" style="text-decoration:none;">${content}</a>`;
                };

                if (mandatoryItems.length > 0) {
                    icons += `<span style="display:inline;background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;padding:1px 6px;margin-right:3px;" title="必修">
                                <i class="fas fa-star" style="color:#f59e0b;font-size:0.6rem;margin-right:2px;"></i>
                                ${mandatoryItems.map(item => renderItem(item, true)).join('')}
                              </span>`;
                }
                if (hasSeparator && regularItems.length > 0) icons += '<span style="margin:0 4px;color:#cbd5e1;font-weight:300;">|</span>';
                icons += regularItems.map(item => renderItem(item, false)).join('');
            }

            return `<tr><td><strong>${catName}</strong></td><td>${icons}</td></tr>`;
        }).join('');

        tbody.classList.add('fade-in');
        if (typeof bootstrap !== 'undefined') {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
        }
    }

    /**
     * Render Curriculum Progress Widget (Homepage)
     */
    renderCurriculumProgressWidget(status) {
        const container = document.getElementById('curriculum-progress-widget');
        const summaryEl = document.getElementById('progress-summary');
        const progressFill = document.getElementById('overall-progress-fill');
        const progressText = document.getElementById('overall-progress-text');

        if (!container) return;

        if (!status || Object.keys(status).length === 0) {
            container.innerHTML = `<div class="text-center py-4 text-muted"><i class="fas fa-info-circle me-2"></i>目前無必修課程設定</div>`;
            if (progressText) progressText.textContent = '無資料';
            return;
        }

        const moodleUrl = this.config.moodleUrl;
        let totalCourses = 0;
        let completedCourses = 0;
        let html = '<div class="progress-categories">';

        Object.entries(status).forEach(([catName, items]) => {
            const hasCourses = items && items.some(item => item.status !== 'separator' && (item.id > 0 || item.is_mandatory_section));
            if (!hasCourses) return;

            const isRequiredCategory = items.some(item => item.is_mandatory_section);
            const categoryLabel = isRequiredCategory ?
                `<strong>${catName}</strong> <span style="background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;padding:1px 6px;font-size:0.7rem;color:#92400e;margin-left:6px;"><i class="fas fa-star" style="font-size:0.6rem;margin-right:2px;"></i>必修</span>` :
                `<strong>${catName}</strong>`;

            html += `<div class="progress-category-row"><div class="category-name">${categoryLabel}</div><div class="category-items">`;

            if (!items || items.length === 0) {
                html += '<span class="text-muted">無課程</span>';
            } else {
                const mandatoryItems = items.filter(i => i.is_mandatory_section);
                const regularItems = items.filter(i => !i.is_mandatory_section && i.status !== 'separator');
                const hasSeparator = items.some(i => i.status === 'separator');

                const sortByStatus = (a, b) => {
                    const order = { green: 0, yellow: 1, red: 2 };
                    return (order[a.status] ?? 2) - (order[b.status] ?? 2);
                };
                mandatoryItems.sort(sortByStatus);
                regularItems.sort(sortByStatus);

                const renderItem = (item) => {
                    totalCourses++;
                    let iconClass, iconColor, title, clickAttr;

                    if (item.status === 'green') {
                        completedCourses++;
                        iconClass = 'fas fa-check-circle';
                        iconColor = '#10b981';
                        title = `${item.fullname}: 已完成 ✅`;
                        clickAttr = item.id > 0 ? `onclick="goToMoodle('${moodleUrl}/course/view.php?id=${item.id}')" style="cursor:pointer;"` : '';
                    } else if (item.status === 'yellow') {
                        iconClass = 'fas fa-spinner';
                        iconColor = '#f59e0b';
                        title = `${item.fullname}: 進行中`;
                        clickAttr = item.id > 0 ? `onclick="goToMoodle('${moodleUrl}/course/view.php?id=${item.id}')" style="cursor:pointer;"` : '';
                    } else {
                        iconClass = 'far fa-circle';
                        iconColor = '#94a3b8';
                        title = item.fullname || '未選課';
                        clickAttr = item.id > 0 ?
                            `onclick="goToMoodle('${moodleUrl}/course/view.php?id=${item.id}')" style="cursor:pointer;"` :
                            `onclick="alert('💡 ${item.fullname || '請挑選課程'}')" style="cursor:pointer;"`;
                    }
                    return `<span class="progress-item" ${clickAttr} title="${title}" data-bs-toggle="tooltip"><i class="${iconClass}" style="color: ${iconColor}; font-size: 1.5rem;"></i></span>`;
                };

                if (mandatoryItems.length > 0) {
                    html += `<span style="display:inline-flex;align-items:center;gap:2px;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:2px 8px;margin-right:4px;" title="必修課程">
                                <i class="fas fa-star" style="color:#f59e0b;font-size:0.7rem;margin-right:3px;"></i>
                                ${mandatoryItems.map(renderItem).join('')}
                             </span>`;
                }
                if (hasSeparator && regularItems.length > 0) html += '<span style="display:inline-flex;align-items:center;margin:0 6px;color:#cbd5e1;font-size:1.5rem;font-weight:300;">|</span>';
                html += regularItems.map(renderItem).join('');
            }
            html += '</div></div>';
        });

        html += '</div>';
        container.innerHTML = html;
        container.classList.add('fade-in');

        const percentage = totalCourses > 0 ? Math.round((completedCourses / totalCourses) * 100) : 0;
        if (progressFill) progressFill.style.width = percentage + '%';
        if (progressText) progressText.textContent = `${percentage}% 完成 (${completedCourses}/${totalCourses} 門課程)`;
        if (summaryEl) summaryEl.innerHTML = `<span class="badge bg-primary">${completedCourses}/${totalCourses} 門完成</span>`;

        if (typeof bootstrap !== 'undefined') {
            container.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
        }
    }

    /**
     * Render Learning History
     */
    renderLearningHistory(historyByYear) {
        const container = document.querySelector('#history');
        if (!container) return;

        const title = container.querySelector('h3');
        container.innerHTML = '';
        if (title) container.appendChild(title);
        else container.innerHTML = '<h3 class="mb-4 fw-bold" style="color: var(--primary);"><i class="fas fa-history me-2"></i>學習歷程</h3>';

        if (!historyByYear || Object.keys(historyByYear).length === 0) {
            container.innerHTML += `<div class="text-center py-5 text-muted"><p>目前沒有學習紀錄</p></div>`;
            return;
        }

        const moodleUrl = this.config.moodleUrl;
        container.innerHTML += Object.entries(historyByYear).map(([year, courses]) => `
            <div class="mb-5">
                <h5 class="mb-4"><span class="year-badge"><i class="fas fa-calendar-alt me-2"></i>${year} 年度</span></h5>
                <div class="row g-4">
                    ${courses.map(course => `
                        <div class="col-md-4">
                            <div class="card course-card h-100" style="cursor:pointer;" onclick="goToMoodle('${moodleUrl}/course/view.php?id=${course.id}')">
                                <div class="card-body"><h6 class="card-title fw-bold">${course.fullname}</h6></div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `).join('');
        container.classList.add('fade-in');
    }

    /**
     * Render Grades Chart
     */
    renderGradesChart(grades) {
        const container = document.getElementById('grades-chart-container');
        if (!container) return;

        if (!grades || grades.length === 0) {
            container.innerHTML = `<div class="text-center py-4 text-muted"><p class="mb-0">目前沒有成績資料</p></div>`;
            return;
        }

        let html = '<div class="vertical-bar-chart">';
        grades.forEach(grade => {
            const percentage = grade.grade_max > 0 ? (grade.grade / grade.grade_max) * 100 : 0;
            let barColor = percentage < 60 ? '#ef4444' : percentage < 80 ? '#f59e0b' : '#10b981';

            html += `
                <div class="bar-column" title="${grade.course_name}: ${grade.grade_formatted}">
                    <div class="bar-value">${Math.round(grade.grade)}</div>
                    <div class="bar-track"><div class="bar-fill" style="height: ${percentage}%; background: ${barColor};"></div></div>
                    <div class="bar-label">${grade.course_name.substring(0, 8)}...</div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
        container.classList.add('fade-in');
    }
}

export const courseManager = new CourseManager();
