<?php
/**
 * 課程招生頁面 - 依維度篩選學員 (支援多組篩選條件)
 * templates/hospital_admin_course_enrol.php
 */
?>

<style>
    .enrol-page {
        max-width: 1200px;
        margin: 0 auto;
    }

    .enrol-header {
        background: var(--bg-surface);
        border: 1px solid var(--border-default);
        border-radius: var(--radius-lg);
        padding: var(--space-6);
        margin-bottom: var(--space-6);
        box-shadow: var(--shadow-sm);
    }

    .enrol-header h1 {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0 0 8px 0;
    }

    .enrol-header .course-info {
        color: #64748b;
        font-size: 0.95rem;
    }

    .filter-section {
        background: var(--bg-surface);
        border: 1px solid var(--border-default);
        border-radius: var(--radius-lg);
        padding: var(--space-6);
        margin-bottom: var(--space-6);
        box-shadow: var(--shadow-sm);
    }

    .filter-section>h2 {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0 0 16px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .filter-section>h2 i {
        color: var(--brand-primary);
    }

    /* 篩選條件組 */
    .filter-groups-container {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .filter-group {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: var(--radius-md);
        padding: 16px;
        position: relative;
    }

    .filter-group-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .filter-group-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .filter-group-label .group-number {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
    }

    .filter-group .remove-group {
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.85rem;
        transition: all 0.2s;
    }

    .filter-group .remove-group:hover {
        background: #fee2e2;
        color: #dc2626;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }

    .filter-item {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .filter-item label {
        font-weight: 500;
        color: #334155;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .filter-item label .dim-icon {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        color: white;
    }

    .filter-item label .dim-icon.category {
        background: linear-gradient(135deg, #ec4899, #f472b6);
    }

    .filter-item label .dim-icon.location {
        background: linear-gradient(135deg, #3b82f6, #60a5fa);
    }

    .filter-item label .dim-icon.attribute {
        background: linear-gradient(135deg, #f59e0b, #fbbf24);
    }

    .filter-item select {
        padding: 10px 12px;
        border: 1px solid #cbd5e1;
        border-radius: var(--radius-md);
        font-size: 0.95rem;
        background: white;
        cursor: pointer;
        transition: all var(--duration-fast);
    }

    .filter-item select:focus {
        border-color: #3b82f6;
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* 運算符分隔線 (AND/OR) */
    .operator-divider {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #94a3b8;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .operator-divider::before,
    .operator-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(to right, transparent, #cbd5e1, transparent);
    }

    .operator-divider .operator-btn {
        background: var(--warning);
        color: white;
        padding: 6px 16px;
        border-radius: var(--radius-full);
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all var(--duration-fast) var(--ease-out);
        user-select: none;
    }

    .operator-divider .operator-btn:hover {
        transform: scale(1.02);
        box-shadow: var(--shadow-sm);
    }

    .logic-badge {
        display: inline-block;
        color: white;
        padding: 1px 6px;
        border-radius: var(--radius-sm);
        font-size: 0.7rem;
        vertical-align: middle;
    }

    .logic-or {
        background: var(--warning);
    }

    .logic-and {
        background: #8b5cf6;
    }

    /* 新增條件組按鈕 */
    .add-group-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px;
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        background: transparent;
        color: #64748b;
        font-size: 0.9rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
    }

    .add-group-btn:hover {
        border-color: #3b82f6;
        color: #3b82f6;
        background: #eff6ff;
    }

    .add-group-btn i {
        font-size: 1rem;
    }

    .filter-actions {
        display: flex;
        gap: 12px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #e2e8f0;
    }

    .filter-actions .btn {
        padding: 10px 20px;
        border-radius: var(--radius-md);
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all var(--duration-fast) var(--ease-out);
    }

    .filter-actions .btn-primary {
        background: var(--brand-primary, #3b82f6);
        color: white;
        border: none;
    }

    .filter-actions .btn-primary:hover {
        background: var(--brand-primary-hover, #2563eb);
        box-shadow: 0 2px 6px rgba(59, 130, 246, 0.2);
    }



    .filter-actions .btn-secondary {
        background: white;
        color: #64748b;
        border: 1px solid #cbd5e1;
    }

    /* 獨立標籤篩選區 */
    .tag-filter-section {
        background: linear-gradient(135deg, rgba(236, 72, 153, 0.05), rgba(244, 63, 94, 0.05));
        border: 1px solid rgba(236, 72, 153, 0.2);
        border-radius: 12px;
        padding: 16px 20px;
        margin: 16px 0;
    }

    .tag-filter-section label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        color: #334155;
        margin-bottom: 10px;
    }

    /* 標籤按鈕 */
    .tag-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 8px 0;
    }

    .tag-btn {
        padding: 6px 14px;
        border-radius: 20px;
        border: 2px solid var(--tag-color, #3b82f6);
        background: white;
        color: var(--tag-color, #3b82f6);
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .tag-btn:hover {
        background: color-mix(in srgb, var(--tag-color) 10%, white);
        transform: translateY(-1px);
    }

    .tag-btn.active {
        background: var(--tag-color, #3b82f6);
        color: white;
        box-shadow: 0 2px 8px color-mix(in srgb, var(--tag-color) 40%, transparent);
    }

    /* 新增標籤按鈕 */
    .add-tag-btn {
        padding: 6px 14px;
        border-radius: 20px;
        border: 2px dashed #cbd5e1;
        background: white;
        color: #64748b;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .add-tag-btn:hover {
        border-color: #3b82f6;
        color: #3b82f6;
        background: #f0f9ff;
    }

    /* 已選標籤 */
    .selected-tags {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .selected-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        background: var(--tag-color, #3b82f6);
        color: white;
        box-shadow: 0 2px 6px color-mix(in srgb, var(--tag-color, #3b82f6) 30%, transparent);
    }

    .selected-tag .remove-tag {
        cursor: pointer;
        opacity: 0.8;
    }

    .selected-tag .remove-tag:hover {
        opacity: 1;
    }

    /* 標籤選擇器彈窗 */
    .tag-selector-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .tag-selector-content {
        background: white;
        border-radius: 12px;
        padding: 24px;
        min-width: 400px;
        max-width: 90vw;
        max-height: 80vh;
        overflow-y: auto;
    }

    .tag-selector-content h3 {
        margin: 0 0 16px 0;
        font-size: 1.1rem;
        color: #1e293b;
    }

    .tag-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }

    .tag-selector-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .results-section {
        background: var(--bg-surface);
        border: 1px solid var(--border-default);
        border-radius: var(--radius-lg);
        padding: var(--space-6);
    }

    .results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .results-header h2 {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }

    .results-header .count-badge {
        background: #eff6ff;
        color: #3b82f6;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .results-table {
        width: 100%;
        border-collapse: collapse;
    }

    .results-table th {
        background: #f8fafc;
        padding: 12px;
        text-align: left;
        font-weight: 500;
        color: #64748b;
        font-size: 0.85rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .results-table td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.9rem;
    }

    .results-table tr:hover {
        background: #f8fafc;
    }

    .results-table .user-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .results-table .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 500;
        font-size: 0.85rem;
    }

    .results-table .user-info {
        display: flex;
        flex-direction: column;
    }

    .results-table .user-name {
        font-weight: 500;
        color: #1e293b;
    }

    .results-table .user-email {
        font-size: 0.8rem;
        color: #94a3b8;
    }

    .cohort-tag {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        margin: 2px;
    }

    .cohort-tag.category {
        background: #fdf2f8;
        color: #be185d;
    }

    .cohort-tag.location {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .cohort-tag.attribute {
        background: #fffbeb;
        color: #b45309;
    }

    .empty-state {
        text-align: center;
        padding: 48px;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .empty-state p {
        margin: 0;
        font-size: 0.95rem;
    }

    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }

    .loading-overlay.hidden {
        display: none;
    }

    .spinner {
        width: 40px;
        height: 40px;
        border: 3px solid #e2e8f0;
        border-top-color: #3b82f6;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    @media (max-width: 768px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }
    }

    /* 操作按鈕區 */
    .action-cards {
        background: var(--bg-surface, #fff);
        border: 1px solid var(--border-default, #e2e8f0);
        border-radius: var(--radius-lg, 12px);
        padding: var(--space-5, 20px);
        box-shadow: var(--shadow-sm, 0 1px 3px rgba(0, 0, 0, 0.06));
    }

    .action-cards h2 {
        font-size: 0.9rem;
        font-weight: 600;
        color: #475569;
        margin: 0 0 14px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .action-cards h2 i {
        color: #94a3b8;
        font-size: 0.85rem;
    }

    .action-cards-grid {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .action-card {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        border-radius: var(--radius-md, 8px);
        cursor: pointer;
        transition: all 0.15s ease;
        font-size: 13px;
        font-weight: 500;
        border: 1px solid;
        white-space: nowrap;
    }

    .action-card__icon {
        font-size: 13px;
        flex-shrink: 0;
    }

    .action-card__content h3 {
        margin: 0;
        font-size: 13px;
        font-weight: 500;
    }

    .action-card__content p {
        display: none;
    }

    .action-card__arrow {
        display: none;
    }

    .action-card--elective {
        background: #eff6ff;
        color: #1d4ed8;
        border-color: #bfdbfe;
    }

    .action-card--elective:hover {
        background: #dbeafe;
        border-color: #93c5fd;
    }

    .action-card--elective .action-card__icon {
        color: #3b82f6;
    }

    .action-card--direct {
        background: #f0fdf4;
        color: #15803d;
        border-color: #bbf7d0;
    }

    .action-card--direct:hover {
        background: #dcfce7;
        border-color: #86efac;
    }

    .action-card--direct .action-card__icon {
        color: #22c55e;
    }
</style>

<div class="enrol-page">
    <div class="loading-overlay hidden" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- 頁面標題 -->
    <div class="enrol-header"
        style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 20px;">

        <div>

            <h1 style="margin-bottom:8px;"><i class="fa fa-user-plus me-2"></i>課程招生 <span id="courseNameDisplay"
                    style="font-weight:400; font-size:0.65em; color:#64748b;"></span></h1>

            <div class="course-info" id="courseInfo">

                <div style="color: #64748b; font-size: 0.85rem; line-height: 1.6;">

                    <b>使用方式：</b>① 選擇篩選條件 ② 直接按「開放選修」儲存規則，或按「搜尋」找到人員後勾選，再選擇「開放選修」或「直接加入」

                </div>

            </div>

        </div>

        <div>

            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="goBackToManagement()"
                style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1px solid #cbd5e1; color:#475569; padding:5px 12px; border-radius:6px; cursor:pointer;"
                onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">

                <i class="fa fa-arrow-left"></i> 返回上一頁

            </button>

        </div>

        <script>

            function goBackToManagement() {

                const urlParams = new URLSearchParams(window.location.search);

                const fromCat = urlParams.get('from_cat');

                const currentPage = urlParams.get('page') || '';

                // 根據當前頁面決定返回目標
                const isTeacher = currentPage.startsWith('teacher');

                let url = PortalConfig.webRoot + '/index.php?page=management';

                if (fromCat) {

                    url += '&select_cat=' + fromCat;

                }

                window.location.href = url;

            }

        </script>

    </div>
    <!-- 課程選擇區 (批次招生) -->
    <div class="filter-section" id="coursePickerSection">
        <h2 style="display:flex;align-items:center;justify-content:space-between;">
            <span><i class="fa fa-book"></i> 選擇課程</span>
            <span class="count-badge" id="selectedCourseCount">0 門課程已選</span>
        </h2>
        <div style="margin-bottom:12px;">
            <input type="text" id="courseSearchInput" class="form-control" placeholder="搜尋課程名稱..."
                oninput="filterCourseList()">
        </div>
        <div id="coursePickerList" style="max-height:250px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;">
            <div class="empty-state" style="padding:24px;"><i class="fa fa-spinner fa-spin"></i>
                <p>載入課程中...</p>
            </div>
        </div>
    </div>

    <!-- 篩選區 -->
    <div class="filter-section">
        <h2 style="display: flex; align-items: center; justify-content: space-between;">
            <span><i class="fa fa-filter"></i>篩選條件</span>
            <small style="font-weight: 400; font-size: 0.75rem; color: #94a3b8;"><i class="fa fa-info-circle"></i>
                點擊條件組間的 OR/AND 可切換邏輯</small>
        </h2>

        <div class="filter-groups-container" id="filterGroupsContainer">
            <!-- 條件組會動態生成 -->
        </div>

        <button class="add-group-btn" onclick="addFilterGroup()" title="點擊 OR/AND 可切換邏輯">
            <i class="fa fa-plus"></i>新增篩選條件組
        </button>

        <!-- 獨立標籤篩選區 -->
        <div class="tag-filter-section">
            <label>
                <i class="fa fa-tags" style="color: #ec4899;"></i>
                標籤篩選 <small style="color:#94a3b8;font-weight:normal;">(選填)</small>
            </label>
            <div class="tag-buttons" id="globalTagButtons">
                <span class="selected-tags" id="globalSelectedTags"></span>
                <button type="button" class="add-tag-btn" onclick="openGlobalTagSelector()">
                    <i class="fa fa-plus"></i> 新增標籤篩選
                </button>
            </div>
        </div>

        <div class="filter-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="searchUsers()">
                <i class="fa fa-search"></i> 搜尋符合人員
            </button>
            <button class="btn btn-secondary" onclick="resetFilters()">
                <i class="fa fa-refresh"></i> 重設條件
            </button>
        </div>
    </div>

    <!-- 結果區 -->
    <div class="results-section" id="resultsSection" style="display:none;">
        <div class="results-header">
            <h2>符合條件的人員</h2>
            <span class="count-badge" id="resultCount">0 人</span>
        </div>

        <div id="resultsContainer">
            <div class="empty-state">
                <i class="fa fa-users"></i>
                <p>請選擇篩選條件後點擊「搜尋」</p>
            </div>
        </div>
    </div>

    <!-- 操作區 -->
    <div class="action-cards" id="actionCards">
        <h2><i class="fa fa-bolt"></i> 操作</h2>
        <div class="action-cards-grid">
            <div class="action-card action-card--elective" onclick="saveElective()">
                <div class="action-card__icon"><i class="fa fa-unlock-alt"></i></div>
                <div class="action-card__content">
                    <h3>開放選修</h3>
                </div>
            </div>
            <div class="action-card action-card--direct" id="directEnrolCard" onclick="enrolSelected()"
                style="opacity:0.5; pointer-events:none; cursor:not-allowed;">
                <div class="action-card__icon"><i class="fa fa-user-plus"></i></div>
                <div class="action-card__content">
                    <h3>直接加入課程</h3>
                </div>
            </div>
        </div>
        <div id="electiveDesc" style="margin-top:10px; font-size:0.8rem; color:#64748b; line-height:1.6;"></div>
    </div>
</div>

<script>
    const courseId = new URLSearchParams(window.location.search).get('course_id') ||
        '<?= htmlspecialchars($_GET['course_id'] ?? '') ?>';
    const batchIds = new URLSearchParams(window.location.search).get('batch_ids') || new URLSearchParams(window.location.search).get('course_ids') || '';

    let allCohorts = [];
    let foundUsers = [];
    let filterGroupCount = 0;
    let presetBatchCourseIds = []; // 從管理頁帶過來的批次課程 IDs
    console.log('[Enrol Page] courseId:', courseId, 'batchIds:', batchIds);

    // 維度選項快取
    let categoryOptions = [];
    let locationOptions = [];
    let attributeOptions = [];

    document.addEventListener('DOMContentLoaded', function () {
        loadDimensions();

        if (courseId) {
            // 單一課程模式
            loadCourseInfo();
            document.getElementById('coursePickerSection').style.display = 'none';
        } else if (batchIds) {
            // 從管理頁帶過來的批次招生 — 隱藏課程選擇器
            presetBatchCourseIds = batchIds.split(',').map(Number);
            document.getElementById('coursePickerSection').style.display = 'none';
            document.getElementById('courseInfo').innerHTML = `
            <div style="color:#3b82f6; font-weight:500;"><i class="fa fa-layer-group"></i> 批次招生模式</div>
            <div style="color:#94a3b8; font-size:0.8rem; margin-top:4px;">① 篩選人員 → ② 選擇「開放選修」或「直接加入」，操作會套用到所有已選課程</div>
            <div style="margin-top:8px; padding:8px 12px; background:#fef3c7; border:1px solid #fcd34d; border-radius:6px; font-size:0.8rem; color:#92400e;">
                <i class="fa fa-exclamation-triangle"></i> <b>注意：</b>「開放選修」操作將會<b>覆蓋</b>所有已選課程的現有規則設定
            </div>
            `;
            // 從 API 取得課程名稱用於標題
            (async () => {
                const names = [];
                for (const cid of presetBatchCourseIds) {
                    try {
                        const res = await fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=courses/get&id=${cid}`);
                        const data = await res.json();
                        if (data.success) {
                            const c = data.data?.course || data.data;
                            names.push(c?.fullname || `ID:${cid}`);
                        } else { names.push(`ID:${cid}`); }
                    } catch (e) { names.push(`ID:${cid}`); }
                }
                const nameEl = document.getElementById('courseNameDisplay');
                if (nameEl && names.length > 0) {
                    nameEl.textContent = names.length <= 3
                        ? '— ' + names.join('、')
                        : '— ' + names.slice(0, 3).join('、') + ` 等 ${names.length} 門課程`;
                }
            })();
        } else {
            // 手動批次模式（無預設 batch_ids）
            document.getElementById('courseInfo').innerHTML = `
            <div style="color:#3b82f6; font-weight:500;"><i class="fa fa-layer-group"></i> 批次招生模式 — 請在下方選擇課程</div>
            <div style="color:#94a3b8; font-size:0.8rem; margin-top:4px;">① 勾選要招生的課程 → ② 篩選人員 → ③ 選擇操作</div>
            `;
            loadCoursePickerList();
        }
    });

    async function loadCourseInfo() {
        try {
            const res = await fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=courses/get&id=${courseId}`);
            const data = await res.json();

            if (data.success && data.course) {
                document.getElementById('courseInfo').innerHTML = `
                <strong>${data.course.fullname}</strong> (${data.course.shortname})
            `;
            }
        } catch (e) {
            console.error('載入課程資訊失敗', e);
        }
    }

    async function loadDimensions() {
        try {
            // 改用 manage_dimensions API，和新增成員頁面一樣
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/get_grouped');
            const data = await res.json();

            if (data.success && data.data) {
                // data.data 是按維度分組的陣列
                data.data.forEach(dim => {
                    const cohorts = dim.cohorts || [];
                    const options = cohorts.map(c => ({
                        id: c.cohort_id,
                        name: c.full_path || c.display_name
                    }));

                    if (dim.name === '職類') {
                        categoryOptions = options;
                    } else if (dim.name === '所屬') {
                        locationOptions = options;
                    } else if (dim.name === '屬性') {
                        attributeOptions = options;
                    }
                });

                // 載入使用者標籤
                await loadAvailableTags();

                // 新增第一個篩選組
                addFilterGroup();
                loadExistingRules();
                updateElectiveDescription();
            } else {
                console.error('載入維度失敗:', data);
            }
        } catch (e) {
            console.error('載入維度失敗', e);
        }
    }

    // 載入可用的使用者標籤
    async function loadAvailableTags() {
        try {
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=tags/course/available');
            const data = await res.json();
            console.log('標籤 API 回應:', data);  // debug
            if (data.success) {
                tagOptions = data.data || [];
                console.log('已載入標籤:', tagOptions.length, '個');  // debug
            } else {
                console.error('標籤 API 錯誤:', data.message);
            }
        } catch (e) {
            console.error('載入標籤失敗', e);
        }
    }

    function addFilterGroup() {
        filterGroupCount++;
        const container = document.getElementById('filterGroupsContainer');
        const groupId = filterGroupCount;

        // 如果不是第一個組，先加運算符分隔線（預設 OR，可點擊切換）
        if (container.children.length > 0) {
            const divider = document.createElement('div');
            divider.className = 'operator-divider';
            divider.id = `operator${groupId}`;
            divider.dataset.operator = 'or';
            divider.innerHTML = '<span class="operator-btn" onclick="toggleOperator(' + groupId + ')" title="點擊切換 AND/OR">OR</span>';
            container.appendChild(divider);
        }

        const groupHtml = `
        <div class="filter-group" id="filterGroup${groupId}" data-group-id="${groupId}">
            <div class="filter-group-header">
                <span class="filter-group-label">
                    <span class="group-number">${groupId}</span>
                    條件組 ${groupId}
                </span>
                ${groupId > 1 ? `<button class="remove-group" onclick="removeFilterGroup(${groupId})"><i class="fa fa-times"></i> 移除</button>` : ''}
            </div>
            <div class="filter-grid">
                <div class="filter-item">
                    <label>
                        <span class="dim-icon category"><i class="fa fa-sitemap"></i></span>
                        職類
                    </label>
                    <select id="filterCategory${groupId}">
                        <option value="">全部職類</option>
                        ${categoryOptions.map(o => `<option value="${o.id}">${o.name}</option>`).join('')}
                    </select>
                </div>
                <div class="filter-item">
                    <label>
                        <span class="dim-icon location"><i class="fa fa-map-marker"></i></span>
                        所屬
                    </label>
                    <select id="filterLocation${groupId}">
                        <option value="">全部所屬</option>
                        ${locationOptions.map(o => `<option value="${o.id}">${o.name}</option>`).join('')}
                    </select>
                </div>
                <div class="filter-item">
                    <label>
                        <span class="dim-icon attribute"><i class="fa fa-tag"></i></span>
                        屬性
                    </label>
                    <select id="filterAttribute${groupId}">
                        <option value="">全部屬性</option>
                        ${attributeOptions.map(o => `<option value="${o.id}">${o.name}</option>`).join('')}
                    </select>
                </div>
            </div>
        </div>
    `;

        container.insertAdjacentHTML('beforeend', groupHtml);
    }

    function removeFilterGroup(groupId) {
        const group = document.getElementById(`filterGroup${groupId}`);
        const divider = document.getElementById(`operator${groupId}`);

        if (group) group.remove();
        if (divider) divider.remove();

        // 重新編號
        renumberFilterGroups();
    }

    // 切換 AND/OR 運算符
    function toggleOperator(groupId) {
        const divider = document.getElementById(`operator${groupId}`);
        if (!divider) return;

        const current = divider.dataset.operator;
        const newOp = current === 'or' ? 'and' : 'or';
        divider.dataset.operator = newOp;
        divider.querySelector('.operator-btn').textContent = newOp.toUpperCase();
    }

    function renumberFilterGroups() {
        const groups = document.querySelectorAll('.filter-group');
        groups.forEach((group, index) => {
            const num = index + 1;
            const label = group.querySelector('.filter-group-label');
            if (label) {
                label.innerHTML = `
                <span class="group-number">${num}</span>
                條件組 ${num}
            `;
            }
        });
    }

    // 標籤選擇相關
    let cachedTags = null;
    let currentTagGroupId = null;

    async function openTagSelector(groupId) {
        currentTagGroupId = groupId;

        // 載入標籤（有快取）
        if (!cachedTags) {
            try {
                const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=tags/course/available');
                const data = await res.json();
                if (data.success) {
                    cachedTags = data.data || [];
                } else {
                    showToast('載入標籤失敗', 'error');
                    return;
                }
            } catch (e) {
                showToast('載入標籤失敗', 'error');
                return;
            }
        }

        if (cachedTags.length === 0) {
            showToast('尚未建立任何標籤', 'warning');
            return;
        }

        // 取得已選的標籤
        const selectedContainer = document.getElementById(`selectedTags${groupId}`);
        const selectedIds = Array.from(selectedContainer.querySelectorAll('.selected-tag'))
            .map(el => el.dataset.tagId);

        // 建立彈窗
        const modal = document.createElement('div');
        modal.className = 'tag-selector-modal';
        modal.id = 'tagSelectorModal';
        modal.innerHTML = `
        <div class="tag-selector-content">
            <h3><i class="fa fa-tags"></i> 選擇標籤</h3>
            <div class="tag-list">
                ${cachedTags.map(t => `
                    <button type="button" class="tag-btn ${selectedIds.includes(String(t.id)) ? 'active' : ''}" 
                            data-tag-id="${t.id}" data-tag-name="${t.name}" data-tag-color="${t.color || '#3b82f6'}"
                            onclick="toggleTagInSelector(this)"
                            style="--tag-color: ${t.color || '#3b82f6'}">
                        ${t.name}
                    </button>
                `).join('')}
            </div>
            <div class="tag-selector-actions">
                <button type="button" class="btn btn-secondary" onclick="closeTagSelector()">取消</button>
                <button type="button" class="btn btn-primary" onclick="confirmTagSelection()">確認</button>
            </div>
        </div>
    `;
        document.body.appendChild(modal);
    }

    function toggleTagInSelector(btn) {
        btn.classList.toggle('active');
    }

    function closeTagSelector() {
        const modal = document.getElementById('tagSelectorModal');
        if (modal) modal.remove();
    }

    function confirmTagSelection() {
        const modal = document.getElementById('tagSelectorModal');
        const selectedBtns = modal.querySelectorAll('.tag-btn.active');

        // 判斷是 global 還是 group
        const containerId = currentTagGroupId === 'global' ? 'globalSelectedTags' : `selectedTags${currentTagGroupId}`;
        const selectedContainer = document.getElementById(containerId);

        // 清空並重建已選標籤
        selectedContainer.innerHTML = '';
        selectedBtns.forEach(btn => {
            const tagId = btn.dataset.tagId;
            const tagName = btn.dataset.tagName;
            const tagColor = btn.dataset.tagColor;

            const tagEl = document.createElement('span');
            tagEl.className = 'selected-tag';
            tagEl.dataset.tagId = tagId;
            tagEl.style.setProperty('--tag-color', tagColor);
            tagEl.innerHTML = `
            ${tagName}
            <span class="remove-tag" onclick="this.closest('.selected-tag').remove()"><i class="fa fa-times"></i></span>
        `;
            selectedContainer.appendChild(tagEl);
        });

        closeTagSelector();
    }

    function removeSelectedTag(el) {
        el.closest('.selected-tag').remove();
    }

    // 開啟全域標籤選擇器
    async function openGlobalTagSelector() {
        currentTagGroupId = 'global';

        // 載入標籤（有快取）
        if (!cachedTags) {
            try {
                const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=tags/course/available');
                const data = await res.json();
                if (data.success) {
                    cachedTags = data.data || [];
                } else {
                    showToast('載入標籤失敗', 'error');
                    return;
                }
            } catch (e) {
                showToast('載入標籤失敗', 'error');
                return;
            }
        }

        if (cachedTags.length === 0) {
            showToast('尚未建立任何標籤', 'warning');
            return;
        }

        // 取得已選的標籤
        const selectedContainer = document.getElementById('globalSelectedTags');
        const selectedIds = Array.from(selectedContainer.querySelectorAll('.selected-tag'))
            .map(el => el.dataset.tagId);

        // 建立彈窗
        const modal = document.createElement('div');
        modal.className = 'tag-selector-modal';
        modal.id = 'tagSelectorModal';
        modal.innerHTML = `
        <div class="tag-selector-content">
            <h3><i class="fa fa-tags"></i> 選擇標籤篩選</h3>
            <div class="tag-list">
                ${cachedTags.map(t => `
                    <button type="button" class="tag-btn ${selectedIds.includes(String(t.id)) ? 'active' : ''}" 
                            data-tag-id="${t.id}" data-tag-name="${t.name}" data-tag-color="${t.color || '#3b82f6'}"
                            onclick="toggleTagInSelector(this)"
                            style="--tag-color: ${t.color || '#3b82f6'}">
                        ${t.name}
                    </button>
                `).join('')}
            </div>
            <div class="tag-selector-actions">
                <button type="button" class="btn btn-secondary" onclick="closeTagSelector()">取消</button>
                <button type="button" class="btn btn-primary" onclick="confirmTagSelection()">確認</button>
            </div>
        </div>
    `;
        document.body.appendChild(modal);
    }


    function updateElectiveDescription() {

        const allUsers = document.querySelectorAll('.user-checkbox').length;

        const checked = document.querySelectorAll('.user-checkbox:checked').length;

        const desc = document.getElementById('electiveDesc');

        if (!desc) return;

        let html = '<div style="margin-bottom:3px;"><i class="fa fa-info-circle" style="color:#f59e0b;"></i> <b>開放選修</b>：儲存篩選規則，符合條件的人員可在選課中心看到並自行選修。</div>';

        html += '<div><i class="fa fa-info-circle" style="color:#22c55e;"></i> <b>直接加入課程</b>：將勾選的人員立即加入課程。';

        if (checked > 0) {

            html += ` 已勾選 <b>${checked}</b> 人。`;

        }

        html += '</div>';

        desc.innerHTML = html;

    }


    // 頁面載入時取得課程名稱與現有規則
    async function loadExistingRules() {
        if (!courseId) return;
        try {
            // 取得課程名稱
            const courseRes = await fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=courses/get&id=${courseId}`);
            const courseData = await courseRes.json();
            if (courseData.success && courseData.data) {
                const course = courseData.data.course || courseData.data;
                const nameEl = document.getElementById('courseNameDisplay');
                if (nameEl) nameEl.textContent = '— ' + (course.fullname || '');
            }

            // 取得現有規則
            const res = await fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=courses/visibility/get_rules&course_id=${courseId}`);
            const data = await res.json();
            if (data.success && data.data?.rules?.rule_snapshot) {
                const snapshot = JSON.parse(data.data.rules.rule_snapshot);
                const groups = data.data.resolved_groups || [];
                if (snapshot.filter_groups && snapshot.filter_groups.length > 0) {
                    // 顯示現有規則摘要
                    let infoEl = document.getElementById('existingRulesInfo');
                    if (!infoEl) {
                        infoEl = document.createElement('div');
                        infoEl.id = 'existingRulesInfo';
                        infoEl.style.cssText = 'margin-top:8px; padding:8px 12px; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; font-size:0.8rem; line-height:1.5;';
                        const desc = document.getElementById('electiveDesc');
                        desc.parentNode.insertBefore(infoEl, desc.nextSibling);
                    }
                    const info = infoEl;
                    if (info) {
                        let summary = '<i class="fa fa-info-circle" style="color:#f59e0b;"></i> <b>已有開放選修規則：</b>';
                        groups.forEach((group, gi) => {
                            if (gi > 0) summary += ` <b>${snapshot.operators?.[gi - 1] === 'and' ? 'AND' : 'OR'}</b> `;
                            summary += group.map(item => `<span style="background:#e0e7ff;color:#3730a3;padding:1px 6px;border-radius:4px;font-size:12px;">${item.name}</span>`).join(' + ');
                        });
                        // 顯示被明確授權的人員
                        const excludedUsers = data.data.excluded_users || [];
                        if (excludedUsers.length > 0) {
                            summary += `<br><i class="fa fa-user-minus" style="color:#94a3b8;"></i> 已微調選修範圍（${excludedUsers.length} 人不適用）`;
                        }
                        summary += '<br><span style="color:#94a3b8; font-size:0.75rem;">修改篩選條件後按「開放選修」會覆蓋舊規則</span>';
                        info.innerHTML = summary;
                    }
                }
            }
        } catch (e) {
            console.warn('載入現有規則失敗', e);
        }
    }

    function resetFilters() {
        const container = document.getElementById('filterGroupsContainer');
        container.innerHTML = '';
        filterGroupCount = 0;
        addFilterGroup();

        // 清空全域標籤
        document.getElementById('globalSelectedTags').innerHTML = '';

        document.getElementById('resultsContainer').innerHTML = `
        <div class="empty-state">
            <i class="fa fa-users"></i>
            <p>請選擇篩選條件後點擊「搜尋」</p>
        </div>
    `;
        document.getElementById('resultCount').textContent = '0 人';
        document.getElementById('directEnrolCard').style.opacity = '0.5'; document.getElementById('directEnrolCard').style.pointerEvents = 'none'; document.getElementById('directEnrolCard').style.cursor = 'not-allowed';
        updateElectiveDescription();
    }

    async function searchUsers() {
        // 收集所有篩選組的條件
        const groups = document.querySelectorAll('.filter-group');
        const filterGroups = [];
        const operators = []; // 收集運算符

        // 從全域標籤區收集標籤
        const globalTagEls = document.querySelectorAll('#globalSelectedTags .selected-tag');
        let allSelectedTags = Array.from(globalTagEls).map(el => el.dataset.tagId);

        groups.forEach((group, index) => {
            const groupId = group.dataset.groupId;
            const catId = document.getElementById(`filterCategory${groupId}`)?.value || '';
            const locId = document.getElementById(`filterLocation${groupId}`)?.value || '';
            const attrId = document.getElementById(`filterAttribute${groupId}`)?.value || '';

            // 只收集有設定條件的組
            const cohortIds = [catId, locId, attrId].filter(id => id);
            if (cohortIds.length > 0) {
                filterGroups.push(cohortIds);

                // 收集這個組前面的運算符（第一個組沒有）
                if (index > 0) {
                    const operatorDiv = document.getElementById(`operator${groupId}`);
                    operators.push(operatorDiv?.dataset?.operator || 'or');
                }
            }
        });

        // 去重標籤
        allSelectedTags = [...new Set(allSelectedTags)];



        showLoading(true);

        try {
            // 呼叫 API，傳送多組篩選條件、運算符和標籤
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/get_members_by_groups', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    filter_groups: filterGroups,
                    operators: operators,
                    tag_ids: allSelectedTags  // 新增：標籤篩選
                })
            });
            const data = await res.json();

            if (data.success) {
                // API 回傳格式: { success: true, data: { users: [...] } }
                foundUsers = data.data?.users || data.users || [];
                document.getElementById('resultsSection').style.display = '';
                renderResults(foundUsers);
                // 有搜尋結果時顯示「直接加入」卡片
                if (foundUsers.length > 0) {
                    document.getElementById('directEnrolCard').style.opacity = '1'; document.getElementById('directEnrolCard').style.pointerEvents = 'auto'; document.getElementById('directEnrolCard').style.cursor = 'pointer';
                }
                updateElectiveDescription();
            } else {
                showToast('搜尋失敗: ' + (data.error || '未知錯誤'), 'error');
            }
        } catch (e) {
            console.error('搜尋失敗', e);
            showToast('搜尋發生錯誤', 'error');
        } finally {
            showLoading(false);
        }
    }

    function renderResults(users) {
        const container = document.getElementById('resultsContainer');
        document.getElementById('resultCount').textContent = `${users.length} 人`;

        if (users.length === 0) {
            container.innerHTML = `
            <div class="empty-state">
                <i class="fa fa-search"></i>
                <p>沒有符合條件的人員</p>
            </div>
        `;
            document.getElementById('directEnrolCard').style.opacity = '0.5'; document.getElementById('directEnrolCard').style.pointerEvents = 'none'; document.getElementById('directEnrolCard').style.cursor = 'not-allowed';
            updateElectiveDescription();
            return;
        }

        // 操作卡片已在 searchUsers 中顯示

        let html = `
        <table class="results-table">
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()" checked></th>
                    <th>使用者</th>
                    <th>群組歸屬</th>
                </tr>
            </thead>
            <tbody>
    `;

        users.forEach(user => {
            const initials = (user.fullname || user.username || '??').substring(0, 2);
            const cohortTags = (user.cohorts || []).map(c => {
                let cls = 'attribute';
                if (c.dimension === '職類') cls = 'category';
                else if (c.dimension === '所屬') cls = 'location';
                return `<span class="cohort-tag ${cls}">${c.name}</span>`;
            }).join('');

            html += `
            <tr>
                <td><input type="checkbox" class="user-checkbox" value="${user.id}" checked></td>
                <td>
                    <div class="user-cell">
                        <div class="user-avatar">${initials}</div>
                        <div class="user-info">
                            <span class="user-name">${user.fullname || user.username}</span>
                            <span class="user-email">${user.email || ''}</span>
                        </div>
                    </div>
                </td>
                <td>${cohortTags || '-'}</td>
            </tr>
        `;
        });

        html += '</tbody></table>';

        // 監聽勾選變化，更新開放選修卡片描述
        setTimeout(() => {
            document.querySelectorAll('.user-checkbox').forEach(cb => {
                cb.addEventListener('change', updateElectiveDescription);
            });
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    setTimeout(updateElectiveDescription, 10);
                });
            }
        }, 50);
        container.innerHTML = html;
    }

    function toggleSelectAll() {
        const checked = document.getElementById('selectAll').checked;
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = checked);
    }

    async function enrolAllUsers() {
        const selectedIds = Array.from(document.querySelectorAll('.user-checkbox:checked'))
            .map(cb => cb.value);

        if (selectedIds.length === 0) {
            showToast('請選擇要加入的人員', 'warning');
            return;
        }

        // 決定要招生的課程 IDs
        let enrollCourseIds = [];
        if (courseId) {
            enrollCourseIds = [courseId];
        } else if (presetBatchCourseIds.length > 0) {
            enrollCourseIds = presetBatchCourseIds.map(String);
        } else {
            enrollCourseIds = Array.from(document.querySelectorAll('.course-pick-cb:checked')).map(cb => cb.value);
        }

        if (enrollCourseIds.length === 0) {
            showToast('請先選擇課程', 'warning');
            return;
        }

        const msg = enrollCourseIds.length > 1
            ? `確定要將 ${selectedIds.length} 位人員加入 ${enrollCourseIds.length} 門課程嗎？`
            : `確定要將 ${selectedIds.length} 位人員加入課程嗎？`;
        if (!confirm(msg)) return;

        showLoading(true);

        try {
            if (enrollCourseIds.length === 1) {
                // 單一課程 - 用原有 API
                const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/enrol_users', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=enrol_users&course_id=${enrollCourseIds[0]}&user_ids=${selectedIds.join(',')}`
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`成功加入 ${data.enrolled_count || selectedIds.length} 位學員！即將自動返回...`, 'success');
                } else {
                    showToast('加入失敗: ' + (data.error || '未知錯誤'), 'error');
                }
            } else {
                // 多課程 - 用 batch_enrol API
                const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/batch_enrol', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'batch_enrol', course_ids: enrollCourseIds.map(Number), user_ids: selectedIds.map(Number) })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`已將 ${selectedIds.length} 人招入 ${enrollCourseIds.length} 門課程，即將自動返回...`, 'success');
                } else {
                    showToast('招生失敗: ' + (data.error || '未知錯誤'), 'error');
                }
            }

            // 成功後延遲跳轉回管理頁面
            setTimeout(() => {
                const urlParams = new URLSearchParams(window.location.search);
                const fromCat = urlParams.get('from_cat');
                const page = urlParams.get('page');
                const mgmtPage = (page && page.startsWith('teacher')) ? 'management' : 'management';
                let backUrl = `${PortalConfig.webRoot}/index.php?page=${mgmtPage}`;
                if (fromCat) backUrl += `&select_cat=${fromCat}`;
                window.location.href = backUrl;
            }, 1500);
        } catch (e) {
            console.error('加入失敗', e);
            showToast('加入發生錯誤', 'error');
        } finally {
            showLoading(false);
        }
    }

    function showLoading(show) {
        document.getElementById('loadingOverlay').classList.toggle('hidden', !show);
    }

    // showToast is now provided by the layout (hospital-admin-nav.php)

    // ========== 儲存開放選修條件 (Rule-based) ==========

    // === 開放選修：存規則 + 若有勾人 => 存可見度 ===
    async function saveElective() {
        const groups = document.querySelectorAll('.filter-group');
        const filterGroups = [];
        const operators = [];

        const globalTagEls = document.querySelectorAll('#globalSelectedTags .selected-tag');
        let allSelectedTags = Array.from(globalTagEls).map(el => el.dataset.tagId);
        allSelectedTags = [...new Set(allSelectedTags)];

        groups.forEach((group, index) => {
            const groupId = group.dataset.groupId;
            const catId = document.getElementById(`filterCategory${groupId}`)?.value || '';
            const locId = document.getElementById(`filterLocation${groupId}`)?.value || '';
            const attrId = document.getElementById(`filterAttribute${groupId}`)?.value || '';

            const cohortIds = [catId, locId, attrId].filter(id => id);
            if (cohortIds.length > 0) {
                filterGroups.push(cohortIds);
                if (index > 0) {
                    const operatorDiv = document.getElementById(`operator${groupId}`);
                    operators.push(operatorDiv?.dataset?.operator || 'or');
                }
            }
        });

        // 決定課程
        let targetCourseIds = [];
        if (courseId) {
            targetCourseIds = [courseId];
        } else if (presetBatchCourseIds.length > 0) {
            targetCourseIds = presetBatchCourseIds.map(String);
        } else {
            targetCourseIds = Array.from(document.querySelectorAll('.course-pick-cb:checked')).map(cb => cb.value);
        }
        if (targetCourseIds.length === 0) {
            showToast('請先選擇要設定的課程', 'warning');
            return;
        }

        // 排除名單：沒勾選的人員（管理員勾的是要開放的，沒勾的存為排除）
        const allUserIds = Array.from(document.querySelectorAll('.user-checkbox')).map(cb => cb.value);
        const checkedUserIds = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
        const excludedUserIds = allUserIds.filter(id => !checkedUserIds.includes(id));
        const hasSearchedUsers = allUserIds.length > 0;

        const ruleSnapshot = { filter_groups: filterGroups, operators: operators, tag_ids: allSelectedTags };
        const ruleSnapshotJson = (filterGroups.length === 0 && allSelectedTags.length === 0)
            ? '[]' : JSON.stringify(ruleSnapshot);

        // 確認訊息
        let msg = '';
        if (filterGroups.length === 0 && allSelectedTags.length === 0 && !hasSearchedUsers) {
            msg = '確定要清除此課程的開放選修條件嗎？';
        } else if (hasSearchedUsers) {
            msg = `將執行以下操作：\n\n` +
                `1. 儲存篩選規則（符合規則的人員未來可自行選修）\n` +
                `2. 開放勾選的 ${checkedUserIds.length} 位人員可選修\n\n` +
                `確定要繼續嗎？`;
        } else {
            msg = `將儲存目前的篩選規則作為「開放選修」條件。\n` +
                `符合條件的人員登入後將可看到並自行選修此課程。\n\n確定要繼續嗎？`;
        }
        if (!confirm(msg)) return;

        showLoading(true);
        try {
            // Step 1: 儲存規則
            for (const cid of targetCourseIds) {
                const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/visibility/save_rules', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `course_id=${cid}&rule_snapshot=${encodeURIComponent(ruleSnapshotJson)}`
                });
                const data = await res.json();
                if (!data.success) {
                    throw new Error(data.error || `保存課程 ${cid} 條件失敗`);
                }

                // Step 2: 存排除名單（沒被勾選的人）
                if (hasSearchedUsers && excludedUserIds.length > 0) {
                    const visRes = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/visibility/add', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `course_id=${cid}&user_ids=${excludedUserIds.join(',')}`
                    });
                    const visData = await visRes.json();
                    if (!visData.success) {
                        console.warn('排除名單寫入警告:', visData.error);
                    }
                } else if (hasSearchedUsers && excludedUserIds.length === 0) {
                    // 全部都勾了，清空排除名單
                    await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/visibility/add', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `course_id=${cid}&user_ids=`
                    });
                }
            }

            if (hasSearchedUsers) {
                showToast(`已儲存，課程已設為限制選修，已選定 ${checkedUserIds.length} 位人員可選修，即將自動返回...`, 'success');
            } else {
                showToast('已儲存，課程已設為限制選修，即將自動返回...', 'success');
            }

            // 成功後延遲跳轉回管理頁面
            setTimeout(() => {
                const urlParams = new URLSearchParams(window.location.search);
                const fromCat = urlParams.get('from_cat');
                const page = urlParams.get('page');
                const mgmtPage = (page && page.startsWith('teacher')) ? 'management' : 'management';
                let backUrl = `${PortalConfig.webRoot}/index.php?page=${mgmtPage}`;
                if (fromCat) backUrl += `&select_cat=${fromCat}`;
                window.location.href = backUrl;
            }, 1500);
        } catch (e) {
            console.error('儲存發生錯誤', e);
            showToast('發生錯誤: ' + e.message, 'error');
        } finally {
            showLoading(false);
        }
    }

    // === 直接加入課程 ===
    async function enrolSelected() {
        const selectedIds = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
        if (selectedIds.length === 0) {
            showToast('請先勾選要加入的人員', 'warning');
            return;
        }

        let enrollCourseIds = [];
        if (courseId) {
            enrollCourseIds = [courseId];
        } else if (presetBatchCourseIds.length > 0) {
            enrollCourseIds = presetBatchCourseIds.map(String);
        } else {
            enrollCourseIds = Array.from(document.querySelectorAll('.course-pick-cb:checked')).map(cb => cb.value);
        }
        if (enrollCourseIds.length === 0) {
            showToast('請先選擇課程', 'warning');
            return;
        }

        const msg = enrollCourseIds.length > 1
            ? `確定要將 ${selectedIds.length} 位人員直接加入 ${enrollCourseIds.length} 門課程嗎？\n\n加入後他們將立即成為課程學員。`
            : `確定要將 ${selectedIds.length} 位人員直接加入課程嗎？\n\n加入後他們將立即成為課程學員。`;
        if (!confirm(msg)) return;

        showLoading(true);
        try {
            if (enrollCourseIds.length === 1) {
                const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/enrol_users', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=enrol_users&course_id=${enrollCourseIds[0]}&user_ids=${selectedIds.join(',')}`
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`成功加入 ${data.enrolled_count || selectedIds.length} 位學員！即將自動返回...`, 'success');
                } else {
                    showToast('加入失敗: ' + (data.error || '未知錯誤'), 'error');
                }
            } else {
                const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/batch_enrol', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        course_ids: enrollCourseIds.map(Number),
                        user_ids: selectedIds.map(Number)
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`成功批次加入！即將自動返回...`, 'success');
                } else {
                    showToast('批次加入失敗: ' + (data.error || '未知錯誤'), 'error');
                }
            }

            // 成功後延遲跳轉回管理頁面
            setTimeout(() => {
                const urlParams = new URLSearchParams(window.location.search);
                const fromCat = urlParams.get('from_cat');
                const page = urlParams.get('page');
                const mgmtPage = (page && page.startsWith('teacher')) ? 'management' : 'management';
                let backUrl = `${PortalConfig.webRoot}/index.php?page=${mgmtPage}`;
                if (fromCat) backUrl += `&select_cat=${fromCat}`;
                window.location.href = backUrl;
            }, 1500);
        } catch (e) {
            console.error('加入課程發生錯誤', e);
            showToast('發生錯誤: ' + e.message, 'error');
        } finally {
            showLoading(false);
        }
    }

    async function saveVisibilityRules() {
        const groups = document.querySelectorAll('.filter-group');
        const filterGroups = [];
        const operators = [];

        const globalTagEls = document.querySelectorAll('#globalSelectedTags .selected-tag');
        let allSelectedTags = Array.from(globalTagEls).map(el => el.dataset.tagId);
        allSelectedTags = [...new Set(allSelectedTags)];

        groups.forEach((group, index) => {
            const groupId = group.dataset.groupId;
            const catId = document.getElementById(`filterCategory${groupId}`)?.value || '';
            const locId = document.getElementById(`filterLocation${groupId}`)?.value || '';
            const attrId = document.getElementById(`filterAttribute${groupId}`)?.value || '';

            const cohortIds = [catId, locId, attrId].filter(id => id);
            if (cohortIds.length > 0) {
                filterGroups.push(cohortIds);
                if (index > 0) {
                    const operatorDiv = document.getElementById(`operator${groupId}`);
                    operators.push(operatorDiv?.dataset?.operator || 'or');
                }
            }
        });

        // 決定要把條件套用到哪些課程
        let targetCourseIds = [];
        if (courseId) {
            targetCourseIds = [courseId];
        } else if (presetBatchCourseIds.length > 0) {
            targetCourseIds = presetBatchCourseIds.map(String);
        } else {
            targetCourseIds = Array.from(document.querySelectorAll('.course-pick-cb:checked')).map(cb => cb.value);
        }

        if (targetCourseIds.length === 0) {
            console.log('[saveElective] presetBatchCourseIds:', presetBatchCourseIds, 'courseId:', courseId);
            showToast('請先選擇要設定的課程', 'warning');
            return;
        }

        const ruleSnapshot = {
            filter_groups: filterGroups,
            operators: operators,
            tag_ids: allSelectedTags
        };

        const ruleSnapshotJson = (filterGroups.length === 0 && allSelectedTags.length === 0)
            ? '[]' : JSON.stringify(ruleSnapshot);

        const msg = (filterGroups.length === 0 && allSelectedTags.length === 0)
            ? '確定要清除這些課程的開放選修條件嗎？清除後該課程將不會自動開放給任何人。'
            : `確定要將目前的「條件」存為開放選修的規則嗎？

• 只要符合這個條件的人員，未來登入時都會看見並能自行選修這些課程。
• 即使目前「搜尋結果為 0 人」，條件依然會被記錄，並套用在未來新增的人員。`;

        if (!confirm(msg)) return;

        showLoading(true);
        try {
            // 由於可能是批次多課程，逐一儲存
            for (const cid of targetCourseIds) {
                const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/visibility/save_rules', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `course_id=${cid}&rule_snapshot=${encodeURIComponent(ruleSnapshotJson)}`
                });
                const data = await res.json();
                if (!data.success) {
                    throw new Error(data.error || `保存課程 ${cid} 條件失敗`);
                }

            }

            showToast('已儲存，已設定開放選課條件', 'success');

        } catch (e) {
            console.error('儲存條件發生錯誤', e);
            showToast('發生錯誤: ' + e.message, 'error');
        } finally {
            showLoading(false);
        }
    }

    // ========== 課程選擇器 (批次模式) ==========
    let allPickerCourses = [];

    async function loadCoursePickerList() {
        try {
            const mgmtCatId = <?php echo $_SESSION['management_category_id'] ?? 0; ?>;
            const res = await fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=courses/list&category_id=${mgmtCatId}&include_subcategories=1`);
            const data = await res.json();
            if (data.success) {
                allPickerCourses = data.data || data.courses || [];
                renderCoursePickerList(allPickerCourses);
            } else {
                document.getElementById('coursePickerList').innerHTML = '<div class="empty-state" style="padding:16px;"><p>載入失敗</p></div>';
            }
        } catch (e) {
            document.getElementById('coursePickerList').innerHTML = '<div class="empty-state" style="padding:16px;"><p>網路錯誤</p></div>';
        }
    }

    function renderCoursePickerList(courses) {
        const container = document.getElementById('coursePickerList');
        if (courses.length === 0) {
            container.innerHTML = '<div class="empty-state" style="padding:16px;"><p>無課程</p></div>';
            return;
        }
        container.innerHTML = `
        <div style="padding:8px 12px;border-bottom:1px solid #e2e8f0;background:#f8fafc;">
            <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;font-weight:500;color:#64748b;cursor:pointer;">
                <input type="checkbox" id="pickAllCourses" onchange="togglePickAllCourses(this)"> 全選
            </label>
        </div>
        ${courses.map(c => `
            <div style="padding:8px 12px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:10px;">
                <input type="checkbox" class="course-pick-cb" value="${c.id}" data-name="${escapeHtml(c.fullname)}" onchange="updatePickedCourseCount()">
                <div style="flex:1;">
                    <div style="font-weight:500;font-size:0.9rem;">${escapeHtml(c.fullname)}</div>
                    <div style="font-size:0.8rem;color:#94a3b8;">${escapeHtml(c.shortname || '')} · ID: ${c.id}</div>
                </div>
            </div>
        `).join('')}
    `;
    }

    function filterCourseList() {
        const q = document.getElementById('courseSearchInput').value.trim().toLowerCase();
        if (!q) { renderCoursePickerList(allPickerCourses); return; }
        renderCoursePickerList(allPickerCourses.filter(c => (c.fullname || '').toLowerCase().includes(q) || (c.shortname || '').toLowerCase().includes(q)));
    }

    function togglePickAllCourses(cb) {
        document.querySelectorAll('.course-pick-cb').forEach(c => c.checked = cb.checked);
        updatePickedCourseCount();
    }

    function updatePickedCourseCount() {
        const count = document.querySelectorAll('.course-pick-cb:checked').length;
        document.getElementById('selectedCourseCount').textContent = `${count} 門課程已選`;
    }

    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text || '').replace(/[&<>"']/g, m => map[m]);
    }
</script>