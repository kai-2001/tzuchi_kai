<?php
/**
 * 系統管理員 - 醫院與屬性管理
 * templates/tabs/admin_settings.php
 */
require_once __DIR__ . '/../../includes/config.php';

// 權限判斷
$is_system_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] && !isset($_SESSION['is_hospital_admin']);
$is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];
?>

<div id="section-admin-settings" class="page-section">
    <!-- 頁面標題 -->
    <div class="page-header-v2">
        <h1 class="page-header-v2__title">部門與職稱設定</h1>
        <p class="page-header-v2__subtitle">
            <?php if ($is_system_admin): ?>
                管理醫院、部門、職稱等基礎資料
            <?php else: ?>
                管理部門、職稱等基礎資料
            <?php endif; ?>
        </p>
    </div>

    <!-- Tab 切換 -->
    <div class="tab-nav-v2">
        <?php if ($is_system_admin): ?>
            <button class="tab-nav-v2__item is-active" data-tab="hospitals">
                <i class="fas fa-hospital"></i> 醫院管理
            </button>
            <button class="tab-nav-v2__item" data-tab="departments">
                <i class="fas fa-sitemap"></i> 部門
            </button>
        <?php else: ?>
            <button class="tab-nav-v2__item is-active" data-tab="departments">
                <i class="fas fa-sitemap"></i> 部門
            </button>
        <?php endif; ?>
        <button class="tab-nav-v2__item" data-tab="job_titles">
            <i class="fas fa-id-badge"></i> 職稱
        </button>
    </div>

    <?php if ($is_system_admin): ?>
        <!-- 醫院管理 - 僅系統管理員可見 -->
        <div id="tab-hospitals" class="tab-content-v2 is-active">
            <div class="toolbar-v2">
                <div class="toolbar-v2__search">
                    <i class="fas fa-search toolbar-v2__search-icon"></i>
                    <input type="text" class="toolbar-v2__search-input" placeholder="搜尋醫院..."
                        oninput="filterHospitals(this.value)">
                </div>
                <div class="toolbar-v2__actions">
                    <button class="btn-v2 btn-v2--primary" onclick="openHospitalModal('add')">
                        <i class="fas fa-plus"></i> 新增醫院
                    </button>
                </div>
            </div>

            <div class="card-v2">
                <div class="card-v2__body" id="hospitals-list" style="padding: 0;">
                    <div class="empty-state-v2">
                        <div class="empty-state-v2__icon"><i class="fas fa-spinner fa-spin"></i></div>
                        <div class="empty-state-v2__title">載入中...</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 部門管理 -->
    <div id="tab-departments" class="tab-content-v2 <?php echo !$is_system_admin ? 'is-active' : ''; ?>">
        <div class="toolbar-v2">
            <div class="toolbar-v2__search">
                <i class="fas fa-search toolbar-v2__search-icon"></i>
                <input type="text" class="toolbar-v2__search-input" placeholder="搜尋部門..."
                    oninput="filterAttributes('department', this.value)">
            </div>
            <div class="toolbar-v2__actions" style="display:none;">
                <!-- 
                <button class="btn-v2 btn-v2--primary" onclick="openAttrModal('department')">
                    <i class="fas fa-plus"></i> 新增部門
                </button> 
                -->
            </div>
        </div>

        <div class="card-v2">
            <div class="card-v2__body" id="departments-list" style="padding: 0;">
                <div class="empty-state-v2">
                    <div class="empty-state-v2__icon"><i class="fas fa-spinner fa-spin"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 職稱管理 -->
    <div id="tab-job_titles" class="tab-content-v2">
        <div class="toolbar-v2">
            <div class="toolbar-v2__search">
                <i class="fas fa-search toolbar-v2__search-icon"></i>
                <input type="text" class="toolbar-v2__search-input" placeholder="搜尋職稱..."
                    oninput="filterAttributes('job_title', this.value)">
            </div>
            <div class="toolbar-v2__actions">
                <button class="btn-v2 btn-v2--primary" onclick="openAttrModal('job_title')">
                    <i class="fas fa-plus"></i> 新增職稱
                </button>
            </div>
        </div>

        <div class="card-v2">
            <div class="card-v2__body" id="job_titles-list" style="padding: 0;">
                <div class="empty-state-v2">
                    <div class="empty-state-v2__icon"><i class="fas fa-spinner fa-spin"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 醫院 Modal -->
