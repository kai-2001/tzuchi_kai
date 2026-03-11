<?php
/**
 * 院區管理員 - 標籤管理介面
 * templates/tabs/hospital_admin_tags.php
 * 
 * 使用新的 portal_tags 表，顯示系統模板和院區專屬標籤
 */
$institution = $_SESSION['institution'] ?? '';
?>
<div id="section-tag-management" class="page-section">
    <div class="section-header">
        <h2><i class="fas fa-tags"></i> 標籤管理</h2>
        <p class="section-subtitle">管理人員分類標籤（系統模板為全系統共用，院區標籤僅限本院區）</p>
    </div>

    <!-- 工具列 -->
    <div class="toolbar-container">
        <div class="search-bar-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="tag-search-input" placeholder="搜尋標籤名稱..." class="search-input"
                onkeyup="filterTags()">
        </div>

        <div style="margin-left: auto; display:flex; gap:10px;">
            <button class="btn-secondary" onclick="loadTags()">
                <i class="fas fa-sync-alt"></i> 重新載入
            </button>
            <button class="btn-primary" onclick="openAddTagModal()">
                <i class="fas fa-plus"></i> 新增院區標籤
            </button>
        </div>
    </div>

    <!-- 系統模板標籤區 -->
    <div
        style="background: white; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden;">
        <div style="padding: 18px 24px; border-bottom: 1px solid #e5e7eb; background: #fafafa;">
            <h4
                style="margin: 0; font-size: 1rem; color: #374151; display: flex; align-items: center; gap: 10px; font-weight: 600;">
                <i class="fas fa-bookmark" style="color: #8b5cf6;"></i>
                系統模板
                <span style="font-weight: 400; font-size: 0.85rem; color: #6b7280;">（所有院區共用，由系統管理員設定）</span>
            </h4>
        </div>
        <div id="template-tags-container"
            style="display: flex; flex-wrap: wrap; gap: 12px; padding: 24px; min-height: 60px;">
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 40px; width: 200px;"></div>
            </div>
        </div>
    </div>

    <!-- 院區專屬標籤區 -->
    <div style="background: white; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden;">
        <div style="padding: 18px 24px; border-bottom: 1px solid #e5e7eb; background: #fafafa;">
            <h4
                style="margin: 0; font-size: 1rem; color: #374151; display: flex; align-items: center; gap: 10px; font-weight: 600;">
                <i class="fas fa-hospital" style="color: #3b82f6;"></i>
                本院區專屬標籤
            </h4>
        </div>
        <div id="custom-tags-container"
            style="display: flex; flex-wrap: wrap; gap: 12px; padding: 24px; min-height: 60px;">
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 40px; width: 200px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- 新增標籤 Modal -->
<div id="add-tag-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 420px; padding: 24px;">
        <div class="modal-header">
            <h3>新增院區標籤</h3>
            <button class="modal-close" onclick="closeAddTagModal()">&times;</button>
        </div>
        <form id="add-tag-form" onsubmit="saveTag(event)">
            <div class="form-group">
                <label>標籤名稱 <span style="color:red;">*</span></label>
                <input type="text" name="name" id="new-tag-name" required placeholder="例如：2026新進、急救組">
            </div>
            <div class="form-group">
                <label>標籤顏色</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="color" id="new-tag-color" value="#3b82f6"
                        style="width: 50px; height: 40px; border: none; border-radius: 8px; cursor: pointer;">
                    <span id="color-preview-tag"
                        style="padding: 6px 14px; background: #3b82f615; color: #3b82f6; border: 1px dashed #3b82f640; border-radius: 20px; font-size: 0.85rem;">
                        <i class="fas fa-tag"></i> 預覽
                    </span>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeAddTagModal()">取消</button>
                <button type="submit" class="btn-primary" id="tag-submit-btn">儲存標籤</button>
            </div>
        </form>
    </div>
</div>

