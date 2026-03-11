<?php
/**
 * 模板標籤管理頁面（系統管理員專用）
 * admin/manage_template_tags.php
 */

// 載入 session 與權限
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 權限檢查：必須是系統管理員（非院區管理員）
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];

if (!$is_admin || $is_hospital_admin) {
    header('Location: /0213/login.php?error=permission');
    exit;
}

// 引入標準 header
include_once __DIR__ . '/../templates/header.php';
?>

<div class="page-section" style="padding-top: 100px;">
    <div class="section-header">
        <h2><i class="fas fa-bookmark"></i> 模板標籤管理</h2>
        <p class="section-subtitle">這些標籤會顯示給所有院區使用，作為預設選項</p>
    </div>

    <!-- 工具列 -->
    <div class="toolbar-container">
        <div class="search-bar-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="tag-search-input" placeholder="搜尋標籤名稱..." class="search-input"
                onkeyup="filterTemplateTags()">
        </div>

        <div style="margin-left: auto; display:flex; gap:10px;">
            <button class="btn-secondary" onclick="loadTemplateTags()">
                <i class="fas fa-sync-alt"></i> 重新載入
            </button>
            <button class="btn-primary" onclick="openAddTemplateTagModal()">
                <i class="fas fa-plus"></i> 新增模板標籤
            </button>
        </div>
    </div>

    <!-- 標籤列表 -->
    <div class="widget-card">
        <div class="widget-body" id="template-tags-list-container">
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 200px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- 新增標籤 Modal -->
<div id="add-template-tag-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 450px; padding: 24px;">
        <div class="modal-header">
            <h3><i class="fas fa-bookmark" style="color: #3b82f6;"></i> 新增模板標籤</h3>
            <button class="modal-close" onclick="closeAddTemplateTagModal()">&times;</button>
        </div>
        <form id="add-template-tag-form" onsubmit="saveTemplateTag(event)">
            <div class="form-group">
                <label>標籤名稱 <span style="color:red;">*</span></label>
                <input type="text" name="name" id="new-template-tag-name" required placeholder="例如：PGY、急救講師、新進人員">
            </div>
            <div class="form-group">
                <label>標籤顏色</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="color" id="new-template-tag-color" value="#3b82f6"
                        style="width: 60px; height: 40px; border: none; border-radius: 8px; cursor: pointer;">
                    <span id="color-preview"
                        style="padding: 6px 16px; background: #3b82f620; color: #3b82f6; border-radius: 20px; font-size: 0.9rem;">
                        <i class="fas fa-tag"></i> 預覽
                    </span>
                </div>
            </div>
            <div class="form-group">
                <label>說明 (選填)</label>
                <input type="text" name="description" id="new-template-tag-desc" placeholder="簡短描述此標籤用途">
            </div>

            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeAddTemplateTagModal()">取消</button>
                <button type="submit" class="btn-primary" id="template-tag-submit-btn">儲存標籤</button>
            </div>
        </form>
    </div>
</div>