<div id="hospital-modal" class="modal-v2">
    <div class="modal-v2__backdrop" onclick="closeHospitalModal()"></div>
    <div class="modal-v2__content">
        <div class="modal-v2__header">
            <h3 class="modal-v2__title" id="hospital-modal-title">新增醫院</h3>
            <button class="modal-v2__close" onclick="closeHospitalModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="hospital-form" onsubmit="saveHospital(event)">
            <div class="modal-v2__body">
                <input type="hidden" id="hospital-id" name="id">

                <div class="form-group-v2">
                    <label class="form-label-v2 form-label-v2--required">醫院名稱</label>
                    <input type="text" class="form-input-v2" id="hospital-name" name="name" required
                        placeholder="例：台北慈濟醫院">
                </div>

                <div class="form-group-v2">
                    <label class="form-label-v2">醫院代碼</label>
                    <input type="text" class="form-input-v2" id="hospital-code" name="code" placeholder="例：TPE（選填）">
                    <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                        用於系統識別，建議使用簡短英文代碼
                    </p>
                </div>

                <div class="form-group-v2">
                    <label class="form-label-v2">Moodle 課程分類 ID</label>
                    <input type="number" class="form-input-v2" id="hospital-category" name="moodle_category_id"
                        placeholder="選填">
                </div>
            </div>
            <div class="modal-v2__footer">
                <button type="button" class="btn-v2 btn-v2--secondary" onclick="closeHospitalModal()">取消</button>
                <button type="submit" class="btn-v2 btn-v2--primary" id="hospital-submit-btn">儲存</button>
            </div>
        </form>
    </div>
</div>

<!-- 屬性 Modal -->
<div id="attr-modal" class="modal-v2">
    <div class="modal-v2__backdrop" onclick="closeAttrModal()"></div>
    <div class="modal-v2__content">
        <div class="modal-v2__header">
            <h3 class="modal-v2__title" id="attr-modal-title">新增項目</h3>
            <button class="modal-v2__close" onclick="closeAttrModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="attr-form" onsubmit="saveAttr(event)">
            <div class="modal-v2__body">
                <input type="hidden" id="attr-id" name="id">
                <input type="hidden" id="attr-type-code" name="type_code">
                <input type="hidden" id="attr-parent-id" name="parent_id">

                <div class="form-group-v2">
                    <label class="form-label-v2 form-label-v2--required">名稱</label>
                    <input type="text" class="form-input-v2" id="attr-name" name="name" required placeholder="請輸入名稱">
                </div>

                <div class="form-group-v2" id="attr-parent-display-group" style="display:none;">
                    <label class="form-label-v2">上層部門</label>
                    <input type="text" class="form-input-v2" id="attr-parent-name" readonly
                        style="background: var(--bg-muted);">
                </div>

                <div class="form-group-v2">
                    <label class="form-label-v2">代碼</label>
                    <input type="text" class="form-input-v2" id="attr-code" name="code" placeholder="選填，用於系統識別">
                </div>
            </div>
            <div class="modal-v2__footer">
                <button type="button" class="btn-v2 btn-v2--secondary" onclick="closeAttrModal()">取消</button>
                <button type="submit" class="btn-v2 btn-v2--primary" id="attr-submit-btn">儲存</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Tab 導航 */
    .tab-nav-v2 {
        display: flex;
        gap: var(--space-1);
        margin-bottom: var(--space-5);
        border-bottom: 1px solid var(--border-default);
        padding-bottom: var(--space-3);
    }

    .tab-nav-v2__item {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-2) var(--space-4);
        font-size: 14px;
        font-weight: 500;
        color: var(--text-secondary);
        background: transparent;
        border: none;
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: all var(--duration-fast);
    }

    .tab-nav-v2__item:hover {
        color: var(--text-primary);
        background: var(--bg-muted);
    }

    .tab-nav-v2__item.is-active {
        color: var(--brand-primary);
        background: rgba(37, 99, 235, 0.08);
    }

    .tab-nav-v2__item i {
        font-size: 14px;
    }

    .tab-content-v2 {
        display: none;
    }

    .tab-content-v2.is-active {
        display: block;
    }
</style>

