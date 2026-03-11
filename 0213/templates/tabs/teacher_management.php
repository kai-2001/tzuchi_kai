<?php
/**
 * 開課教師 - 課程管理介面
 * templates/tabs/teacher_management.php
 * 
 * Explorer 佈局：左側類別樹 + 右側課程列表
 * 從 hospital_admin_management.php 複製並精簡
 * 差異：不能建類別、不能設定類別、只能看到被指派的類別
 */
$teacherCatIds = $_SESSION['coursecreator_category_ids'] ?? [];
?>
<style>
    /* ==========================================
   Explorer 佈局
   ========================================== */
    .mgmt-explorer {
        display: flex;
        gap: 0;
        min-height: calc(100vh - 140px);
        border: 1px solid var(--border-default);
        border-radius: var(--radius-lg);
        overflow: hidden;
        background: var(--bg-surface);
    }

    /* 左側類別樹面板 */
    .mgmt-sidebar {
        width: 280px;
        min-width: 280px;
        background: #f8fafc;
        border-right: 1px solid var(--border-default);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .mgmt-sidebar__header {
        padding: 16px 16px 12px;
        border-bottom: 1px solid var(--border-default);
        background: white;
    }

    .mgmt-sidebar__header h3 {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .mgmt-sidebar__header h3 i {
        color: #f59e0b;
    }

    .mgmt-sidebar__search {
        position: relative;
    }

    .mgmt-sidebar__search input {
        width: 100%;
        height: 32px;
        padding: 0 10px 0 30px !important;
        box-sizing: border-box;
        font-size: 12px;
        border: 1px solid var(--border-default);
        border-radius: 6px;
        background: #f8fafc;
        color: var(--text-primary);
        font-family: var(--font-sans);
    }

    .mgmt-sidebar__search input:focus {
        outline: none;
        border-color: var(--brand-primary);
        background: white;
    }

    .mgmt-sidebar__search i {
        position: absolute;
        left: 9px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 11px;
        color: var(--text-muted);
    }

    .mgmt-sidebar__tree {
        flex: 1;
        overflow-y: auto;
        padding: 8px 0;
    }

    /* 類別樹節點 */
    .tree-item__row {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 6px 12px 6px 0;
        cursor: pointer;
        transition: all 0.15s;
        font-size: 13px;
        color: var(--text-primary);
        border-left: 3px solid transparent;
        user-select: none;
    }

    .tree-item__row:hover {
        background: rgba(59, 130, 246, 0.06);
    }

    .tree-item__row.active {
        background: rgba(59, 130, 246, 0.1);
        color: var(--brand-primary);
        font-weight: 600;
        border-left-color: var(--brand-primary);
    }

    .tree-item__toggle {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 9px;
        color: var(--text-muted);
        transition: transform 0.2s;
        flex-shrink: 0;
    }

    .tree-item__toggle.expanded {
        transform: rotate(90deg);
    }

    .tree-item__toggle.leaf {
        visibility: hidden;
    }

    .tree-item__icon {
        font-size: 14px;
        color: #f59e0b;
        flex-shrink: 0;
        margin-right: 6px;
    }

    .tree-item__row.active .tree-item__icon {
        color: var(--brand-primary);
    }

    .tree-item__name {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .tree-item__count {
        font-size: 11px;
        color: var(--text-muted);
        background: #e5e7eb;
        padding: 0 6px;
        border-radius: 10px;
        flex-shrink: 0;
        line-height: 18px;
    }

    .mandatory-user-list.show {
        display: block !important;
    }

    .tree-item__row.active .tree-item__count {
        background: rgba(59, 130, 246, 0.2);
        color: var(--brand-primary);
    }

    .tree-item__actions {
        display: none;
        gap: 2px;
        flex-shrink: 0;
    }

    .tree-item__row:hover .tree-item__actions {
        display: flex;
    }

    .tree-item__action-btn {
        width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
        border-radius: 4px;
        font-size: 11px;
        transition: all 0.15s;
        padding: 0;
    }

    .tree-item__action-btn:hover {
        background: rgba(0, 0, 0, 0.08);
        color: var(--text-primary);
    }

    .tree-item__action-btn.danger:hover {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .tree-item__children {
        display: none;
    }

    .tree-item__children.open {
        display: block;
    }

    /* 底部操作 */
    .mgmt-sidebar__footer {
        padding: 10px 12px;
        border-top: 1px solid var(--border-default);
        background: white;
        display: flex;
        gap: 6px;
    }

    .mgmt-sidebar__footer button {
        flex: 1;
        height: 32px;
        font-size: 12px;
        font-weight: 500;
        border: 1px dashed var(--border-default);
        background: white;
        color: var(--text-secondary);
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        transition: all 0.15s;
        font-family: var(--font-sans);
    }

    .mgmt-sidebar__footer button:hover {
        border-color: var(--brand-primary);
        color: var(--brand-primary);
        background: rgba(59, 130, 246, 0.04);
    }

    /* 右側主內容 */
    .mgmt-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        min-width: 0;
    }

    .mgmt-main__header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-default);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: white;
        flex-wrap: wrap;
        gap: 10px;
    }

    .mgmt-main__breadcrumb {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: var(--text-muted);
        flex-wrap: wrap;
    }

    .mgmt-main__breadcrumb a {
        color: var(--brand-primary);
        text-decoration: none;
        cursor: pointer;
    }

    .mgmt-main__breadcrumb a:hover {
        text-decoration: underline;
    }

    .mgmt-main__breadcrumb .sep {
        color: #d1d5db;
        font-size: 10px;
    }

    .mgmt-main__breadcrumb .current {
        color: var(--text-primary);
        font-weight: 600;
    }

    .mgmt-main__actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* 統計列 */
    .mgmt-stats {
        display: flex;
        gap: 16px;
        padding: 14px 20px;
        border-bottom: 1px solid var(--border-default);
        background: #fafbfc;
        flex-wrap: wrap;
    }

    .mgmt-stat {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .mgmt-stat__value {
        font-weight: 700;
        font-size: 18px;
        color: var(--text-primary);
    }

    .mgmt-stat__icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    /* 批次工具列 */
    .mgmt-batch-bar {
        display: none;
        padding: 10px 20px;
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border-bottom: 1px solid #bae6fd;
        align-items: center;
        justify-content: space-between;
        animation: slideDown 0.2s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .mgmt-batch-bar.show {
        display: flex;
    }

    /* 課程表格區 */
    .mgmt-main__body {
        flex: 1;
        overflow-y: auto;
        padding: 0;
    }

    .mgmt-table {
        width: 100%;
        border-collapse: collapse;
    }

    .mgmt-table th {
        padding: 10px 16px;
        text-align: left;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #f8fafc;
        border-bottom: 1px solid var(--border-default);
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .mgmt-table td {
        padding: 10px 16px;
        font-size: 13px;
        color: var(--text-primary);
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .mgmt-table tbody tr {
        transition: background 0.1s;
    }

    .mgmt-table tbody tr:hover {
        background: #f8fafc;
    }

    .mgmt-table .course-name {
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .mgmt-table .badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 500;
    }

    .mgmt-table .badge-visible {
        background: #dcfce7;
        color: #166534;
    }

    .mgmt-table .badge-hidden {
        background: #f3f4f6;
        color: #6b7280;
    }

    .mgmt-table .badge-mandatory {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
    }

    .mgmt-table .action-btns {
        display: flex;
        gap: 4px;
    }

    .mgmt-table .action-btn {
        height: 28px;
        padding: 0 10px;
        font-size: 11px;
        font-weight: 500;
        border: 1px solid var(--border-default);
        background: white;
        color: var(--text-secondary);
        border-radius: 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.15s;
        font-family: var(--font-sans);
        white-space: nowrap;
    }

    .mgmt-table .action-btn:hover {
        border-color: var(--brand-primary);
        color: var(--brand-primary);
    }

    .mgmt-table .action-btn.enroll {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
        border: none;
    }

    .mgmt-table .action-btn.enroll:hover {
        box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
    }

    .mgmt-table .action-btn.danger:hover {
        border-color: #ef4444;
        color: #ef4444;
    }

    /* 類別設定面板 */
    .mgmt-settings-panel {
        border-top: 1px solid var(--border-default);
        background: #fafbfc;
    }

    .mgmt-settings-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        font-size: 13px;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        width: 100%;
        border: none;
        background: transparent;
        font-family: var(--font-sans);
        text-align: left;
    }

    .mgmt-settings-toggle:hover {
        color: var(--text-primary);
        background: rgba(0, 0, 0, 0.02);
    }

    .mgmt-settings-toggle i.chevron {
        transition: transform 0.2s;
        font-size: 10px;
    }

    .mgmt-settings-toggle.expanded i.chevron {
        transform: rotate(180deg);
    }

    .mgmt-settings-body {
        display: none;
        padding: 0 20px 16px;
    }

    .mgmt-settings-body.open {
        display: block;
    }

    /* 空狀態 */
    .mgmt-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        color: var(--text-muted);
        text-align: center;
    }

    .mgmt-empty i {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.3;
    }

    .mgmt-empty p {
        margin: 0;
        font-size: 14px;
    }

    /* 行動版 */
    @media (max-width: 768px) {
        .mgmt-explorer {
            flex-direction: column;
            min-height: auto;
        }

        .mgmt-sidebar {
            width: 100%;
            min-width: unset;
            max-height: 300px;
            border-right: none;
            border-bottom: 1px solid var(--border-default);
        }
    }

    /* 三點下拉選單 */
    .cat-dropdown-wrapper {
        position: relative;
        display: inline-block;
    }

    .cat-dropdown-trigger {
        height: 32px;
        padding: 0 10px;
        font-size: 13px;
        font-weight: 500;
        border: 1px solid var(--border-default);
        background: white;
        color: var(--text-secondary);
        border-radius: 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-family: var(--font-sans);
        transition: all 0.15s;
    }

    .cat-dropdown-trigger:hover {
        border-color: var(--brand-primary);
        color: var(--brand-primary);
        background: #f8fafc;
    }

    .cat-dropdown-menu {
        display: none;
        position: absolute;
        right: 0;
        top: calc(100% + 4px);
        min-width: 200px;
        background: white;
        border: 1px solid var(--border-default);
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        z-index: 100;
        overflow: hidden;
    }

    .cat-dropdown-menu.open {
        display: block;
        animation: dropIn 0.12s ease-out;
    }

    @keyframes dropIn {
        from {
            opacity: 0;
            transform: translateY(-4px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .cat-dropdown-menu__header {
        padding: 8px 12px;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        border-bottom: 1px solid #f1f5f9;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .cat-dropdown-menu__item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        font-size: 13px;
        color: var(--text-primary);
        cursor: pointer;
        transition: background 0.1s;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
        font-family: var(--font-sans);
    }

    .cat-dropdown-menu__item:hover {
        background: #f0f4ff;
        color: var(--brand-primary);
    }

    .cat-dropdown-menu__item i {
        width: 16px;
        text-align: center;
        font-size: 12px;
        color: var(--text-muted);
    }

    .cat-dropdown-menu__item:hover i {
        color: var(--brand-primary);
    }

    .cat-dropdown-menu__divider {
        height: 1px;
        background: #f1f5f9;
        margin: 4px 0;
    }
</style>

<div id="section-management" class="page-section" style="max-width: 1400px;">
    <div class="section-header">
        <h2><i class="fas fa-th-large"></i> 課程管理</h2>
        <p class="section-subtitle">管理您負責的課程</p>
    </div>

    <div class="mgmt-explorer">
        <!-- ========== 左側：類別樹 ========== -->
        <div class="mgmt-sidebar">
            <div class="mgmt-sidebar__header">
                <h3><i class="fas fa-folder-tree"></i> 類別結構</h3>
                <div class="mgmt-sidebar__search">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="搜尋類別..." id="treeSearchInput" oninput="filterTree(this.value)">
                </div>
            </div>
            <div class="mgmt-sidebar__tree" id="categoryTree">
                <div style="text-align:center; padding:30px; color:#94a3b8;">
                    <i class="fas fa-spinner fa-spin" style="font-size:20px;"></i>
                    <p style="margin:8px 0 0; font-size:12px;">載入類別...</p>
                </div>
            </div>
            <div class="mgmt-sidebar__footer">
                <button onclick="loadCategoryTree()"><i class="fas fa-sync-alt"></i> 重新整理</button>
            </div>
        </div>

        <!-- ========== 右側：課程列表 ========== -->
        <div class="mgmt-main">
            <div class="mgmt-main__header">
                <div class="mgmt-main__breadcrumb" id="mainBreadcrumb">
                    <span class="current">請選擇類別</span>
                </div>
                <div class="mgmt-main__actions" style="display:flex; gap:6px; align-items:center;">
                    <a id="createCourseLink" href="<?php echo $web_root; ?>/index.php?page=teacher_course_create"
                        onclick="if(selectedCatId){this.href=this.href.split('&category=')[0]+'&category='+selectedCatId}"
                        class="btn-primary"
                        style="text-decoration:none; font-size:13px; height:32px; padding:0 14px; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fas fa-plus"></i> 新增課程
                    </a>
                    <div class="cat-dropdown-wrapper" id="catDropdownWrapper">
                        <button class="cat-dropdown-trigger" onclick="toggleCatDropdown()">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                        <div class="cat-dropdown-menu" id="catDropdownMenu">
                            <div class="cat-dropdown-menu__header" id="catDropdownLabel">操作</div>
                            <button class="cat-dropdown-menu__item"
                                onclick="closeCatDropdown(); loadCoursesForSelected();">
                                <i class="fas fa-sync-alt"></i> 重新整理
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mgmt-stats" id="courseStats">
                <div class="mgmt-stat">
                    <div class="mgmt-stat__icon" style="background:rgba(139,92,246,0.1); color:#8b5cf6;"><i
                            class="fas fa-book"></i></div>
                    <div>
                        <div class="mgmt-stat__value" id="statTotal">-</div>
                        <div>課程</div>
                    </div>
                </div>
                <div class="mgmt-stat">
                    <div class="mgmt-stat__icon" style="background:rgba(59,130,246,0.1); color:#3b82f6;"><i
                            class="fas fa-eye"></i></div>
                    <div>
                        <div class="mgmt-stat__value" id="statVisible">-</div>
                        <div>已公開</div>
                    </div>
                </div>
                <div class="mgmt-stat">
                    <div class="mgmt-stat__icon" style="background:rgba(34,197,94,0.1); color:#22c55e;"><i
                            class="fas fa-users"></i></div>
                    <div>
                        <div class="mgmt-stat__value" id="statEnrolled">-</div>
                        <div>已註冊</div>
                    </div>
                </div>
            </div>

            <!-- 批次操作列 -->
            <div class="mgmt-batch-bar" id="batchBar">
                <div style="display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-check-square" style="color:#3b82f6;"></i>
                    <span>已選擇 <strong id="batchCount">0</strong> 門課程</span>
                </div>
                <div style="display:flex; gap:6px;">
                    <button class="btn-primary" onclick="goToBatchEnrol()"
                        style="font-size:12px; height:28px; padding:0 12px;">
                        <i class="fas fa-user-plus"></i> 批次招生
                    </button>
                    <button onclick="openBatchDeleteModal()"
                        style="font-size:12px; height:28px; padding:0 12px; background:linear-gradient(135deg,#ef4444,#dc2626); color:white; border:none; border-radius:6px; cursor:pointer;">
                        <i class="fas fa-trash-alt"></i> 批次刪除
                    </button>
                    <button class="btn-secondary" onclick="clearSelection()"
                        style="font-size:12px; height:28px; padding:0 10px;">
                        <i class="fas fa-times"></i> 取消
                    </button>
                </div>
            </div>

            <div class="mgmt-main__body" id="courseListBody">
                <div class="mgmt-empty">
                    <i class="fas fa-arrow-left"></i>
                    <p>← 請從左側選擇一個類別</p>
                </div>
            </div>

        </div>
    </div>
</div>





<!-- ========== 刪除課程 Modal ========== -->
<div class="modal-overlay" id="deleteCourseModal" style="display:none;">
    <div class="modal-content"
        style="width:450px; padding:0; border-radius:20px; box-shadow:0 32px 64px rgba(0,0,0,0.2); overflow:hidden;">
        <div
            style="padding:20px 24px; background:linear-gradient(135deg,#ef4444 0%,#dc2626 50%,#b91c1c 100%); color:white; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:16px; font-weight:600; text-shadow:0 1px 2px rgba(0,0,0,0.2);"><i
                    class="fas fa-exclamation-triangle"></i> 確認刪除課程</h3>
            <button onclick="closeModal('deleteCourseModal')"
                style="background:none; border:none; color:white; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:28px 24px; background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);">
            <div style="text-align:center; margin-bottom:20px;">
                <i class="fas fa-trash-alt" style="font-size:3rem; color:#ef4444;"></i>
            </div>
            <p style="text-align:center; font-size:1.1rem; margin-bottom:16px; color:#1e293b; font-weight:500;">
                您確定要刪除以下課程嗎？</p>
            <div
                style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:12px; text-align:center; margin-bottom:20px;">
                <strong id="deleteCourseName" style="color:#991b1b;"></strong>
            </div>
            <p style="color:#64748b; font-size:0.9rem; text-align:center; margin-bottom:16px;">
                <i class="fas fa-exclamation-circle"></i> 此操作無法復原！課程內容將被永久刪除。
            </p>
            <input type="hidden" id="deleteCourseId">
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:8px; font-weight:500;">請輸入課程名稱以確認刪除：</label>
                <input type="text" id="deleteConfirmInput" placeholder="輸入課程名稱..."
                    style="width:100%; height:40px; padding:0 12px; border:2px solid #e2e8f0; border-radius:10px; font-size:1rem; transition:all 0.2s;"
                    oninput="checkDeleteConfirm()">
            </div>
            <div
                style="display:flex; gap:12px; justify-content:flex-end; padding-top:10px; border-top:1px solid #e2e8f0;">
                <button class="btn-secondary" onclick="closeModal('deleteCourseModal')">取消</button>
                <button id="confirmDeleteBtn" onclick="submitDeleteCourse()" disabled
                    style="background:linear-gradient(135deg,#ef4444,#dc2626); color:white; border:none; padding:10px 20px; border-radius:6px; cursor:not-allowed; opacity:0.5; font-weight:500;"><i
                        class="fas fa-trash-alt"></i> 確認刪除</button>
            </div>
        </div>
    </div>
</div>

<!-- 衝突確認 Modal -->
<div class="modal-overlay" id="conflictModal" style="display:none;">
    <div class="modal-content"
        style="width:520px; padding:0; border-radius:20px; box-shadow:0 32px 64px rgba(0,0,0,0.2); overflow:hidden;">
        <div
            style="padding:20px 24px; background:linear-gradient(135deg,#f59e0b 0%,#d97706 50%,#b45309 100%); color:white; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:16px; font-weight:600; text-shadow:0 1px 2px rgba(0,0,0,0.2);"><i
                    class="fas fa-exclamation-triangle"></i> 招生衝突</h3>
            <button onclick="resolveConflictModal(false)"
                style="background:none; border:none; color:white; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div style="padding:24px; background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);">
            <div id="conflictModalBody" style="margin-bottom:20px;"></div>
            <div id="conflictModalAction"
                style="background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:14px; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-hand-point-right" style="color:#d97706; font-size:1.2rem;"></i>
                <span id="conflictModalActionText" style="color:#92400e; font-size:0.9rem; font-weight:500;"></span>
            </div>
            <div
                style="display:flex; gap:12px; justify-content:flex-end; padding-top:12px; border-top:1px solid #e2e8f0;">
                <button class="btn-secondary" onclick="resolveConflictModal(false)">取消</button>
                <button id="conflictConfirmBtn" onclick="resolveConflictModal(true)"
                    style="background:linear-gradient(135deg,#f59e0b,#d97706); color:white; border:none; padding:10px 24px; border-radius:10px; cursor:pointer; font-weight:600; font-size:14px;"><i
                        class="fas fa-check"></i> 確定執行</button>
            </div>
        </div>
    </div>
</div>

<script>
    // ==========================================
    // 狀態管理（教師版）
    // ==========================================
    const TEACHER_CAT_IDS = <?php echo json_encode(array_map('intval', $teacherCatIds)); ?>;
    const TEACHER_MOODLE_UID = <?php echo (int) ($_SESSION['moodle_uid'] ?? 0); ?>;
    let selectedCatId = null;
    let selectedCatName = '';
    let treeData = {};
    let breadcrumbPath = [];

    // 衝突 Modal 相關
    let _conflictResolve = null;
    function showConflictModal(bodyHtml, actionText) {
        return new Promise(resolve => {
            _conflictResolve = resolve;
            document.getElementById('conflictModalBody').innerHTML = bodyHtml;
            document.getElementById('conflictModalActionText').textContent = actionText;
            document.getElementById('conflictModal').style.display = 'flex';
        });
    }
    function resolveConflictModal(result) {
        document.getElementById('conflictModal').style.display = 'none';
        if (_conflictResolve) { _conflictResolve(result); _conflictResolve = null; }
    }
    let currentCourses = [];
    let currentSettings = {};
    let currentCatIsMandatory = false;

    // 三點下拉選單
    function toggleCatDropdown() {
        if (!selectedCatId) { showToast('請先選擇類別', 'warning'); return; }
        const menu = document.getElementById('catDropdownMenu');
        const label = document.getElementById('catDropdownLabel');
        if (label) label.textContent = selectedCatName || '操作';
        menu.classList.toggle('open');
    }
    function closeCatDropdown() {
        document.getElementById('catDropdownMenu')?.classList.remove('open');
    }
    document.addEventListener('click', function (e) {
        const wrapper = document.getElementById('catDropdownWrapper');
        if (wrapper && !wrapper.contains(e.target)) closeCatDropdown();
    });

    // ==========================================
    // 類別樹
    // ==========================================
    async function loadCategoryTree() {
        const treeEl = document.getElementById('categoryTree');
        treeEl.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin" style="font-size:20px;"></i><p style="margin:8px 0 0; font-size:12px;">載入類別...</p></div>';

        try {
            // 教師版：使用教師專屬 API，只回傳被指派的類別
            const res = await fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=teacher/courses/list_categories`);
            const data = await res.json();
            if (data.success) {
                treeEl.innerHTML = '';
                const cats = data.data || [];
                if (cats.length === 0) {
                    treeEl.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8; font-size:12px;">尚無指派的類別</div>';
                    return;
                }
                for (const cat of cats) {
                    treeEl.appendChild(buildTreeNodeEl(cat, 12));
                }
            } else {
                treeEl.innerHTML = '<div style="text-align:center; padding:20px; color:#ef4444; font-size:12px;"><i class="fas fa-exclamation-circle"></i> 載入失敗</div>';
            }
        } catch (e) {
            console.error(e);
            treeEl.innerHTML = '<div style="text-align:center; padding:20px; color:#ef4444; font-size:12px;">網路錯誤</div>';
        }
    }

    function buildTreeNodeEl(cat, indent) {
        const hasChildren = (cat.childcount > 0) || (cat.children && cat.children.length > 0);
        const div = document.createElement('div');
        div.className = 'tree-item';
        div.dataset.id = cat.id;
        div.dataset.name = cat.name;

        const row = document.createElement('div');
        row.className = 'tree-item__row' + (cat.id == selectedCatId ? ' active' : '');
        row.style.paddingLeft = indent + 'px';
        row.onclick = () => onSelectCategory(cat.id, cat.name, row);

        const toggle = document.createElement('span');
        toggle.className = 'tree-item__toggle' + (hasChildren ? '' : ' leaf');
        toggle.innerHTML = '<i class="fas fa-chevron-right"></i>';
        toggle.onclick = (e) => { e.stopPropagation(); onToggleNode(cat.id, div, indent); };

        const icon = document.createElement('i');
        icon.className = 'fas fa-folder tree-item__icon';

        const name = document.createElement('span');
        name.className = 'tree-item__name';
        name.textContent = cat.name;

        // 必修標記 (放在 name 外面，不會被 overflow:hidden 裁掉)
        let mandatoryBadge = null;
        if (cat.is_mandatory) {
            mandatoryBadge = document.createElement('span');
            mandatoryBadge.style.cssText = 'background:linear-gradient(135deg,#f59e0b,#ef4444); color:white; padding:2px 8px; border-radius:10px; font-size:0.65rem; font-weight:600; margin-left:4px; display:inline-flex; align-items:center; gap:3px; flex-shrink:0; white-space:nowrap;';
            mandatoryBadge.innerHTML = '<i class="fas fa-star" style="font-size:7px;"></i>必修';
        }

        const count = document.createElement('span');
        count.className = 'tree-item__count';
        count.textContent = cat.coursecount ?? 0;

        // 教師版：不顯示類別管理按鈕（設定/編輯/刪除）

        row.append(toggle, icon, name);
        if (mandatoryBadge) row.appendChild(mandatoryBadge);
        row.append(count);
        div.appendChild(row);

        // 子層容器 (延遲載入)
        const childrenDiv = document.createElement('div');
        childrenDiv.className = 'tree-item__children';
        childrenDiv.id = `tree-children-${cat.id}`;
        div.appendChild(childrenDiv);

        // 如果 data 已經帶子層
        if (cat.children && cat.children.length > 0) {
            for (const child of cat.children) {
                childrenDiv.appendChild(buildTreeNodeEl(child, indent + 20));
            }
        }

        return div;
    }

    async function onToggleNode(catId, containerEl, indent) {
        const childrenEl = containerEl.querySelector(`#tree-children-${catId}`);
        const toggleEl = containerEl.querySelector(':scope > .tree-item__row .tree-item__toggle');
        const iconEl = containerEl.querySelector(':scope > .tree-item__row .tree-item__icon');

        if (childrenEl.classList.contains('open')) {
            // 收合
            childrenEl.classList.remove('open');
            toggleEl.classList.remove('expanded');
            iconEl.classList.replace('fa-folder-open', 'fa-folder');
            return;
        }

        // 展開
        toggleEl.classList.add('expanded');
        iconEl.classList.replace('fa-folder', 'fa-folder-open');

        // 延遲載入子層
        if (childrenEl.children.length === 0) {
            childrenEl.innerHTML = '<div style="padding:6px 0 6px ' + (indent + 20) + 'px; color:#94a3b8; font-size:11px;"><i class="fas fa-spinner fa-spin"></i> 載入中...</div>';
            childrenEl.classList.add('open');

            try {
                const res = await fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=categories/list_children&parent=${catId}`);
                const data = await res.json();
                childrenEl.innerHTML = '';
                if (data.success && data.data.length > 0) {
                    for (const child of data.data) {
                        childrenEl.appendChild(buildTreeNodeEl(child, indent + 20));
                    }
                } else {
                    toggleEl.classList.add('leaf');
                }
            } catch (e) {
                childrenEl.innerHTML = '<div style="padding:4px; color:#ef4444; font-size:11px;">載入失敗</div>';
            }
        } else {
            childrenEl.classList.add('open');
        }
    }

    function onSelectCategory(catId, catName, rowEl) {
        // 如果沒傳 rowEl（例如從麵包屑點擊），從 DOM 自動找
        if (!rowEl && catId) {
            const treeItem = document.querySelector(`.tree-item[data-id="${catId}"]`);
            if (treeItem) {
                rowEl = treeItem.querySelector(':scope > .tree-item__row');
                if (!catName) catName = treeItem.dataset.name || '';
            }
        }

        // 去掉舊 active
        document.querySelectorAll('.tree-item__row.active').forEach(r => r.classList.remove('active'));
        if (rowEl) rowEl.classList.add('active');

        selectedCatId = catId;
        selectedCatName = catName;
        sessionStorage.setItem('mgmt_selected_cat_id', catId);

        // 更新 breadcrumb（從 DOM 關係建立）
        buildBreadcrumbFromDOM(rowEl);

        // 處理 null ID (點擊 home icon)
        if (!catId) {
            selectedCatId = null;
            selectedCatName = '';
            sessionStorage.removeItem('mgmt_selected_cat_id');
            const bc = document.getElementById('mainBreadcrumb');
            if (bc) bc.innerHTML = `<a><i class="fas fa-home"></i></a>`;
        }

        loadCoursesForSelected();
    }

    function buildBreadcrumbFromDOM(rowEl) {
        const parts = [];
        let el = rowEl?.closest('.tree-item');
        while (el) {
            const id = el.dataset.id;
            const name = el.dataset.name;
            if (id && name) parts.unshift({ id: parseInt(id), name });
            el = el.parentElement?.closest('.tree-item');
        }

        const bc = document.getElementById('mainBreadcrumb');
        // 教師版沒有 ROOT_CAT_ID (0)，會回到不選取類別的狀態，或是直接不讓 home icon 可以點擊。
        // 但為了能在沒有選擇類別時重新載入所有課程，這裡設定為重置選擇
        let html = `<a onclick="onSelectCategory(null, '', null)" style="cursor:pointer;"><i class="fas fa-home"></i></a>`;
        for (let i = 0; i < parts.length; i++) {
            html += '<span class="sep"><i class="fas fa-chevron-right"></i></span>';
            if (i < parts.length - 1) {
                html += `<a onclick="onSelectCategory(${parts[i].id}, '${escapeHtml(parts[i].name)}')">${escapeHtml(parts[i].name)}</a>`;
            } else {
                html += `<span class="current">${escapeHtml(parts[i].name)}</span>`;
            }
        }
        bc.innerHTML = html;
    }

    // 搜尋
    function filterTree(query) {
        const items = document.querySelectorAll('#categoryTree .tree-item');
        query = query.toLowerCase().trim();
        items.forEach(item => {
            const name = (item.dataset.name || '').toLowerCase();
            if (!query || name.includes(query)) {
                item.style.display = '';
            } else {
                const hasMatch = Array.from(item.querySelectorAll('.tree-item'))
                    .some(c => (c.dataset.name || '').toLowerCase().includes(query));
                item.style.display = hasMatch ? '' : 'none';
            }
        });
    }

    // ==========================================
    // 課程列表
    // ==========================================
    async function loadCoursesForSelected() {
        if (!selectedCatId) return;
        const body = document.getElementById('courseListBody');
        body.innerHTML = '<div style="text-align:center; padding:40px; color:#94a3b8;"><i class="fas fa-spinner fa-spin" style="font-size:20px;"></i><p style="margin:8px 0 0; font-size:13px;">載入課程...</p></div>';

        try {
            const [courseRes, settingsRes] = await Promise.all([
                fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=courses/list&category_id=${selectedCatId}`).then(r => r.json()),
                fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=categories/get_settings&category_id=${selectedCatId}`).then(r => r.json())
            ]);

            currentCatIsMandatory = settingsRes.success && settingsRes.settings?.is_mandatory_category == 1;

            if (courseRes.success) {
                currentCourses = courseRes.data || [];
                renderCourseTable(currentCourses);
                updateStats(currentCourses);
                // 從招生頁返回時，還原批次勾選狀態
                const savedBatch = sessionStorage.getItem('mgmt_batch_checked_ids');
                if (savedBatch) {
                    try {
                        const ids = JSON.parse(savedBatch);
                        ids.forEach(id => {
                            const cb = document.querySelector(`.course-cb[value="${id}"]`);
                            if (cb) cb.checked = true;
                        });
                        updateBatchBar();
                    } catch (e) { }
                    sessionStorage.removeItem('mgmt_batch_checked_ids');
                }
            } else {
                body.innerHTML = '<div class="mgmt-empty"><i class="fas fa-exclamation-circle"></i><p>載入失敗：' + escapeHtml(courseRes.error || '未知錯誤') + '</p></div>';
            }
        } catch (e) {
            console.error(e);
            body.innerHTML = '<div class="mgmt-empty"><i class="fas fa-exclamation-circle"></i><p>網路錯誤</p></div>';
        }
    }

    function renderCourseTable(courses) {
        const body = document.getElementById('courseListBody');

        if (courses.length === 0) {
            body.innerHTML = '<div class="mgmt-empty"><i class="fas fa-book-open"></i><p>此類別尚無課程</p><p style="font-size:12px; margin-top:8px;">點擊上方「新增課程」來建立第一門課程</p></div>';
            return;
        }

        let html = '<table class="mgmt-table"><thead><tr>';
        html += '<th style="width:36px; text-align:center;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>';
        html += '<th style="width:50px;">ID</th>';
        html += '<th>課程名稱</th>';
        html += '<th style="width:100px;">簡稱</th>';
        html += '<th style="width:70px; text-align:center;">人數</th>';
        html += '<th style="width:60px; text-align:center;">狀態</th>';
        html += '<th style="width:200px;">操作</th>';
        html += '</tr></thead><tbody>';

        for (const c of courses) {
            const vis = c.visible == 1;
            const isMandatory = c.is_mandatory == 1;
            const enrolled = c.enrolledusercount ?? c.enrolledusers ?? 0;

            html += `<tr data-course-id="${c.id}">`;
            html += `<td style="text-align:center;"><input type="checkbox" class="course-cb" value="${c.id}" data-name="${escapeHtml(c.fullname)}" onchange="updateBatchBar()"></td>`;
            html += `<td style="color:#9ca3af;">${c.id}</td>`;
            html += `<td><div class="course-name">`;
            if (isMandatory) html += `<span class="badge badge-mandatory"><i class="fas fa-star" style="margin-right:2px; font-size:9px;"></i>必修</span>`;
            html += `${escapeHtml(c.fullname)}</div></td>`;
            html += `<td style="color:#6b7280;">${escapeHtml(c.shortname || '')}</td>`;
            html += `<td style="text-align:center;"><span style="font-weight:600;">${enrolled}</span></td>`;
            // 狀態 badge
            const isPublic = (c.has_rules && c.is_active) || (!c.has_rules && vis);
            html += `<td style="text-align:center;"><span class="badge ${isPublic ? 'badge-visible' : 'badge-hidden'}">${isPublic ? '公開' : '隱藏'}</span></td>`;
            html += `<td><div class="action-btns">`;
            html += `<button class="action-btn enroll" onclick="goToEnrol(${c.id})"><i class="fas fa-user-plus"></i> 招生</button>`;
            html += `<button class="action-btn" onclick="viewEnrolmentStatus(${c.id}, '${escapeHtml(c.fullname)}')" title="查看招生狀態" style="background:#f0f9ff; color:#0369a1; border:1px solid #bae6fd;"><i class="fas fa-users"></i></button>`;
            if (currentCatIsMandatory) {
                html += isMandatory
                    ? `<button class="action-btn" onclick="toggleMandatory(${c.id}, false)" title="取消必修" style="background:#f59e0b; color:white; border:none;"><i class="fas fa-star"></i></button>`
                    : `<button class="action-btn" onclick="toggleMandatory(${c.id}, true)" title="設為必修"><i class="far fa-star"></i></button>`;
            }
            // 可見度切換：公開→隱藏，隱藏→公開
            if (isPublic) {
                html += `<button class="action-btn" onclick="toggleCourseVisibility(${c.id}, 0, ${c.has_rules ? 'true' : 'false'})" title="設為隱藏"><i class="fas fa-eye"></i></button>`;
            } else {
                html += `<button class="action-btn" onclick="toggleCourseVisibility(${c.id}, 1, ${c.has_rules ? 'true' : 'false'})" title="設為公開"><i class="fas fa-eye-slash"></i></button>`;
            }
            html += `<button class="action-btn" onclick="goToMoodleCourse(${c.id})" title="在 Moodle 開啟"><i class="fas fa-external-link-alt"></i></button>`;
            html += `<button class="action-btn danger" onclick="openDeleteCourseModal(${c.id}, '${escapeHtml(c.fullname)}')" title="刪除"><i class="fas fa-trash"></i></button>`;
            html += `</div></td>`;
            html += `</tr>`;
        }

        html += '</tbody></table>';
        body.innerHTML = html;
    }

    // 查看課程招生狀態
    async function viewEnrolmentStatus(courseId, courseName) {
        // 找到課程行，在下方插入展開面板
        const row = document.querySelector(`tr[data-course-id="${courseId}"]`);
        if (!row) return;

        // 如果已有面板，則關閉
        const existing = row.nextElementSibling;
        if (existing && existing.classList.contains('enrol-status-row')) {
            existing.remove();
            return;
        }

        const detailRow = document.createElement('tr');
        detailRow.className = 'enrol-status-row';
        detailRow.innerHTML = `<td colspan="7" style="padding:0;"><div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; margin:4px 8px 8px; padding:14px;">
        <div style="text-align:center; color:#6b7280; font-size:13px;"><i class="fas fa-spinner fa-spin"></i> 載入中...</div>
    </div></td>`;
        row.after(detailRow);

        try {
            // 同時查詢已招生和可見度規則
            const [enrolRes, visRes] = await Promise.all([
                fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=courses/enrolled_users&course_id=${courseId}`),
                fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=courses/visibility/get_rules&course_id=${courseId}`)
            ]);
            const enrolData = await enrolRes.json();
            const visData = await visRes.json();

            const enrolledUsers = (enrolData.success ? enrolData.data : []) || [];
            const ruleSnapshotStr = (visData.success ? visData.data?.rules?.rule_snapshot : null);
            const resolvedGroups = (visData.success ? visData.data?.resolved_groups : []) || [];
            const excludedUsers = (visData.success ? visData.data?.excluded_users : []) || [];

            let html = `<div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; margin:4px 8px 8px; padding:14px;">`;
            html += `<div style="font-weight:600; color:#0c4a6e; font-size:13px; margin-bottom:10px;"><i class="fas fa-info-circle" style="margin-right:4px;"></i>${escapeHtml(courseName)} — 招生狀態</div>`;

            // 已招生 (直接加入)
            html += `<div style="margin-bottom:10px;">`;
            html += `<div style="font-size:12px; font-weight:600; color:#166534; margin-bottom:4px;"><i class="fas fa-user-minus" style="margin-right:4px;"></i>已招生（直接加入）：${Array.isArray(enrolledUsers) ? enrolledUsers.length : 0} 人</div>`;
            if (Array.isArray(enrolledUsers) && enrolledUsers.length > 0) {
                html += `<div style="display:flex; flex-wrap:wrap; gap:3px; max-height:100px; overflow-y:auto;">`;
                for (const u of enrolledUsers.slice(0, 50)) {
                    const name = u.fullname || u.username || '';
                    const inst = u.institution || u.department || '';
                    const moodleId = u.moodle_id || u.id;
                    const portalId = u.portal_id || u.id;
                    html += `<span style="background:#dcfce7; color:#166534; padding:2px 8px; border-radius:10px; font-size:11px; border:1px solid #bbf7d0; display:inline-flex; align-items:center;">${escapeHtml(name)}${inst ? ' <span style="color:#6b7280;">(' + escapeHtml(inst) + ')</span>' : ''} <button onclick="removeUserFromCourse(${courseId}, ${portalId}, ${moodleId}, '${escapeHtml(name)}', this)" style="background:none; border:none; color:#166534; margin-left:4px; cursor:pointer; font-size:10px; padding:0; display:flex; align-items:center; justify-content:center; width:14px; height:14px; border-radius:50%;" onmouseover="this.style.background='#bbf7d0'" onmouseout="this.style.background='none'"><i class="fas fa-times"></i></button></span>`;
                }
                if (enrolledUsers.length > 50) html += `<span style="font-size:11px; color:#9ca3af;">...還有 ${enrolledUsers.length - 50} 人</span>`;
                html += `</div>`;
            } else {
                html += `<div style="font-size:11px; color:#9ca3af;">尚無人員</div>`;
            }
            html += `</div>`;

            // 開放選修規則 - 使用解析後的 resolved_groups 顯示詳細條件
            if (ruleSnapshotStr) {
                try {
                    const rules = JSON.parse(ruleSnapshotStr);
                    const filterGroups = rules.filter_groups || [];
                    const operators = rules.operators || [];
                    const tagIds = rules.tag_ids || [];

                    html += `<div style="margin-bottom:12px; background:#fff; padding:8px 12px; border-radius:6px; border:1px solid #e0f2fe;">`;
                    html += `<div style="font-size:12px; font-weight:600; color:#0369a1; margin-bottom:6px;"><i class="fas fa-filter" style="margin-right:4px;"></i>目前的開放選修條件：</div>`;

                    if (resolvedGroups.length > 0) {
                        resolvedGroups.forEach((group, gIdx) => {
                            if (gIdx > 0) {
                                const op = (operators[gIdx - 1] || 'or').toUpperCase();
                                html += `<div style="text-align:center; padding:4px 0; font-size:11px; font-weight:600; color:#6b7280;">${op}</div>`;
                            }
                            html += `<div style="display:flex; flex-wrap:wrap; gap:4px; align-items:center; background:#f8fafc; padding:6px 8px; border-radius:6px; border:1px dashed #cbd5e1; margin-bottom:4px;">`;
                            html += `<span style="font-size:10px; color:#9ca3af; margin-right:2px;">組${gIdx + 1}:</span>`;
                            group.forEach((item, iIdx) => {
                                if (iIdx > 0) {
                                    html += `<span style="font-size:10px; color:#9ca3af;">+</span>`;
                                }
                                let bgColor = '#f0fdf4', textColor = '#166534', borderColor = '#bbf7d0', icon = 'fa-tag';
                                if (item.dimension === '職類') { bgColor = '#f0fdf4'; textColor = '#166534'; borderColor = '#bbf7d0'; icon = 'fa-sitemap'; }
                                else if (item.dimension === '所屬') { bgColor = '#fefce8'; textColor = '#854d0e'; borderColor = '#fef08a'; icon = 'fa-map-marker-alt'; }
                                else if (item.dimension === '屬性') { bgColor = '#fdf2f8'; textColor = '#9d174d'; borderColor = '#fbcfe8'; icon = 'fa-tag'; }
                                html += `<span style="background:${bgColor}; color:${textColor}; padding:2px 8px; border-radius:10px; font-size:11px; border:1px solid ${borderColor}; display:inline-flex; align-items:center; gap:3px;"><i class="fas ${icon}" style="font-size:9px;"></i>${escapeHtml(item.dimension)}: ${escapeHtml(item.name)}</span>`;
                            });
                            html += `</div>`;
                        });
                    } else if (filterGroups.length > 0) {
                        html += `<span style="background:#f0fdf4; color:#166534; padding:2px 8px; border-radius:10px; font-size:11px; border:1px solid #bbf7d0;">已設定 ${filterGroups.length} 組維度條件</span>`;
                    }

                    if (tagIds.length > 0) {
                        html += `<div style="margin-top:4px;"><span style="background:#fdf2f8; color:#9d174d; padding:2px 8px; border-radius:10px; font-size:11px; border:1px solid #fbcfe8;"><i class="fas fa-tags" style="font-size:9px; margin-right:3px;"></i>已限定 ${tagIds.length} 個標籤</span></div>`;
                    }

                    if (filterGroups.length === 0 && tagIds.length === 0) {
                        html += `<span style="font-size:11px; color:#9ca3af;">尚未設定任何條件 (無人可看到此課程)</span>`;
                    }

                    html += `</div>`;
                } catch (e) {
                    console.error("解析 rule_snapshot 失敗", e);
                }

                // 顯示被明確授權的特定人員
                if (excludedUsers.length > 0) {
                    html += `<div style="margin-top:6px; font-size:12px; font-weight:600; color:#7c3aed; margin-bottom:4px;"><i class="fas fa-user-minus" style="margin-right:4px;"></i>已微調選修範圍（${excludedUsers.length} 人不適用）</div>`;
                    html += `<div style="display:flex; flex-wrap:wrap; gap:3px; max-height:80px; overflow-y:auto;">`;
                    for (const u of excludedUsers) {
                        html += `<span style="background:#f5f3ff; color:#7c3aed; padding:2px 8px; border-radius:10px; font-size:11px; border:1px solid #ddd6fe;">${escapeHtml(u.fullname || u.username || 'user#' + u.user_id)}</span>`;
                    }
                    html += `</div>`;
                }

            } else {
                html += `<div style="margin-bottom:12px; background:#fff; padding:8px 12px; border-radius:6px; border:1px solid #e0f2fe;">`;
                html += `<div style="font-size:12px; font-weight:600; color:#0369a1; margin-bottom:6px;"><i class="fas fa-filter" style="margin-right:4px;"></i>目前的開放選修條件：</div>`;
                html += `<div style="font-size:11px; color:#9ca3af;">尚未設定任何條件 (只有被註冊的人可看到此課程)</div>`;
                html += `</div>`;

                // 即使沒有規則也顯示特定人員
                if (excludedUsers.length > 0) {
                    html += `<div style="margin-top:8px; background:#fff; padding:8px 12px; border-radius:6px; border:1px solid #e0f2fe;">`;
                    html += `<div style="font-size:12px; font-weight:600; color:#7c3aed; margin-bottom:4px;"><i class="fas fa-user-minus" style="margin-right:4px;"></i>已微調選修範圍（${excludedUsers.length} 人不適用）</div>`;
                    html += `<div style="display:flex; flex-wrap:wrap; gap:3px; max-height:80px; overflow-y:auto;">`;
                    for (const u of excludedUsers) {
                        html += `<span style="background:#f5f3ff; color:#7c3aed; padding:2px 8px; border-radius:10px; font-size:11px; border:1px solid #ddd6fe;">${escapeHtml(u.fullname || u.username || 'user#' + u.user_id)}</span>`;
                    }
                    html += `</div></div>`;
                }

            }

            html += `</div>`;
            detailRow.querySelector('td').innerHTML = html;
        } catch (e) {
            console.error('取得招生狀態失敗', e);
            detailRow.querySelector('td').innerHTML = `<div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; margin:4px 8px; padding:14px; text-align:center; color:#dc2626; font-size:13px;">載入失敗</div>`;
        }
    }

    // 移除使用者 (取消招生 / 排除)
    async function removeUserFromCourse(courseId, portalId, moodleId, userName, btnEl) {
        if (!confirm(`確定要將 ${userName} 從課程中移除嗎？\n\n這將會將他從招生名單中剔除，並且從課程中刪除。`)) return;

        // 避免重複點擊
        const oldHtml = btnEl.innerHTML;
        btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btnEl.disabled = true;

        try {
            const formData = new FormData();
            formData.append('course_id', courseId);
            formData.append('user_ids', portalId);
            formData.append('user_id', portalId);
            formData.append('moodle_id', moodleId);

            const res = await fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=courses/visibility/remove`, {
                method: 'POST', body: formData
            });
            const data = await res.json();

            if (data.success) {
                if (data.data?.moodle_unenrolled) {
                    showToast(`已成功移除 ${userName}`, 'success');
                } else {
                    showToast(`${userName} 的本地記錄已移除，但 Moodle 退選失敗`, 'warning');
                    console.warn('[removeUser] Moodle unenrol failed, response:', data);
                }
                // 重新載入這個課程的 panel
                const row = document.querySelector(`tr[data-course-id="${courseId}"]`);
                if (row) {
                    const titleNode = row.querySelector('.course-list-item__title');
                    const courseName = titleNode ? titleNode.textContent.trim() : '課程';
                    const detailRow = row.nextElementSibling;
                    if (detailRow && detailRow.classList.contains('enrol-status-row')) {
                        detailRow.remove();
                    }
                    viewEnrolmentStatus(courseId, courseName);
                }
            } else {
                throw new Error(data.error || '移除失敗');
            }
        } catch (e) {
            console.error("Remove failed", e);
            showToast(e.message || '移除失敗', 'error');
            btnEl.innerHTML = oldHtml;
            btnEl.disabled = false;
        }
    }

    function updateStats(courses) {
        document.getElementById('statTotal').textContent = courses.length;
        document.getElementById('statVisible').textContent = courses.filter(c => c.visible == 1).length;
        const enrolled = courses.reduce((sum, c) => sum + (parseInt(c.enrolledusers || c.enrolled_count || 0)), 0);
        document.getElementById('statEnrolled').textContent = enrolled;
    }


    // (教師版：已移除類別設定、類別 CRUD 功能)


    // ==========================================
    // 課程操作
    // ==========================================


    function openDeleteCourseModal(courseId, courseName) {
        document.getElementById('deleteCourseId').value = courseId;
        document.getElementById('deleteCourseName').textContent = courseName;
        document.getElementById('deleteConfirmInput').value = '';
        document.getElementById('confirmDeleteBtn').disabled = true;
        document.getElementById('confirmDeleteBtn').style.opacity = '0.5';
        document.getElementById('deleteCourseModal').style.display = 'flex';
    }

    function checkDeleteConfirm() {
        const input = document.getElementById('deleteConfirmInput').value.trim();
        const expected = document.getElementById('deleteCourseName').textContent.trim();
        const btn = document.getElementById('confirmDeleteBtn');
        btn.disabled = input !== expected;
        btn.style.opacity = input === expected ? '1' : '0.5';
    }

    async function submitDeleteCourse() {
        const courseId = document.getElementById('deleteCourseId').value;
        const btn = document.getElementById('confirmDeleteBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 刪除中...';

        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', courseId);

            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/delete', {
                method: 'POST', body: formData
            });
            const data = await res.json();
            if (data.success) {
                showToast('課程已刪除', 'success');
                closeModal('deleteCourseModal');
                loadCoursesForSelected();
            } else { showToast(data.error || '刪除失敗', 'error'); }
        } catch (e) { showToast('網路錯誤', 'error'); }

        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash"></i> 確認刪除';
    }

    async function toggleCourseVisibility(courseId, newState, hasRules = false) {
        try {
            const formData = new FormData();
            formData.append('id', courseId);
            formData.append('visible', newState);
            formData.append('has_rules', hasRules ? '1' : '0');

            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/toggle_visible', {
                method: 'POST', body: formData
            });
            const data = await res.json();
            if (data.success) {
                const msg = newState ? '已設為公開' : '已設為隱藏';
                showToast(msg, 'success');
                loadCoursesForSelected();
            } else { showToast(data.error || '操作失敗', 'error'); }
        } catch (e) { showToast('網路錯誤', 'error'); }
    }

    function goToEnrol(courseId) {
        let url = `${PortalConfig.webRoot}/index.php?page=teacher_course_enrol&course_id=${courseId}`;
        if (selectedCatId) url += `&from_cat=${selectedCatId}`;
        window.location.href = url;
    }

    function showGlobalLoading(text) {
        let loader = document.getElementById('global-nav-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'global-nav-loader';
            loader.className = 'global-nav-loader-overlay';
            loader.innerHTML = `
                <div class="loader-content">
                    <img src="assets/img/Image_1768032378449.gif" alt="Loading..." style="width: 120px; height: auto; margin-bottom: 20px;">
                    <div class="loader-text">${text || '正在前往課程...'}</div>
                </div>
            `;
            document.body.appendChild(loader);

            // 動態加入樣式
            const style = document.createElement('style');
            style.textContent = `
                .global-nav-loader-overlay {
                    position: fixed;
                    top: 0; left: 0; right: 0; bottom: 0;
                    background: rgba(255, 255, 255, 0.9);
                    backdrop-filter: blur(15px);
                    z-index: 9999;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    opacity: 0;
                    transition: opacity 0.4s ease;
                    pointer-events: all;
                }
                .global-nav-loader-overlay.show { opacity: 1; }
                .loader-content { text-align: center; }
                .loader-text { 
                    font-weight: 600; 
                    color: var(--primary); 
                    font-size: 18px;
                    letter-spacing: 1px;
                }
            `;
            document.head.appendChild(style);
        }

        setTimeout(() => loader.classList.add('show'), 10);
    }

    function goToMoodleCourse(courseId) {
        // 顯示全域讀取動畫
        showGlobalLoading('正在前往 Moodle 設定...');

        const moodleUrl = `${PortalConfig.moodleUrl}/course/view.php?id=${courseId}`;
        const ssoEndpoint = `${PortalConfig.webRoot}/get_sso_url.php?url=${encodeURIComponent(moodleUrl)}`;
        fetch(ssoEndpoint).then(r => r.json()).then(data => {
            if (data.success && data.sso_url) {
                // 同分頁開啟
                window.location.href = data.sso_url;
            } else {
                let loader = document.getElementById('global-nav-loader');
                if (loader) loader.classList.remove('show');
                showToast('SSO 無法連線，將直接開啟 Moodle', 'warning');
                window.location.href = moodleUrl;
            }
        }).catch(() => {
            let loader = document.getElementById('global-nav-loader');
            if (loader) loader.classList.remove('show');
            showToast('SSO 無法連線，將直接開啟 Moodle', 'warning');
            window.location.href = moodleUrl;
        });
    }

    // ==========================================
    // 批次操作
    // ==========================================
    function toggleSelectAll(cb) {
        document.querySelectorAll('.course-cb').forEach(c => c.checked = cb.checked);
        updateBatchBar();
    }

    function updateBatchBar() {
        const all = document.querySelectorAll('.course-cb');
        const checked = document.querySelectorAll('.course-cb:checked');
        const bar = document.getElementById('batchBar');
        document.getElementById('batchCount').textContent = checked.length;
        bar.classList.toggle('show', checked.length > 0);
        // selectAll indeterminate 狀態
        const sa = document.getElementById('selectAll');
        if (sa) {
            sa.checked = all.length > 0 && checked.length === all.length;
            sa.indeterminate = checked.length > 0 && checked.length < all.length;
        }
    }

    function clearSelection() {
        document.querySelectorAll('.course-cb').forEach(c => c.checked = false);
        const sa = document.getElementById('selectAll');
        if (sa) sa.checked = false;
        updateBatchBar();
    }

    function getSelectedCourseIds() {
        return Array.from(document.querySelectorAll('.course-cb:checked')).map(c => parseInt(c.value));
    }

    // openBatchEnrolModal is defined below with full in-page modal

    // ==========================================
    // 設定面板折疊
    // ==========================================
    function toggleSettingsPanel(btn) {
        btn.classList.toggle('expanded');
        document.getElementById('settingsBody').classList.toggle('open');
    }

    // ==========================================
    // 工具函數
    // ==========================================
    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    // 點擊 modal 背景關閉
    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; });
    });

    // ==========================================
    // 必修課程切換
    // ==========================================
    async function toggleMandatory(courseId, setMandatory) {
        const formData = new FormData();
        formData.append('action', 'set_mandatory');
        formData.append('course_id', courseId);
        formData.append('category_id', selectedCatId);
        formData.append('is_mandatory', setMandatory ? 1 : 0);
        try {
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/set_mandatory', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) { showToast(data.message || '已更新', 'success'); loadCoursesForSelected(); }
            else { showToast(data.error || '操作失敗', 'error'); }
        } catch (e) { showToast('網路錯誤', 'error'); }
    }

    // ==========================================
    // 批次刪除
    // ==========================================
    function openBatchDeleteModal() {
        const cbs = document.querySelectorAll('.course-cb:checked');
        if (cbs.length === 0) { showToast('請先選擇課程', 'warning'); return; }
        const names = Array.from(cbs).map(c => c.dataset.name);

        let m = document.getElementById('batchDeleteModal');
        if (m) m.remove();
        m = document.createElement('div');
        m.id = 'batchDeleteModal';
        m.className = 'modal-overlay';
        m.style.display = 'flex';
        m.innerHTML = `
        <div class="modal-content" style="width:480px; padding:0;">
            <div style="padding:16px 20px; background:linear-gradient(135deg,#ef4444,#dc2626); color:white; border-radius:12px 12px 0 0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:16px;"><i class="fas fa-trash-alt"></i> 批次刪除 ${cbs.length} 門課程</h3>
                <button onclick="closeModal('batchDeleteModal')" style="background:none; border:none; color:white; font-size:20px; cursor:pointer;">&times;</button>
            </div>
            <div style="padding:20px;">
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px; max-height:120px; overflow-y:auto; margin-bottom:14px;">
                    <ul style="margin:0; padding-left:18px; font-size:13px; color:#991b1b;">${names.map(n => `<li style="padding:2px 0;">${escapeHtml(n)}</li>`).join('')}</ul>
                </div>
                <p style="color:#b91c1c; font-size:12px; margin-bottom:12px;"><i class="fas fa-exclamation-circle"></i> 此操作無法復原！</p>
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px; color:#6b7280; display:block; margin-bottom:4px;">請輸入「<span style="color:#dc2626;">確認刪除</span>」以繼續</label>
                    <input type="text" id="batchDeleteConfirmInput" style="width:100%; height:36px; padding:0 10px; border:1px solid #d1d5db; border-radius:6px; font-size:14px;" oninput="checkBatchDeleteConfirm()">
                </div>
                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button class="btn-secondary" onclick="closeModal('batchDeleteModal')">取消</button>
                    <button id="confirmBatchDeleteBtn" disabled style="background:#ef4444; color:white; border:none; padding:8px 16px; border-radius:6px; cursor:not-allowed; opacity:0.5; font-size:13px;" onclick="submitBatchDelete()"><i class="fas fa-trash"></i> 確認刪除</button>
                </div>
            </div>
        </div>`;
        document.body.appendChild(m);
        m.addEventListener('click', e => { if (e.target === m) closeModal('batchDeleteModal'); });
    }

    function checkBatchDeleteConfirm() {
        const btn = document.getElementById('confirmBatchDeleteBtn');
        const ok = document.getElementById('batchDeleteConfirmInput').value.trim() === '確認刪除';
        btn.disabled = !ok; btn.style.opacity = ok ? '1' : '0.5'; btn.style.cursor = ok ? 'pointer' : 'not-allowed';
    }

    async function submitBatchDelete() {
        const ids = getSelectedCourseIds();
        const btn = document.getElementById('confirmBatchDeleteBtn');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 刪除中...';
        let ok = 0, fail = 0;
        for (const id of ids) {
            try {
                const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
                const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/delete', { method: 'POST', body: fd });
                const d = await res.json();
                d.success ? ok++ : fail++;
            } catch (e) { fail++; }
        }
        closeModal('batchDeleteModal'); clearSelection();
        showToast(fail === 0 ? `成功刪除 ${ok} 門課程` : `刪除完成：成功 ${ok}，失敗 ${fail}`, fail === 0 ? 'success' : 'warning');
        loadCoursesForSelected();
    }

    // (新增課程已移至獨立頁面 course_create，不再使用 in-page modal)

    // ==========================================
    // 批次招生 （跳轉到招生頁面）
    // ==========================================
    function goToBatchEnrol() {
        const ids = getSelectedCourseIds();
        if (ids.length === 0) { showToast('請先選擇課程', 'warning'); return; }
        // 存勾選狀態到 sessionStorage，返回時還原
        sessionStorage.setItem('mgmt_batch_checked_ids', JSON.stringify(ids));
        let url = `${PortalConfig.webRoot}/index.php?page=teacher_course_enrol&batch_ids=${ids.join(',')}`;
        if (selectedCatId) url += `&from_cat=${selectedCatId}`;
        window.location.href = url;
    }

// (教師版：已移除完整訓練設定 Modal 功能)
/*  --- BEGIN REMOVED SETTINGS BLOCK (teacher version) ---













                    <div style="flex:1;">
                        <div style="font-weight:600; color:#1e40af; font-size:13px;">已指派 ${s.mandatory_user_count} 位必修對象</div>
                        <div style="font-size:11px; color:#6b7280;">如需變更，請重新篩選並搜尋後儲存</div>
                    </div>
                    ${(s.mandatory_users && s.mandatory_users.length > 0) ? `<button type="button" onclick="const list=this.closest('div').parentElement.querySelector('.mandatory-user-list'); if(list){list.classList.toggle('show');} this.querySelector('i').classList.toggle('fa-chevron-down'); this.querySelector('i').classList.toggle('fa-chevron-up');" style="background:none; border:none; color:#3b82f6; cursor:pointer; font-size:12px; white-space:nowrap;"><i class="fas fa-chevron-down"></i> 查看名單</button>` : ''}
                </div>
                ${(s.filter_snapshot && s.filter_snapshot.length > 0) ? `
                <div style="margin-bottom:10px; padding:8px 12px; background:rgba(255,255,255,0.6); border-radius:8px; border:1px solid rgba(147,197,253,0.4);">
                    <div style="font-size:11px; color:#6b7280; margin-bottom:6px;"><i class="fas fa-filter" style="margin-right:4px;"></i>篩選條件：</div>
                    <div style="display:flex; flex-wrap:wrap; gap:4px;">
                        ${s.filter_snapshot.map((g, i) => {
                            let tags = [];
                            if (g.category) tags.push('<span style="background:#dbeafe; color:#1e40af; padding:2px 8px; border-radius:10px; font-size:11px;">職類: ' + escapeHtml(g.category_name || g.category) + '</span>');
                            if (g.location) tags.push('<span style="background:#dcfce7; color:#166534; padding:2px 8px; border-radius:10px; font-size:11px;">所屬: ' + escapeHtml(g.location_name || g.location) + '</span>');
                            if (g.attribute) tags.push('<span style="background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:10px; font-size:11px;">屬性: ' + escapeHtml(g.attribute_name || g.attribute) + '</span>');
                            return tags.length > 0 ? '<span style="display:inline-flex; gap:4px; align-items:center;">' + (i > 0 ? '<span style="color:#9ca3af; font-size:10px; margin:0 4px;">＋</span>' : '') + tags.join('') + '</span>' : '';
                        }).join('')}
                    </div>
                </div>
                ` : ''}
                ${(s.mandatory_users && s.mandatory_users.length > 0) ? `
                <div style="display:none; max-height:160px; overflow-y:auto; padding:8px; background:rgba(255,255,255,0.7); border-radius:8px;" class="mandatory-user-list">
                    ${s.mandatory_users.map(u => `<span class="mandatory-user-pill" style="display:inline-flex; align-items:center; background:white; padding:3px 10px; border-radius:16px; margin:2px; font-size:11px; color:#374151; border:1px solid #e5e7eb;">
                        ${escapeHtml(u.fullname || u.username)}${u.institution ? ' <span style="color:#9ca3af; font-size:10px; margin-left:4px;">(' + escapeHtml(u.institution) + ')</span>' : ''}
                        <button type="button" onclick="removeMandatoryUser(${s.moodle_category_id}, ${u.requirement_id || 0}, ${u.id}, '${escapeHtml(u.fullname || u.username)}', this)" style="background:none; border:none; color:#dc2626; margin-left:6px; cursor:pointer; font-size:10px; padding:0; display:flex; align-items:center; justify-content:center; width:14px; height:14px; border-radius:50%;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'"><i class="fas fa-times"></i></button>
                    </span>`).join('')}
                    ${s.mandatory_user_count > s.mandatory_users.length ? `<span style="display:inline-block; padding:3px 10px; font-size:11px; color:#9ca3af;" id="mandatoryUsersRemainingCount">...還有 <span class="count">${s.mandatory_user_count - s.mandatory_users.length}</span> 位</span>` : ''}
                </div>
                ` : ''}
            </div>
            ` : ''}
            
            <!-- 可見性 -->
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:#1f2937;"><i class="fas fa-eye" style="margin-right:4px; color:#6366f1;"></i>課程可見性</label>
                <div style="display:flex; gap:10px;">
                    <label style="flex:1; display:flex; align-items:center; gap:8px; cursor:pointer; padding:10px 14px; background:white; border-radius:8px; border:2px solid ${vis==='all'?'#6366f1':'#e5e7eb'}; font-size:13px; transition:all 0.2s;">
                        <input type="radio" name="settingsVis" value="all" ${vis==='all'?'checked':''} style="accent-color:#6366f1;">
                        🔓 所有人可見
                    </label>
                    <label style="flex:1; display:flex; align-items:center; gap:8px; cursor:pointer; padding:10px 14px; background:white; border-radius:8px; border:2px solid ${vis==='mandatory_only'?'#6366f1':'#e5e7eb'}; font-size:13px; transition:all 0.2s;">
                        <input type="radio" name="settingsVis" value="mandatory_only" ${vis==='mandatory_only'?'checked':''} style="accent-color:#6366f1;">
                        🔒 僅必修對象
                    </label>
                </div>
            </div>
            
            <!--固定堂數 + 期限-- >
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="font-size:13px; font-weight:500; color:#4b5563; display:flex; align-items:center; gap:4px; margin-bottom:8px;">
                        <i class="fas fa-check-circle" style="color:#10b981;"></i> 固定通過堂數
                    </label>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" id="fixedCountCheck" ${fixedCount ? 'checked' : ''} style="accent-color:#10b981; width:16px; height:16px;" onchange="document.getElementById('fixedCountInput').style.display=this.checked?'inline':'none'; document.getElementById('fixedCountHint').style.display=this.checked?'none':'block';">
                        <input type="number" id="fixedCountInput" value="${s.required_pass_count || 1}" min="1" max="99" style="width:60px; height:32px; text-align:center; border:1px solid #d1d5db; border-radius:6px; display:${fixedCount?'inline':'none'}; font-size:14px;">
                        <span style="font-size:13px; color:#6b7280; display:${fixedCount?'inline':'none'};">堂</span>
                    </div>
                    <div id="fixedCountHint" style="font-size:11px; color:#9ca3af; margin-top:4px; display:${fixedCount?'none':'block'};">目前：有幾堂課就顯示幾個燈</div>
                </div>
                <div>
                    <label style="font-size:13px; font-weight:500; color:#4b5563; display:flex; align-items:center; gap:4px; margin-bottom:8px;">
                        <i class="fas fa-calendar-alt" style="color:#f59e0b;"></i> 完成期限
                    </label>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <input type="number" id="periodMonths" value="${period}" min="0" max="120" style="width:60px; height:32px; text-align:center; border:1px solid #d1d5db; border-radius:6px; font-size:14px;">
                        <span style="font-size:13px; color:#6b7280;">個月 (0=無期限)</span>
                    </div>
                </div>
            </div>
            
            <!-- 篩選條件 -->
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:#1f2937;"><i class="fas fa-filter" style="margin-right:4px; color:#8b5cf6;"></i>必修對象篩選</label>
                <div id="settingsFilterGroups"></div>
                <button type="button" onclick="addSettingsFilterGroup()" style="width:100%; padding:10px; border:2px dashed #cbd5e1; border-radius:8px; background:white; color:#64748b; cursor:pointer; font-size:12px; margin-top:8px; transition:all 0.2s;" onmouseover="this.style.borderColor='#8b5cf6'" onmouseout="this.style.borderColor='#cbd5e1'">
                    <i class="fas fa-plus"></i> 新增篩選條件組
                </button>
            </div>
            
            <!-- 標籤篩選（在篩選器下方） -->
            <div style="background:linear-gradient(135deg, rgba(236,72,153,0.05), rgba(244,63,94,0.05)); border:1px solid rgba(236,72,153,0.2); border-radius:12px; padding:14px 18px; margin-bottom:16px;">
                <label style="display:block; margin-bottom:8px; font-weight:500; color:#1f2937; font-size:13px;">
                    <i class="fas fa-tags" style="color:#ec4899; margin-right:6px;"></i>
                    標籤篩選 <small style="color:#94a3b8;font-weight:normal;">(選填)</small>
                </label>
                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:6px;">
                    <span id="settingsSelectedTags" style="display:inline-flex; flex-wrap:wrap; gap:6px; align-items:center;"></span>
                    <button type="button" onclick="openSettingsTagSelector()"
                        style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border-radius:20px; border:1px dashed #cbd5e1; background:white; color:#64748b; font-size:12px; cursor:pointer; transition:all 0.2s;"
                        onmouseover="this.style.borderColor='#ec4899'; this.style.color='#ec4899';"
                        onmouseout="this.style.borderColor='#cbd5e1'; this.style.color='#64748b';">
                        <i class="fas fa-plus"></i> 新增標籤篩選
                    </button>
                </div>
            </div>
            
            <div style="display:flex; gap:8px; margin-bottom:12px;">
                <button type="button" class="btn-primary" onclick="searchMandatoryUsers()" style="font-size:13px; padding:8px 16px;"><i class="fas fa-search"></i> 搜尋符合人員</button>
                <button type="button" class="btn-secondary" onclick="resetSettingsFilters()" style="font-size:13px; padding:8px 16px;"><i class="fas fa-redo"></i> 重設</button>
            </div>
            
            <div id="mandatoryUsersPreview" style="background:white; border-radius:8px; padding:12px; border:1px solid #e5e7eb; min-height:50px;">
                <div style="text-align:center; color:#9ca3af; padding:16px; font-size:13px;">
                    <i class="fas fa-users" style="font-size:24px; margin-bottom:8px; opacity:0.4; display:block;"></i>
                    請選擇篩選條件後點擊「搜尋」
                </div>
            </div>
        </div>
        
        <!--儲存 -->
        <div style="display:flex; gap:10px; justify-content:flex-end; padding-top:14px; border-top:1px solid #e5e7eb;">
            <button class="btn-secondary" onclick="closeSettingsModal()">取消</button>
            <button class="btn-primary" onclick="saveFullSettings()" style="font-size:14px; padding:10px 20px;"><i class="fas fa-save"></i> 儲存全部設定</button>
        </div>
    `;
    
    // 渲染已選標籤
    renderSelectedTags();
    
    // 如果必修，載入維度並新增篩選組
    if (isMandatory) {
        loadSettingsDimensions().then(() => {
            if (settingsFilterGroupCount === 0) addSettingsFilterGroup();
        });
    }
}

function renderSelectedTags() {
    const container = document.getElementById('settingsSelectedTags');
    if (!container) return;
    container.innerHTML = '';
    if (settingsSelectedTagIds.length === 0) return;
    const tags = settingsCachedTags || [];
    settingsSelectedTagIds.forEach(id => {
        const tag = tags.find(t => t.id == id);
        const name = tag ? tag.name : `Tag #${ id } `;
        const color = tag?.color || '#3b82f6';
        const el = document.createElement('span');
        el.className = 'settings-selected-tag';
        el.dataset.tagId = id;
        el.style.cssText = `display: inline - flex; align - items: center; gap: 4px; padding: 5px 10px; border - radius: 20px; font - size: 0.8rem; font - weight: 500; background:${ color } 20; color:${ color }; border: 1px solid ${ color } 40; `;
        el.innerHTML = `${ escapeHtml(name) } <span onclick="this.closest('.settings-selected-tag').remove()" style="cursor:pointer; margin-left:2px; opacity:0.7;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'"><i class="fas fa-times" style="font-size:0.7rem;"></i></span>`;
        container.appendChild(el);
    });
}

async function openSettingsTagSelector() {
    if (!settingsCachedTags) {
        try {
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=tags/course/available');
            const data = await res.json();
            settingsCachedTags = data.success ? (data.data || []) : [];
        } catch (e) { settingsCachedTags = []; }
    }

    if (settingsCachedTags.length === 0) {
        showToast('沒有可用的標籤', 'warning');
        return;
    }
    
    // 取得目前已選的 tag IDs
    const selectedContainer = document.getElementById('settingsSelectedTags');
    const selectedIds = Array.from(selectedContainer.querySelectorAll('.settings-selected-tag'))
        .map(el => el.dataset.tagId);
    
    // 建立 overlay modal
    const modal = document.createElement('div');
    modal.id = 'settingsTagSelectorModal';
    modal.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:10001; display:flex; align-items:center; justify-content:center;';
    modal.innerHTML = `
        < div style = "background:white; border-radius:16px; padding:24px; width:480px; max-width:90vw; max-height:70vh; overflow-y:auto; box-shadow:0 20px 40px rgba(0,0,0,0.15);" >
            <h3 style="margin:0 0 16px; font-size:1rem; color:#1f2937;"><i class="fas fa-tags" style="color:#ec4899; margin-right:6px;"></i> 選擇標籤篩選</h3>
            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:20px;">
                ${settingsCachedTags.map(t => `
                    <button type="button" class="stag-btn ${selectedIds.includes(String(t.id)) ? 'stag-active' : ''}" 
                            data-tag-id="${t.id}" data-tag-name="${escapeHtml(t.name)}" data-tag-color="${t.color || '#3b82f6'}"
                            onclick="this.classList.toggle('stag-active')"
                            style="padding:8px 16px; border-radius:20px; border:2px solid ${t.color || '#3b82f6'}40; background:white; color:${t.color || '#3b82f6'}; font-size:0.85rem; cursor:pointer; transition:all 0.2s; font-weight:500;">
                        ${escapeHtml(t.name)}
                    </button>
                `).join('')}
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; border-top:1px solid #f0f0f0; padding-top:14px;">
                <button type="button" onclick="closeSettingsTagSelector()" 
                    style="padding:8px 20px; border-radius:8px; background:#f3f4f6; color:#374151; border:none; cursor:pointer; font-size:13px;">取消</button>
                <button type="button" onclick="confirmSettingsTagSelection()" 
                    style="padding:8px 20px; border-radius:8px; background:#ec4899; color:white; border:none; cursor:pointer; font-size:13px; font-weight:500;">確認選擇</button>
            </div>
        </div >
        `;
    
    // stag-active 樣式需動態處理
    modal.addEventListener('click', (e) => {
        const btn = e.target.closest('.stag-btn');
        if (btn) {
            const color = btn.dataset.tagColor;
            if (btn.classList.contains('stag-active')) {
                btn.style.background = color;
                btn.style.color = 'white';
                btn.style.borderColor = color;
            } else {
                btn.style.background = 'white';
                btn.style.color = color;
                btn.style.borderColor = color + '40';
            }
        }
        if (e.target === modal) closeSettingsTagSelector();
    });
    
    document.body.appendChild(modal);
    
    // 初始化已選按鈕樣式
    modal.querySelectorAll('.stag-btn.stag-active').forEach(btn => {
        const color = btn.dataset.tagColor;
        btn.style.background = color;
        btn.style.color = 'white';
        btn.style.borderColor = color;
    });
}

function closeSettingsTagSelector() {
    const modal = document.getElementById('settingsTagSelectorModal');
    if (modal) modal.remove();
}

function confirmSettingsTagSelection() {
    const modal = document.getElementById('settingsTagSelectorModal');
    const selectedBtns = modal.querySelectorAll('.stag-btn.stag-active');
    const container = document.getElementById('settingsSelectedTags');
    
    // 清空並重建
    container.innerHTML = '';
    settingsSelectedTagIds = [];
    
    selectedBtns.forEach(btn => {
        const tagId = btn.dataset.tagId;
        const tagName = btn.dataset.tagName;
        const tagColor = btn.dataset.tagColor;
        
        settingsSelectedTagIds.push(parseInt(tagId));
        
        const tagEl = document.createElement('span');
        tagEl.className = 'settings-selected-tag';
        tagEl.dataset.tagId = tagId;
        tagEl.style.cssText = `display: inline - flex; align - items: center; gap: 4px; padding: 5px 10px; border - radius: 20px; font - size: 0.8rem; font - weight: 500; background:${ tagColor } 20; color:${ tagColor }; border: 1px solid ${ tagColor } 40; `;
        tagEl.innerHTML = `${ escapeHtml(tagName) } <span onclick="this.closest('.settings-selected-tag').remove()" style="cursor:pointer; margin-left:2px; opacity:0.7;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'"><i class="fas fa-times" style="font-size:0.7rem;"></i></span>`;
        container.appendChild(tagEl);
    });
    
    closeSettingsTagSelector();
}


function toggleMandatorySection() {
    const isChecked = document.getElementById('setMandatory').checked;
    const section = document.getElementById('mandatoryDetailsSection');
    section.style.display = isChecked ? 'block' : 'none';
    if (isChecked && settingsFilterGroupCount === 0) {
        loadSettingsDimensions().then(() => addSettingsFilterGroup());
    }
}

async function loadSettingsDimensions() {
    if (settingsDimLoaded) return;
    try {
        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/get_grouped');
        const data = await res.json();
        if (data.success && data.data) {
            data.data.forEach(dim => {
                const opts = (dim.cohorts || []).map(c => ({ id: c.cohort_id, name: c.full_path || c.display_name }));
                if (dim.name === '職類') settingsDims.cat = opts;
                else if (dim.name === '所屬') settingsDims.loc = opts;
                else if (dim.name === '屬性') settingsDims.attr = opts;
            });
        }
        settingsDimLoaded = true;
    } catch (e) { console.error(e); }
}

function addSettingsFilterGroup() {
    settingsFilterGroupCount++;
    const gid = settingsFilterGroupCount;
    const container = document.getElementById('settingsFilterGroups');
    if (!container) return;
    
    if (container.children.length > 0) {
        const opDiv = document.createElement('div');
        opDiv.id = `settingsOp${ gid } `; opDiv.dataset.operator = 'or';
        opDiv.style.cssText = 'text-align:center; margin:6px 0;';
        opDiv.innerHTML = `< span onclick = "toggleSettingsOp(${gid})" style = "background:linear-gradient(135deg,#f59e0b,#d97706); color:white; padding:3px 14px; border-radius:20px; font-size:11px; font-weight:600; cursor:pointer;" > OR</span > `;
        container.appendChild(opDiv);
    }
    
    const catOpts = settingsDims.cat.map(o => `< option value = "${o.id}" > ${ o.name }</option > `).join('');
    const locOpts = settingsDims.loc.map(o => `< option value = "${o.id}" > ${ o.name }</option > `).join('');
    const attrOpts = settingsDims.attr.map(o => `< option value = "${o.id}" > ${ o.name }</option > `).join('');
    
    const div = document.createElement('div');
    div.className = 'settings-filter-group';
    div.id = `settingsGroup${ gid } `;
    div.style.cssText = 'background:white; border-radius:8px; padding:12px; border:1px solid #e5e7eb; margin-bottom:4px;';
    div.innerHTML = `
        < div style = "display:grid; grid-template-columns:repeat(3,1fr); gap:10px;" >
            <div><label style="font-size:11px; color:#9ca3af; font-weight:500;"><i class="fas fa-sitemap" style="color:#8b5cf6;"></i> 職類</label>
                <select id="sCat${gid}" style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:6px; font-size:12px;"><option value="">全部</option>${catOpts}</select></div>
            <div><label style="font-size:11px; color:#9ca3af; font-weight:500;"><i class="fas fa-map-marker" style="color:#3b82f6;"></i> 所屬</label>
                <select id="sLoc${gid}" style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:6px; font-size:12px;"><option value="">全部</option>${locOpts}</select></div>
            <div><label style="font-size:11px; color:#9ca3af; font-weight:500;"><i class="fas fa-tag" style="color:#f59e0b;"></i> 屬性</label>
                <select id="sAttr${gid}" style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:6px; font-size:12px;"><option value="">全部</option>${attrOpts}</select></div>
        </div >
        ${ gid > 1 ? `<button type="button" onclick="removeSettingsFilterGroup(${gid})" style="margin-top:6px; font-size:11px; color:#ef4444; background:none; border:none; cursor:pointer;"><i class="fas fa-times"></i> 移除</button>` : '' }
    `;
    container.appendChild(div);
}

function toggleSettingsOp(gid) {
    const d = document.getElementById(`settingsOp${ gid } `);
    if (!d) return;
    const next = d.dataset.operator === 'or' ? 'and' : 'or';
    d.dataset.operator = next;
    const s = d.querySelector('span');
    s.textContent = next.toUpperCase();
    s.style.background = next === 'and' ? 'linear-gradient(135deg,#8b5cf6,#7c3aed)' : 'linear-gradient(135deg,#f59e0b,#d97706)';
}

function removeSettingsFilterGroup(gid) {
    document.getElementById(`settingsGroup${ gid } `)?.remove();
    document.getElementById(`settingsOp${ gid } `)?.remove();
}

function resetSettingsFilters() {
    const c = document.getElementById('settingsFilterGroups');
    if (c) c.innerHTML = '';
    settingsFilterGroupCount = 0;
    addSettingsFilterGroup();
    foundMandatoryUsers = [];
    // 清除已選標籤
    settingsSelectedTagIds = [];
    const tagsContainer = document.getElementById('settingsSelectedTags');
    if (tagsContainer) tagsContainer.innerHTML = '';
    document.getElementById('mandatoryUsersPreview').innerHTML = '<div style="text-align:center; color:#9ca3af; padding:16px; font-size:13px;"><i class="fas fa-users" style="font-size:24px; margin-bottom:8px; opacity:0.4; display:block;"></i>請選擇篩選條件後點擊「搜尋」</div>';
}

async function searchMandatoryUsers() {
    const preview = document.getElementById('mandatoryUsersPreview');
    preview.innerHTML = '<div style="text-align:center; padding:16px;"><i class="fas fa-spinner fa-spin"></i> 搜尋中...</div>';
    
    const groups = document.querySelectorAll('.settings-filter-group');
    const filterGroups = [], operators = [];
    groups.forEach((g, i) => {
        const idx = i + 1;
        const cat = document.getElementById(`sCat${ idx } `)?.value || '';
        const loc = document.getElementById(`sLoc${ idx } `)?.value || '';
        const attr = document.getElementById(`sAttr${ idx } `)?.value || '';
        if (cat || loc || attr) {
            filterGroups.push({ category: cat, location: loc, attribute: attr });
            if (i > 0) {
                const opDiv = document.getElementById(`settingsOp${ idx } `);
                operators.push(opDiv?.dataset?.operator || 'or');
            }
        }
    });
    
    if (filterGroups.length === 0) {
        preview.innerHTML = '<div style="text-align:center; color:#f59e0b; padding:16px; font-size:13px;"><i class="fas fa-exclamation-triangle"></i> 請至少選擇一個條件</div>';
        return;
    }
    
    try {
        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=categories/search_users_by_filter', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ filter_groups: filterGroups, operators })
        });
        const data = await res.json();
        if (data.success) {
            foundMandatoryUsers = data.users || [];
            if (foundMandatoryUsers.length === 0) {
                preview.innerHTML = '<div style="text-align:center; color:#9ca3af; padding:16px; font-size:13px;"><i class="fas fa-user-slash"></i> 沒有符合條件的人員</div>';
            } else {
                preview.innerHTML = `
        < div style = "display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;" >
            <span style="font-weight:600; color:#1f2937; font-size:14px;"><i class="fas fa-users" style="color:#10b981; margin-right:4px;"></i>找到 ${foundMandatoryUsers.length} 人</span>
        </div >
        <div style="max-height:120px; overflow-y:auto; font-size:12px; color:#4b5563;">
            ${foundMandatoryUsers.slice(0, 30).map(u => `<span style="display:inline-block; background:#f3f4f6; padding:3px 8px; border-radius:4px; margin:2px;">${u.fullname || u.username}</span>`).join('')}
            ${foundMandatoryUsers.length > 30 ? `<span style="color:#9ca3af;">...還有 ${foundMandatoryUsers.length - 30} 人</span>` : ''}
        </div>`;
            }
        } else {
            preview.innerHTML = `< div style = "text-align:center; color:#ef4444; padding:16px; font-size:13px;" > ${ data.error || '搜尋失敗' }</div > `;
        }
    } catch (e) {
        preview.innerHTML = '<div style="text-align:center; color:#ef4444; padding:16px; font-size:13px;">網路錯誤</div>';
    }
}

async function removeMandatoryUser(categoryId, requirementId, userId, userName, btnEl) {
    const currentCount = currentSettings?.mandatory_user_count || 0;
    
    // 最後一個人 → 必須取消必修類別
    if (currentCount <= 1) {
        if (!confirm('必修類別至少需要一位對象，若要取消則必須取消必修類別設定。\n\n確定要移除此人並取消必修類別嗎？')) return;
    } else {
        if (!confirm(`確定要將 ${ userName } 從這個必修類別中移除嗎？`)) return;
    }
    
    const oldHtml = btnEl.innerHTML;
    btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btnEl.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('category_id', categoryId);
        if (requirementId > 0) formData.append('requirement_id', requirementId);
        if (userId > 0) formData.append('user_id', userId);
        
        const res = await fetch(`${ PortalConfig.webRoot } /api/v2 / index.php ? route = categories / requirement / remove`, {
            method: 'POST', body: formData
        });
        const data = await res.json();
        console.log('[removeMandatoryUser] API回傳:', data, 'reqId:', requirementId, 'userId:', userId);
        
        if (data.success) {
            const deletedCount = data.data?.deleted_count ?? 0;
            
            if (deletedCount === 0) {
                // DB 中找不到匹配的記錄 — 顯示警告
                showToast(`移除失敗：在資料庫中找不到 ${ userName } 的必修記錄(id = ${ userId })`, 'warning');
                btnEl.innerHTML = oldHtml;
                btnEl.disabled = false;
                return;
            }
            
            showToast(`已成功移除 ${ userName } `, 'success');
            // 視覺上移除這個 pill
            const pill = btnEl.closest('.mandatory-user-pill');
            if (pill) pill.remove();
            
            // 同步更新 currentSettings
            if (currentSettings) {
                if (currentSettings.mandatory_user_count > 0) {
                    currentSettings.mandatory_user_count--;
                }
                if (currentSettings.mandatory_users) {
                    currentSettings.mandatory_users = currentSettings.mandatory_users.filter(u => u.requirement_id !== requirementId);
                }
                // 全部刪光了 → 取消必修類別
                if (currentSettings.mandatory_user_count <= 0) {
                    // 取消勾勾
                    const chk = document.getElementById('setMandatory');
                    if (chk) { chk.checked = false; toggleMandatorySection(); }
                    // 立即儲存取消必修到 DB
                    const cancelForm = new FormData();
                    cancelForm.append('category_id', categoryId);
                    cancelForm.append('is_mandatory_category', 0);
                    cancelForm.append('required_pass_count', 0);
                    cancelForm.append('period_months', 0);
                    cancelForm.append('require_order', 0);
                    cancelForm.append('visibility', 'all');
                    await fetch(`${ PortalConfig.webRoot } /api/v2 / index.php ? route = categories / update_settings`, { method: 'POST', body: cancelForm });
                    showToast('已取消必修類別設定', 'info');
                    currentSettings.is_mandatory_category = 0;
                }
            }
            
            // 同步更新 foundMandatoryUsers (如果是剛搜出來的人)
            foundMandatoryUsers = foundMandatoryUsers.filter(u => u.id !== userId);
        } else {
            throw new Error(data.error || '移除失敗');
        }
    } catch (e) {
        console.error("移除必修人員失敗", e);
        showToast(e.message || '移除失敗', 'error');
        btnEl.innerHTML = oldHtml;
        btnEl.disabled = false;
    }
}