<script>
    let allTemplateTags = [];
    const API_BASE = '/api/v2/index.php?route=';

    // 初始化
    document.addEventListener('DOMContentLoaded', function () {
        loadTemplateTags();

        // 顏色預覽
        document.getElementById('new-template-tag-color').addEventListener('input', function () {
            const color = this.value;
            const preview = document.getElementById('color-preview');
            preview.style.background = color + '20';
            preview.style.color = color;
        });
    });

    async function loadTemplateTags() {
        const listDiv = document.getElementById('template-tags-list-container');
        listDiv.innerHTML = '<div class="loading-skeleton"><div class="skeleton-pulse" style="height: 100px;"></div></div>';

        try {
            const resp = await fetch(API_BASE + 'tags/templates');
            const data = await resp.json();
            if (data.success) {
                allTemplateTags = data.data;
                renderTemplateTags(allTemplateTags);
            } else {
                listDiv.innerHTML = `<div class="error-message">${data.message || '載入失敗'}</div>`;
            }
        } catch (e) {
            listDiv.innerHTML = '<div class="error-message">載入失敗</div>';
        }
    }

    function renderTemplateTags(tags) {
        const listDiv = document.getElementById('template-tags-list-container');
        if (tags.length === 0) {
            listDiv.innerHTML = '<div class="empty-state"><i class="fas fa-tags" style="font-size: 3rem; color: #e2e8f0; margin-bottom: 16px;"></i><p>尚無模板標籤</p><p style="font-size: 0.9rem; color: #94a3b8;">點擊右上角「新增模板標籤」開始建立</p></div>';
            return;
        }

        let html = `
    <table class="member-table">
        <thead>
            <tr>
                <th style="width: 60px;">ID</th>
                <th>標籤</th>
                <th>說明</th>
                <th style="text-align:right;">操作</th>
            </tr>
        </thead>
        <tbody>`;

        tags.forEach(t => {
            const color = t.color || '#6b7280';
            html += `
        <tr>
            <td style="color:#888;">${t.id}</td>
            <td>
                <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 14px; background:${color}15; color:${color}; border-radius:20px; font-weight:600; font-size:0.9rem; border: 1px solid ${color}30;">
                    <span style="width:8px;height:8px;background:${color};border-radius:50%;"></span>
                    ${escapeHtml(t.name)}
                </span>
            </td>
            <td style="color:#64748b; font-size:0.9rem;">${escapeHtml(t.description || '-')}</td>
            <td style="text-align:right;">
                <button class="btn-icon" onclick="deleteTemplateTag(${t.id}, '${escapeHtml(t.name)}')" title="刪除標籤" style="color:#ef4444;">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
        });

        html += `</tbody></table>`;
        listDiv.innerHTML = html;
    }

    function filterTemplateTags() {
        const term = document.getElementById('tag-search-input').value.toLowerCase();
        const filtered = allTemplateTags.filter(t =>
            t.name.toLowerCase().includes(term) ||
            (t.description && t.description.toLowerCase().includes(term))
        );
        renderTemplateTags(filtered);
    }

    function openAddTemplateTagModal() {
        document.getElementById('add-template-tag-modal').style.display = 'flex';
        document.getElementById('new-template-tag-name').focus();
    }

    function closeAddTemplateTagModal() {
        document.getElementById('add-template-tag-modal').style.display = 'none';
        document.getElementById('add-template-tag-form').reset();
        // 重設顏色預覽
        const preview = document.getElementById('color-preview');
        preview.style.background = '#3b82f620';
        preview.style.color = '#3b82f6';
    }

    async function saveTemplateTag(e) {
        e.preventDefault();
        const name = document.getElementById('new-template-tag-name').value.trim();
        const color = document.getElementById('new-template-tag-color').value;
        const description = document.getElementById('new-template-tag-desc').value.trim();

        if (!name) return;

        const btn = document.getElementById('template-tag-submit-btn');
        btn.disabled = true;
        btn.textContent = '處理中...';

        try {
            const resp = await fetch(API_BASE + 'tags/create_template', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, color, description })
            });
            const data = await resp.json();

            if (data.success) {
                showToast('模板標籤已建立');
                closeAddTemplateTagModal();
                loadTemplateTags();
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

    async function deleteTemplateTag(id, name) {
        if (!confirm(`確定要刪除模板標籤「${name}」嗎？\n此標籤將從所有院區中移除。`)) return;

        try {
            const resp = await fetch(API_BASE + 'tags/delete_template&id=' + id, { method: 'POST' });
            const data = await resp.json();

            if (data.success) {
                showToast('模板標籤已刪除');
                loadTemplateTags();
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

    // Toast 通知 (使用全域 showToast 如果存在)
    if (typeof showToast === 'undefined') {
        function showToast(msg) {
            alert(msg);
        }
    }
</script>

<?php include_once __DIR__ . '/../templates/footer.php'; ?>