<script>
    const INSTITUTION = '<?php echo addslashes($institution); ?>';
    const TAG_API = PortalConfig.webRoot + '/api/v2/index.php?route=';
    let allTemplateTags = [];
    let allCustomTags = [];

    // 初始化
    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('section-tag-management')) {
            loadTags();

            // 顏色預覽
            document.getElementById('new-tag-color')?.addEventListener('input', function () {
                const c = this.value;
                const p = document.getElementById('color-preview-tag');
                if (p) {
                    p.style.background = c + '15';
                    p.style.color = c;
                    p.style.borderColor = c + '40';
                }
            });
        }
    });

    async function loadTags() {
        const templateDiv = document.getElementById('template-tags-container');
        const customDiv = document.getElementById('custom-tags-container');
        templateDiv.innerHTML = '<div class="loading-skeleton"><div class="skeleton-pulse" style="height: 40px; width: 200px;"></div></div>';
        customDiv.innerHTML = '<div class="loading-skeleton"><div class="skeleton-pulse" style="height: 40px; width: 200px;"></div></div>';

        try {
            const resp = await fetch(TAG_API + 'tags&institution=' + encodeURIComponent(INSTITUTION));
            const data = await resp.json();
            if (data.success) {
                allTemplateTags = data.data.templates || [];
                allCustomTags = data.data.custom || [];
                renderTags();
            } else {
                templateDiv.innerHTML = `<div style="color:#94a3b8;">載入失敗</div>`;
                customDiv.innerHTML = `<div style="color:#94a3b8;">載入失敗</div>`;
            }
        } catch (e) {
            templateDiv.innerHTML = `<div style="color:#ef4444;">載入錯誤</div>`;
            customDiv.innerHTML = `<div style="color:#ef4444;">載入錯誤</div>`;
        }
    }

    function renderTags() {
        const templateDiv = document.getElementById('template-tags-container');
        const customDiv = document.getElementById('custom-tags-container');

        // 渲染系統模板（有鎖頭、不可刪除）
        if (allTemplateTags.length === 0) {
            templateDiv.innerHTML = '<span style="color: #94a3b8; font-size: 0.9rem;">尚無系統模板標籤</span>';
        } else {
            templateDiv.innerHTML = allTemplateTags.map(t => {
                const c = t.color || '#6b7280';
                return `
            <span title="系統模板（由系統管理員設定，無法刪除）" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: ${c}12; border: 2px dashed ${c}40; border-radius: 24px; font-size: 0.9rem; color: ${c}; font-weight: 500;">
                <i class="fas fa-lock" style="font-size: 0.75rem; opacity: 0.6;"></i>
                <span style="width: 10px; height: 10px; background: ${c}; border-radius: 50%;"></span>
                ${escapeHtml(t.name)}
            </span>`;
            }).join('');
        }

        // 渲染院區專屬（可刪除）
        if (allCustomTags.length === 0) {
            customDiv.innerHTML = '<span style="color: #94a3b8; font-size: 0.9rem;">尚無院區專屬標籤，點擊右上方新增</span>';
        } else {
            customDiv.innerHTML = allCustomTags.map(t => {
                const c = t.color || '#3b82f6';
                return `
            <span style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: ${c}15; border: 1px solid ${c}30; border-radius: 24px; font-size: 0.9rem; color: ${c}; font-weight: 500;">
                <span style="width: 10px; height: 10px; background: ${c}; border-radius: 50%;"></span>
                ${escapeHtml(t.name)}
                <button onclick="deleteTag(${t.id}, '${escapeHtml(t.name)}')" style="background: none; border: none; color: ${c}; cursor: pointer; padding: 0; margin-left: 4px; opacity: 0.6;" title="刪除">
                    <i class="fas fa-times"></i>
                </button>
            </span>`;
            }).join('');
        }
    }

    function filterTags() {
        const term = document.getElementById('tag-search-input').value.toLowerCase();

        // 篩選模板
        const filteredTemplates = allTemplateTags.filter(t => t.name.toLowerCase().includes(term));
        const filteredCustom = allCustomTags.filter(t => t.name.toLowerCase().includes(term));

        const templateDiv = document.getElementById('template-tags-container');
        const customDiv = document.getElementById('custom-tags-container');

        // 重新渲染篩選結果
        if (filteredTemplates.length === 0) {
            templateDiv.innerHTML = '<span style="color: #94a3b8; font-size: 0.9rem;">無符合的系統模板</span>';
        } else {
            templateDiv.innerHTML = filteredTemplates.map(t => {
                const c = t.color || '#6b7280';
                return `
            <span title="系統模板" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: ${c}12; border: 2px dashed ${c}40; border-radius: 24px; font-size: 0.9rem; color: ${c}; font-weight: 500;">
                <i class="fas fa-lock" style="font-size: 0.75rem; opacity: 0.6;"></i>
                <span style="width: 10px; height: 10px; background: ${c}; border-radius: 50%;"></span>
                ${escapeHtml(t.name)}
            </span>`;
            }).join('');
        }

        if (filteredCustom.length === 0) {
            customDiv.innerHTML = '<span style="color: #94a3b8; font-size: 0.9rem;">無符合的院區標籤</span>';
        } else {
            customDiv.innerHTML = filteredCustom.map(t => {
                const c = t.color || '#3b82f6';
                return `
            <span style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: ${c}15; border: 1px solid ${c}30; border-radius: 24px; font-size: 0.9rem; color: ${c}; font-weight: 500;">
                <span style="width: 10px; height: 10px; background: ${c}; border-radius: 50%;"></span>
                ${escapeHtml(t.name)}
                <button onclick="deleteTag(${t.id}, '${escapeHtml(t.name)}')" style="background: none; border: none; color: ${c}; cursor: pointer; padding: 0; margin-left: 4px; opacity: 0.6;" title="刪除">
                    <i class="fas fa-times"></i>
                </button>
            </span>`;
            }).join('');
        }
    }

    function openAddTagModal() {
        document.getElementById('add-tag-modal').style.display = 'flex';
        document.getElementById('new-tag-name').focus();
    }

    function closeAddTagModal() {
        document.getElementById('add-tag-modal').style.display = 'none';
        document.getElementById('add-tag-form').reset();
        // 重設預覽
        const p = document.getElementById('color-preview-tag');
        if (p) {
            p.style.background = '#3b82f615';
            p.style.color = '#3b82f6';
            p.style.borderColor = '#3b82f640';
        }
    }

    async function saveTag(e) {
        e.preventDefault();
        const name = document.getElementById('new-tag-name').value.trim();
        const color = document.getElementById('new-tag-color').value;
        if (!name) return;

        const btn = document.getElementById('tag-submit-btn');
        btn.disabled = true;
        btn.textContent = '處理中...';

        try {
            const resp = await fetch(TAG_API + 'tags/create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, color, institution: INSTITUTION })
            });
            const data = await resp.json();

            if (data.success) {
                showToast('標籤已建立');
                closeAddTagModal();
                loadTags();
            } else {
                alert(data.message || '建立失敗');
            }
        } catch (e) {
            alert('建立失敗');
        } finally {
            btn.disabled = false;
            btn.textContent = '儲存標籤';
        }
    }

    async function deleteTag(id, name) {
        if (!confirm(`確定要刪除標籤「${name}」嗎？`)) return;

        try {
            const resp = await fetch(TAG_API + 'tags/delete&id=' + id + '&institution=' + encodeURIComponent(INSTITUTION), {
                method: 'POST'
            });
            const data = await resp.json();

            if (data.success) {
                showToast('標籤已刪除');
                loadTags();
            } else {
                alert(data.message || '刪除失敗');
            }
        } catch (e) {
            alert('刪除失敗');
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>