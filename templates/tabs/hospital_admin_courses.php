<?php
/**
 * 院區管理員 - 課程管理介面
 * templates/tabs/hospital_admin_courses.php
 */
?>
<div id="section-course-management" class="page-section">
    <div class="section-header">
        <h2><i class="fas fa-chalkboard"></i> 課程管理</h2>
        <p class="section-subtitle">管理、新增或隱藏 Moodle 課程</p>
    </div>

    <!-- 工具列 -->
    <div class="toolbar-container" style="flex-wrap: wrap; gap: 10px;">
        <!-- 類別選擇器 -->
        <div style="display:flex; align-items:center; gap:8px;">
            <label for="course-cat-select" style="font-weight:500;">選擇類別：</label>
            <select id="course-cat-select" onchange="loadCourses(this.value)"
                style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px;">
                <option value="0">載入中...</option>
            </select>
        </div>

        <div style="margin-left: auto; display:flex; gap:10px;">
            <button class="btn-secondary" onclick="loadCourses()">
                <i class="fas fa-sync-alt"></i> 重新載入
            </button>
            <button class="btn-primary" onclick="openCourseModal('add')">
                <i class="fas fa-plus"></i> 新增課程
            </button>
        </div>
    </div>

    <!-- 課程列表 -->
    <div class="widget-card">
        <div class="widget-body" id="courses-list">
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- 新增/編輯課程 Modal -->
<div id="course-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 500px; padding: 24px; border-radius: 12px;">
        <div class="modal-header" style="border-bottom: 1px solid #f0f0f0; padding-bottom: 16px; margin-bottom: 20px;">
            <h3 id="course-modal-title" style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;">新增課程
            </h3>
            <button class="modal-close" onclick="closeCourseModal()">&times;</button>
        </div>
        <form id="course-form" onsubmit="saveCourse(event)">
            <input type="hidden" id="course-id" name="id">
            <input type="hidden" id="course-modal-mode" name="action" value="create">
            <input type="hidden" id="course-category-id" name="category_id">

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="course-fullname"
                    style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">課程全名 <span
                        style="color:#ef4444">*</span></label>
                <input type="text" id="course-fullname" name="fullname" required placeholder="例如：2024 新進人員教育訓練"
                    style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px;">
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="course-shortname"
                    style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">課程簡稱 <span
                        style="color:#ef4444">*</span></label>
                <input type="text" id="course-shortname" name="shortname" required placeholder="例如：new_staff_2024"
                    style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px;">
                <small style="color:#6b7280; display:block; margin-top:4px;">簡稱必須唯一，通常使用英數組合</small>
            </div>

            <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 10px;">
                <button type="button" class="btn-secondary" onclick="closeCourseModal()"
                    style="padding: 10px 18px; border-radius: 8px; background: #f3f4f6; color: #374151; border: none; cursor: pointer; font-weight: 500;">
                    取消
                </button>
                <button type="submit" class="btn-primary" id="course-submit-btn"
                    style="padding: 10px 18px; border-radius: 8px; background: #3b82f6; color: white; border: none; cursor: pointer; font-weight: 500;">
                    儲存
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let allCourses = [];
    let currentCatId = 0;

    // 初始化：先載入類別，再載入第一分類的課程
    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('section-course-management')) {
            initCoursePage();
        }
    });

    function initCoursePage() {
        // 載入類別下拉選單
        const select = document.getElementById('course-cat-select');
        fetch('/api/hospital_admin/manage_category.php?action=list')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    select.innerHTML = '';
                    data.data.forEach(cat => {
                        const option = document.createElement('option');
                        option.value = cat.id;
                        option.textContent = cat.name;
                        select.appendChild(option);
                    });
                    // 載入第一個類別的課程
                    currentCatId = data.data[0].id; // 或者 select.value
                    loadCourses(currentCatId);
                } else {
                    select.innerHTML = '<option value="0">無子類別</option>';
                    document.getElementById('courses-list').innerHTML = '<div class="empty-state"><p>請先建立子類別</p></div>';
                }
            })
            .catch(err => {
                console.error('Init courses error:', err);
                select.innerHTML = '<option value="0">載入失敗</option>';
            });
    }

    function loadCourses(catId) {
        if (catId) currentCatId = catId;
        else catId = document.getElementById('course-cat-select').value;

        if (!catId || catId == 0) return;

        const container = document.getElementById('courses-list');
        container.innerHTML = `
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px;"></div>
            </div>`;

        fetch(`/api/hospital_admin/manage_course.php?action=list&category_id=${catId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    allCourses = data.data || [];
                    renderCourses(allCourses);
                } else {
                    container.innerHTML = `<div class="error-message">無法載入課程: ${data.error}</div>`;
                }
            })
            .catch(err => {
                console.error('Load courses error:', err);
                container.innerHTML = `<div class="error-message">網路錯誤</div>`;
            });
    }

    function renderCourses(courses) {
        const container = document.getElementById('courses-list');
        if (!courses || courses.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-chalkboard fa-3x"></i>
                    <p>此類別尚無課程</p>
                    <button class="btn-primary" onclick="openCourseModal('add')">
                        <i class="fas fa-plus"></i> 新增第一門課程
                    </button>
                </div>`;
            return;
        }

        let html = '<table class="member-table"><thead><tr>';
        html += '<th style="width: 50px;">ID</th><th>課程全名</th><th>簡稱</th><th>狀態</th><th style="text-align:center">操作</th>';
        html += '</tr></thead><tbody>';

        courses.forEach(c => {
            const isVisible = c.visible == 1;
            const statusBadge = isVisible
                ? '<span class="badge bg-green" style="background:#d1fae5; color:#065f46;">顯示中</span>'
                : '<span class="badge bg-gray" style="background:#f3f4f6; color:#374151;">隱藏</span>';
            const toggleIcon = isVisible ? 'fa-eye-slash' : 'fa-eye';
            const toggleTitle = isVisible ? '隱藏' : '顯示';

            html += `<tr>
                <td style="color:#6b7280;">${c.id}</td>
                <td><strong>${escapeHtml(c.fullname)}</strong></td>
                <td>${escapeHtml(c.shortname)}</td>
                <td>${statusBadge}</td>
                <td class="action-cell" style="text-align:center">
                    <button class="btn-icon btn-edit" onclick="openCourseModal('edit', ${c.id}, '${escapeHtml(c.fullname)}', '${escapeHtml(c.shortname)}')" title="編輯">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon btn-role" onclick="toggleCourseVisible(${c.id}, ${c.visible})" title="${toggleTitle}">
                        <i class="fas ${toggleIcon}"></i>
                    </button>
                    <button class="btn-icon btn-delete" onclick="deleteCourse(${c.id}, '${escapeHtml(c.fullname)}')" title="刪除">
                        <i class="fas fa-trash"></i>
                    </button>
                    <a href="<?php echo $moodle_url; ?>/course/view.php?id=${c.id}" target="_blank" class="btn-icon" title="前往課程">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </td>
            </tr>`;
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function openCourseModal(mode, id, fullname, shortname) {
        const modal = document.getElementById('course-modal');
        const form = document.getElementById('course-form');
        form.reset();

        document.getElementById('course-modal-mode').value = (mode === 'add') ? 'create' : 'update';
        document.getElementById('course-id').value = id || '';
        document.getElementById('course-category-id').value = currentCatId;

        if (mode === 'add') {
            document.getElementById('course-modal-title').textContent = '新增課程';
        } else {
            document.getElementById('course-modal-title').textContent = '編輯課程';
            document.getElementById('course-fullname').value = fullname;
            document.getElementById('course-shortname').value = shortname;
        }

        modal.style.display = 'flex';
        setTimeout(() => document.getElementById('course-fullname').focus(), 100);
    }

    function closeCourseModal() {
        document.getElementById('course-modal').style.display = 'none';
    }

    function saveCourse(e) {
        e.preventDefault();
        const form = document.getElementById('course-form');
        const formData = new FormData(form);
        const btn = document.getElementById('course-submit-btn');
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 處理中...';

        fetch('/api/hospital_admin/manage_course.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                if (data.success) {
                    closeCourseModal();
                    loadCourses(currentCatId);
                    showToast(data.message || '操作成功', 'success');
                } else {
                    showToast(data.error || '操作失敗', 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showToast('網路錯誤', 'error');
            });
    }

    function toggleCourseVisible(id, currentVisible) {
        const newVisible = currentVisible == 1 ? 0 : 1;
        const formData = new FormData();
        formData.append('action', 'toggle_visible');
        formData.append('id', id);
        formData.append('visible', newVisible);

        fetch('/api/hospital_admin/manage_course.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadCourses(currentCatId);
                    showToast('狀態已更新', 'success');
                } else {
                    showToast(data.error || '更新失敗', 'error');
                }
            });
    }

    function deleteCourse(id, fullname) {
        if (!confirm(`確定要刪除課程「${fullname}」嗎？\n\n警告：此操作不可復原！`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        fetch('/api/hospital_admin/manage_course.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadCourses(currentCatId);
                    showToast('課程已刪除', 'success');
                } else {
                    showToast(data.error || '刪除失敗', 'error');
                }
            });
    }
</script>