<?php
/**
 * 院區管理員 - 群組管理（全新設計）
 * templates/tabs/hospital_admin_cohorts.php
 * 
 * 特點：
 * - 維度分頁（職類/層級/屬性）
 * - 簡潔專業的 UI
 * - 快速搜尋和篩選
 */
?>

<style>
    /* 群組管理專用樣式 */
    .cohort-page {
        padding: 0;
    }

    .cohort-page .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .cohort-page .page-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .cohort-page .page-title i {
        color: #3b82f6;
    }

    /* 維度 Tabs */
    .dimension-tabs {
        display: flex;
        gap: 4px;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 10px;
        margin-bottom: 24px;
    }

    .dimension-tab {
        padding: 10px 24px;
        border-radius: 8px;
        border: none;
        background: transparent;
        color: #64748b;
        font-weight: 500;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .dimension-tab:hover {
        color: #334155;
    }

    .dimension-tab.active {
        background: white;
        color: #1e293b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .dimension-tab .count {
        background: #e2e8f0;
        color: #64748b;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.75rem;
        margin-left: 8px;
    }

    .dimension-tab.active .count {
        background: #3b82f6;
        color: white;
    }

    /* 二級分類 Tab */
    .sub-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
        padding: 12px;
        background: #f8fafc;
        border-radius: 8px;
    }

    .sub-tab {
        padding: 6px 14px;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        background: white;
        cursor: pointer;
        font-size: 0.85rem;
        transition: all 0.2s;
    }

    .sub-tab:hover {
        border-color: #3b82f6;
        background: #f0f9ff;
    }

    .sub-tab.active {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }

    /* 麵包屑導航 */
    .breadcrumb-nav {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 16px;
        padding: 12px 16px;
        background: #f8fafc;
        border-radius: 8px;
    }

    .breadcrumb-back {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border: none;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        color: #64748b;
        transition: all 0.2s;
    }

    .breadcrumb-back:hover {
        background: #e2e8f0;
        color: #1e40af;
    }

    .breadcrumb-path {
        font-size: 0.9rem;
        color: #475569;
    }

    .breadcrumb-path span {
        color: #94a3b8;
    }

    /* 子分類卡片區 */
    .sub-folders {
        margin-bottom: 24px;
    }

    .sub-folders-title {
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .sub-folders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 12px;
    }

    .sub-folder-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .sub-folder-card:hover {
        border-color: #3b82f6;
        background: #f0f9ff;
    }

    .sub-folder-card i {
        color: #3b82f6;
        font-size: 1.2rem;
    }

    .sub-folder-info {
        flex: 1;
    }

    .sub-folder-name {
        font-weight: 500;
        color: #1e293b;
    }

    .sub-folder-count {
        font-size: 0.8rem;
        color: #94a3b8;
    }

    /* 群組列表 */
    .cohorts-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .cohort-row {
        display: flex;
        align-items: center;
        padding: 14px 18px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .cohort-row:hover {
        border-color: #3b82f6;
        background: #f8fafc;
    }

    .cohort-row.selected {
        border-color: #3b82f6;
        background: #eff6ff;
    }

    .cohort-row-name {
        flex: 1;
        font-weight: 500;
        color: #1e293b;
    }

    .cohort-row-meta {
        display: flex;
        align-items: center;
        gap: 16px;
        color: #64748b;
        font-size: 0.9rem;
    }

    .cohort-row-badge {
        padding: 4px 10px;
        background: #dbeafe;
        color: #1e40af;
        border-radius: 12px;
        font-size: 0.75rem;
    }

    .cohort-row-id {
        font-family: monospace;
        font-size: 0.8rem;
        color: #94a3b8;
        background: #f1f5f9;
        padding: 2px 8px;
        border-radius: 4px;
    }

    .cohort-row-arrow {
        color: #3b82f6;
        margin-right: 8px;
        transition: transform 0.2s;
    }

    .cohort-row.has-children:hover .cohort-row-arrow {
        transform: translateX(4px);
    }

    .cohort-row.has-children {
        background: #fafbfc;
    }

    .cohort-child-count {
        font-size: 0.8rem;
        color: #94a3b8;
        font-weight: 400;
        margin-left: 8px;
    }

    /* 成員視圖樣式 */
    .members-view {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .members-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .members-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .members-title i {
        color: #3b82f6;
    }

    .members-actions {
        display: flex;
        gap: 10px;
    }

    .members-table {
        width: 100%;
    }

    .members-table-header {
        display: grid;
        grid-template-columns: 40px 2fr 1fr 2fr 60px;
        gap: 16px;
        padding: 12px 24px;
        background: transparent;
        font-weight: 500;
        color: #64748b;
        font-size: 0.85rem;
        border-bottom: 1px solid #e2e8f0;
        align-items: center;
    }

    .member-row {
        display: grid;
        grid-template-columns: 40px 2fr 1fr 2fr 60px;
        gap: 16px;
        padding: 14px 24px;
        border-bottom: 1px solid #f1f5f9;
        align-items: center;
        transition: background 0.15s;
    }

    .member-row:hover {
        background: #f8fafc;
    }

    .member-row:last-child {
        border-bottom: none;
    }

    .member-name-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .member-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .member-username {
        color: #64748b;
        font-size: 0.9rem;
    }

    .member-email {
        color: #94a3b8;
        font-size: 0.9rem;
    }

    .members-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 24px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        color: #64748b;
        font-size: 0.9rem;
    }

    .page-btn {
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: white;
        color: #475569;
        font-size: 0.85rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s;
    }

    .page-btn:hover:not(:disabled) {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .page-btn.active {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }

    .page-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s;
        background: transparent;
        color: #94a3b8;
    }

    .btn-icon:hover {
        background: #fee2e2;
        color: #dc2626;
    }

    .btn-icon.danger:hover {
        background: #fee2e2;
        color: #dc2626;
    }

    .loading-state,
    .empty-state {
        padding: 60px 20px;
        text-align: center;
        color: #94a3b8;
    }

    .loading-state i,
    .empty-state i {
        font-size: 2rem;
        margin-bottom: 16px;
        display: block;
    }

    .empty-state.error {
        color: #ef4444;
    }

    .empty-state button {
        margin-top: 16px;
    }

    /* 匯入成員項目 */
    .import-member-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: 6px;
        transition: background 0.15s;
    }

    .import-member-item:hover {
        background: #f1f5f9;
    }

    /* 群組網格 - 保留向後兼容 */
    .cohorts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
    }

    .cohort-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .cohort-card:hover {
        border-color: #3b82f6;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
    }

    .cohort-card.selected {
        border-color: #3b82f6;
        background: #f0f9ff;
    }

    .cohort-path {
        font-size: 0.75rem;
        color: #64748b;
        background: #f1f5f9;
        padding: 4px 8px;
        border-radius: 4px;
        margin-bottom: 8px;
        display: inline-block;
    }

    .cohort-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .cohort-name {
        font-weight: 600;
        font-size: 1rem;
        color: #1e293b;
    }

    .cohort-badge {
        background: #dbeafe;
        color: #1d4ed8;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .cohort-meta {
        display: flex;
        gap: 16px;
        color: #64748b;
        font-size: 0.85rem;
    }

    .cohort-meta-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* 成員面板 */
    .members-panel {
        position: fixed;
        top: 0;
        right: -450px;
        width: 450px;
        height: 100vh;
        background: white;
        box-shadow: -4px 0 20px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        transition: right 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .members-panel.open {
        right: 0;
    }

    .members-panel-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .members-panel-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1e293b;
    }

    .members-panel-close {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        background: #f1f5f9;
        color: #64748b;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .members-panel-close:hover {
        background: #e2e8f0;
    }

    .members-panel-actions {
        padding: 16px 24px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        gap: 12px;
    }

    .members-panel-body {
        flex: 1;
        overflow-y: auto;
        padding: 16px 24px;
    }

    .member-item {
        display: flex;
        align-items: center;
        padding: 12px;
        border-radius: 8px;
        gap: 12px;
    }

    .member-item:hover {
        background: #f8fafc;
    }

    .member-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        font-size: 0.9rem;
    }

    .member-info {
        flex: 1;
    }

    .member-name {
        font-weight: 500;
        color: #1e293b;
        font-size: 0.9rem;
    }

    .member-email {
        color: #64748b;
        font-size: 0.8rem;
    }

    .member-remove {
        opacity: 0;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        border: none;
        background: #fee2e2;
        color: #dc2626;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: opacity 0.2s;
    }

    .member-item:hover .member-remove {
        opacity: 1;
    }

    /* 空狀態 */
    .empty-cohorts {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }

    .empty-cohorts i {
        font-size: 3rem;
        margin-bottom: 16px;
        color: #cbd5e1;
    }

    /* 按鈕組 */
    .btn-compact {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 500;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
    }

    .btn-compact.primary {
        background: #3b82f6;
        color: white;
    }

    .btn-compact.primary:hover {
        background: #2563eb;
    }

    .btn-compact.secondary {
        background: #f1f5f9;
        color: #475569;
    }

    .btn-compact.secondary:hover {
        background: #e2e8f0;
    }

    .btn-compact.danger {
        background: #fef2f2;
        color: #dc2626;
    }

    .btn-compact.danger:hover {
        background: #fee2e2;
    }

    /* 遮罩 */
    .panel-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.3);
        z-index: 999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s;
    }

    .panel-overlay.visible {
        opacity: 1;
        visibility: visible;
    }

    /* 搜尋框 */
    .search-box {
        position: relative;
        margin-bottom: 20px;
    }

    .search-box input {
        width: 100%;
        padding: 12px 16px 12px 44px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.9rem;
        transition: border-color 0.2s;
    }

    .search-box input:focus {
        outline: none;
        border-color: #3b82f6;
    }

    .search-box i {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }

    /* 群組篩選器 */
    .cohort-filter-section {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        margin: 16px 0;
    }

    .cohort-filter-section h4 {
        margin: 0 0 14px;
        font-size: 0.95rem;
        color: #475569;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .cohort-filter-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 12px;
    }

    .cohort-filter-item label {
        display: block;
        font-size: 0.8rem;
        font-weight: 500;
        color: #64748b;
        margin-bottom: 6px;
    }

    .cohort-filter-item label i {
        margin-right: 4px;
    }

    .cohort-filter-item select {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.85rem;
        background: white;
    }

    .cohort-filter-tags {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 12px;
    }

    .cohort-filter-tags .filter-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        background: #dbeafe;
        color: #1e40af;
        cursor: default;
    }

    .cohort-filter-tags .filter-tag .remove-tag {
        cursor: pointer;
        opacity: 0.6;
    }

    .cohort-filter-tags .filter-tag .remove-tag:hover {
        opacity: 1;
    }

    .cohort-filter-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .cohort-filter-results {
        margin-top: 16px;
        border-top: 1px solid #e2e8f0;
        padding-top: 16px;
    }

    .cohort-filter-results .result-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: 8px;
        transition: background 0.15s;
    }

    .cohort-filter-results .result-item:hover {
        background: #f1f5f9;
    }

    .filter-operator {
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 8px 0;
    }

    .filter-operator .op-btn {
        padding: 2px 12px;
        border-radius: 12px;
        border: none;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .filter-operator .op-btn.or {
        background: #f59e0b;
        color: white;
    }

    .filter-operator .op-btn.and {
        background: #8b5cf6;
        color: white;
    }
</style>

<div id="section-cohort-management" class="page-section cohort-page">
    <!-- 頁面標題 -->
    <div class="page-header">
        <h2 class="page-title">
            <i class="fas fa-layer-group"></i>
            群組管理
        </h2>
        <button class="btn-compact primary" onclick="openNewCohortModal()">
            <i class="fas fa-plus"></i> 新增群組
        </button>
    </div>

    <!-- 維度說明卡片 -->
    <div class="dimension-info" style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
        <div class="info-card"
            style="background: linear-gradient(135deg, #eef2ff, #e0e7ff); padding: 12px 16px; border-radius: 10px; flex: 1; min-width: 200px;">
            <div style="font-weight: 600; color: #4338ca; font-size: 0.85rem; margin-bottom: 4px;">
                <i class="fas fa-briefcase" style="margin-right: 6px;"></i>職類
            </div>
            <div style="color: #6366f1; font-size: 0.8rem;">專業及科別，對應課程分類（護理 > 內科）</div>
        </div>
        <div class="info-card"
            style="background: linear-gradient(135deg, #ecfdf5, #d1fae5); padding: 12px 16px; border-radius: 10px; flex: 1; min-width: 200px;">
            <div style="font-weight: 600; color: #047857; font-size: 0.85rem; margin-bottom: 4px;">
                <i class="fas fa-map-marker-alt" style="margin-right: 6px;"></i>所屬
            </div>
            <div style="color: #10b981; font-size: 0.8rem;">工作地點，如：9A病房、門診區等</div>
        </div>
        <div class="info-card"
            style="background: linear-gradient(135deg, #fff7ed, #ffedd5); padding: 12px 16px; border-radius: 10px; flex: 1; min-width: 200px;">
            <div style="font-weight: 600; color: #c2410c; font-size: 0.85rem; margin-bottom: 4px;">
                <i class="fas fa-tags" style="margin-right: 6px;"></i>屬性
            </div>
            <div style="color: #f97316; font-size: 0.8rem;">身份特性，如：新進人員、PGY、行政人員、主管</div>
        </div>
    </div>

    <!-- 維度分頁 -->
    <div class="dimension-tabs" id="dimension-tabs">
        <button class="dimension-tab active" data-dim="all" onclick="filterByDimension('all')">
            全部 <span class="count" id="count-all">0</span>
        </button>
        <button class="dimension-tab" data-dim="主群組" onclick="filterByDimension('主群組')">
            主群組 <span class="count" id="count-主群組">0</span>
        </button>
        <button class="dimension-tab" data-dim="職類" onclick="filterByDimension('職類')">
            職類 <span class="count" id="count-職類">0</span>
        </button>
        <button class="dimension-tab" data-dim="所屬" onclick="filterByDimension('所屬')">
            所屬 <span class="count" id="count-所屬">0</span>
        </button>
        <button class="dimension-tab" data-dim="屬性" onclick="filterByDimension('屬性')">
            屬性 <span class="count" id="count-屬性">0</span>
        </button>
        <button class="dimension-tab" data-dim="未分類" onclick="filterByDimension('未分類')">
            未分類 <span class="count" id="count-未分類">0</span>
        </button>
    </div>

    <!-- 二級分類 Tab（子分類） -->
    <div class="sub-tabs" id="sub-tabs" style="display:none;">
        <!-- 動態生成 -->
    </div>

    <!-- 搜尋 -->
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="cohort-search" placeholder="搜尋群組名稱..." oninput="searchCohorts(this.value)">
    </div>

    <!-- 麵包屑導航 -->
    <div class="breadcrumb-nav" id="breadcrumb-nav" style="display:none;">
        <button class="breadcrumb-back" onclick="navigateBack()">
            <i class="fas fa-arrow-left"></i> 返回
        </button>
        <div class="breadcrumb-path" id="breadcrumb-path"></div>
    </div>

    <!-- 子分類卡片區 -->
    <div class="sub-folders" id="sub-folders" style="display:none;">
        <div class="sub-folders-title">子分類</div>
        <div class="sub-folders-grid" id="sub-folders-grid"></div>
    </div>

    <!-- 群組列表 -->
    <div class="cohorts-list" id="cohorts-list">
        <div class="empty-cohorts">
            <i class="fas fa-spinner fa-spin"></i>
            <p>載入中...</p>
        </div>
    </div>
</div>

<!-- 成員面板遮罩 -->
<div class="panel-overlay" id="panel-overlay" onclick="closeMembersPanel()"></div>

<!-- 成員側邊面板 -->
<div class="members-panel" id="members-panel">
    <div class="members-panel-header">
        <div style="display:flex; align-items:center; gap:8px;">
            <div class="members-panel-title" id="panel-cohort-name">群組成員</div>
            <button id="settings-cohort-btn" class="btn-compact secondary" style="padding:4px 8px;"
                onclick="openEditCohortModal()" title="設定群組">
                <i class="fas fa-cog"></i>
            </button>
        </div>
        <button class="members-panel-close" onclick="closeMembersPanel()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="members-panel-actions">
        <!-- 原有按鈕... -->
        <button class="btn-compact primary" onclick="openAddMemberModal()">
            <i class="fas fa-user-plus"></i> 加入
        </button>
        <button class="btn-compact danger" onclick="removeSelectedMembers()">
            <i class="fas fa-user-minus"></i> 移除
        </button>
    </div>
    <div class="members-panel-body" id="members-list">
        <div class="empty-cohorts">
            <i class="fas fa-users"></i>
            <p>尚無成員</p>
        </div>
    </div>
</div>

<!-- 編輯群組 Modal -->
<div id="edit-cohort-modal" class="modal-overlay" style="display:none; z-index:1100;">
    <div class="modal-content" style="max-width: 420px; padding: 24px;">
        <div class="modal-header" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 1.1rem;">群組設定</h3>
            <button class="modal-close" onclick="closeEditCohortModal()">&times;</button>
        </div>
        <form onsubmit="updateCohort(event)">
            <input type="hidden" id="edit-cohort-id">
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">群組名稱</label>
                <input type="text" id="edit-cohort-name" disabled
                    style="width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; color:#6b7280;">
                <div style="font-size:0.8rem; color:#9ca3af; margin-top:4px;">目前僅支援修改分類</div>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">歸入維度</label>
                <select id="edit-cohort-dimension"
                    style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px;">
                    <option value="">不指定</option>
                </select>
            </div>
            <div style="display:flex; gap:12px; justify-content:space-between; margin-top:32px;">
                <button type="button" id="delete-cohort-btn" class="btn-compact danger" onclick="deleteCohort()">
                    <i class="fas fa-trash-alt"></i> 刪除群組
                </button>
                <div style="display:flex; gap:12px;">
                    <button type="button" class="btn-compact secondary" onclick="closeEditCohortModal()">取消</button>
                    <button type="submit" class="btn-compact primary">儲存設定</button>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- 新增群組 Modal -->
<div id="new-cohort-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width: 420px; padding: 24px;">
        <div class="modal-header" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 1.1rem;">新增群組</h3>
            <button class="modal-close" onclick="closeNewCohortModal()">&times;</button>
        </div>
        <form onsubmit="createCohort(event)">
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">群組名稱</label>
                <input type="text" id="new-cohort-name" required
                    style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px;">
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">建立於</label>
                <select id="new-cohort-dimension" onchange="onDimensionChange(this)"
                    style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px;">
                    <option value="">請選擇</option>
                </select>
            </div>
            <!-- 動態子類別/子群組容器（可無限層級）-->
            <div id="subcategory-container"></div>
            <div style="display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" class="btn-compact secondary" onclick="closeNewCohortModal()">取消</button>
                <button type="submit" class="btn-compact primary">建立</button>
            </div>
        </form>
    </div>
</div>

<!-- 加入成員 Modal -->
<div id="add-member-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content"
        style="max-width: 500px; padding: 24px; max-height: 80vh; display:flex; flex-direction:column;">
        <div class="modal-header" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 1.1rem;">加入成員</h3>
            <button class="modal-close" onclick="closeAddMemberModal()">&times;</button>
        </div>
        <input type="text" id="member-search" placeholder="搜尋姓名或帳號..."
            style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; margin-bottom:16px;"
            oninput="filterMemberList(this.value)">
        <div id="member-list" style="flex:1; overflow-y:auto; max-height:400px;">
            <p style="text-align:center; color:#94a3b8;">載入中...</p>
        </div>
        <div
            style="display:flex; gap:12px; justify-content:flex-end; padding-top:16px; border-top:1px solid #e2e8f0; margin-top:16px;">
            <button type="button" class="btn-compact secondary" onclick="closeAddMemberModal()">取消</button>
            <button type="button" class="btn-compact primary" onclick="addSelectedMembers()">加入所選</button>
        </div>
    </div>
</div>

<!-- 從其他群組匯入成員 Modal（篩選器版） -->
<div id="import-from-group-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content"
        style="max-width: 600px; padding: 24px; max-height: 85vh; display:flex; flex-direction:column;">
        <div class="modal-header" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 1.1rem;"><i class="fas fa-filter"
                    style="color:#3b82f6; margin-right:8px;"></i> 篩選並匯入成員</h3>
            <button class="modal-close" onclick="closeImportFromGroupModal()">&times;</button>
        </div>
        <!-- 篩選條件區 -->
        <div id="import-filter-groups" style="margin-bottom:12px;"></div>
        <div style="display:flex; gap:8px; margin-bottom:16px;">
            <button class="btn-compact secondary" onclick="addImportFilterGroup()" title="新增條件組">
                <i class="fas fa-plus"></i> 新增條件組
            </button>
            <button class="btn-compact primary" onclick="searchImportFilteredUsers()">
                <i class="fas fa-search"></i> 搜尋
            </button>
            <button class="btn-compact secondary" onclick="resetImportFilter()">
                <i class="fas fa-undo"></i> 重置
            </button>
        </div>
        <!-- 搜尋結果 -->
        <div id="import-filter-results" style="flex:1; overflow-y:auto; max-height:350px; margin-bottom:12px;">
            <p style="text-align:center; color:#94a3b8; padding:40px 0;">請設定篩選條件後點擊搜尋</p>
        </div>
        <div
            style="display:flex; gap:12px; justify-content:flex-end; padding-top:16px; border-top:1px solid #e2e8f0; margin-top:auto;">
            <button type="button" class="btn-compact secondary" onclick="closeImportFromGroupModal()">取消</button>
            <button type="button" class="btn-compact primary" onclick="importFilteredUsersToGroup()">
                <i class="fas fa-file-import"></i> 匯入所選
            </button>
        </div>
    </div>
</div>

<script src="<?php echo $web_root; ?>/assets/js/pages/cohorts.js"></script>