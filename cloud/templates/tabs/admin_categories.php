<?php
/**
 * 院區管理員 - 類別與教師管理
 * templates/tabs/admin_categories.php
 */
?>
<div id="section-admin-categories" class="page-section">
    <div class="page-header-v2">
        <h1 class="page-header-v2__title">類別管理</h1>
        <p class="page-header-v2__subtitle">查看 Moodle 課程類別架構及各類別教師指派狀況</p>
    </div>

    <div class="split-view">
        <!-- 左側：類別樹狀圖 -->
        <div class="split-view__sidebar">
            <div class="card-v2" style="height: 100%;">
                <div class="card-v2__header">
                    <h3 class="card-v2__title">課程類別</h3>
                    <div class="toolbar-v2__actions">
                        <button class="btn-v2 btn-v2--sm btn-v2--ghost" onclick="loadMoodleCategories()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="card-v2__body" style="padding: 0; overflow-y: auto; height: calc(100vh - 250px);">
                    <div id="moodle-category-tree" class="category-tree">
                        <div class="empty-state-v2">
                            <i class="fas fa-spinner fa-spin"></i> 載入中...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 右側：教師名單 -->
        <div class="split-view__main">
            <div class="card-v2" style="height: 100%;">
                <div class="card-v2__header">
                    <h3 class="card-v2__title" id="selected-category-title">請選擇類別</h3>
                </div>
                <div class="card-v2__body" id="category-users-list">
                    <div class="empty-state-v2">
                        <div class="empty-state-v2__icon"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="empty-state-v2__title">請從左側選擇一個類別</div>
                        <p class="empty-state-v2__desc">將顯示該類別下的教師、管理者名單</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .split-view {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: var(--space-4);
        height: calc(100vh - 180px);
    }

    .category-tree {
        font-size: 14px;
    }

    .cat-node {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid var(--border-default);
        display: flex;
        align-items: center;
        transition: background-color 0.2s;
    }

    .cat-node:hover {
        background-color: var(--bg-surface-hover);
    }

    .cat-node.is-active {
        background-color: rgba(37, 99, 235, 0.08);
        border-right: 3px solid var(--brand-primary);
        font-weight: 500;
        color: var(--brand-primary);
    }

    .cat-indent {
        display: inline-block;
        width: 20px;
    }

    .role-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        margin-right: 8px;
    }

    .role-manager {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Red */
    .role-creator {
        background: #fef3c7;
        color: #92400e;
    }

    /* Amber */
    .role-teacher {
        background: #dbf4ff;
        color: #1e40af;
    }

    /* Blue */
</style>

<script>
    (function () {
        // Init
        document.addEventListener('DOMContentLoaded', function () {
            // Lazy load when tab is shown? Or manual trigger.
            // For now, load on first click of tab, handled by showTab logic if we add specific hooks,
            // or just load immediately if simple.
            // Let's rely on manual refresh or automatic load when section visible.
        });

        const API_URL = '<?= BASE_URL ?>/api/admin/moodle_categories.php';

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        window.loadMoodleCategories = async function () {
            const container = document.getElementById('moodle-category-tree');
            container.innerHTML = '<div class="empty-state-v2"><i class="fas fa-spinner fa-spin"></i> 載入中...</div>';

            try {
                const res = await fetch(`${API_URL}?action=tree`);
                const text = await res.text();
                let result;

                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('Server Error: ' + text.substring(0, 500));
                }

                if (result.success) {
                    renderCategoryTree(result.data, container);
                } else {
                    container.innerHTML = `<div class="p-4 text-center text-danger">載入失敗: ${result.message}</div>`;
                }
            } catch (e) {
                container.innerHTML = `<div class="p-4 text-center text-danger">網路錯誤: ${e.message}</div>`;
                console.error(e);
            }
        };

        function renderCategoryTree(tree, container) {
            let html = '';

            function buildNode(node, level) {
                const indent = '<span class="cat-indent"></span>'.repeat(level);
                const icon = level === 0 ? '<i class="fas fa-folder text-warning me-2"></i>' : '<i class="fas fa-folder-open text-muted me-2"></i>';

                let row = `
                <div class="cat-node" onclick="loadCategoryUsers(${node.id}, '${escapeHtml(node.name)}', this)">
                    ${indent}
                    ${icon}
                    <span>${escapeHtml(node.name)}</span>
                    <span class="badge bg-secondary ms-2" style="font-size: 10px;">${node.id}</span>
                </div>`;

                if (node.children && node.children.length > 0) {
                    node.children.forEach(child => {
                        row += buildNode(child, level + 1);
                    });
                }
                return row;
            }

            if (tree.length === 0) {
                html = '<div class="p-4 text-center text-muted">無類別資料</div>';
            } else {
                tree.forEach(root => {
                    html += buildNode(root, 0);
                });
            }
            container.innerHTML = html;
        }

        window.loadCategoryUsers = async function (catId, catName, el) {
            // Highlight
            document.querySelectorAll('.cat-node').forEach(n => n.classList.remove('is-active'));
            el.classList.add('is-active');

            const title = document.getElementById('selected-category-title');
            const container = document.getElementById('category-users-list');

            title.textContent = `${catName} - 教師名單`;
            container.innerHTML = '<div class="empty-state-v2"><i class="fas fa-spinner fa-spin"></i> 載入成員中...</div>';

            try {
                const res = await fetch(`${API_URL}?action=users&cat_id=${catId}`);
                const result = await res.json();

                if (result.success) {
                    renderUserList(result.data, container);
                } else {
                    container.innerHTML = `<div class="p-4 text-center text-danger">${result.message}</div>`;
                }
            } catch (e) {
                container.innerHTML = '<div class="p-4 text-center text-danger">網路錯誤</div>';
            }
        };

        function renderUserList(users, container) {
            if (users.length === 0) {
                container.innerHTML = `
                <div class="empty-state-v2">
                    <div class="empty-state-v2__icon"><i class="fas fa-user-slash"></i></div>
                    <div class="empty-state-v2__title">此類別尚無指派教師</div>
                </div>`;
                return;
            }

            let html = '<table class="table-v2"><thead><tr><th>角色</th><th>姓名</th><th>Email</th></tr></thead><tbody>';

            // Map Moodle role shortnames to display names
            const roleMap = {
                'manager': { name: '管理者', class: 'role-manager' },
                'coursecreator': { name: '開課教師', class: 'role-creator' },
                'editingteacher': { name: '教師', class: 'role-teacher' },
                'teacher': { name: '助教', class: 'role-teacher' }
            };

            users.forEach(u => {
                const roleConfig = roleMap[u.role] || { name: u.role, class: 'bg-light text-dark' };
                html += `
                <tr>
                    <td><span class="role-badge ${roleConfig.class}">${roleConfig.name}</span></td>
                    <td>${escapeHtml(u.fullname)}</td>
                    <td class="text-muted">${escapeHtml(u.email)}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Add hook to load data when tab is switched
        // Use window load to ensure we run AFTER main.js defines showTab
        window.addEventListener('load', function() {
            const originalShowTab = window.showTab;
            window.showTab = function (tabId) {
                // Call original
                if (originalShowTab) originalShowTab(tabId);

                // Trigger load if empty and we are showing this tab
                if (tabId === 'admin-categories') {
                    const tree = document.getElementById('moodle-category-tree');
                    // Check if already loaded or currently empty/loading state
                    if (tree && (!tree.innerHTML.includes('cat-node') && !tree.innerHTML.includes('載入中'))) {
                        loadMoodleCategories();
                    }
                    // CASE: If it IS "載入中" (default state) but never triggered because previous hook was lost?
                    // Safe to call anyway if we have a flag, but for now let's rely on checking content.
                    // Actually, if it is "載入中" it implies current HTML is static default.
                    // If we never called loadMoodleCategories, it will stay "載入中".
                    // So we SHOULD call it if it's the loading spinner.
                    if (tree && tree.innerHTML.includes('載入中')) {
                         loadMoodleCategories();
                    }
                }
            };
        });
    })();
</script>