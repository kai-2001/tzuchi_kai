
    (function () {
        'use strict';

        // 共享 BASE_URL
        if (typeof window.CLOUD_BASE_URL === 'undefined') {
            window.CLOUD_BASE_URL = '"/cloud"';
        }
        const BASE_URL = window.CLOUD_BASE_URL;

        let allHospitals = [];
        let attrData = { department: [], job_title: [] };
        let attrTypes = {};

        // 初始化
        document.addEventListener('DOMContentLoaded', function () {
            if (document.getElementById('section-admin-settings')) {
                loadHospitals();
                loadAttrTypes();
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
            const labels = { department: '部門', job_title: '職稱' };
            const label = labels[typeCode] || '項目';

            if (!items || items.length === 0) {
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

        function openAttrModal(typeCode, id) {
            const modal = document.getElementById('attr-modal');
            const form = document.getElementById('attr-form');
            const title = document.getElementById('attr-modal-title');
            const labels = { department: '部門', job_title: '職稱' };
            const label = labels[typeCode] || '項目';

            form.reset();
            document.getElementById('attr-id').value = id || '';
            document.getElementById('attr-type-code').value = typeCode;

            if (id) {
                title.textContent = `編輯${label}`;
                const item = attrData[typeCode].find(x => x.id == id);
                if (item) {
                    document.getElementById('attr-name').value = item.name;
                    document.getElementById('attr-code').value = item.code || '';
                }
            } else {
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
                code: document.getElementById('attr-code').value
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
            if (!confirm(`確定要刪除「${name}」嗎？`)) return;

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
