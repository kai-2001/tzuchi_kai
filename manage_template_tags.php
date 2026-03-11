<?php
/**
 * 模板標籤管理頁面（系統管理員專用）
 */
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// 權限檢查
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];

if (!$is_admin || $is_hospital_admin) {
    header('Location: /0213/login.php');
    exit;
}

// 引入標準 header
include_once __DIR__ . '/templates/header.php';
?>

<style>
    .template-tag-page {
        padding-top: 100px;
        max-width: 800px;
        margin: 0 auto;
        padding-left: 20px;
        padding-right: 20px;
        padding-bottom: 60px;
    }

    /* 頁面標題 */
    .template-tag-page .page-header {
        margin-bottom: 32px;
    }

    .template-tag-page .page-title {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 8px;
    }

    .template-tag-page .page-icon {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);
    }

    .template-tag-page .page-icon i {
        font-size: 1.5rem;
        color: white;
    }

    .template-tag-page .page-title h1 {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .template-tag-page .page-subtitle {
        color: #64748b;
        font-size: 0.95rem;
        margin-left: 72px;
    }

    /* 主卡片 */
    .template-tag-page .main-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    /* 標籤展示區 */
    .template-tag-page .tags-section {
        padding: 28px;
    }

    .template-tag-page .section-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 16px;
    }

    .template-tag-page .tags-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        min-height: 50px;
    }

    .template-tag-page .tag-chip {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 18px;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.2s ease;
        cursor: default;
    }

    .template-tag-page .tag-chip:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .template-tag-page .tag-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }

    .template-tag-page .tag-delete {
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px;
        margin-left: 2px;
        opacity: 0.5;
        transition: opacity 0.2s;
        border-radius: 50%;
    }

    .template-tag-page .tag-delete:hover {
        opacity: 1;
        background: rgba(0, 0, 0, 0.1);
    }

    .template-tag-page .empty-state {
        color: #94a3b8;
        font-style: italic;
        padding: 30px;
        text-align: center;
        background: #f8fafc;
        border-radius: 12px;
        border: 2px dashed #e2e8f0;
    }

    /* 新增表單區 */
    .template-tag-page .add-section {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        padding: 24px 28px;
        border-top: 1px solid #e2e8f0;
    }

    .template-tag-page .add-form {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .template-tag-page .color-picker-wrapper {
        position: relative;
    }

    .template-tag-page .color-picker {
        width: 52px;
        height: 52px;
        border: 3px solid white;
        border-radius: 14px;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        transition: transform 0.2s;
    }

    .template-tag-page .color-picker:hover {
        transform: scale(1.05);
    }

    .template-tag-page .name-input {
        flex: 1;
        padding: 14px 20px;
        border: 2px solid #e2e8f0;
        border-radius: 14px;
        font-size: 1rem;
        background: white;
        transition: all 0.2s;
    }

    .template-tag-page .name-input:focus {
        outline: none;
        border-color: #8b5cf6;
        box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
    }

    .template-tag-page .btn-add {
        padding: 14px 28px;
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        color: white;
        border: none;
        border-radius: 14px;
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);
        transition: all 0.2s;
    }

    .template-tag-page .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
    }

    /* 預覽區 */
    .template-tag-page .preview-section {
        margin-top: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .template-tag-page .preview-label {
        font-size: 0.85rem;
        color: #64748b;
    }

    .template-tag-page .preview-tag {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s;
    }

    /* Toast 訊息 */
    .template-tag-page .toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 14px 24px;
        border-radius: 12px;
        font-weight: 500;
        z-index: 9999;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.3s ease;
    }

    .template-tag-page .toast.show {
        transform: translateY(0);
        opacity: 1;
    }

    .template-tag-page .toast.success {
        background: #dcfce7;
        color: #16a34a;
    }

    .template-tag-page .toast.error {
        background: #fee2e2;
        color: #dc2626;
    }
</style>

