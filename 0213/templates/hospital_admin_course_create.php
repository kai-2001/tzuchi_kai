<?php
/**
 * 新增課程頁面 - 複刻 Moodle UI
 * templates/hospital_admin_course_create.php
 */
?>

<style>
    /* ========== Moodle Form Styles (複刻) ========== */
    .moodle-form {
        max-width: 1000px;
        margin: 0 auto;
        background: white;
        border-radius: 12px;
        padding: 32px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .moodle-form .page-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 24px;
    }

    .moodle-form .expand-all {
        float: right;
        color: #3b82f6;
        font-size: 0.9rem;
        cursor: pointer;
    }

    /* Section Header (可摺疊區塊) */
    .form-section {
        margin-bottom: 8px;
        border-bottom: 1px solid #e2e8f0;
    }

    .section-header {
        display: flex;
        align-items: center;
        padding: 12px 0;
        cursor: pointer;
        user-select: none;
    }

    .section-header:hover {
        background: #f8fafc;
        margin: 0 -16px;
        padding: 12px 16px;
        border-radius: 8px;
    }

    .section-header .chevron {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        transition: transform 0.2s;
    }

    .section-header.collapsed .chevron {
        transform: rotate(-90deg);
    }

    .section-header h3 {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
        margin-left: 8px;
    }

    .section-content {
        padding: 16px 0;
        transition: all 0.3s ease;
    }

    .section-content.collapsed {
        display: none;
    }

    /* Form Item (表單元素) */
    .fitem {
        display: flex;
        margin-bottom: 16px;
        align-items: flex-start;
    }

    .fitem .col-label {
        flex: 0 0 200px;
        padding-right: 16px;
        padding-top: 8px;
        text-align: right;
    }

    .fitem .col-label label {
        color: #334155;
        font-weight: 500;
        font-size: 0.9rem;
    }

    .fitem .col-label .form-label-addon {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-left: 8px;
    }

    .fitem .col-label .req-icon {
        color: #ef4444;
        font-size: 1rem;
    }

    .fitem .col-label .help-icon {
        color: #3b82f6;
        font-size: 0.85rem;
        cursor: help;
        background: #eff6ff;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .fitem .col-input {
        flex: 1;
    }

    .fitem .form-control {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 0.95rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .fitem .form-control:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }

    .fitem .form-select {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 0.95rem;
        background-color: white;
    }

    /* Date Selector (日期選擇器) */
    .date-selector {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
    }

    .date-selector select,
    .date-selector input[type="number"] {
        width: auto;
        padding: 6px 10px;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        font-size: 0.9rem;
    }

    .date-selector .date-sep {
        color: #64748b;
        padding: 0 2px;
    }

    .date-selector .calendar-btn {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        padding: 6px 10px;
        cursor: pointer;
        color: #64748b;
    }

    .date-selector .calendar-btn:hover {
        background: #e2e8f0;
    }

    /* Category Select (類別選擇器 - Tag Style) */
    .category-select-wrapper {
        position: relative;
    }

    .selected-category {
        display: inline-flex;
        align-items: center;
        background: #eff6ff;
        border: 1px solid #93c5fd;
        border-radius: 20px;
        padding: 6px 12px;
        margin-bottom: 8px;
        font-size: 0.9rem;
        color: #1e40af;
    }

    .selected-category .remove-btn {
        margin-left: 8px;
        cursor: pointer;
        color: #3b82f6;
    }

    .selected-category .remove-btn:hover {
        color: #1d4ed8;
    }

    /* Visibility Toggle */
    .visibility-toggle {
        display: inline-flex;
        background: #f1f5f9;
        border-radius: 6px;
        overflow: hidden;
    }

    .visibility-toggle button {
        padding: 8px 16px;
        border: none;
        background: transparent;
        cursor: pointer;
        color: #64748b;
        font-size: 0.9rem;
        transition: all 0.2s;
    }

    .visibility-toggle button.active {
        background: white;
        color: #1e293b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* Enable Checkbox (啟用 checkbox) */
    .enable-checkbox {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .enable-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #3b82f6;
    }

    /* Form Actions */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid #e2e8f0;
    }

    .form-actions .btn {
        padding: 10px 24px;
        border-radius: 6px;
        font-weight: 500;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .form-actions .btn-primary {
        background: #3b82f6;
        color: white;
        border: none;
    }

    .form-actions .btn-primary:hover {
        background: #2563eb;
    }

    .form-actions .btn-secondary {
        background: white;
        color: #64748b;
        border: 1px solid #cbd5e1;
    }

    .form-actions .btn-secondary:hover {
        background: #f8fafc;
    }

    /* Rich Text Editor */
    .summary-editor {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        overflow: hidden;
    }

    .summary-editor .editor-toolbar {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 8px;
        display: flex;
        gap: 4px;
    }

    .summary-editor .editor-toolbar button {
        padding: 6px 10px;
        border: none;
        background: transparent;
        border-radius: 4px;
        cursor: pointer;
        color: #64748b;
    }

    .summary-editor .editor-toolbar button:hover {
        background: #e2e8f0;
    }

    .summary-editor textarea {
        width: 100%;
        min-height: 150px;
        border: none;
        padding: 12px;
        font-size: 0.95rem;
        resize: vertical;
    }

    .summary-editor textarea:focus {
        outline: none;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .fitem {
            flex-direction: column;
        }

        .fitem .col-label {
            text-align: left;
            padding-bottom: 8px;
        }
    }
</style>

<div class="moodle-form">
    <div class="page-header">
        <h1 class="page-title">新增課程</h1>
        <a class="expand-all" onclick="toggleAllSections()">展開全部</a>
    </div>

    <form id="courseForm" onsubmit="return submitCourse(event)">
        <!-- 一般區塊 -->
        <div class="form-section">
            <div class="section-header" onclick="toggleSection(this)">
                <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                <h3>一般</h3>
            </div>
            <div class="section-content">
                <!-- 課程全名 -->
                <div class="fitem">
                    <div class="col-label">
                        <label>課程全名</label>
                        <span class="form-label-addon">
                            <span class="req-icon" title="必填">!</span>
                            <span class="help-icon" title="課程的完整名稱">?</span>
                        </span>
                    </div>
                    <div class="col-input">
                        <input type="text" class="form-control" name="fullname" id="fullname" maxlength="254"
                            style="width: 100%;" required>
                    </div>
                </div>

                <!-- 課程簡稱 -->
                <div class="fitem">
                    <div class="col-label">
                        <label>課程簡稱</label>
                        <span class="form-label-addon">
                            <span class="req-icon" title="必填">!</span>
                            <span class="help-icon" title="用於導覽和報表的簡短名稱">?</span>
                        </span>
                    </div>
                    <div class="col-input">
                        <input type="text" class="form-control" name="shortname" id="shortname" maxlength="255"
                            style="width: 300px;" required>
                    </div>
                </div>

                <!-- 課程類別 -->
                <div class="fitem">
                    <div class="col-label">
                        <label>課程類別</label>
                        <span class="form-label-addon">
                            <span class="req-icon" title="必填">!</span>
                            <span class="help-icon" title="選擇課程所屬的類別">?</span>
                        </span>
                    </div>
                    <div class="col-input">
                        <div class="category-select-wrapper">
                            <div id="selectedCategory" class="selected-category" style="display: none;">
                                <span class="cat-path"></span>
                                <span class="remove-btn" onclick="clearCategory()">×</span>
                            </div>
                            <select class="form-select" id="categorySelect" name="categoryid"
                                style="width: 100%; max-width: 400px;" required>
                                <option value="">搜尋...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 課程可見度 -->
                <div class="fitem">
                    <div class="col-label">
                        <label>課程可見度</label>
                        <span class="form-label-addon">
                            <span class="help-icon" title="學生是否能看到此課程">?</span>
                        </span>
                    </div>
                    <div class="col-input">
                        <div class="visibility-toggle">
                            <button type="button" class="active" data-value="1"
                                onclick="setVisibility(1, this)">顯示</button>
                            <button type="button" data-value="0" onclick="setVisibility(0, this)">隱藏</button>
                        </div>
                        <input type="hidden" name="visible" id="visible" value="1">
                    </div>
                </div>

                <!-- 課程開始日期 -->
                <div class="fitem">
                    <div class="col-label">
                        <label>課程開始日期</label>
                        <span class="form-label-addon">
                            <span class="help-icon" title="課程開始的日期">?</span>
                        </span>
                    </div>
                    <div class="col-input">
                        <div class="date-selector">
                            <input type="number" name="start_day" id="start_day" min="1" max="31"
                                value="<?= date('d') ?>" style="width: 60px;">
                            <select name="start_month" id="start_month">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>><?= $m ?>月</option>
                                <?php endfor; ?>
                            </select>
                            <input type="number" name="start_year" id="start_year" min="2020" max="2030"
                                value="<?= date('Y') ?>" style="width: 80px;">
                            <input type="number" name="start_hour" id="start_hour" min="0" max="23" value="00"
                                style="width: 60px;">
                            <input type="number" name="start_minute" id="start_minute" min="0" max="59" value="00"
                                style="width: 60px;">
                            <button type="button" class="calendar-btn"><i class="fa fa-calendar"></i></button>
                        </div>
                    </div>
                </div>

                <!-- 課程結束日期 -->
                <div class="fitem">
                    <div class="col-label">
                        <label>課程結束日期</label>
                        <span class="form-label-addon">
                            <span class="help-icon" title="課程結束的日期（可選）">?</span>
                        </span>
                    </div>
                    <div class="col-input">
                        <div class="enable-checkbox">
                            <input type="checkbox" id="enddate_enabled" name="enddate_enabled" checked>
                            <label for="enddate_enabled">啟用</label>
                        </div>
                        <div class="date-selector" id="endDateSelector" style="margin-top: 8px;">
                            <input type="number" name="end_day" id="end_day" min="1" max="31" value="<?= date('d') ?>"
                                style="width: 60px;">
                            <select name="end_month" id="end_month">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>><?= $m ?>月</option>
                                <?php endfor; ?>
                            </select>
                            <input type="number" name="end_year" id="end_year" min="2020" max="2030"
                                value="<?= date('Y') + 1 ?>" style="width: 80px;">
                            <input type="number" name="end_hour" id="end_hour" min="0" max="23" value="00"
                                style="width: 60px;">
                            <input type="number" name="end_minute" id="end_minute" min="0" max="59" value="00"
                                style="width: 60px;">
                            <button type="button" class="calendar-btn"><i class="fa fa-calendar"></i></button>
                        </div>
                    </div>
                </div>

                <!-- 課程編號 -->
                <div class="fitem">
                    <div class="col-label">
                        <label>課程編號</label>
                        <span class="form-label-addon">
                            <span class="help-icon" title="課程的識別編號（可選）">?</span>
                        </span>
                    </div>
                    <div class="col-input">
                        <input type="text" class="form-control" name="idnumber" id="idnumber" maxlength="100"
                            style="width: 200px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- 說明區塊 -->
        <div class="form-section">
            <div class="section-header" onclick="toggleSection(this)">
                <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                <h3>說明</h3>
            </div>
            <div class="section-content">
                <!-- 課程摘要 -->
                <div class="fitem">
                    <div class="col-label">
                        <label>課程摘要</label>
                        <span class="form-label-addon">
                            <span class="help-icon" title="課程的簡短描述">?</span>
                        </span>
                    </div>
                    <div class="col-input">
                        <div class="summary-editor">
                            <div class="editor-toolbar">
                                <button type="button" onclick="formatText('bold')"><i class="fa fa-bold"></i></button>
                                <button type="button" onclick="formatText('italic')"><i
                                        class="fa fa-italic"></i></button>
                                <button type="button" onclick="formatText('underline')"><i
                                        class="fa fa-underline"></i></button>
                                <button type="button" onclick="formatText('list')"><i
                                        class="fa fa-list-ul"></i></button>
                            </div>
                            <textarea name="summary" id="summary" placeholder="輸入課程描述..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 表單按鈕 -->
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="history.back()">取消</button>
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-save me-2"></i>儲存並前往招生
            </button>
        </div>
    </form>
</div>

<script>
    // 載入類別選項
    document.addEventListener('DOMContentLoaded', function () {
        loadCategories();

        // 結束日期啟用開關
        document.getElementById('enddate_enabled').addEventListener('change', function () {
            document.getElementById('endDateSelector').style.display = this.checked ? 'flex' : 'none';
        });
    });

    async function loadCategories() {
        try {
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/get_categories');
            const data = await res.json();

            if (data.success && data.categories) {
                const select = document.getElementById('categorySelect');
                select.innerHTML = '<option value="">搜尋...</option>';

                data.categories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat.id;
                    option.textContent = cat.path;
                    option.dataset.path = cat.path;
                    select.appendChild(option);
                });
            }
        } catch (e) {
            console.error('載入類別失敗', e);
        }
    }

    function toggleSection(header) {
        header.classList.toggle('collapsed');
        const content = header.nextElementSibling;
        content.classList.toggle('collapsed');
    }

    function toggleAllSections() {
        const headers = document.querySelectorAll('.section-header');
        const allCollapsed = Array.from(headers).every(h => h.classList.contains('collapsed'));

        headers.forEach(h => {
            if (allCollapsed) {
                h.classList.remove('collapsed');
                h.nextElementSibling.classList.remove('collapsed');
            } else {
                h.classList.add('collapsed');
                h.nextElementSibling.classList.add('collapsed');
            }
        });
    }

    function setVisibility(value, btn) {
        document.getElementById('visible').value = value;
        document.querySelectorAll('.visibility-toggle button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    function clearCategory() {
        document.getElementById('selectedCategory').style.display = 'none';
        document.getElementById('categorySelect').value = '';
        document.getElementById('categorySelect').style.display = 'block';
    }

    // 類別選擇後顯示為 tag
    document.getElementById('categorySelect')?.addEventListener('change', function () {
        if (this.value) {
            const option = this.options[this.selectedIndex];
            document.querySelector('#selectedCategory .cat-path').textContent = option.dataset.path || option.textContent;
            document.getElementById('selectedCategory').style.display = 'inline-flex';
            // this.style.display = 'none'; // 保持可見讓使用者可以換類別
        }
    });

    async function submitCourse(e) {
        e.preventDefault();

        const form = document.getElementById('courseForm');
        const formData = new FormData(form);
        formData.append('action', 'create');

        // 組合日期
        const startDate = `${formData.get('start_year')}-${String(formData.get('start_month')).padStart(2, '0')}-${String(formData.get('start_day')).padStart(2, '0')} ${String(formData.get('start_hour')).padStart(2, '0')}:${String(formData.get('start_minute')).padStart(2, '0')}:00`;
        formData.set('startdate', startDate);

        if (formData.get('enddate_enabled')) {
            const endDate = `${formData.get('end_year')}-${String(formData.get('end_month')).padStart(2, '0')}-${String(formData.get('end_day')).padStart(2, '0')} ${String(formData.get('end_hour')).padStart(2, '0')}:${String(formData.get('end_minute')).padStart(2, '0')}:00`;
            formData.set('enddate', endDate);
        }

        try {
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/create', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                // 跳轉到招生篩選頁面
                if (data.course_id) {
                    window.location.href = `${PortalConfig.webRoot}/index.php?page=course_enrol&course_id=${data.course_id}`;
                } else if (data.data && data.data[0]) {
                    window.location.href = `${PortalConfig.webRoot}/index.php?page=course_enrol&course_id=${data.data[0].id}`;
                } else {
                    alert('課程已建立！');
                    history.back();
                }
            } else {
                alert('錯誤：' + (data.error || '建立失敗'));
            }
        } catch (e) {
            console.error(e);
            alert('發生錯誤');
        }

        return false;
    }
</script>