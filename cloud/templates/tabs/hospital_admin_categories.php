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
    </div>

    <!-- 工具列 -->
    <div class="toolbar-container">
        <div class="search-bar-container">
            <!-- 類別通常不多，暫不需要搜尋，或者之後做前端 filter -->
            <button class="btn-secondary" onclick="loadCategories()">
                <i class="fas fa-sync-alt"></i> 重新載入
            </button>
        </div>
        <button class="btn-primary" onclick="openCategoryModal('add')">
            <i class="fas fa-plus"></i> 新增子類別
        </button>
        <!-- 說明文字 -->
        <span style="margin-left:auto; color:#6b7280; font-size:0.9rem;">
            <i class="fas fa-info-circle"></i> 僅顯示第一層子類別
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
            <input type="hidden" id="cat-modal-mode" name="action" value="create">

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="cat-name" style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">類別名稱
                    <span style="color:#ef4444">*</span></label>
                <input type="text" id="cat-name" name="name" required placeholder="例如：護理部、醫師培訓"
                    style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
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

    function loadCategories() {
        const container = document.getElementById('categories-list');
        container.innerHTML = `
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px;"></div>
            </div>`;

        fetch('/api/hospital_admin/manage_category.php?action=list')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    allCategories = data.data || [];
                    renderCategories(allCategories);
                } else {
                    container.innerHTML = `<div class="error-message">無法載入類別: ${data.error}</div>`;
                }
            })
            .catch(err => {
                console.error('Load categories error:', err);
                container.innerHTML = `<div class="error-message">網路錯誤，請稍後再試</div>`;
            });
    }

    function renderCategories(categories) {
        const container = document.getElementById('categories-list');
        if (!categories || categories.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-folder-open fa-3x"></i>
                    <p>尚無子類別</p>
                    <button class="btn-primary" onclick="openCategoryModal('add')">
                        <i class="fas fa-plus"></i> 建立第一個類別
                    </button>
                </div>`;
            return;
        }

        let html = '<table class="member-table"><thead><tr>';
        html += '<th style="width: 50px;">ID</th><th>類別名稱</th><th>含有課程數</th><th style="text-align:center">操作</th>';
        html += '</tr></thead><tbody>';

        categories.forEach(c => {
            html += `<tr>
                <td style="color:#6b7280;">${c.id}</td>
                <td>
                    <div style="font-weight:600; color:#1f2937;">
                        <i class="fas fa-folder text-yellow-500" style="color:#f59e0b; margin-right:8px;"></i>${escapeHtml(c.name)}
                    </div>
                </td>
                <td><span class="badge bg-gray">${c.coursecount || 0}</span></td>
                <td class="action-cell" style="text-align:center">
                    <button class="btn-icon btn-edit" onclick="openCategoryModal('edit', ${c.id}, '${escapeHtml(c.name)}')" title="編輯名稱">
                        <i class="fas fa-edit"></i>
                    </button>
                    <!-- 未來可做進入子類別功能 -->
                    <button class="btn-icon btn-delete" onclick="deleteCategory(${c.id}, '${escapeHtml(c.name)}')" title="刪除類別">
                        <i class="fas fa-trash"></i>
                    </button>
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

        if (mode === 'add') {
            document.getElementById('cat-modal-title').textContent = '新增類別';
            document.getElementById('cat-name').value = '';
        } else {
            document.getElementById('cat-modal-title').textContent = '編輯類別';
            document.getElementById('cat-name').value = name;
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

        fetch('/api/hospital_admin/manage_category.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                if (data.success) {
                    closeCategoryModal();
                    loadCategories();
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

    function deleteCategory(id, name) {
        if (!confirm(`確定要刪除類別「${name}」嗎？\n\n警告：這將會刪除該類別下所有課程！`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        fetch('/api/hospital_admin/manage_category.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadCategories();
                    showToast('類別已刪除', 'success');
                } else {
                    showToast(data.error || '刪除失敗', 'error');
                }
            })
            .catch(err => showToast('網路錯誤', 'error'));
    }

    // 點擊背景關閉 (除了表單 Modal)
    // 這裡我們沿用 hospital_admin_members 的邏輯：表單 Modal 不給點背景關閉
    // 但因為本頁面沒有獨立的 delete modal (用 confirm)，所以不需要額外處理
</script>