async function saveFullSettings() {
    const isMandatory = document.getElementById('setMandatory')?.checked;
    const isOrdered = document.getElementById('setOrdered')?.checked;
    const fixedEnabled = document.getElementById('fixedCountCheck')?.checked;
    const requiredCount = fixedEnabled ? (document.getElementById('fixedCountInput')?.value || 0) : 0;
    const periodMonths = document.getElementById('periodMonths')?.value || 0;
    const visibility = document.querySelector('input[name="settingsVis"]:checked')?.value || 'all';
    const catId = currentSettingsCategoryId;
    
    // 必修但沒選人：警告但不阻擋（使用者可能透過 X 刪光了人，DB 已更新）
    const existingUserCount = currentSettings?.mandatory_user_count || 0;
    if (isMandatory && foundMandatoryUsers.length === 0 && existingUserCount === 0) {
        if (!confirm('目前沒有指派任何必修對象，確定要儲存嗎？')) return;
    }

    // 檢查必修類別衝突（已有課程的情況）
    if (isMandatory && (foundMandatoryUsers.length > 0 || existingUserCount > 0)) {
        try {
            const conflictForm = new FormData();
            conflictForm.append('category_id', catId);
            conflictForm.append('visibility', visibility);
            conflictForm.append('required_pass_count', requiredCount);
            // 傳入待儲存的必修對象 moodle_id（非 DB 裡的舊資料）
            if (foundMandatoryUsers.length > 0) {
                const moodleIds = foundMandatoryUsers.map(u => u.moodle_id || u.id).filter(Boolean);
                conflictForm.append('moodle_user_ids', moodleIds.join(','));
            }
            const conflictRes = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=categories/check_mandatory_conflicts', {
                method: 'POST', body: conflictForm
            });
            const conflictData = await conflictRes.json();
            if (conflictData.success && conflictData.data?.has_conflicts) {
                const conflicts = conflictData.data.conflicts;
                
                // 分類衝突
                const extraConflicts = conflicts.filter(c => c.type === 'extra_users');
                const missingConflicts = conflicts.filter(c => c.type === 'missing_users');
                
                // 僅他們可見 + 多餘的人
                if (extraConflicts.length > 0) {
                    let bodyHtml = '<div style="font-size:14px; color:#1e293b; margin-bottom:12px;">以下課程有學員<b style="color:#dc2626;">不在必修篩選範圍</b>內：</div>';
                    for (const c of extraConflicts) {
                        bodyHtml += `< div style = "background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px 14px; margin-bottom:8px;" > `;
                        bodyHtml += `< div style = "font-weight:600; color:#991b1b; font-size:13px;" > <i class="fas fa-book" style="margin-right:4px;"></i>${ c.course_name }</div > `;
                        if (c.users?.length > 0) {
                            bodyHtml += `< div style = "color:#b91c1c; font-size:12px; margin-top:4px;" > ${ c.users.map(u => `<span style="background:#fee2e2; padding:2px 8px; border-radius:10px; margin:2px; display:inline-block;">${u.fullname}</span>`).join('') }</div > `;
                        }
                        bodyHtml += '</div>';
                    }
                    const confirmed = await showConflictModal(bodyHtml, '按「確定執行」將這些人從課程中移除，按「取消」放棄設定');
                    if (!confirmed) return;
                    
                    // 執行移除：從 Moodle 退選 + Portal 可見度
                    for (const c of extraConflicts) {
                        const extraIds = c.users.map(u => u.id);
                        try {
                            const unenrolForm = new FormData();
                            unenrolForm.append('course_id', c.course_id);
                            unenrolForm.append('user_ids', extraIds.join(','));
                            await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/unenrol_users', {
                                method: 'POST', body: unenrolForm
                            });
                        } catch (e) { console.warn('移除失敗', e); }
                    }
                    showToast('已移除衝突學員', 'success');
                }
                
                // (已移除) 全部可見 + 缺少必修對象 的檢查：現在依課堂數規範即可，無須強制加入
                // 招生數不足提醒（僅警告，不阻擋儲存）
                const insuffConflicts = conflicts.filter(c => c.type === 'insufficient_enrollment');
                if (insuffConflicts.length > 0) {
                    const ic = insuffConflicts[0];
                    let warnHtml = `< div style = "font-size:14px; color:#1e293b; margin-bottom:12px;" > 以下 < b style = "color:#d97706;" > ${ ic.count } 位</b > 必修對象目前被招的課程 < b style = "color:#dc2626;" > 不足 ${ ic.required_count } 堂</b >，可能無法達成通過條件：</div > `;
                    if (ic.users?.length > 0) {
                        warnHtml += '<div style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:8px;">';
                        for (const u of ic.users) {
                            warnHtml += `< span style = "background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:10px; font-size:12px; border:1px solid #fde68a;" > ${ u.fullname } (${ u.enrolled_count }堂)</span > `;
                        }
                        warnHtml += '</div>';
                    }
                    await showConflictModal(warnHtml, '此為提醒，按「確定執行」繼續儲存，或按「取消」回去調整招生');
                }
            }
        } catch (e) {
            console.warn('衝突檢查失敗，繼續儲存', e);
        }
    }
    
    try {
        const formData = new FormData();
        formData.append('category_id', catId);
        formData.append('is_mandatory_category', isMandatory ? 1 : 0);
        formData.append('required_pass_count', requiredCount);
        formData.append('period_months', periodMonths);
        formData.append('require_order', isOrdered ? 1 : 0);
        formData.append('visibility', visibility);
        // 從 DOM 讀取實際已選標籤（因為使用者可以用 × 移除個別標籤）
        const tagEls = document.querySelectorAll('#settingsSelectedTags .settings-selected-tag');
        const saveTagIds = Array.from(tagEls).map(el => el.dataset.tagId);
        if (saveTagIds.length > 0) {
            formData.append('tag_ids', JSON.stringify(saveTagIds));
        }
        
        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=categories/update_settings', { method: 'POST', body: formData });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '儲存失敗');
        
        // 如果必修且有人員，儲存需求
        if (isMandatory && foundMandatoryUsers.length > 0) {
            const groups = document.querySelectorAll('.settings-filter-group');
            const filterGroups = [];
            groups.forEach((g, i) => {
                const idx = i + 1;
                const catSel = document.getElementById(`sCat${ idx } `);
                const locSel = document.getElementById(`sLoc${ idx } `);
                const attrSel = document.getElementById(`sAttr${ idx } `);
                const cat = catSel?.value || '';
                const loc = locSel?.value || '';
                const attr = attrSel?.value || '';
                if (cat || loc || attr) filterGroups.push({
                    category: cat,
                    category_name: catSel?.selectedOptions[0]?.text || '',
                    location: loc,
                    location_name: locSel?.selectedOptions[0]?.text || '',
                    attribute: attr,
                    attribute_name: attrSel?.selectedOptions[0]?.text || ''
                });
            });
            
            const reqRes = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=categories/save_mandatory_requirements', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    category_id: catId,
                    required_pass_count: requiredCount,
                    period_months: periodMonths,
                    user_ids: foundMandatoryUsers,
                    filter_groups: filterGroups,
                    tag_ids: settingsSelectedTagIds
                })
            });
            const reqData = await reqRes.json();
            if (!reqData.success) throw new Error(reqData.error || '儲存需求失敗');
            showToast(`設定已儲存，已為 ${ reqData.inserted_count } 位使用者設定必修需求`, 'success');
        } else {
            showToast('設定已儲存', 'success');
        }
        