<script>
    (function () {
        'use strict';

        // 共享 BASE_URL
        if (typeof window.CLOUD_BASE_URL === 'undefined') {
            window.CLOUD_BASE_URL = '<?= BASE_URL ?>';
        }
        const BASE_URL = window.CLOUD_BASE_URL;

        let allHospitals = [];
        let attrData = { department: [], job_title: [] };
        let attrTypes = {};
        const isSystemAdmin = <?= $is_system_admin ? 'true' : 'false' ?>;

        // 初始化
        document.addEventListener('DOMContentLoaded', function () {
            if (document.getElementById('section-admin-settings')) {
                loadAttrTypes().then(() => {
                    if (isSystemAdmin) {
                        loadHospitals();
                    } else {
                        // 院區管理員直接載入部門
                        loadAttributes('department');
                    }
                });
                setupTabs();
            }
        });

        // Tab 切換
        function setupTabs() {
            document.querySelectorAll('.tab-nav-v2__item').forEach(btn => {
                btn.addEventListener('click', function () {
                    const tabId = this.dataset.tab;

                    // 切換 Tab 按鈕狀態
                    document.querySelectorAll('.tab-nav-v2__item').forEach(b => b.classList.remove('is-active'));
                    this.classList.add('is-active');

                    // 切換內容
                    document.querySelectorAll('.tab-content-v2').forEach(c => c.classList.remove('is-active'));
                    document.getElementById('tab-' + tabId).classList.add('is-active');

                    // 載入資料
                    if (tabId === 'departments' && attrData.department.length === 0) {
                        loadAttributes('department');
                    } else if (tabId === 'job_titles' && attrData.job_title.length === 0) {
                        loadAttributes('job_title');
                    }
                });
            });
        }

        // 載入屬性類型
        async function loadAttrTypes() {
            try {
                const res = await fetch(`${BASE_URL}/api/admin/attribute_types.php`);
                const data = await res.json();
                if (data.success) {
                    data.data.forEach(t => { attrTypes[t.code] = t; });
                }
            } catch (e) {
                console.error('Load attr types error:', e);
            }
        }

        // ========== 醫院管理 ==========
        async function loadHospitals() {
            try {
                const res = await fetch(`${BASE_URL}/api/admin/hospitals.php`);
                const data = await res.json();
                if (data.success) {
                    allHospitals = data.data || [];
                    renderHospitals(allHospitals);
                }
            } catch (e) {
                console.error('Load hospitals error:', e);
            }
        }

        function renderHospitals(hospitals) {
            const container = document.getElementById('hospitals-list');

            if (!hospitals || hospitals.length === 0) {
                container.innerHTML = `
            <div class="empty-state-v2">
                <div class="empty-state-v2__icon"><i class="fas fa-hospital"></i></div>
                <div class="empty-state-v2__title">尚無醫院資料</div>
                <button class="btn-v2 btn-v2--primary" onclick="openHospitalModal('add')">
                    <i class="fas fa-plus"></i> 新增第一家醫院
                </button>
            </div>`;
                return;
            }

            let html = `<table class="table-v2">
        <thead><tr><th>代碼</th><th>名稱</th><th>Moodle 分類</th><th style="text-align:right;">操作</th></tr></thead>
        <tbody>`;

            hospitals.forEach(h => {
                html += `<tr>
            <td><code style="background: var(--bg-muted); padding: 2px 6px; border-radius: 4px;">${h.code || '—'}</code></td>
            <td><strong>${escapeHtml(h.name)}</strong></td>
            <td style="color: var(--text-secondary);">${h.moodle_category_id || '—'}</td>
            <td style="text-align:right;">
                <button class="btn-v2 btn-v2--ghost btn-v2--sm btn-v2--icon" onclick="openHospitalModal('edit', ${h.id})" title="編輯">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-v2 btn-v2--ghost btn-v2--sm btn-v2--icon" onclick="deleteHospital(${h.id}, '${escapeHtml(h.name)}')" title="刪除" style="color: var(--error);">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        function filterHospitals(query) {
            if (!query) { renderHospitals(allHospitals); return; }
            const q = query.toLowerCase();
            const filtered = allHospitals.filter(h =>
                (h.name && h.name.toLowerCase().includes(q)) ||
                (h.code && h.code.toLowerCase().includes(q))
            );
            renderHospitals(filtered);
        }

        function openHospitalModal(mode, id) {
            const modal = document.getElementById('hospital-modal');
            const form = document.getElementById('hospital-form');
            const title = document.getElementById('hospital-modal-title');

            form.reset();
            document.getElementById('hospital-id').value = id || '';

            if (mode === 'add') {
                title.textContent = '新增醫院';
            } else {
                title.textContent = '編輯醫院';
                const h = allHospitals.find(x => x.id == id);
                if (h) {
                    document.getElementById('hospital-name').value = h.name;
                    document.getElementById('hospital-code').value = h.code || '';
                    document.getElementById('hospital-category').value = h.moodle_category_id || '';
                }
            }

            modal.classList.add('is-open');
        }

        function closeHospitalModal() {
            document.getElementById('hospital-modal').classList.remove('is-open');
        }

        async function saveHospital(event) {
            event.preventDefault();

            const id = document.getElementById('hospital-id').value;
            const data = {
                id: id || undefined,
                name: document.getElementById('hospital-name').value,
                code: document.getElementById('hospital-code').value,
                moodle_category_id: document.getElementById('hospital-category').value || null
            };

            const method = id ? 'PUT' : 'POST';

            try {
                const res = await fetch(`${BASE_URL}/api/admin/hospitals.php`, {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();

                if (result.success) {
                    closeHospitalModal();
                    loadHospitals();
                    showToast(id ? '醫院已更新' : '醫院已新增', 'success');
                } else {
                    showToast(result.error || '儲存失敗', 'error');
                }
            } catch (e) {
                showToast('網路錯誤', 'error');
            }
        }

        async function deleteHospital(id, name) {
            if (!confirm(`確定要刪除「${name}」嗎？`)) return;

            try {
                const res = await fetch(`${BASE_URL}/api/admin/hospitals.php`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const result = await res.json();

                if (result.success) {
                    loadHospitals();
                    showToast(result.message || '已刪除', 'success');
                } else {
                    showToast(result.error || '刪除失敗', 'error');
                }
            } catch (e) {
                showToast('網路錯誤', 'error');
            }
        }

        // ========== 屬性管理 ==========
        async function loadAttributes(typeCode) {
            const type = attrTypes[typeCode];
            if (!type) return;

            try {
                const res = await fetch(`${BASE_URL}/api/admin/attribute_values.php?type_id=${type.id}`);
                const data = await res.json();
                if (data.success) {
                    attrData[typeCode] = data.data || [];
                    renderAttributes(typeCode, attrData[typeCode]);
                }
            } catch (e) {
                console.error('Load attributes error:', e);
            }
        }

        function renderAttributes(typeCode, items) {
            const container = document.getElementById(typeCode + 's-list');

            if (typeCode === 'department') {
                renderDepartmentHierarchy(items, container);
            } else {
                renderFlatAttributes(typeCode, items, container);
            }
        }


        function renderDepartmentHierarchy(items, container) {
            if (!items || items.length === 0) {
                container.innerHTML = `<div class="empty-state-v2">...</div>`;
                return;
            }

            // 1. Build Tree Structure
            const tree = buildTree(items);

            // 2. Recursive Render Function
            function renderNode(node, level = 0) {
                const isGlobal = !node.hospital_id;
                const canEdit = isSystemAdmin || (!isGlobal);
                const hasChildren = node.children && node.children.length > 0;
                const indent = level * 20; // 縮排

                let html = `
                <div class="hierarchy-item" style="margin-left: ${indent}px; margin-bottom: 8px;">
                    <div class="hierarchy-content">
                        <div class="hierarchy-info">
                            <span class="hierarchy-icon" style="color: var(--brand-primary); width: 20px; display: inline-block; text-align: center;">
                                ${level === 0 ? '<i class="fas fa-building"></i>' : '<i class="fas fa-level-up-alt fa-rotate-90"></i>'}
                            </span>
                            <span class="hierarchy-name" style="font-weight: 600; font-size: 15px;">${escapeHtml(node.name)}</span>
                            ${node.code ? `<code class="hierarchy-code" style="background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 4px; font-size: 12px; margin-left: 8px;">${node.code}</code>` : ''}
                            ${isGlobal ? '<span class="badge-v2 badge-v2--default" style="font-size:10px; margin-left: 8px;">全院通用</span>' : ''}
                        </div>
                        <div class="hierarchy-actions">
                            <button class="btn-v2 btn-v2--primary btn-v2--sm btn-v2--icon" 
                                onclick="openAttrModal('department', null, ${node.id}, '${escapeHtml(node.name)}')" title="新增子單位">
                                <i class="fas fa-plus"></i>
                            </button>
                            ${canEdit ? `
                            <button class="btn-v2 btn-v2--ghost btn-v2--sm btn-v2--icon" onclick="openAttrModal('department', ${node.id})" title="編輯">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-v2 btn-v2--ghost btn-v2--sm btn-v2--icon" onclick="deleteAttr(${node.id}, '${escapeHtml(node.name)}', 'department')" title="刪除" style="color: var(--error);">
                                <i class="fas fa-trash"></i>
                            </button>` : ''}
                        </div>
                    </div>
                </div>`;

                // Render Children recursively
                if (hasChildren) {
                    node.children.forEach(child => {
                        html += renderNode(child, level + 1);
                    });
                }
                return html;
            }

            let html = `<div class="hierarchy-list">`;
            tree.forEach(root => {
                html += renderNode(root, 0);
            });
            html += `</div>
            <style>
                .hierarchy-list { display: flex; flex-direction: column; }
                .hierarchy-item { background: var(--bg-surface); border: 1px solid var(--border-default); border-radius: 8px; overflow: hidden; transition: all 0.2s; }
                .hierarchy-item:hover { border-color: var(--brand-primary); box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
                .hierarchy-content { padding: 8px 16px; display: flex; justify-content: space-between; align-items: center; }
                .hierarchy-info { display: flex; align-items: center; }
            </style>`;

            container.innerHTML = html;
        }

        function buildTree(items) {
            const map = {};
            const roots = [];

            // Initial map
            items.forEach(item => {
                map[item.id] = { ...item, children: [] };
            });

            // Connect
            items.forEach(item => {
                if (item.parent_id && map[item.parent_id]) {
                    map[item.parent_id].children.push(map[item.id]);
                } else {
                    roots.push(map[item.id]);
                }
            });

            return roots;
        }

        function renderFlatAttributes(typeCode, items, container) {
            if (!items || items.length === 0) {
                const labels = { department: '部門', job_title: '職稱' };
                const label = labels[typeCode] || '項目';
                container.innerHTML = `
            <div class="empty-state-v2">
                <div class="empty-state-v2__icon"><i class="fas fa-folder-open"></i></div>
                <div class="empty-state-v2__title">尚無${label}資料</div>
                <button class="btn-v2 btn-v2--primary" onclick="openAttrModal('${typeCode}')">
                    <i class="fas fa-plus"></i> 新增${label}
                </button>
            </div>`;
                return;
            }

            let html = `<table class="table-v2">
        <thead><tr><th>代碼</th><th>名稱</th><th style="text-align:right;">操作</th></tr></thead>
        <tbody>`;
            items.forEach(item => {
                html += `<tr>
            <td><code style="background: var(--bg-muted); padding: 2px 6px; border-radius: 4px;">${item.code || '—'}</code></td>
            <td><strong>${escapeHtml(item.name)}</strong></td>
            <td style="text-align:right;">
                <button class="btn-v2 btn-v2--ghost btn-v2--sm btn-v2--icon" onclick="openAttrModal('${typeCode}', ${item.id})" title="編輯">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-v2 btn-v2--ghost btn-v2--sm btn-v2--icon" onclick="deleteAttr(${item.id}, '${escapeHtml(item.name)}', '${typeCode}')" title="刪除" style="color: var(--error);">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        }

        function filterAttributes(typeCode, query) {
            if (!query) { renderAttributes(typeCode, attrData[typeCode]); return; }
            const q = query.toLowerCase();
            const filtered = attrData[typeCode].filter(item =>
                (item.name && item.name.toLowerCase().includes(q)) ||
                (item.code && item.code.toLowerCase().includes(q))
            );
            renderAttributes(typeCode, filtered);
        }

        function openAttrModal(typeCode, id, parentId = null, parentName = '') {
            const modal = document.getElementById('attr-modal');
            const form = document.getElementById('attr-form');
            const title = document.getElementById('attr-modal-title');
            const labels = { department: '部門', job_title: '職稱' };
            const label = labels[typeCode] || '項目';
            const parentGroup = document.getElementById('attr-parent-display-group');

            form.reset();
            document.getElementById('attr-id').value = id || '';
            document.getElementById('attr-type-code').value = typeCode;
            document.getElementById('attr-parent-id').value = parentId || '';

            // 處理 Parent 顯示
            if (parentId && parentName) {
                parentGroup.style.display = 'block';
                document.getElementById('attr-parent-name').value = parentName;
                title.textContent = `新增${parentName}單位`;
            } else if (id) {
                // 編輯模式，如果是子項目? We don't have parent info easily unless we look it up.
                // Assuming flat edit for now or look up from data
                const item = attrData[typeCode].find(x => x.id == id);
                if (item && item.parent_id) {
                    // Find parent name
                    const parent = attrData[typeCode].find(x => x.id == item.parent_id);
                    if (parent) {
                        parentGroup.style.display = 'block';
                        document.getElementById('attr-parent-name').value = parent.name;
                        document.getElementById('attr-parent-id').value = item.parent_id;
                    }
                } else {
                    parentGroup.style.display = 'none';
                }
            } else {
                parentGroup.style.display = 'none';
            }

            if (id) {
                title.textContent = `編輯${label}`;
                const item = attrData[typeCode].find(x => x.id == id);
                if (item) {
                    document.getElementById('attr-name').value = item.name;
                    document.getElementById('attr-code').value = item.code || '';
                }
            } else if (!parentId) {
                title.textContent = `新增${label}`;
            }

            modal.classList.add('is-open');
        }

        function closeAttrModal() {
            document.getElementById('attr-modal').classList.remove('is-open');
        }

        async function saveAttr(event) {
            event.preventDefault();

            const id = document.getElementById('attr-id').value;
            const typeCode = document.getElementById('attr-type-code').value;
            const type = attrTypes[typeCode];

            const data = {
                id: id || undefined,
                type_id: type.id,
                name: document.getElementById('attr-name').value,
                code: document.getElementById('attr-code').value,
                parent_id: document.getElementById('attr-parent-id').value || null
            };

            const method = id ? 'PUT' : 'POST';

            try {
                const res = await fetch(`${BASE_URL}/api/admin/attribute_values.php`, {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();

                if (result.success) {
                    closeAttrModal();
                    loadAttributes(typeCode);
                    showToast(id ? '已更新' : '已新增', 'success');
                } else {
                    showToast(result.error || '儲存失敗', 'error');
                }
            } catch (e) {
                showToast('網路錯誤', 'error');
            }
        }

        async function deleteAttr(id, name, typeCode) {
            if (!confirm(`確定要刪除「${name}」嗎？\n\n⚠️ 注意：此操作將一併刪除該項目下所有的子單位及其下層單位！`)) return;

            try {
                const res = await fetch(`${BASE_URL}/api/admin/attribute_values.php`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const result = await res.json();

                if (result.success) {
                    loadAttributes(typeCode);
                    showToast(result.message || '已刪除', 'success');
                } else {
                    showToast(result.error || '刪除失敗', 'error');
                }
            } catch (e) {
                showToast('網路錯誤', 'error');
            }
        }

        // 工具函數
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showToast(message, type = 'info') {
            const existing = document.querySelector('.toast-v2');
            if (existing) existing.remove();

            const toast = document.createElement('div');
            toast.className = `toast-v2 toast-v2--${type}`;
            const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
            toast.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i> ${escapeHtml(message)}`;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideInRight 0.3s reverse forwards';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // ESC 關閉 Modal
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeHospitalModal();
                closeAttrModal();
            }
        });

        // 暴露需要的函數到全域
        window.openHospitalModal = openHospitalModal;
        window.closeHospitalModal = closeHospitalModal;
        window.saveHospital = saveHospital;
        window.deleteHospital = deleteHospital;
        window.filterHospitals = filterHospitals;
        window.openAttrModal = openAttrModal;
        window.closeAttrModal = closeAttrModal;
        window.saveAttr = saveAttr;
        window.deleteAttr = deleteAttr;
        window.filterAttributes = filterAttributes;
    })();
</script>