<div class="template-tag-page">
    <div class="page-header">
        <div class="page-title">
            <div class="page-icon">
                <i class="fas fa-bookmark"></i>
            </div>
            <h1>模板標籤管理</h1>
        </div>
        <p class="page-subtitle">這些標籤會顯示給所有院區使用，作為系統預設選項</p>
    </div>

    <div class="main-card">
        <div class="tags-section">
            <div class="section-label">目前的模板標籤</div>
            <div id="tags-grid" class="tags-grid">
                <div class="empty-state">載入中...</div>
            </div>
        </div>

        <div class="add-section">
            <div class="add-form">
                <div class="color-picker-wrapper">
                    <input type="color" id="new-color" class="color-picker" value="#8b5cf6" title="選擇顏色">
                </div>
                <input type="text" id="new-name" class="name-input" placeholder="輸入新模板標籤名稱..."
                    onkeypress="if(event.key==='Enter')addTag()">
                <button class="btn-add" onclick="addTag()">
                    <i class="fas fa-plus"></i> 新增標籤
                </button>
            </div>
            <div class="preview-section">
                <span class="preview-label">預覽：</span>
                <span id="preview-tag" class="preview-tag"
                    style="background: #8b5cf615; color: #8b5cf6; border: 1px solid #8b5cf630;">
                    <span style="width: 8px; height: 8px; background: #8b5cf6; border-radius: 50%;"></span>
                    新標籤
                </span>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="template-tag-page toast"></div>

<script>
    const API = '/api/v2/index.php?route=';
    let tags = [];

    // 顏色選擇器即時預覽
    document.getElementById('new-color').addEventListener('input', updatePreview);
    document.getElementById('new-name').addEventListener('input', updatePreview);

    function updatePreview() {
        const color = document.getElementById('new-color').value;
        const name = document.getElementById('new-name').value || '新標籤';
        const preview = document.getElementById('preview-tag');
        preview.style.background = color + '15';
        preview.style.color = color;
        preview.style.borderColor = color + '30';
        preview.innerHTML = `<span style="width: 8px; height: 8px; background: ${color}; border-radius: 50%;"></span>${esc(name)}`;
    }

    async function loadTags() {
        try {
            const r = await fetch(API + 'tags/templates');
            const d = await r.json();
            if (d.success) {
                tags = d.data || [];
                render();
            }
        } catch (e) {
            document.getElementById('tags-grid').innerHTML = '<div class="empty-state">載入失敗</div>';
        }
    }

    function render() {
        const grid = document.getElementById('tags-grid');
        if (tags.length === 0) {
            grid.innerHTML = '<div class="empty-state"><i class="fas fa-tag" style="margin-right: 8px;"></i>尚無模板標籤，請使用下方表單新增</div>';
            return;
        }
        grid.innerHTML = tags.map(t => {
            const c = t.color || '#6b7280';
            return `
        <div class="tag-chip" style="background:${c}12; color:${c}; border:2px solid ${c}25;">
            <span class="tag-dot" style="background:${c};"></span>
            ${esc(t.name)}
            <button class="tag-delete" onclick="delTag(${t.id},'${esc(t.name)}')" style="color:${c};" title="刪除">
                <i class="fas fa-times"></i>
            </button>
        </div>`;
        }).join('');
    }

    async function addTag() {
        const name = document.getElementById('new-name').value.trim();
        const color = document.getElementById('new-color').value;
        if (!name) { showToast('請輸入標籤名稱', 'error'); return; }

        try {
            const r = await fetch(API + 'tags/create_template', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, color })
            });
            const d = await r.json();
            if (d.success) {
                tags.push(d.data.tag);
                render();
                document.getElementById('new-name').value = '';
                updatePreview();
                showToast('標籤已新增', 'success');
            } else {
                showToast(d.message || '新增失敗', 'error');
            }
        } catch (e) {
            showToast('新增失敗', 'error');
        }
    }

    async function delTag(id, name) {
        if (!confirm('確定要刪除「' + name + '」標籤？\n此操作會影響所有院區。')) return;
        try {
            const r = await fetch(API + 'tags/delete_template&id=' + id, { method: 'POST' });
            const d = await r.json();
            if (d.success) {
                tags = tags.filter(t => t.id != id);
                render();
                showToast('標籤已刪除', 'success');
            } else {
                showToast(d.message || '刪除失敗', 'error');
            }
        } catch (e) {
            showToast('刪除失敗', 'error');
        }
    }

    function showToast(text, type) {
        const toast = document.getElementById('toast');
        toast.textContent = text;
        toast.className = 'template-tag-page toast ' + type + ' show';
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function esc(s) {
        if (!s) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    loadTags();

    // 處理導航連結
    function showTab(tabId) { window.location.href = PortalConfig.webRoot + '/?tab=' + tabId; }
    function showHome() { window.location.href = PortalConfig.webRoot + '/'; }
    function goToMoodle(url) { window.location.href = url; }
</script>

<?php include_once __DIR__ . '/templates/footer.php'; ?>