--- END REMOVED SETTINGS BLOCK --- */

// ==========================================
// 初始化
// ==========================================
    document.addEventListener('DOMContentLoaded', async function () {
        if (document.getElementById('section-management')) {
            await loadCategoryTree();
            // 從 URL 自動選中類別，或從 sessionStorage 還原
            const urlParams = new URLSearchParams(window.location.search);
            const selectCatId = urlParams.get('select_cat') || urlParams.get('category') || sessionStorage.getItem('mgmt_selected_cat_id');
            if (selectCatId) {
                try {
                    // 取得該類別的路徑 (Moodle 格式如 /1/15/23/45)
                    const res = await fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=categories/show&id=${selectCatId}`);
                    const apiData = await res.json();
                    const catInfo = apiData.data?.category || apiData.category;

                    if (apiData.success && catInfo && catInfo.path) {
                        let pathIds = catInfo.path.split('/').filter(id => id !== '').map(Number);
                        // 確保目標 ID 在路徑末尾
                        const targetId = parseInt(selectCatId);
                        if (!pathIds.includes(targetId)) {
                            pathIds.push(targetId);
                        }

                        console.log('[TreeRestore] path:', catInfo.path, '→ filtered:', pathIds, 'target:', targetId);

                        // 依照路徑從第一層子類別向下展開
                        for (let i = 0; i < pathIds.length; i++) {
                            const pid = pathIds[i];
                            const targetItem = document.querySelector(`.tree-item[data-id="${pid}"]`);

                            if (targetItem) {
                                if (pid === targetId) {
                                    // 找到目標節點，觸發選取
                                    const targetRow = targetItem.querySelector(':scope > .tree-item__row');
                                    if (targetRow) {
                                        targetRow.click();
                                        setTimeout(() => {
                                            targetItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                        }, 100);
                                    }
                                    break;
                                } else {
                                    // 父層節點，展開它以載入子層
                                    const childrenContainer = targetItem.querySelector(`#tree-children-${pid}`);
                                    if (childrenContainer && !childrenContainer.classList.contains('open')) {
                                        const row = targetItem.querySelector(':scope > .tree-item__row');
                                        const indent = row ? parseInt(row.style.paddingLeft || '12') : 12;
                                        await onToggleNode(pid, targetItem, indent);
                                    }
                                }
                            } else {
                                console.warn('[TreeRestore] Node not found in DOM:', pid);
                            }
                        }
                    } else {
                        console.warn('[TreeRestore] API failed or no path, fallback to direct find');
                        // API 失敗，至少嘗試選中第一層
                        const firstItem = document.querySelector(`.tree-item[data-id="${selectCatId}"]`);
                        if (firstItem) {
                            const row = firstItem.querySelector(':scope > .tree-item__row');
                            if (row) row.click();
                        }
                    }
                } catch (e) {
                    console.error("Auto expand failed", e);
                }
            }
        }
    });
</script>