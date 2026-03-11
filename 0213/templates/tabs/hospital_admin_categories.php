<?php
/**
 * 院區管理員 - 類別管理介面
 * templates/tabs/hospital_admin_categories.php
 */
?>
<div id="section-category-management" class="page-section">
    <div class="section-header">
        <h2><i class="fas fa-folder-tree"></i> 子類別管理</h2>
        <p class="section-subtitle">管理
            <?php echo h($_SESSION['institution'] ?? ''); ?> 院區的所有課程分類
        </p>
        <!-- Breadcrumb -->
        <div id="cat-breadcrumb" style="margin-top: 10px; font-size: 0.95rem; color: #4b5563;">
            <i class="fas fa-home"></i> <span id="breadcrumb-path">根目錄</span>
        </div>
    </div>

    <!-- 工具列 -->
    <div class="toolbar-container">
        <div class="search-bar-container">
            <!-- 類別通常不多，暫不需要搜尋，或者之後做前端 filter -->
            <!-- 類別通常不多，暫不需要搜尋，或者之後做前端 filter -->
            <button class="btn-secondary" onclick="reloadCurrentLevel()">
                <i class="fas fa-sync-alt"></i> 重新載入
            </button>
        </div>
        <button id="add-category-btn" class="btn-primary" onclick="openCategoryModal('add')" style="display:none;">
            <i class="fas fa-plus"></i> <span id="add-btn-text">新增子類別</span>
        </button>
        <!-- 說明文字 -->
        <span style="margin-left:auto; color:#6b7280; font-size:0.9rem;">
            <i class="fas fa-info-circle"></i> 點擊類別名稱可進入下層
        </span>
    </div>

    <!-- 類別列表 -->
    <div class="widget-card">
        <div class="widget-body" id="categories-list">
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- 新增/編輯類別 Modal -->
<div id="category-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 480px; padding: 24px; border-radius: 12px;">
        <div class="modal-header" style="border-bottom: 1px solid #f0f0f0; padding-bottom: 16px; margin-bottom: 20px;">
            <h3 id="cat-modal-title" style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;">新增類別</h3>
            <button class="modal-close" onclick="closeCategoryModal()">&times;</button>
        </div>
        <form id="category-form" onsubmit="saveCategory(event)">
            <input type="hidden" id="cat-id" name="id">
            <input type="hidden" id="cat-parent-id" name="parent_id">
            <input type="hidden" id="cat-modal-mode" name="action" value="create">

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="cat-name" style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">類別名稱
                    <span style="color:#ef4444">*</span></label>
                <input type="text" id="cat-name" name="name" required placeholder="例如：護理部、醫師培訓"
                    style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
            </div>

            <!-- 群組選項（僅新增時顯示） -->
            <div id="cohort-options" class="form-group" style="margin-bottom: 24px;">
                <label
                    style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px 16px; background: #f0f9ff; border-radius: 8px; border: 1px solid #bae6fd;">
                    <input type="checkbox" id="cat-create-cohort" name="create_cohort" value="1" checked
                        style="width: 18px; height: 18px; accent-color: #3b82f6;">
                    <span style="color: #0369a1; font-weight: 500;">
                        <i class="fas fa-users" style="margin-right: 6px;"></i>
                        建立群組並歸入職類
                    </span>
                </label>
            </div>

            <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 10px;">
                <button type="button" class="btn-secondary" onclick="closeCategoryModal()"
                    style="padding: 10px 18px; border-radius: 8px; background: #f3f4f6; color: #374151; border: none; cursor: pointer; font-weight: 500;">
                    取消
                </button>
                <button type="submit" class="btn-primary" id="cat-submit-btn"
                    style="padding: 10px 18px; border-radius: 8px; background: #3b82f6; color: white; border: none; cursor: pointer; font-weight: 500;">
                    儲存
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let allCategories = [];
    let currentParentId = 0; // Will be set by API response or default
    let breadcrumbs = []; // Array of {id, name}

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        const colors = { success: '#10b981', error: '#ef4444', info: '#3b82f6', warning: '#f59e0b' };
        toast.style.cssText = `position:fixed;top:80px;right:24px;z-index:10000;padding:14px 24px;border-radius:10px;color:#fff;font-size:14px;font-weight:500;box-shadow:0 4px 20px rgba(0,0,0,0.15);background:${colors[type] || colors.info};transition:opacity 0.3s;`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
    }

    function loadCategories(parentId = null) {
        const container = document.getElementById('categories-list');
        // Keep UI responsive
        if (!parentId && parentId !== 0) {
            parentId = currentParentId;
        }
        container.innerHTML = `
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px;"></div>
            </div>`;

        const url = parentId ?
            `${PortalConfig.webRoot}/api/v2/index.php?route=categories/list_children&parent=${parentId}` :
            `${PortalConfig.webRoot}/api/v2/index.php?route=categories/list_children`;

        fetch(url)
            .then(res => {
                if (!res.ok) {
                    return res.text().then(text => {
                        throw new Error(`HTTP ${res.status}: ${text.substring(0, 200)}`);
                    });
                }
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    allCategories = data.data || [];
                    if (data.current_parent !== undefined) {
                        currentParentId = data.current_parent;
                    }
                    renderCategories(allCategories);
                    updateBreadcrumbUI();
                } else {
                    container.innerHTML = `<div class="error-message">無法載入類別: ${data.error || '未知錯誤'}</div>`;
                }
            })
            .catch(err => {
                console.error('Load categories error:', err);
                container.innerHTML = `<div class="error-message" style="color:#ef4444; padding:20px;">
                    <i class="fas fa-exclamation-triangle"></i> 載入失敗: ${err.message}<br>
                    <small style="color:#9ca3af;">API: ${url}</small><br>
                    <button class="btn-secondary" onclick="loadCategories()" style="margin-top:10px;">
                        <i class="fas fa-redo"></i> 重試
                    </button>
                </div>`;
            });
    }

    function renderCategories(categories) {
        const container = document.getElementById('categories-list');
        if (!categories || categories.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-folder-open fa-3x"></i>
                    <p>此層級尚無子類別</p>
                    <button class="btn-primary" onclick="openCategoryModal('add')">
                        <i class="fas fa-plus"></i> 建立類別
                    </button>
                </div>`;
            return;
        }

        const isFirstLevel = breadcrumbs.length === 0;

        let html = '<table class="member-table"><thead><tr>';
        html += '<th style="width: 50px;">ID</th><th>類別名稱</th><th style="text-align:center">含有課程數</th>';
        html += isFirstLevel ? '<th style="text-align:center">子類別數</th>' : '<th style="text-align:center">操作</th>';
        html += '</tr></thead><tbody>';

        categories.forEach(c => {
            const lastColumn = isFirstLevel
                ? `<span class="badge" style="background:#3b82f6; color:white;">${c.childcount || 0}</span>`
                : `<div style="display:inline-flex; gap:8px;">
                       <button class="btn-icon" onclick="openSettingsModal(${c.id}, '${escapeHtml(c.name)}')" title="訓練設定" style="background:#10b981; color:white;">
                           <i class="fas fa-cog"></i>
                       </button>
                       <button class="btn-icon btn-edit" onclick="openCategoryModal('edit', ${c.id}, '${escapeHtml(c.name)}')" title="編輯名稱">
                           <i class="fas fa-edit"></i>
                       </button>
                       <button class="btn-icon btn-delete" onclick="deleteCategory(${c.id}, '${escapeHtml(c.name)}')" title="刪除類別">
                           <i class="fas fa-trash"></i>
                       </button>
                   </div>`;

            html += `<tr>
                <td style="color:#6b7280;">${c.id}</td>
                <td>
                    <div style="font-weight:600; color:#1f2937; cursor:pointer; display: flex; align-items: center; gap: 8px;" onclick="enterCategory(${c.id}, '${escapeHtml(c.name)}')" title="點擊進入子目錄">
                        <i class="fas fa-folder" style="color:#f59e0b;"></i>${escapeHtml(c.name)}
                        ${c.is_mandatory ? '<span style="background: linear-gradient(135deg, #f59e0b, #ef4444); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 600;"><i class="fas fa-star" style="margin-right:3px;"></i>必修</span>' : ''}
                    </div>
                </td>
                <td style="text-align:center"><span class="badge" style="background:#3b82f6; color:white;">${c.coursecount || 0}</span></td>
                <td class="action-cell" style="text-align:center">
                    ${lastColumn}
                </td>
            </tr>`;
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function openCategoryModal(mode, id, name) {
        const modal = document.getElementById('category-modal');
        const form = document.getElementById('category-form');
        form.reset();

        document.getElementById('cat-modal-mode').value = (mode === 'add') ? 'create' : 'update';
        document.getElementById('cat-id').value = id || '';
        document.getElementById('cat-parent-id').value = currentParentId;

        const cohortOptions = document.getElementById('cohort-options');

        if (mode === 'add') {
            document.getElementById('cat-modal-title').textContent = '新增類別';
            document.getElementById('cat-name').value = '';
            cohortOptions.style.display = 'block';
        } else {
            document.getElementById('cat-modal-title').textContent = '編輯類別';
            document.getElementById('cat-name').value = name;
            cohortOptions.style.display = 'none'; // 編輯時不顯示群組選項
        }

        modal.style.display = 'flex';
        setTimeout(() => document.getElementById('cat-name').focus(), 100);
    }

    function closeCategoryModal() {
        document.getElementById('category-modal').style.display = 'none';
    }

    function saveCategory(e) {
        e.preventDefault();
        const form = document.getElementById('category-form');
        const formData = new FormData(form);
        const btn = document.getElementById('cat-submit-btn');
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 處理中...';

        const action = formData.get('action') || 'create';
        fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=categories/${action}`, {
            method: 'POST',
            body: formData
        })
            .then(res => {
                if (!res.ok) {
                    return res.text().then(t => { throw new Error(`HTTP ${res.status}: ${t.substring(0,200)}`); });
                }
                return res.json();
            })
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                if (data.success) {
                    closeCategoryModal();
                    loadCategories(currentParentId);
                    showToast(data.message || '操作成功', 'success');
                } else {
                    showToast(data.error || '操作失敗', 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                console.error('Save category error:', err);
                showToast('操作失敗: ' + err.message, 'error');
            });
    }

    function deleteCategory(id, name) {
        // 第一層確認
        if (!confirm(`確定要刪除類別「${name}」嗎？\n\n⚠️ 警告：這將會刪除該類別下所有課程！`)) {
            return;
        }

        // 第二層確認：輸入類別名稱
        const inputName = prompt(`請輸入類別名稱「${name}」以確認刪除：\n\n（此操作無法復原）`);
        if (inputName === null) {
            return; // 使用者取消
        }
        if (inputName !== name) {
            showToast('名稱不符，刪除已取消', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        fetch(PortalConfig.webRoot + '/api/v2/index.php?route=categories/delete', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(res => res.json())
            .then(data => {
                console.log('Delete response:', data);
                if (data.success) {
                    loadCategories(currentParentId);
                    showToast('類別已刪除', 'success');
                } else {
                    showToast(data.error || '刪除失敗', 'error');
                }
            })
            .catch(err => {
                console.error('Delete error:', err);
                showToast('網路錯誤: ' + err.message, 'error');
            });
    }

    // New helper functions
    function enterCategory(id, name) {
        breadcrumbs.push({ id: id, name: name });
        loadCategories(id);
    }

    function navigateBreadcrumb(index) {
        if (index === -1) {
            // Root
            breadcrumbs = [];
            currentParentId = 0; // Reset to root
            loadCategories(null); // Pass null to trigger default root behavior
        } else {
            // Go to specific level
            breadcrumbs = breadcrumbs.slice(0, index + 1);
            const target = breadcrumbs[breadcrumbs.length - 1];
            currentParentId = target.id;
            loadCategories(target.id);
        }
    }

    function updateBreadcrumbUI() {
        const el = document.getElementById('breadcrumb-path');
        const addBtn = document.getElementById('add-category-btn');

        if (breadcrumbs.length === 0) {
            el.innerHTML = '<span style="font-weight:600;">根目錄</span>';
            document.getElementById('add-btn-text').textContent = "新增子類別";
            // 第一層隱藏新增按鈕
            if (addBtn) addBtn.style.display = 'none';
            return;
        }

        // 子層級顯示新增按鈕
        if (addBtn) addBtn.style.display = 'inline-flex';

        let html = '<span class="breadcrumb-item" onclick="navigateBreadcrumb(-1)" style="cursor:pointer; color:#3b82f6;">根目錄</span>';

        breadcrumbs.forEach((b, index) => {
            if (index === breadcrumbs.length - 1) {
                // Current
                html += ` <i class="fas fa-chevron-right" style="font-size:0.8rem; margin:0 5px;"></i> <span style="font-weight:600;">${escapeHtml(b.name)}</span>`;
            } else {
                html += ` <i class="fas fa-chevron-right" style="font-size:0.8rem; margin:0 5px;"></i> <span class="breadcrumb-item" onclick="navigateBreadcrumb(${index})" style="cursor:pointer; color:#3b82f6;">${escapeHtml(b.name)}</span>`;
            }
        });
        el.innerHTML = html;
        document.getElementById('add-btn-text').textContent = "在此建立類別";
    }

    function reloadCurrentLevel() {
        loadCategories(currentParentId);
    }

    // Init on load
    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('section-category-management')) {
            loadCategories();
        }
    });

    // 點擊背景關閉 (除了表單 Modal)
    // 這裡我們沿用 hospital_admin_members 的邏輯：表單 Modal 不給點背景關閉
    // 但因為本頁面沒有獨立的 delete modal (用 confirm)，所以不需要額外處理

    // ========================================
    // 類別設定 Modal 功能
    // ========================================
    let currentSettingsCategoryId = 0;

    function openSettingsModal(categoryId, categoryName) {
        currentSettingsCategoryId = categoryId;
        document.getElementById('settings-modal-title').textContent = `訓練設定：${categoryName}`;
        document.getElementById('settings-modal').style.display = 'flex';

        // 載入現有設定
        loadCategorySettings(categoryId);
    }

    function closeSettingsModal() {
        document.getElementById('settings-modal').style.display = 'none';
    }

    function loadCategorySettings(categoryId) {
        // 重設狀態
        settingsFilterGroupCount = 0;
        foundMandatoryUsers = [];
        const filterContainer = document.getElementById('settings-filter-groups');
        if (filterContainer) filterContainer.innerHTML = '';

        fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=categories/get_settings&category_id=${categoryId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const s = data.settings;
                    const isMandatory = s.is_mandatory_category == 1;
                    document.getElementById('setting-is-mandatory').checked = isMandatory;
                    document.getElementById('setting-required-count').value = s.required_pass_count || 0;
                    document.getElementById('setting-period-months').value = s.period_months || 0;
                    document.getElementById('setting-require-order').checked = s.require_order == 1;

                    // 載入可見性設定
                    const vis = s.visibility || 'all';
                    document.getElementById('visibility-all').checked = (vis === 'all');
                    document.getElementById('visibility-mandatory-only').checked = (vis === 'mandatory_only');
                    updateVisibilityStyle();

                    // 如果已儲存為必修類別，自動展開必修設定區
                    const section = document.getElementById('mandatory-details-section');
                    if (isMandatory) {
                        section.style.display = 'block';
                        loadSettingsDimensions().then(() => {
                            if (settingsFilterGroupCount === 0) addSettingsFilterGroup();
                        });
                    } else {
                        section.style.display = 'none';
                    }
                }
            })
            .catch(err => {
                console.error('Load settings error:', err);
                showToast('載入設定失敗', 'error');
            });
    }

    async function saveCategorySettings(e) {
        e.preventDefault();

        const isMandatory = document.getElementById('setting-is-mandatory').checked;
        const btn = document.getElementById('settings-submit-btn');
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 儲存中...';

        // 檢查固定堂數設定
        const fixedCountEnabled = document.getElementById('setting-fixed-count-enabled')?.checked || false;
        const requiredCount = fixedCountEnabled ? document.getElementById('setting-required-count').value : 0;

        try {
            // 1. 儲存基本設定（必修旗標、堂數、期限、順序）
            const formData = new FormData();
            formData.append('action', 'update_settings');
            formData.append('category_id', currentSettingsCategoryId);
            formData.append('is_mandatory_category', isMandatory ? 1 : 0);
            formData.append('required_pass_count', requiredCount);
            formData.append('period_months', document.getElementById('setting-period-months')?.value || 0);
            formData.append('require_order', document.getElementById('setting-require-order')?.checked ? 1 : 0);
            formData.append('visibility', document.querySelector('input[name="visibility"]:checked')?.value || 'all');

            const settingsRes = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=categories/update_settings', {
                method: 'POST',
                body: formData
            });
            const settingsData = await settingsRes.json();

            if (!settingsData.success) {
                throw new Error(settingsData.error || '儲存設定失敗');
            }

            // 2. 如果勾選必修且有搜尋到人員，儲存使用者需求
            if (isMandatory && foundMandatoryUsers.length > 0) {
                const groups = document.querySelectorAll('.settings-filter-group');
                const filterGroups = [];
                groups.forEach((g, i) => {
                    const idx = i + 1;
                    const cat = document.getElementById(`settings-cat-${idx}`)?.value || '';
                    const loc = document.getElementById(`settings-loc-${idx}`)?.value || '';
                    const attr = document.getElementById(`settings-attr-${idx}`)?.value || '';
                    if (cat || loc || attr) {
                        filterGroups.push({ category: cat, location: loc, attribute: attr });
                    }
                });

                // 收集標籤 IDs
                const saveTagEls = document.querySelectorAll('#settingsSelectedTags .settings-selected-tag');
                const saveTagIds = Array.from(saveTagEls).map(el => el.dataset.tagId);

                const reqRes = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=categories/save_mandatory_requirements', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        category_id: currentSettingsCategoryId,
                        required_pass_count: requiredCount,
                        period_months: document.getElementById('setting-period-months').value,
                        user_ids: foundMandatoryUsers,
                        filter_groups: filterGroups,
                        tag_ids: saveTagIds
                    })
                });
                const reqData = await reqRes.json();

                if (!reqData.success) {
                    throw new Error(reqData.error || '儲存使用者需求失敗');
                }

                showToast(`設定已儲存，已為 ${reqData.inserted_count} 位使用者設定必修需求`, 'success');
            } else if (isMandatory && foundMandatoryUsers.length === 0) {
                // 必修但沒選人 → 擋住不能儲存
                showToast('請先篩選並搜尋必修對象', 'warning');
                btn.disabled = false;
                btn.innerHTML = originalText;
                return;
            } else {
                showToast('設定已儲存', 'success');
            }

            closeSettingsModal();
            reloadCurrentLevel(); // 重新載入列表以更新必修標記

        } catch (err) {
            console.error('Save error:', err);
            showToast(err.message || '儲存失敗', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
</script>

<!-- 類別設定 Modal -->
<div id="settings-modal" class="modal-overlay" style="display: none; z-index: 9999;">
    <div class="modal-content"
        style="max-width: 800px; padding: 24px; border-radius: 12px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="border-bottom: 1px solid #f0f0f0; padding-bottom: 16px; margin-bottom: 20px;">
            <h3 id="settings-modal-title" style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;">
                <i class="fas fa-cog" style="color:#10b981; margin-right:8px;"></i>類別設定
            </h3>
            <button class="modal-close" onclick="closeSettingsModal()">&times;</button>
        </div>
        <form id="settings-form" onsubmit="saveCategorySettings(event)">
            <!-- 是否必修 -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label
                    style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 14px 16px; background: #f0fdf4; border-radius: 10px; border: 1px solid #bbf7d0;">
                    <input type="checkbox" id="setting-is-mandatory"
                        style="width: 20px; height: 20px; accent-color: #10b981;" onchange="toggleMandatorySection()">
                    <div>
                        <span style="color: #166534; font-weight: 600; display: block;">
                            <i class="fas fa-star" style="margin-right: 6px;"></i>設為必修類別
                        </span>
                        <span style="color: #4ade80; font-size: 0.85rem;">指定人員必須完成此類別課程</span>
                    </div>
                </label>
            </div>

            <!-- 必修詳細設定（勾選必修後顯示） -->
            <div id="mandatory-details-section"
                style="display: none; background: #f8fafc; border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 1px dashed #cbd5e1;">

                <!-- 可見性設定 -->
                <div style="margin-bottom: 20px;">
                    <label
                        style="display:block; margin-bottom:10px; font-weight:600; color:#1f2937; font-size: 0.95rem;">
                        <i class="fas fa-eye" style="margin-right:6px; color:#6366f1;"></i>課程可見性
                    </label>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label
                            style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px 14px; background: white; border-radius: 8px; border: 1px solid #e5e7eb; transition: all 0.2s;"
                            onclick="this.querySelector('input').checked=true; updateVisibilityStyle();">
                            <input type="radio" name="visibility" id="visibility-all" value="all" checked
                                style="width: 18px; height: 18px; accent-color: #6366f1;">
                            <div>
                                <span style="font-weight: 500; color: #374151; display: block;">🔓 所有人可見</span>
                                <span style="color: #9ca3af; font-size: 0.8rem;">所有人都能看到此類別，必修對象會顯示「必修」標記</span>
                            </div>
                        </label>
                        <label
                            style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px 14px; background: white; border-radius: 8px; border: 1px solid #e5e7eb; transition: all 0.2s;"
                            onclick="this.querySelector('input').checked=true; updateVisibilityStyle();">
                            <input type="radio" name="visibility" id="visibility-mandatory-only" value="mandatory_only"
                                style="width: 18px; height: 18px; accent-color: #6366f1;">
                            <div>
                                <span style="font-weight: 500; color: #374151; display: block;">🔒 僅必修對象可見</span>
                                <span style="color: #9ca3af; font-size: 0.8rem;">只有被指定的必修人員才能看到此類別</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- 需通過堂數 -->
                <div style="margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 8px;">
                        <input type="checkbox" id="setting-fixed-count-enabled"
                            style="width: 18px; height: 18px; accent-color: #10b981;" onchange="toggleFixedCount()">
                        <span style="font-weight:500; color:#4b5563; font-size: 0.9rem;">
                            <i class="fas fa-check-circle" style="margin-right:4px; color:#10b981;"></i>指定固定通過堂數
                        </span>
                    </label>
                    <div id="fixed-count-input" style="display: none; margin-left: 28px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="number" id="setting-required-count" min="1" max="99" value="1"
                                style="width: 70px; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; text-align: center;">
                            <span style="color:#6b7280; font-size: 0.9rem;">堂</span>
                        </div>
                    </div>
                    <p id="fixed-count-hint" style="color:#9ca3af; font-size:0.8rem; margin: 6px 0 0 28px;">
                        目前：有幾堂課就顯示幾個燈
                    </p>
                </div>

                <!-- 期限 -->
                <div style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom:8px; font-weight:500; color:#4b5563; font-size: 0.9rem;">
                        <i class="fas fa-calendar-alt" style="margin-right:4px; color:#f59e0b;"></i>完成期限
                    </label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="number" id="setting-period-months" min="0" max="120" value="12"
                            style="width: 70px; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; text-align: center;">
                        <span style="color:#6b7280; font-size: 0.9rem;">個月 (0=無期限)</span>
                    </div>
                </div>

                <!-- 篩選條件區 -->
                <div style="margin-bottom: 16px;">
                    <label style="display:block; margin-bottom:12px; font-weight:600; color:#1f2937;">
                        <i class="fas fa-filter" style="margin-right:6px; color:#8b5cf6;"></i>必修對象篩選
                    </label>

                    <div id="settings-filter-groups" style="margin-bottom: 12px;">
                        <!-- 條件組動態生成 -->
                    </div>

                    <button type="button" onclick="addSettingsFilterGroup()" title="點擊 OR/AND 可切換邏輯"
                        style="width: 100%; padding: 10px; border: 2px dashed #cbd5e1; border-radius: 8px; background: white; color: #64748b; cursor: pointer; font-size: 0.9rem;">
                        <i class="fas fa-plus"></i> 新增篩選條件組
                    </button>
                </div>

                <!-- 標籤篩選區 -->
                <div
                    style="background: linear-gradient(135deg, rgba(236, 72, 153, 0.05), rgba(244, 63, 94, 0.05)); border: 1px solid rgba(236, 72, 153, 0.2); border-radius: 12px; padding: 14px 18px; margin: 12px 0;">
                    <label style="display:block; margin-bottom:8px; font-weight:500; color:#1f2937; font-size:0.9rem;">
                        <i class="fas fa-tags" style="color:#ec4899; margin-right:6px;"></i>
                        標籤篩選 <small style="color:#94a3b8;font-weight:normal;">(選填)</small>
                    </label>
                    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:6px;">
                        <span id="settingsSelectedTags"
                            style="display:inline-flex; flex-wrap:wrap; gap:6px; align-items:center;"></span>
                        <button type="button" onclick="openSettingsTagSelector()"
                            style="display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border-radius:20px; border:1px dashed #cbd5e1; background:white; color:#64748b; font-size:0.85rem; cursor:pointer; transition:all 0.2s;"
                            onmouseover="this.style.borderColor='#ec4899'; this.style.color='#ec4899';"
                            onmouseout="this.style.borderColor='#cbd5e1'; this.style.color='#64748b';">
                            <i class="fas fa-plus"></i> 新增標籤篩選
                        </button>
                    </div>
                </div>

                <!-- 搜尋按鈕 -->
                <div style="display: flex; gap: 10px; margin-bottom: 12px;">
                    <button type="button" onclick="searchMandatoryUsers()"
                        style="flex:1; padding: 10px 16px; border-radius: 8px; background: #3b82f6; color: white; border: none; cursor: pointer; font-weight: 500;">
                        <i class="fas fa-search"></i> 搜尋符合人員
                    </button>
                    <button type="button" onclick="resetSettingsFilters()"
                        style="padding: 10px 16px; border-radius: 8px; background: #f3f4f6; color: #374151; border: none; cursor: pointer;">
                        <i class="fas fa-redo"></i> 重設
                    </button>
                </div>

                <!-- 搜尋結果 -->
                <div id="mandatory-users-preview"
                    style="background: white; border-radius: 8px; padding: 12px; border: 1px solid #e5e7eb;">
                    <div style="text-align: center; color: #9ca3af; padding: 20px;">
                        <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 8px; opacity: 0.5;"></i>
                        <p style="margin: 0;">請選擇篩選條件後點擊「搜尋」</p>
                    </div>
                </div>
            </div>

            <!-- 順序要求 -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label
                    style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 14px 16px; background: #fefce8; border-radius: 10px; border: 1px solid #fef08a;">
                    <input type="checkbox" id="setting-require-order"
                        style="width: 20px; height: 20px; accent-color: #eab308;">
                    <div>
                        <span style="color: #854d0e; font-weight: 600; display: block;">
                            <i class="fas fa-sort-numeric-down" style="margin-right: 6px;"></i>要求按順序完成
                        </span>
                        <span style="color: #ca8a04; font-size: 0.85rem;">啟用後需標記課程順序</span>
                    </div>
                </label>
            </div>

            <div class="form-actions"
                style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 10px; border-top: 1px solid #f0f0f0;">
                <button type="button" class="btn-secondary" onclick="closeSettingsModal()"
                    style="padding: 10px 18px; border-radius: 8px; background: #f3f4f6; color: #374151; border: none; cursor: pointer; font-weight: 500;">
                    取消
                </button>
                <button type="button" class="btn-primary" id="settings-submit-btn" onclick="saveCategorySettings(event)"
                    style="padding: 10px 18px; border-radius: 8px; background: #10b981; color: white; border: none; cursor: pointer; font-weight: 500;">
                    <i class="fas fa-save" style="margin-right:6px;"></i>儲存設定
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // ========================================
    // 類別設定篩選器功能
    // ========================================
    let settingsFilterGroupCount = 0;
    let settingsCategoryOptions = [];
    let settingsLocationOptions = [];
    let settingsAttributeOptions = [];
    let foundMandatoryUsers = [];
    let settingsCachedTags = null;

    // ========================================
    // 標籤篩選器功能
    // ========================================
    async function openSettingsTagSelector() {
        // 載入標籤（有快取）
        if (!settingsCachedTags) {
            try {
                const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=tags/course/available');
                const data = await res.json();
                if (data.success) {
                    settingsCachedTags = data.data || [];
                } else {
                    showToast('載入標籤失敗', 'error');
                    return;
                }
            } catch (e) {
                showToast('載入標籤失敗', 'error');
                return;
            }
        }

        if (settingsCachedTags.length === 0) {
            showToast('尚未建立任何標籤', 'warning');
            return;
        }

        // 取得已選標籤
        const selectedContainer = document.getElementById('settingsSelectedTags');
        const selectedIds = Array.from(selectedContainer.querySelectorAll('.settings-selected-tag'))
            .map(el => el.dataset.tagId);

        // 建立彈窗
        const modal = document.createElement('div');
        modal.id = 'settingsTagSelectorModal';
        modal.style.cssText = 'position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:10001; display:flex; align-items:center; justify-content:center;';
        modal.innerHTML = `
        <div style="background:white; border-radius:12px; padding:24px; max-width:500px; width:90%; max-height:70vh; overflow-y:auto;">
            <h3 style="margin:0 0 16px; font-size:1rem; color:#1f2937;"><i class="fas fa-tags" style="color:#ec4899; margin-right:6px;"></i> 選擇標籤篩選</h3>
            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:20px;">
                ${settingsCachedTags.map(t => `
                    <button type="button" class="stag-btn ${selectedIds.includes(String(t.id)) ? 'stag-active' : ''}" 
                            data-tag-id="${t.id}" data-tag-name="${t.name}" data-tag-color="${t.color || '#3b82f6'}"
                            onclick="this.classList.toggle('stag-active')"
                            style="padding:8px 14px; border-radius:20px; font-size:0.85rem; cursor:pointer; transition:all 0.2s;
                                   border:1px solid ${t.color || '#3b82f6'}; 
                                   background:${selectedIds.includes(String(t.id)) ? (t.color || '#3b82f6') : 'white'};
                                   color:${selectedIds.includes(String(t.id)) ? 'white' : (t.color || '#3b82f6')};">
                        ${t.name}
                    </button>
                `).join('')}
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" onclick="closeSettingsTagSelector()" 
                    style="padding:8px 16px; border-radius:8px; background:#f3f4f6; color:#374151; border:none; cursor:pointer;">取消</button>
                <button type="button" onclick="confirmSettingsTagSelection()" 
                    style="padding:8px 16px; border-radius:8px; background:#ec4899; color:white; border:none; cursor:pointer; font-weight:500;">確認</button>
            </div>
        </div>
    `;
        document.body.appendChild(modal);

        // 加入動態切換樣式
        modal.addEventListener('click', function (e) {
            const btn = e.target.closest('.stag-btn');
            if (btn) {
                const color = btn.dataset.tagColor;
                if (btn.classList.contains('stag-active')) {
                    btn.style.background = color;
                    btn.style.color = 'white';
                } else {
                    btn.style.background = 'white';
                    btn.style.color = color;
                }
            }
            // 點擊背景關閉
            if (e.target === modal) closeSettingsTagSelector();
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

        // 清空並重建已選標籤
        container.innerHTML = '';
        selectedBtns.forEach(btn => {
            const tagId = btn.dataset.tagId;
            const tagName = btn.dataset.tagName;
            const tagColor = btn.dataset.tagColor;

            const tagEl = document.createElement('span');
            tagEl.className = 'settings-selected-tag';
            tagEl.dataset.tagId = tagId;
            tagEl.style.cssText = `display:inline-flex; align-items:center; gap:4px; padding:5px 10px; border-radius:20px; font-size:0.8rem; font-weight:500; background:${tagColor}20; color:${tagColor}; border:1px solid ${tagColor}40;`;
            tagEl.innerHTML = `
            ${tagName}
            <span onclick="this.closest('.settings-selected-tag').remove()" style="cursor:pointer; margin-left:2px; opacity:0.7;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'"><i class="fas fa-times" style="font-size:0.7rem;"></i></span>
        `;
            container.appendChild(tagEl);
        });

        closeSettingsTagSelector();
    }

    function toggleMandatorySection() {
        const isChecked = document.getElementById('setting-is-mandatory').checked;
        const section = document.getElementById('mandatory-details-section');

        if (!isChecked) {
            // 取消勾選 → 隱藏必修設定區
            section.style.display = 'none';
        } else {
            // 勾選必修 → 顯示提示，請先儲存再設定對象
            section.style.display = 'block';

            // 載入維度選項並新增第一個條件組
            if (settingsFilterGroupCount === 0) {
                loadSettingsDimensions().then(() => {
                    addSettingsFilterGroup();
                });
            }
        }
    }

    function toggleFixedCount() {
        const isChecked = document.getElementById('setting-fixed-count-enabled').checked;
        const inputDiv = document.getElementById('fixed-count-input');
        const hintP = document.getElementById('fixed-count-hint');

        inputDiv.style.display = isChecked ? 'block' : 'none';
        hintP.textContent = isChecked ? '將顯示固定燈數' : '目前：有幾堂課就顯示幾個燈';
    }

    function updateVisibilityStyle() {
        const allRadio = document.getElementById('visibility-all');
        const mandatoryRadio = document.getElementById('visibility-mandatory-only');
        const allLabel = allRadio.closest('label');
        const mandatoryLabel = mandatoryRadio.closest('label');

        if (allRadio.checked) {
            allLabel.style.borderColor = '#6366f1';
            allLabel.style.background = '#eef2ff';
            mandatoryLabel.style.borderColor = '#e5e7eb';
            mandatoryLabel.style.background = 'white';
        } else {
            mandatoryLabel.style.borderColor = '#6366f1';
            mandatoryLabel.style.background = '#eef2ff';
            allLabel.style.borderColor = '#e5e7eb';
            allLabel.style.background = 'white';
        }
    }

    async function loadSettingsDimensions() {
        try {
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/get_grouped');
            const data = await res.json();

            if (data.success && data.data) {
                // data.data 是維度陣列，每個維度有 name 和 cohorts
                data.data.forEach(dim => {
                    const cohorts = dim.cohorts || [];
                    const options = cohorts.map(c => ({ id: c.cohort_id, name: c.full_path || c.display_name }));

                    if (dim.name === '職類') {
                        settingsCategoryOptions = options;
                    } else if (dim.name === '所屬') {
                        settingsLocationOptions = options;
                    } else if (dim.name === '屬性') {
                        settingsAttributeOptions = options;
                    }
                });
            }
        } catch (err) {
            console.error('Load dimensions error:', err);
        }
    }

    function addSettingsFilterGroup() {
        settingsFilterGroupCount++;
        const container = document.getElementById('settings-filter-groups');
        const groupId = settingsFilterGroupCount;

        // 運算符分隔線（可點擊切換 AND/OR）
        if (container.children.length > 0) {
            const divider = document.createElement('div');
            divider.id = `settings-op-${groupId}`;
            divider.dataset.operator = 'or';
            divider.style.cssText = 'text-align:center; padding:8px 0;';
            divider.innerHTML = `<span onclick="toggleSettingsOperator(${groupId})" style="background:linear-gradient(135deg,#f59e0b,#d97706); color:white; padding:4px 14px; border-radius:20px; font-size:0.75rem; font-weight:600; cursor:pointer; transition:all 0.2s;" title="點擊切換 AND/OR">OR</span>`;
            container.appendChild(divider);
        }

        const groupHtml = `
        <div class="settings-filter-group" id="settings-group-${groupId}" style="background:white; border-radius:8px; padding:12px; border:1px solid #e5e7eb; margin-bottom:8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <span style="font-size:0.85rem; color:#6b7280;">條件組 ${groupId}</span>
                ${groupId > 1 ? `<button type="button" onclick="removeSettingsFilterGroup(${groupId})" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:0.85rem;"><i class="fas fa-times"></i></button>` : ''}
            </div>
            <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:10px;">
                <div>
                    <label style="font-size:0.75rem; color:#9ca3af; display:block; margin-bottom:4px;">
                        <i class="fas fa-sitemap" style="color:#8b5cf6;"></i> 職類
                    </label>
                    <select id="settings-cat-${groupId}" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; font-size:0.85rem;">
                        <option value="">全部</option>
                        ${settingsCategoryOptions.map(o => `<option value="${o.id}">${o.name}</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label style="font-size:0.75rem; color:#9ca3af; display:block; margin-bottom:4px;">
                        <i class="fas fa-map-marker" style="color:#3b82f6;"></i> 所屬
                    </label>
                    <select id="settings-loc-${groupId}" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; font-size:0.85rem;">
                        <option value="">全部</option>
                        ${settingsLocationOptions.map(o => `<option value="${o.id}">${o.name}</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label style="font-size:0.75rem; color:#9ca3af; display:block; margin-bottom:4px;">
                        <i class="fas fa-tag" style="color:#f59e0b;"></i> 屬性
                    </label>
                    <select id="settings-attr-${groupId}" style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; font-size:0.85rem;">
                        <option value="">全部</option>
                        ${settingsAttributeOptions.map(o => `<option value="${o.id}">${o.name}</option>`).join('')}
                    </select>
                </div>
            </div>
        </div>
    `;

        container.insertAdjacentHTML('beforeend', groupHtml);
    }

    function removeSettingsFilterGroup(groupId) {
        document.getElementById(`settings-group-${groupId}`)?.remove();
        document.getElementById(`settings-op-${groupId}`)?.remove();
    }

    function toggleSettingsOperator(groupId) {
        const divider = document.getElementById(`settings-op-${groupId}`);
        if (!divider) return;
        const current = divider.dataset.operator;
        const newOp = current === 'or' ? 'and' : 'or';
        divider.dataset.operator = newOp;
        const btn = divider.querySelector('span');
        btn.textContent = newOp.toUpperCase();
        btn.style.background = newOp === 'and' ? 'linear-gradient(135deg,#8b5cf6,#7c3aed)' : 'linear-gradient(135deg,#f59e0b,#d97706)';
    }

    function resetSettingsFilters() {
        const container = document.getElementById('settings-filter-groups');
        container.innerHTML = '';
        settingsFilterGroupCount = 0;
        addSettingsFilterGroup();

        // 清空已選標籤
        const tagsContainer = document.getElementById('settingsSelectedTags');
        if (tagsContainer) tagsContainer.innerHTML = '';

        document.getElementById('mandatory-users-preview').innerHTML = `
        <div style="text-align: center; color: #9ca3af; padding: 20px;">
            <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 8px; opacity: 0.5;"></i>
            <p style="margin: 0;">請選擇篩選條件後點擊「搜尋」</p>
        </div>
    `;
        foundMandatoryUsers = [];
    }

    async function searchMandatoryUsers() {
        const preview = document.getElementById('mandatory-users-preview');
        preview.innerHTML = '<div style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> 搜尋中...</div>';

        // 收集篩選條件
        const groups = document.querySelectorAll('.settings-filter-group');
        const filterGroups = [];
        const operators = [];

        groups.forEach((g, i) => {
            const idx = i + 1;
            const cat = document.getElementById(`settings-cat-${idx}`)?.value || '';
            const loc = document.getElementById(`settings-loc-${idx}`)?.value || '';
            const attr = document.getElementById(`settings-attr-${idx}`)?.value || '';
            if (cat || loc || attr) {
                filterGroups.push({ category: cat, location: loc, attribute: attr });
                // 收集運算符（第一組沒有）
                if (i > 0) {
                    const opDiv = document.getElementById(`settings-op-${idx}`);
                    operators.push(opDiv?.dataset?.operator || 'or');
                }
            }
        });

        // 收集標籤 IDs
        const tagEls = document.querySelectorAll('#settingsSelectedTags .settings-selected-tag');
        const tagIds = Array.from(tagEls).map(el => el.dataset.tagId);

        if (filterGroups.length === 0 && tagIds.length === 0) {
            preview.innerHTML = '<div style="text-align:center; color:#f59e0b; padding:20px;"><i class="fas fa-exclamation-triangle"></i> 請至少選擇一個篩選條件或標籤</div>';
            return;
        }

        try {
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=categories/search_users_by_filter', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ filter_groups: filterGroups, operators: operators, tag_ids: tagIds })
            });
            const data = await res.json();

            if (data.success) {
                foundMandatoryUsers = data.users || [];
                const count = foundMandatoryUsers.length;

                if (count === 0) {
                    preview.innerHTML = '<div style="text-align:center; color:#9ca3af; padding:20px;"><i class="fas fa-user-slash"></i> 沒有符合條件的人員</div>';
                } else {
                    preview.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span style="font-weight:600; color:#1f2937;"><i class="fas fa-users" style="color:#10b981; margin-right:6px;"></i>找到 ${count} 人</span>
                    </div>
                    <div style="max-height:150px; overflow-y:auto; font-size:0.85rem; color:#4b5563;">
                        ${foundMandatoryUsers.slice(0, 20).map(u => `<span style="display:inline-block; background:#f3f4f6; padding:4px 8px; border-radius:4px; margin:2px;">${u.fullname || u.username}</span>`).join('')}
                        ${count > 20 ? `<span style="color:#9ca3af;">...還有 ${count - 20} 人</span>` : ''}
                    </div>
                `;
                }
            } else {
                preview.innerHTML = `<div style="text-align:center; color:#ef4444; padding:20px;"><i class="fas fa-times-circle"></i> ${data.error || '搜尋失敗'}</div>`;
            }
        } catch (err) {
            console.error('Search users error:', err);
            preview.innerHTML = '<div style="text-align:center; color:#ef4444; padding:20px;"><i class="fas fa-times-circle"></i> 網路錯誤</div>';
        }
    }
</script>