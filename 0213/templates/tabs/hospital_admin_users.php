<?php
/**
 * 院區管理員 - 人員管理介面
 * templates/tabs/hospital_admin_users.php
 */
?>
<div id="section-users-management" class="page-section" style="max-width:1600px; margin:0 auto;">
    <div class="section-header">
        <h2><i class="fas fa-users"></i> 成員管理</h2>
        <p class="section-subtitle">管理 <?php echo h($_SESSION['institution'] ?? ''); ?> 院區的所有註冊學員與人員</p>
    </div>

    <!-- 成員管理卡片 -->
    <div class="widget-card" style="padding:24px;">
        <!-- 第一行：搜尋框 + 按鈕 -->
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
            <!-- 搜尋框 -->
            <div class="search-bar-container" style="flex:1; min-width:280px;">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="user-search-input" placeholder="搜尋姓名、帳號、Email、標籤..." class="search-input"
                    onkeyup="filterUsers()">
            </div>

            <!-- 按鈕組 -->
            <div style="display:flex; gap:8px; align-items:center;">
                <button class="btn-secondary" onclick="loadUsers()" style="white-space:nowrap;">
                    <i class="fas fa-sync-alt"></i> 重新載入
                </button>
                <button class="btn-secondary" onclick="openBatchModal()" style="white-space:nowrap;">
                    <i class="fas fa-file-import"></i> 批次匯入
                </button>
                <button class="btn-primary" onclick="openAddUserModal()" style="white-space:nowrap;">
                    <i class="fas fa-user-plus"></i> 新增成員
                </button>
            </div>
        </div>

        <!-- 第二行：篩選器 -->
        <div style="display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; align-items:flex-end;">
            <!-- 職類篩選 -->
            <div style="flex:1; min-width:160px;">
                <label
                    style="display:block; font-size:12px; color:#64748b; margin-bottom:6px; font-weight:600; letter-spacing:0.3px;">職類</label>
                <select id="filter-dim-job" class="form-select"
                    style="width:100%; height:38px; padding:0 12px; border-radius:8px; border:1px solid #e2e8f0; font-size:14px; background:#fff;"
                    onchange="applyFilters()">
                    <option value="">全部職類</option>
                    <!-- JS 載入 -->
                </select>
            </div>

            <!-- 所屬篩選 -->
            <div style="flex:1; min-width:160px;">
                <label
                    style="display:block; font-size:12px; color:#64748b; margin-bottom:6px; font-weight:600; letter-spacing:0.3px;">所屬</label>
                <select id="filter-dim-dept" class="form-select"
                    style="width:100%; height:38px; padding:0 12px; border-radius:8px; border:1px solid #e2e8f0; font-size:14px; background:#fff;"
                    onchange="applyFilters()">
                    <option value="">全部所屬</option>
                    <!-- JS 載入 -->
                </select>
            </div>

            <!-- 屬性篩選 -->
            <div style="flex:1; min-width:160px;">
                <label
                    style="display:block; font-size:12px; color:#64748b; margin-bottom:6px; font-weight:600; letter-spacing:0.3px;">屬性</label>
                <select id="filter-dim-attr" class="form-select"
                    style="width:100%; height:38px; padding:0 12px; border-radius:8px; border:1px solid #e2e8f0; font-size:14px; background:#fff;"
                    onchange="applyFilters()">
                    <option value="">全部屬性</option>
                    <!-- JS 載入 -->
                </select>
            </div>

            <!-- 標籤篩選 -->
            <div style="flex:1; min-width:160px;">
                <label
                    style="display:block; font-size:12px; color:#64748b; margin-bottom:6px; font-weight:600; letter-spacing:0.3px;">標籤</label>
                <select id="filter-tags" class="form-select"
                    style="width:100%; height:38px; padding:0 12px; border-radius:8px; border:1px solid #e2e8f0; font-size:14px; background:#fff;"
                    onchange="applyFilters()">
                    <option value="">全部標籤</option>
                    <!-- JS 載入 -->
                </select>
            </div>
        </div>

        <!-- 批次操作列 -->
        <div id="batch-action-bar"
            style="display:none; background:linear-gradient(135deg,#eff6ff,#dbeafe); border:1px solid #93c5fd; border-radius:12px; padding:12px 20px; margin-bottom:16px; display:none; align-items:center; justify-content:space-between;">
            <span style="font-weight:600; color:#1e40af;">
                <i class="fas fa-check-square"></i>
                已選 <span id="batch-count">0</span> 人
            </span>
            <div style="display:flex; gap:8px;">
                <button class="btn-secondary" onclick="openBatchEditModal()" style="font-size:0.85rem;">
                    <i class="fas fa-edit"></i> 批次編輯
                </button>
                <button class="btn-danger" onclick="batchDeleteUsers()"
                    style="font-size:0.85rem; background:#fef2f2; color:#dc2626; border:1px solid #fca5a5;">
                    <i class="fas fa-trash"></i> 批次刪除
                </button>
                <button class="btn-secondary" onclick="clearBatchSelection()" style="font-size:0.85rem;">
                    <i class="fas fa-times"></i> 取消
                </button>
            </div>
        </div>

        <!-- 人員列表 -->
        <div id="users-list-container">
            <!-- Loaded by JS -->
        </div>
    </div>
</div>

<!-- 編輯使用者 Modal (含密碼修改) -->
<div id="edit-user-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 480px; padding: 24px;">
        <div class="modal-header">
            <h3>編輯成員資料</h3>
            <button class="modal-close" onclick="closeEditUserModal()">&times;</button>
        </div>
        <form id="edit-user-form" onsubmit="saveUser(event)">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-user-id">

            <div class="form-group">
                <label>帳號 (無法修改)</label>
                <input type="text" id="edit-user-username" disabled style="background:#f3f4f6;">
            </div>
            <div class="form-group">
                <label>姓名</label>
                <input type="text" name="fullname" id="edit-user-fullname" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="edit-user-email" required>
            </div>
            <div class="form-group"
                style="background: #fdf2f8; padding: 15px; border-radius: 8px; border: 1px dashed #ec4899;">
                <label style="color:#be185d;"><i class="fas fa-key"></i> 修改密碼</label>
                <input type="text" name="password" id="edit-user-password" placeholder="若不修改請留空"
                    style="margin-top:5px;">
                <small style="color:#666;">若輸入，將重設使用者的登入密碼</small>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label><i class="fas fa-tags"></i> 標籤</label>
                <div class="user-tag-selector">
                    <div class="selected-user-tags" id="edit-user-tags-container"></div>
                    <button type="button" class="add-tag-btn" onclick="openUserTagSelector()">
                        <i class="fas fa-plus"></i> 新增標籤
                    </button>
                </div>
                <input type="hidden" name="tags" id="edit-user-tag-ids">
            </div>

            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeEditUserModal()">取消</button>
                <button type="submit" class="btn-primary" id="edit-submit-btn">儲存變更</button>
            </div>
        </form>
    </div>
</div>

<!-- 編輯權限 Modal (新增) -->
<div id="edit-role-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 400px; padding: 24px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-shield"></i> 編輯權限</h3>
            <button class="modal-close" onclick="closeEditRoleModal()">&times;</button>
        </div>
        <form id="edit-role-form" onsubmit="saveRole(event)">
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="id" id="role-user-id">

            <p style="margin-bottom:20px; color:#555;">
                正在調整成員 <strong id="role-user-name" style="color:#2563eb;"></strong> 的系統權限
            </p>

            <div class="form-group">
                <label>選擇權限等級</label>
                <select name="role" id="role-select" class="form-select" onchange="toggleCategorySelect()">
                    <option value="student">一般學員 (Student)</option>
                    <option value="coursecreator">開課教師 (Course Creator)</option>
                </select>
            </div>

            <!-- 類別選擇 (只在選擇開課教師時顯示) -->
            <div class="form-group" id="category-select-group" style="display:none;">
                <label>開課範圍 <small style="color:#6b7280;">(選填，預設為整個院區)</small></label>
                <select name="target_category_id" id="target-category-select" class="form-select">
                    <option value="">整個院區 (所有課程)</option>
                    <!-- 動態載入子類別 -->
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeEditRoleModal()">取消</button>
                <button type="submit" class="btn-danger" id="role-submit-btn">更新權限</button>
            </div>
        </form>
    </div>
</div>

<!-- 新增成員 Modal -->
<div id="add-user-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 480px; padding: 24px;">
        <div class="modal-header">
            <h3>新增成員</h3>
            <button class="modal-close" onclick="closeAddUserModal()">&times;</button>
        </div>
        <form id="add-user-form" onsubmit="addNewUser(event)">
            <div class="form-group">
                <label>帳號 <span style="color:red;">*</span></label>
                <input type="text" name="username" id="add-user-username" required pattern="[a-zA-Z0-9_]+"
                    placeholder="英文、數字、底線">
            </div>
            <div class="form-group">
                <label>姓名 <span style="color:red;">*</span></label>
                <input type="text" name="fullname" id="add-user-fullname" required>
            </div>
            <div class="form-group">
                <label>Email <span style="color:red;">*</span></label>
                <input type="email" name="email" id="add-user-email" required>
            </div>
            <div class="form-group">
                <label>密碼 <span style="color:red;">*</span></label>
                <input type="text" name="password" id="add-user-password" required minlength="8" placeholder="至少 8 個字元">
            </div>
            <div class="form-group">
                <label>角色</label>
                <select name="role" id="add-user-role" class="form-select">
                    <option value="student">學生</option>
                    <option value="coursecreator">開課教師</option>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-layer-group"></i> 群組指派（依維度選擇）</label>
                <div
                    style="display: flex; flex-direction: column; gap: 10px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="min-width: 50px; font-size: 0.9rem; color: #64748b;">職類</label>
                        <select name="dim_cohort_1" id="add-user-dim1" class="form-select" style="flex:1;">
                            <option value="">不指定</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="min-width: 50px; font-size: 0.9rem; color: #64748b;">所屬</label>
                        <select name="dim_cohort_2" id="add-user-dim2" class="form-select" style="flex:1;">
                            <option value="">不指定</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="min-width: 50px; font-size: 0.9rem; color: #64748b;">屬性</label>
                        <select name="dim_cohort_3" id="add-user-dim3" class="form-select" style="flex:1;">
                            <option value="">不指定</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-tags"></i> 標籤</label>
                <div class="user-tag-selector">
                    <div class="selected-user-tags" id="add-user-tags-container"></div>
                    <button type="button" class="add-tag-btn" onclick="openAddUserTagSelector()">
                        <i class="fas fa-plus"></i> 新增標籤
                    </button>
                </div>
                <input type="hidden" name="tags" id="add-user-tag-ids">
            </div>

            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeAddUserModal()">取消</button>
                <button type="submit" class="btn-primary" id="add-submit-btn">新增成員</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* 排序指示器 */
    .member-table th[data-sort] {
        user-select: none;
        transition: background 0.2s;
    }

    .member-table th[data-sort]:hover {
        background: #f1f5f9;
    }

    .member-table th.sorted-asc .fa-sort:before {
        content: "\f0de";
        /* fa-sort-up */
        color: #3b82f6;
    }

    .member-table th.sorted-desc .fa-sort:before {
        content: "\f0dd";
        /* fa-sort-down */
        color: #3b82f6;
    }

    /* 讓表格可橫向捲動且操作欄不擠壓 */
    #users-list-container {
        overflow-x: auto;
    }

    .member-table td:last-child,
    .member-table th:last-child {
        white-space: nowrap;
        min-width: 80px;
    }
</style>

<script>
    let allUsers = [];
    let dimOptions = {};

    // Toast notification helper
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        const colors = { success: '#22c55e', error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
        toast.style.cssText = 'position:fixed;top:80px;right:24px;z-index:10000;padding:12px 24px;border-radius:8px;font-size:14px;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.15);max-width:400px;background:' + (colors[type] || colors.success);
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function () { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; setTimeout(function () { toast.remove(); }, 300); }, 3000);
    }

    // Utility: Escape HTML
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // Map roles to display name
    const roleMap = {
        'student': '<span class="badge" style="background:#e5e7eb; color:#374151;">學員</span>',
        'coursecreator': '<span class="badge" style="background:#dbeafe; color:#1e40af;">開課教師</span>',
        'hospital_admin': '<span class="badge" style="background:#fce7f3; color:#9d174d;">院區管理員</span>',
        'admin': '<span class="badge" style="background:#000; color:#fff;">系統管理員</span>'
    };

    // 初始化
    window.loadMembers = function () {
        loadUsers();
    };

    /* Auto-init */
    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('section-users-management')) {
            loadUsers();
        }
    });

    let currentSort = { column: null, asc: true };

    function loadUsers() {
        const listDiv = document.getElementById('users-list-container');

        listDiv.innerHTML = '<div class="loading-skeleton"><div class="skeleton-pulse"></div></div>';

        // Switch to V2 API
        let url = `${PortalConfig.webRoot}/api/v2/index.php?route=hospital/users&_t=${new Date().getTime()}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Handle V2 Structure: data.data.users / data.data.dim_options
                    const result = data.data;
                    if (result.users) {
                        allUsers = result.users;
                        if (result.dim_options) dimOptions = result.dim_options;
                    } else {
                        // Fallback just in case
                        allUsers = Array.isArray(result) ? result : [];
                        if (data.dim_options) dimOptions = data.dim_options;
                    }

                    // 載入篩選器選項
                    loadFilterOptions(dimOptions, allUsers);

                    // 顯示使用者
                    renderUsers(allUsers);
                } else {
                    listDiv.innerHTML = `<div class="error-message">${data.error || data.message || '載入失敗'}</div>`;
                }
            })
            .catch(err => {
                listDiv.innerHTML = `<div class="error-message">系統錯誤: ${err.message}</div>`;
            });
    }

    function loadFilterOptions(dimOpts, users) {
        // 排序函數：按 display 名稱排序，讓子項目排在父項目下方
        const sortByDisplay = (a, b) => a.display.localeCompare(b.display, 'zh-Hant');

        // 職類
        const jobSelect = document.getElementById('filter-dim-job');
        const jobOptions = (dimOpts['職類'] || []).sort(sortByDisplay);
        jobSelect.innerHTML = '<option value="">全部職類</option>' + jobOptions.map(o =>
            `<option value="${o.cohort_id}">${escapeHtml(o.display)}</option>`
        ).join('');

        // 所屬
        const deptSelect = document.getElementById('filter-dim-dept');
        const deptOptions = (dimOpts['所屬'] || []).sort(sortByDisplay);
        deptSelect.innerHTML = '<option value="">全部所屬</option>' + deptOptions.map(o =>
            `<option value="${o.cohort_id}">${escapeHtml(o.display)}</option>`
        ).join('');

        // 屬性
        const attrSelect = document.getElementById('filter-dim-attr');
        const attrOptions = (dimOpts['屬性'] || []).sort(sortByDisplay);
        attrSelect.innerHTML = '<option value="">全部屬性</option>' + attrOptions.map(o =>
            `<option value="${o.cohort_id}">${escapeHtml(o.display)}</option>`
        ).join('');

        // 標籤 - 從 users 收集所有標籤
        const allTags = new Set();
        users.forEach(u => {
            if (u.tags) {
                u.tags.split(/[;；,，]/).forEach(t => {
                    const trimmed = t.trim();
                    if (trimmed) allTags.add(trimmed);
                });
            }
        });
        const tagSelect = document.getElementById('filter-tags');
        tagSelect.innerHTML = '<option value="">全部標籤</option>' + Array.from(allTags).sort().map(t =>
            `<option value="${escapeHtml(t)}">${escapeHtml(t)}</option>`
        ).join('');
    }

    function applyFilters() {
        const filterJob = document.getElementById('filter-dim-job').value;
        const filterDept = document.getElementById('filter-dim-dept').value;
        const filterAttr = document.getElementById('filter-dim-attr').value;
        const filterTag = document.getElementById('filter-tags').value;

        let filtered = allUsers;

        // 職類篩選
        if (filterJob) {
            filtered = filtered.filter(u => u.dim_職類_ids && u.dim_職類_ids.includes(parseInt(filterJob)));
        }

        // 所屬篩選
        if (filterDept) {
            filtered = filtered.filter(u => u.dim_所屬_ids && u.dim_所屬_ids.includes(parseInt(filterDept)));
        }

        // 屬性篩選
        if (filterAttr) {
            filtered = filtered.filter(u => u.dim_屬性_ids && u.dim_屬性_ids.includes(parseInt(filterAttr)));
        }

        // 標籤篩選
        if (filterTag) {
            filtered = filtered.filter(u => u.tags && u.tags.includes(filterTag));
        }

        renderUsers(filtered);
    }

    function filterUsers() {
        const query = document.getElementById('user-search-input').value.trim().toLowerCase();

        if (!query) {
            applyFilters(); // 如果搜尋框空白，只套用篩選器
            return;
        }

        // 先套用篩選器
        const filterJob = document.getElementById('filter-dim-job').value;
        const filterDept = document.getElementById('filter-dim-dept').value;
        const filterAttr = document.getElementById('filter-dim-attr').value;
        const filterTag = document.getElementById('filter-tags').value;

        let filtered = allUsers;

        if (filterJob) filtered = filtered.filter(u => u.dim_職類_ids && u.dim_職類_ids.includes(parseInt(filterJob)));
        if (filterDept) filtered = filtered.filter(u => u.dim_所屬_ids && u.dim_所屬_ids.includes(parseInt(filterDept)));
        if (filterAttr) filtered = filtered.filter(u => u.dim_屬性_ids && u.dim_屬性_ids.includes(parseInt(filterAttr)));
        if (filterTag) filtered = filtered.filter(u => u.tags && u.tags.includes(filterTag));

        // 再套用搜尋（姓名、帳號、email、標籤）
        filtered = filtered.filter(u => {
            return (u.fullname && u.fullname.toLowerCase().includes(query)) ||
                (u.username && u.username.toLowerCase().includes(query)) ||
                (u.email && u.email.toLowerCase().includes(query)) ||
                (u.tags && u.tags.toLowerCase().includes(query));
        });

        renderUsers(filtered);
    }

    function sortUsers(column) {
        if (currentSort.column === column) {
            currentSort.asc = !currentSort.asc;
        } else {
            currentSort.column = column;
            currentSort.asc = true;
        }

        // 取得目前顯示的資料（可能已經過篩選）
        const tbody = document.querySelector('#users-list-container table tbody');
        if (!tbody) return;

        const rows = Array.from(tbody.querySelectorAll('tr'));
        const sortedRows = rows.sort((a, b) => {
            let aVal, bVal;

            switch (column) {
                case 'id':
                    aVal = parseInt(a.cells[1].textContent);
                    bVal = parseInt(b.cells[1].textContent);
                    break;
                case 'fullname':
                    aVal = a.cells[2].textContent.trim();
                    bVal = b.cells[2].textContent.trim();
                    break;
                case 'username':
                    aVal = a.cells[3].textContent.trim();
                    bVal = b.cells[3].textContent.trim();
                    break;
                case 'email':
                    aVal = a.cells[7].textContent.trim();
                    bVal = b.cells[7].textContent.trim();
                    break;
                case 'role':
                    aVal = a.cells[4].textContent.trim();
                    bVal = b.cells[4].textContent.trim();
                    break;
                default:
                    return 0;
            }

            if (aVal < bVal) return currentSort.asc ? -1 : 1;
            if (aVal > bVal) return currentSort.asc ? 1 : -1;
            return 0;
        });

        sortedRows.forEach(row => tbody.appendChild(row));

        // 更新排序指示器
        document.querySelectorAll('.member-table th').forEach(th => {
            th.classList.remove('sorted-asc', 'sorted-desc');
        });
        const th = document.querySelector(`th[data-sort="${column}"]`);
        if (th) {
            th.classList.add(currentSort.asc ? 'sorted-asc' : 'sorted-desc');
        }
    }

    function renderUsers(users) {
        const listDiv = document.getElementById('users-list-container');
        if (users.length === 0) {
            listDiv.innerHTML = '<div class="empty-state">尚無人員資料</div>';
            return;
        }

        let html = `
    <table class="member-table">
        <thead>
            <tr>
                <th style="width:40px;"><input type="checkbox" id="select-all-users" onchange="toggleSelectAllUsers(this)"></th>
                <th data-sort="id" onclick="sortUsers('id')" style="cursor:pointer;">ID <i class="fas fa-sort"></i></th>
                <th data-sort="fullname" onclick="sortUsers('fullname')" style="cursor:pointer;">姓名 <i class="fas fa-sort"></i></th>
                <th data-sort="username" onclick="sortUsers('username')" style="cursor:pointer;">帳號 <i class="fas fa-sort"></i></th>
                <th data-sort="role" onclick="sortUsers('role')" style="cursor:pointer;">權限 <i class="fas fa-sort"></i></th>
                <th>職類</th>
                <th>所屬</th>
                <th>屬性</th>
                <th>標籤</th>
                <th data-sort="email" onclick="sortUsers('email')" style="cursor:pointer;">Email <i class="fas fa-sort"></i></th>
                <th style="text-align:right; white-space:nowrap; min-width:80px;">操作</th>
            </tr>
        </thead>
        <tbody>`;

        users.forEach(u => {
            const roleBadge = roleMap[u.role] || u.role;
            const dim1 = u.dim_職類 || '';
            const dim2 = u.dim_所屬 || '';
            const dim3 = u.dim_屬性 || '';

            html += `
        <tr>
            <td><input type="checkbox" class="user-batch-cb" value="${u.id}" data-user='${JSON.stringify({ id: u.id, fullname: u.fullname || '', username: u.username || '', email: u.email || '', tags: u.tags || '', moodle_uid: u.moodle_uid || '' }).replace(/'/g, '&apos;')}' onchange="updateBatchBar()"></td>
            <td style="color:#888;">${u.id}</td>
            <td style="font-weight:600;">${escapeHtml(u.fullname)}</td>
            <td>${escapeHtml(u.username)}</td>
            <td>${roleBadge}</td>
            <td><span style="font-size:0.85rem; color:#475569;">${escapeHtml(dim1)}</span></td>
            <td><span style="font-size:0.85rem; color:#475569;">${escapeHtml(dim2)}</span></td>
            <td><span style="font-size:0.85rem; color:#475569;">${escapeHtml(dim3)}</span></td>
            <td><div style="max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:0.85rem; color:#64748b;">${escapeHtml(u.tags || '')}</div></td>
            <td>${escapeHtml(u.email)}</td>
            <td style="text-align:right; white-space:nowrap;">
                <button class="btn-icon" onclick="openEditUserModal(${u.id})" title="編輯資料與密碼">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon" onclick="openEditRoleModal(${u.id})" title="編輯權限" style="color:#be185d;">
                    <i class="fas fa-user-shield"></i>
                </button>
            </td>
        </tr>`;
        });

        html += `</tbody></table> 
             <div style="text-align:right; margin-top:10px; color:#666; font-size:0.85rem;">共 ${users.length} 位人員</div>`;

        listDiv.innerHTML = html;
    }

    function filterUsers() {
        const term = document.getElementById('user-search-input').value.toLowerCase();
        const filtered = allUsers.filter(u =>
            u.fullname.toLowerCase().includes(term) ||
            u.username.toLowerCase().includes(term)
        );
        renderUsers(filtered);
    }

    // Edit User Modal
    function openEditUserModal(id) {
        const user = allUsers.find(u => u.id == id);
        if (!user) return;

        document.getElementById('edit-user-id').value = user.id;
        document.getElementById('edit-user-username').value = user.username;
        document.getElementById('edit-user-fullname').value = user.fullname;
        document.getElementById('edit-user-email').value = user.email;
        document.getElementById('edit-user-password').value = ''; // Reset password field

        // 載入使用者現有標籤
        currentUserTags = [];
        const existingTags = (user.tags || '').trim();
        if (existingTags) {
            existingTags.split(/[,;]\s*/).forEach(tagName => {
                tagName = tagName.trim();
                if (tagName) {
                    currentUserTags.push({ id: 0, name: tagName, color: '#3b82f6' });
                }
            });
        }
        renderUserTags();

        document.getElementById('edit-user-modal').style.display = 'flex';
    }

    function closeEditUserModal() {
        document.getElementById('edit-user-modal').style.display = 'none';
    }

    function saveUser(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);

        const btn = document.getElementById('edit-submit-btn');
        btn.disabled = true;
        btn.textContent = '儲存中...';

        fetch(PortalConfig.webRoot + '/api/v2/index.php?route=hospital/users/update', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('資料已更新');
                    closeEditUserModal();
                    loadUsers(); // Reload list
                } else {
                    alert(data.error || '更新失敗');
                }
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = '儲存變更';
            });
    }

    // Role Edit Modal
    function openEditRoleModal(id) {
        const user = allUsers.find(u => u.id == id);
        if (!user) return;

        document.getElementById('role-user-id').value = user.id;
        document.getElementById('role-user-name').textContent = user.fullname;
        document.getElementById('role-select').value = user.role;
        document.getElementById('edit-role-modal').style.display = 'flex';

        // Load subcategories for dropdown
        loadSubcategories();

        // Toggle category select visibility
        toggleCategorySelect();
    }

    function toggleCategorySelect() {
        const roleSelect = document.getElementById('role-select');
        const categoryGroup = document.getElementById('category-select-group');

        if (roleSelect.value === 'coursecreator') {
            categoryGroup.style.display = 'block';
        } else {
            categoryGroup.style.display = 'none';
        }
    }

    function loadSubcategories() {
        const select = document.getElementById('target-category-select');

        // Fetch subcategories from category API
        fetch(PortalConfig.webRoot + '/api/v2/index.php?route=categories/list_children')
            .then(res => res.json())
            .then(data => {
                let html = '<option value="">整個院區 (所有課程)</option>';
                if (data.success && data.data) {
                    data.data.forEach(cat => {
                        html += `<option value="${cat.id}">${escapeHtml(cat.name)}</option>`;
                    });
                }
                select.innerHTML = html;
            })
            .catch(err => {
                console.error('Failed to load subcategories:', err);
            });
    }

    function closeEditRoleModal() {
        document.getElementById('edit-role-modal').style.display = 'none';
    }

    function saveRole(e) {
        e.preventDefault();
        const form = e.target;
        // Remove disabled check here just in case, but rely on button state
        const btn = document.getElementById('role-submit-btn');
        const originalText = btn.textContent;

        btn.disabled = true;
        btn.textContent = '更新中...';

        const formData = new FormData(form);

        fetch(PortalConfig.webRoot + '/api/v2/index.php?route=hospital/users/update_role', {
            method: 'POST',
            body: formData
        })
            .then(res => {
                if (!res.ok) {
                    return res.text().then(text => {
                        try {
                            const errData = JSON.parse(text);
                            throw new Error(errData.error || '伺服器錯誤 (' + res.status + ')');
                        } catch (parseErr) {
                            if (parseErr.message.includes('伺服器錯誤') || parseErr.message.includes('權限')) {
                                throw parseErr;
                            }
                            console.error('Server Raw Response:', text);
                            throw new Error('伺服器回應錯誤 (' + res.status + ')');
                        }
                    });
                }
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    showToast(data.data?.message || '權限更新成功！', 'success');
                    closeEditRoleModal();
                    loadUsers();
                } else {
                    alert(data.error || '更新失敗');
                }
            })
            .catch(err => {
                console.error('saveRole error:', err);
                alert(err.message || '發生錯誤，請稍後再試');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = originalText;
            });
    }

    // Add User Modal
    async function openAddUserModal() {
        document.getElementById('add-user-modal').style.display = 'flex';

        // 重設標籤
        addUserTags = [];
        document.getElementById('add-user-tags-container').innerHTML = '';
        document.getElementById('add-user-tag-ids').value = '';

        // 載入維度群組資料
        await loadDimensionCohorts();
    }

    async function loadDimensionCohorts() {
        try {
            // 使用 hospital_admin 路徑取得維度化群組
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/get_grouped');
            const data = await res.json();

            console.log('Dimension API response:', data); // Debug

            if (!data.success) {
                console.warn('Dimension API failed:', data.error);
                return;
            }

            // 對應維度名稱到下拉選單
            const dimMap = {
                '職類': 'add-user-dim1',
                '所屬': 'add-user-dim2',
                '屬性': 'add-user-dim3'
            };

            data.data.forEach(dim => {
                const selectId = dimMap[dim.name];
                if (!selectId) return;

                const select = document.getElementById(selectId);
                if (!select) return;

                select.innerHTML = '<option value="">不指定</option>';
                dim.cohorts.forEach(c => {
                    const displayName = c.full_path || c.display_name || '群組#' + c.cohort_id;
                    select.innerHTML += `<option value="${c.cohort_id}">${escapeHtml(displayName)}</option>`;
                });
            });
        } catch (e) {
            console.error('Failed to load dimension cohorts:', e);
        }
    }
    function closeAddUserModal() { document.getElementById('add-user-modal').style.display = 'none'; }
    function addNewUser(e) {
        e.preventDefault();
        const btn = document.getElementById('add-submit-btn');
        btn.disabled = true; btn.textContent = '處理中...';

        const fd = new FormData(e.target);
        fd.append('action', 'add');

        fetch(PortalConfig.webRoot + '/api/v2/index.php?route=hospital/users/create', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('新增成功');
                    closeAddUserModal();
                    e.target.reset();
                    loadUsers();
                } else {
                    alert(data.error);
                }
            })
            .finally(() => { btn.disabled = false; btn.textContent = '新增成員'; });
    }

    // === 批次匯入相關 ===
    async function openBatchModal() {
        const modal = document.getElementById('batch-modal');
        modal.style.display = 'flex';
        document.getElementById('batch-csv-text').value = '';
        document.getElementById('batch-result-log').style.display = 'none';
        document.getElementById('batch-result-log').innerHTML = '';

        // 載入維度群組資料
        await loadBatchDimensionCohorts();
    }

    async function loadBatchDimensionCohorts() {
        try {
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/get_grouped');
            const data = await res.json();

            if (!data.success) {
                console.warn('Dimension API failed:', data.error);
                return;
            }

            // 對應維度名稱到下拉選單
            const dimMap = {
                '職類': 'batch-dim1',
                '所屬': 'batch-dim2',
                '屬性': 'batch-dim3'
            };

            data.data.forEach(dim => {
                const selectId = dimMap[dim.name];
                if (selectId) {
                    const select = document.getElementById(selectId);
                    if (select) {
                        select.innerHTML = '<option value="">不指定</option>';
                        dim.cohorts.forEach(c => {
                            const displayName = c.full_path || c.display_name || '群組#' + c.cohort_id;
                            select.innerHTML += `<option value="${c.cohort_id}">${escapeHtml(displayName)}</option>`;
                        });
                    }
                }
            });
        } catch (e) {
            console.error('Failed to load batch dimension cohorts:', e);
        }
    }

    function closeBatchModal() {
        document.getElementById('batch-modal').style.display = 'none';
    }

    function handleBatchFileSelect(input) {
        if (!input.files || !input.files[0]) return;
        const reader = new FileReader();
        reader.onload = function (e) { document.getElementById('batch-csv-text').value = e.target.result; };
        reader.readAsText(input.files[0]);
    }

    function runBatchImport() {
        const text = document.getElementById('batch-csv-text').value.trim();
        if (!text) return alert('請先輸入資料');

        const btn = document.getElementById('batch-run-btn');
        const log = document.getElementById('batch-result-log');
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 處理中...';
        log.style.display = 'block';
        log.innerHTML = '<div style="color:#aaa;">連線中...</div>';

        const formData = new FormData();
        formData.append('csv_text', text);
        formData.append('default_role', document.getElementById('batch-default-role').value);

        // 新增：傳送維度群組 ID
        const dim1 = document.getElementById('batch-dim1')?.value || '';
        const dim2 = document.getElementById('batch-dim2')?.value || '';
        const dim3 = document.getElementById('batch-dim3')?.value || '';
        if (dim1) formData.append('dim_cohort_ids[]', dim1);
        if (dim2) formData.append('dim_cohort_ids[]', dim2);
        if (dim3) formData.append('dim_cohort_ids[]', dim3);

        fetch(PortalConfig.webRoot + '/api/hospital_admin/batch_add_members.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    let html = '';
                    data.results.forEach(r => {
                        const color = r.status === 'success' ? '#4ade80' : '#f87171';
                        html += `<div style="margin-bottom:2px; color:${color}">Row ${r.row}: ${escapeHtml(r.message)}</div>`;
                    });
                    log.innerHTML = html;
                    loadUsers(); // 重新載入列表
                    showToast(`匯入完成！成功: ${data.summary.success}, 失敗: ${data.summary.fail}`, 'success');
                } else {
                    log.innerHTML = `<div style="color:#f87171">錯誤: ${escapeHtml(data.error)}</div>`;
                }
            })
            .catch(err => { log.innerHTML = '<div style="color:#f87171">網路連線不穩</div>'; })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
    }
</script>

<!-- 批次匯入 Modal UI (Redesigned for better UX) -->
<div id="batch-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content"
        style="max-width: 750px; padding: 0; border-radius: 16px; height: 90vh; display: flex; flex-direction: column; overflow: hidden; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">

        <!-- Header -->
        <div class="modal-header"
            style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div
                    style="width: 40px; height: 40px; background: #3b82f6; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="fas fa-file-import fa-lg"></i>
                </div>
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #1e293b; margin: 0;">批次匯入成員</h3>
                    <p style="font-size: 0.8rem; color: #64748b; margin: 0;">快速建立多筆帳號並指派群組</p>
                </div>
            </div>
            <button class="modal-close" onclick="closeBatchModal()"
                style="font-size: 24px; color: #94a3b8;">&times;</button>
        </div>

        <div style="flex: 1; overflow-y: auto; padding: 24px;">

            <!-- Step 1: Format Guide -->
            <div style="margin-bottom: 24px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                    <span
                        style="width: 20px; height: 20px; background: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">1</span>
                    <h4 style="font-size: 0.95rem; font-weight: 600; color: #334155; margin: 0;">確認 CSV 資料格式</h4>
                </div>
                <div
                    style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; font-size: 0.8rem; overflow-x: auto;">
                    <!-- 欄位說明卡片 - 彩色 (新順序：帳號,姓名,Email,密碼*,角色,職類,所屬,屬性,標籤) -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px;">
                        <!-- 基本資料 -->
                        <div
                            style="padding: 10px; background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 8px; border-left: 4px solid #2563eb;">
                            <b style="color: #1e40af;">帳號 <span style="color:#dc2626;">*</span></b>
                            <div style="color: #475569; font-size: 0.75rem;">必填，英數底線</div>
                        </div>
                        <div
                            style="padding: 10px; background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius: 8px; border-left: 4px solid #16a34a;">
                            <b style="color: #166534;">姓名</b>
                            <div style="color: #475569; font-size: 0.75rem;">選填，空白=帳號</div>
                        </div>
                        <div
                            style="padding: 10px; background: linear-gradient(135deg, #fefce8, #fef9c3); border-radius: 8px; border-left: 4px solid #ca8a04;">
                            <b style="color: #854d0e;">Email</b>
                            <div style="color: #475569; font-size: 0.75rem;">選填，自動產生</div>
                        </div>
                        <div
                            style="padding: 10px; background: linear-gradient(135deg, #fdf4ff, #f5d0fe); border-radius: 8px; border-left: 4px solid #a855f7;">
                            <b style="color: #7e22ce;">密碼 <span style="color:#dc2626;">*</span></b>
                            <div style="color: #475569; font-size: 0.75rem;">必填</div>
                        </div>
                        <div
                            style="padding: 10px; background: linear-gradient(135deg, #fff7ed, #fed7aa); border-radius: 8px; border-left: 4px solid #ea580c;">
                            <b style="color: #c2410c;">角色</b>
                            <div style="color: #475569; font-size: 0.75rem;">學生/老師</div>
                        </div>
                        <!-- 維度群組 -->
                        <div
                            style="padding: 10px; background: linear-gradient(135deg, #ecfeff, #a5f3fc); border-radius: 8px; border-left: 4px solid #0891b2;">
                            <b style="color: #0e7490;">職類</b>
                            <div style="color: #475569; font-size: 0.75rem;">用 <code>;</code> 隔開多個</div>
                        </div>
                        <div
                            style="padding: 10px; background: linear-gradient(135deg, #f0fdfa, #99f6e4); border-radius: 8px; border-left: 4px solid #0d9488;">
                            <b style="color: #0f766e;">所屬</b>
                            <div style="color: #475569; font-size: 0.75rem;">用 <code>;</code> 隔開多個</div>
                        </div>
                        <div
                            style="padding: 10px; background: linear-gradient(135deg, #faf5ff, #e9d5ff); border-radius: 8px; border-left: 4px solid #9333ea;">
                            <b style="color: #7c3aed;">屬性</b>
                            <div style="color: #475569; font-size: 0.75rem;">用 <code>;</code> 隔開多個</div>
                        </div>
                        <!-- 標籤放最後 -->
                        <div
                            style="padding: 10px; background: linear-gradient(135deg, #fef2f2, #fecaca); border-radius: 8px; border-left: 4px solid #dc2626;">
                            <b style="color: #991b1b;">標籤</b>
                            <div style="color: #475569; font-size: 0.75rem;">用 <code>;</code> 隔開多個</div>
                        </div>
                    </div>

                    <!-- 範例 -->
                    <div
                        style="background: #f8fafc; border-radius: 8px; padding: 12px; font-family: 'Consolas', monospace; font-size: 0.75rem; line-height: 1.8; overflow-x: auto;">
                        <div style="color: #94a3b8; margin-bottom: 4px;">// 欄位順序：帳號*,姓名,Email,密碼*,角色,職類,所屬,屬性,標籤</div>
                        <div><span style="color:#2563eb; font-weight: 600;">user01</span>,<span
                                style="color:#16a34a;">林大山</span>,<span
                                style="color:#ca8a04;">ds@tzuchi.org</span>,<span
                                style="color:#a855f7; font-weight: 600;">Pass123</span>,<span
                                style="color:#ea580c;">老師</span>,<span style="color:#0891b2;">護理職類</span>,<span
                                style="color:#0d9488;">9A病房</span>,<span style="color:#9333ea;">PGY;專科護理師</span>,<span
                                style="color:#dc2626;">臨床教師;2026新進</span></div>
                        <div><span style="color:#2563eb; font-weight: 600;">user02</span>,<span
                                style="color:#16a34a;">陳小芳</span>,,<span
                                style="color:#a855f7; font-weight: 600;">Pass456</span>,<span
                                style="color:#ea580c;">學生</span>,,,,<span style="color:#dc2626;">進修中</span></div>
                    </div>

                    <!-- 群組說明 -->
                    <div
                        style="margin-top: 12px; padding: 12px; background: #f0f9ff; border-radius: 8px; border: 1px solid #bae6fd; color: #0369a1; font-size: 0.8rem;">
                        <i class="fas fa-users"></i> <b>群組加入規則：</b><br>
                        • CSV 有填寫維度（職類/所屬/屬性）→ 加入該筆指定的群組<br>
                        • CSV 空白 → 統一加入下方「步驟 3」選擇的預設群組
                    </div>

                    <div
                        style="margin-top: 8px; padding: 10px; background: #fffbeb; border-radius: 6px; border: 1px solid #fef3c7; color: #92400e; font-size: 0.8rem;">
                        <i class="fas fa-info-circle"></i> <b>多值欄位：</b>維度和標籤可用 <code
                            style="background:#fef3c7; padding:2px 4px; border-radius:3px;">;</code> 隔開填入多個值
                    </div>
                </div>
            </div>

            <!-- Step 2: Input (moved up) -->
            <div style="display: flex; flex-direction: column; margin-bottom: 24px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span
                            style="width: 20px; height: 20px; background: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">2</span>
                        <h4 style="font-size: 0.95rem; font-weight: 600; color: #334155; margin: 0;">貼上清單或上傳檔案</h4>
                    </div>
                    <button class="btn-secondary" style="padding: 6px 14px; font-size: 0.85rem; border-radius: 24px;"
                        onclick="document.getElementById('batch-file-input').click()">
                        <i class="fas fa-file-upload"></i> 上傳 CSV/TXT 檔案
                    </button>
                    <input type="file" id="batch-file-input" accept=".csv,.txt" style="display:none;"
                        onchange="handleBatchFileSelect(this)">
                </div>

                <textarea id="batch-csv-text"
                    placeholder="帳號*,姓名,Email,密碼*,角色,職類,所屬,屬性,標籤 (每行一筆)&#10;&#10;user01,林大山,ds@tzuchi.org,Pass123,老師,護理職類,護理部,PGY;專科護理師,臨床教師;2026新進&#10;user02,陳小芳,,Pass456,學生,,,,進修中"
                    style="min-height: 150px; width: 100%; padding: 16px; border: 2px dashed #cbd5e1; border-radius: 12px; font-family: 'Consolas', monospace; font-size: 13px; line-height: 1.6; resize: none; background: #fff;"></textarea>
            </div>

            <!-- Step 3: Default Settings -->
            <div style="margin-bottom: 24px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                    <span
                        style="width: 20px; height: 20px; background: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">3</span>
                    <h4 style="font-size: 0.95rem; font-weight: 600; color: #334155; margin: 0;">預設值 <small
                            style="color: #94a3b8; font-weight: 400;">(CSV 空白時套用)</small></h4>
                </div>

                <!-- 角色選擇 -->
                <div
                    style="display: flex; gap: 16px; background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 12px;">
                    <div style="flex: 1;">
                        <label
                            style="display:block; font-size:0.85rem; font-weight: 500; color:#475569; margin-bottom:6px;">預設角色
                            <small style="color:#94a3b8;">(CSV 沒填時套用)</small></label>
                        <select id="batch-default-role" class="form-select"
                            style="padding:10px; border-radius: 8px; border-color: #cbd5e1; width: 100%;">
                            <option value="student">學生 (一般學員)</option>
                            <option value="coursecreator">老師 (開課教師)</option>
                        </select>
                    </div>
                </div>

                <!-- 維度群組選擇 -->
                <div style="background: #f0f9ff; padding: 16px; border-radius: 12px; border: 1px solid #bae6fd;">
                    <div style="font-size: 0.85rem; font-weight: 600; color: #0369a1; margin-bottom: 12px;">
                        <i class="fas fa-users"></i> 選擇要加入的群組（全部成員都會加入）
                    </div>
                    <div style="display: flex; gap: 16px;">
                        <div style="flex: 1;">
                            <label
                                style="display:block; font-size:0.85rem; font-weight: 500; color:#0369a1; margin-bottom:6px;">職類</label>
                            <select id="batch-dim1" class="form-select"
                                style="padding:10px; border-radius: 8px; border-color: #7dd3fc; width: 100%;">
                                <option value="">不指定</option>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <label
                                style="display:block; font-size:0.85rem; font-weight: 500; color:#0369a1; margin-bottom:6px;">所屬</label>
                            <select id="batch-dim2" class="form-select"
                                style="padding:10px; border-radius: 8px; border-color: #7dd3fc; width: 100%;">
                                <option value="">不指定</option>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <label
                                style="display:block; font-size:0.85rem; font-weight: 500; color:#0369a1; margin-bottom:6px;">屬性</label>
                            <select id="batch-dim3" class="form-select"
                                style="padding:10px; border-radius: 8px; border-color: #7dd3fc; width: 100%;">
                                <option value="">不指定</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Result log -->
            <div id="batch-result-log"
                style="display:none; height: 150px; overflow-y: auto; background: #0f172a; color: #f8fafc; padding: 16px; border-radius: 12px; font-size: 13px; font-family: 'Consolas', monospace; border: 1px solid #334155;">
            </div>
        </div>

        <!-- Footer -->
        <div class="form-actions"
            style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 24px; display: flex; gap: 12px; justify-content: flex-end;">
            <button type="button" class="btn-secondary"
                style="padding: 10px 24px; border-radius: 24px; background: white; border: 1px solid #e2e8f0; color: #64748b; font-weight: 600;"
                onclick="closeBatchModal()">取消</button>
            <button type="button" class="btn-primary" id="batch-run-btn"
                style="padding: 10px 32px; border-radius: 24px; background: #2563eb; color: white; border: none; font-weight: 600; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);"
                onclick="runBatchImport()">
                <i class="fas fa-play"></i> 確認並開始匯入
            </button>
        </div>
    </div>
</div>

<style>
    #batch-csv-text:focus {
        outline: none;
        border-color: #3b82f6;
        background: #f0f9ff;
    }

    .form-select:hover {
        border-color: #94a3b8;
    }

    .modal-overlay {
        z-index: 10001;
    }

    /* User Tag Selector */
    .user-tag-selector {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        padding: 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        min-height: 44px;
    }

    .selected-user-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .user-tag-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 16px;
        font-size: 0.8rem;
        font-weight: 500;
        background: var(--tag-color, #3b82f6);
        color: white;
    }

    .user-tag-chip .remove-chip {
        cursor: pointer;
        opacity: 0.8;
        margin-left: 2px;
    }

    .user-tag-chip .remove-chip:hover {
        opacity: 1;
    }

    .add-tag-btn {
        padding: 6px 12px;
        border-radius: 16px;
        border: 2px dashed #cbd5e1;
        background: white;
        color: #64748b;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .add-tag-btn:hover {
        border-color: #3b82f6;
        color: #3b82f6;
        background: #f0f9ff;
    }

    /* Tag Selector Modal */
    .user-tag-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10002;
    }

    .user-tag-modal-content {
        background: white;
        border-radius: 12px;
        padding: 24px;
        min-width: 400px;
        max-width: 90vw;
        max-height: 80vh;
        overflow-y: auto;
    }

    .user-tag-modal-content h3 {
        margin: 0 0 16px 0;
        font-size: 1.1rem;
        color: #1e293b;
    }

    .user-tag-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
        max-height: 300px;
        overflow-y: auto;
    }

    .user-tag-option {
        padding: 6px 14px;
        border-radius: 20px;
        border: 2px solid var(--tag-color, #3b82f6);
        background: white;
        color: var(--tag-color, #3b82f6);
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .user-tag-option:hover {
        background: color-mix(in srgb, var(--tag-color, #3b82f6) 10%, white);
    }

    .user-tag-option.selected {
        background: var(--tag-color, #3b82f6);
        color: white;
    }

    .user-tag-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    /* Searchable Dropdown */
    .searchable-select {
        position: relative;
    }

    .searchable-select::after {
        content: '▼';
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.7rem;
        color: #64748b;
        pointer-events: none;
    }

    .searchable-input {
        width: 100%;
        padding: 8px 32px 8px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.9rem;
        background: white;
        cursor: pointer;
    }

    .searchable-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .searchable-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        max-height: 280px;
        overflow-y: auto;
        z-index: 10003;
        margin-top: 4px;
    }

    .searchable-dropdown.open {
        display: block;
    }

    .searchable-option {
        padding: 10px 14px;
        cursor: pointer;
        font-size: 0.9rem;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
    }

    .searchable-option:last-child {
        border-bottom: none;
    }

    .searchable-option:hover {
        background: #f0f9ff;
        color: #0369a1;
    }

    .searchable-option.selected {
        background: #3b82f6;
        color: white;
    }

    .searchable-option.no-select {
        color: #94a3b8;
        font-style: italic;
    }
</style>

<!-- 載入標籤模組 -->
<script src="<?php echo $web_root; ?>/assets/js/modules/tags.js"></script>

<script>
    // User tag selector for edit modal
    let userTagCache = null;
    let currentUserTags = [];

    async function openUserTagSelector() {
        // Load tags if not cached
        if (!userTagCache) {
            try {
                const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=tags/course/available');
                const data = await res.json();
                if (data.success) {
                    userTagCache = data.data || [];
                } else {
                    showToast('載入標籤失敗', 'error');
                    return;
                }
            } catch (e) {
                showToast('載入標籤失敗', 'error');
                return;
            }
        }

        if (userTagCache.length === 0) {
            showToast('尚未建立任何標籤', 'warning');
            return;
        }

        // Get currently selected tag IDs
        const selectedIds = currentUserTags.map(t => String(t.id));

        // Create modal
        const modal = document.createElement('div');
        modal.className = 'user-tag-modal';
        modal.id = 'userTagModal';
        modal.innerHTML = `
        <div class="user-tag-modal-content">
            <h3><i class="fas fa-tags"></i> 選擇標籤</h3>
            <div class="user-tag-list" id="userTagList">
                ${userTagCache.map(t => `
                    <button type="button" class="user-tag-option ${selectedIds.includes(String(t.id)) ? 'selected' : ''}" 
                            data-tag-id="${t.id}" data-tag-name="${t.name}" data-tag-color="${t.color || '#3b82f6'}"
                            onclick="toggleUserTagOption(this)"
                            style="--tag-color: ${t.color || '#3b82f6'}">
                        ${t.name}
                    </button>
                `).join('')}
            </div>
            
            <!-- 新增標籤 -->
            <div class="new-tag-form" style="margin-top: 16px; padding-top: 16px; border-top: 1px dashed #e2e8f0;">
                <label style="font-size: 0.85rem; color: #64748b; display: block; margin-bottom: 8px;">
                    <i class="fas fa-plus-circle"></i> 新增標籤
                </label>
                <div style="display: flex; gap: 8px;">
                    <input type="color" id="newTagColor" value="#3b82f6" style="width: 40px; height: 36px; border: none; border-radius: 6px; cursor: pointer;">
                    <input type="text" id="newTagName" placeholder="輸入新標籤名稱..." style="flex: 1; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem;">
                    <button type="button" onclick="createNewTag()" style="padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
                        新增
                    </button>
                </div>
            </div>
            
            <div class="user-tag-modal-actions" style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn-secondary" onclick="closeUserTagModal()">取消</button>
                <button type="button" class="btn-primary" onclick="confirmUserTagSelection()">確認</button>
            </div>
        </div>
    `;
        document.body.appendChild(modal);
    }

    function toggleUserTagOption(btn) {
        btn.classList.toggle('selected');
    }

    function closeUserTagModal() {
        const modal = document.getElementById('userTagModal');
        if (modal) modal.remove();
    }

    function confirmUserTagSelection() {
        const modal = document.getElementById('userTagModal');
        const selectedBtns = modal.querySelectorAll('.user-tag-option.selected');

        currentUserTags = [];
        selectedBtns.forEach(btn => {
            currentUserTags.push({
                id: btn.dataset.tagId,
                name: btn.dataset.tagName,
                color: btn.dataset.tagColor
            });
        });

        renderUserTags();
        closeUserTagModal();
    }

    function renderUserTags() {
        const container = document.getElementById('edit-user-tags-container');
        const hiddenInput = document.getElementById('edit-user-tag-ids');

        container.innerHTML = currentUserTags.map(t => `
        <span class="user-tag-chip" style="--tag-color: ${t.color}">
            ${t.name}
            <span class="remove-chip" onclick="removeUserTag(${t.id})"><i class="fas fa-times"></i></span>
        </span>
    `).join('');

        hiddenInput.value = currentUserTags.map(t => t.name).join(';');
    }

    function removeUserTag(tagId) {
        currentUserTags = currentUserTags.filter(t => t.id != tagId);
        renderUserTags();
    }

    // 新增標籤並加到列表
    async function createNewTag() {
        const nameInput = document.getElementById('newTagName');
        const colorInput = document.getElementById('newTagColor');
        const name = nameInput.value.trim();
        const color = colorInput.value;

        if (!name) {
            showToast('請輸入標籤名稱', 'warning');
            return;
        }

        try {
            // 呼叫 API 新增標籤
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=tags/course/create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, color })
            });
            const data = await res.json();

            if (data.success) {
                const newTag = data.data;
                // 加到快取
                userTagCache.push(newTag);

                // 加到彈窗列表並選中
                const tagList = document.getElementById('userTagList');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'user-tag-option selected';
                btn.dataset.tagId = newTag.id;
                btn.dataset.tagName = newTag.name;
                btn.dataset.tagColor = newTag.color || '#3b82f6';
                btn.style.setProperty('--tag-color', newTag.color || '#3b82f6');
                btn.textContent = newTag.name;
                btn.onclick = function () { toggleUserTagOption(this); };
                tagList.appendChild(btn);

                // 清空輸入
                nameInput.value = '';
                showToast('標籤新增成功', 'success');
            } else {
                showToast(data.message || '新增失敗', 'error');
            }
        } catch (e) {
            showToast('新增標籤失敗', 'error');
        }
    }

    // === 新增成員的標籤選擇器 ===
    let addUserTags = [];

    async function openAddUserTagSelector() {
        // Load tags if not cached
        if (!userTagCache) {
            try {
                const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=tags/course/available');
                const data = await res.json();
                if (data.success) {
                    userTagCache = data.data || [];
                } else {
                    showToast('載入標籤失敗', 'error');
                    return;
                }
            } catch (e) {
                showToast('載入標籤失敗', 'error');
                return;
            }
        }

        if (userTagCache.length === 0) {
            showToast('尚未建立任何標籤', 'warning');
            return;
        }

        const selectedIds = addUserTags.map(t => String(t.id));

        const modal = document.createElement('div');
        modal.className = 'user-tag-modal';
        modal.id = 'addUserTagModal';
        modal.innerHTML = `
        <div class="user-tag-modal-content">
            <h3><i class="fas fa-tags"></i> 選擇標籤</h3>
            <div class="user-tag-list" id="addUserTagList">
                ${userTagCache.map(t => `
                    <button type="button" class="user-tag-option ${selectedIds.includes(String(t.id)) ? 'selected' : ''}" 
                            data-tag-id="${t.id}" data-tag-name="${t.name}" data-tag-color="${t.color || '#3b82f6'}"
                            onclick="toggleUserTagOption(this)"
                            style="--tag-color: ${t.color || '#3b82f6'}">
                        ${t.name}
                    </button>
                `).join('')}
            </div>
            
            <div class="new-tag-form" style="margin-top: 16px; padding-top: 16px; border-top: 1px dashed #e2e8f0;">
                <label style="font-size: 0.85rem; color: #64748b; display: block; margin-bottom: 8px;">
                    <i class="fas fa-plus-circle"></i> 新增標籤
                </label>
                <div style="display: flex; gap: 8px;">
                    <input type="color" id="addNewTagColor" value="#3b82f6" style="width: 40px; height: 36px; border: none; border-radius: 6px; cursor: pointer;">
                    <input type="text" id="addNewTagName" placeholder="輸入新標籤名稱..." style="flex: 1; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem;">
                    <button type="button" onclick="createNewTagForAdd()" style="padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
                        新增
                    </button>
                </div>
            </div>
            
            <div class="user-tag-modal-actions" style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn-secondary" onclick="closeAddUserTagModal()">取消</button>
                <button type="button" class="btn-primary" onclick="confirmAddUserTagSelection()">確認</button>
            </div>
        </div>
    `;
        document.body.appendChild(modal);
    }

    function closeAddUserTagModal() {
        const modal = document.getElementById('addUserTagModal');
        if (modal) modal.remove();
    }

    function confirmAddUserTagSelection() {
        const modal = document.getElementById('addUserTagModal');
        const selectedBtns = modal.querySelectorAll('.user-tag-option.selected');

        addUserTags = [];
        selectedBtns.forEach(btn => {
            addUserTags.push({
                id: btn.dataset.tagId,
                name: btn.dataset.tagName,
                color: btn.dataset.tagColor
            });
        });

        renderAddUserTags();
        closeAddUserTagModal();
    }

    function renderAddUserTags() {
        const container = document.getElementById('add-user-tags-container');
        const hiddenInput = document.getElementById('add-user-tag-ids');

        container.innerHTML = addUserTags.map(t => `
        <span class="user-tag-chip" style="--tag-color: ${t.color}">
            ${t.name}
            <span class="remove-chip" onclick="removeAddUserTag(${t.id})"><i class="fas fa-times"></i></span>
        </span>
    `).join('');

        hiddenInput.value = addUserTags.map(t => t.name).join(';');
    }

    function removeAddUserTag(tagId) {
        addUserTags = addUserTags.filter(t => t.id != tagId);
        renderAddUserTags();
    }

    async function createNewTagForAdd() {
        const nameInput = document.getElementById('addNewTagName');
        const colorInput = document.getElementById('addNewTagColor');
        const name = nameInput.value.trim();
        const color = colorInput.value;

        if (!name) {
            showToast('請輸入標籤名稱', 'warning');
            return;
        }

        try {
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=tags/course/create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, color })
            });
            const data = await res.json();

            if (data.success) {
                const newTag = data.data;
                userTagCache.push(newTag);

                const tagList = document.getElementById('addUserTagList');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'user-tag-option selected';
                btn.dataset.tagId = newTag.id;
                btn.dataset.tagName = newTag.name;
                btn.dataset.tagColor = newTag.color || '#3b82f6';
                btn.style.setProperty('--tag-color', newTag.color || '#3b82f6');
                btn.textContent = newTag.name;
                btn.onclick = function () { toggleUserTagOption(this); };
                tagList.appendChild(btn);

                nameInput.value = '';
                showToast('標籤新增成功', 'success');
            } else {
                showToast(data.message || '新增失敗', 'error');
            }
        } catch (e) {
            showToast('新增標籤失敗', 'error');
        }
    }

    // 重設新增成員表單時清空標籤
    const originalOpenAddUserModal = typeof openAddUserModal === 'function' ? openAddUserModal : null;

    // ====== 批次操作功能 ======

    function toggleSelectAllUsers(cb) {
        document.querySelectorAll('.user-batch-cb').forEach(c => c.checked = cb.checked);
        updateBatchBar();
    }

    function updateBatchBar() {
        const checked = document.querySelectorAll('.user-batch-cb:checked').length;
        const bar = document.getElementById('batch-action-bar');
        if (checked > 0) {
            bar.style.display = 'flex';
            document.getElementById('batch-count').textContent = checked;
        } else {
            bar.style.display = 'none';
        }
    }

    function clearBatchSelection() {
        document.querySelectorAll('.user-batch-cb').forEach(c => c.checked = false);
        const selectAll = document.getElementById('select-all-users');
        if (selectAll) selectAll.checked = false;
        updateBatchBar();
    }

    function getSelectedUsers() {
        return Array.from(document.querySelectorAll('.user-batch-cb:checked')).map(cb => {
            try { return JSON.parse(cb.dataset.user.replace(/&apos;/g, "'")); }
            catch (e) { return { id: cb.value }; }
        });
    }

    // 批次編輯 Modal
    function openBatchEditModal() {
        const users = getSelectedUsers();
        if (users.length === 0) { showToast('請先勾選人員', 'warning'); return; }

        const roleOptions = `
        <option value="student">學生</option>
        <option value="coursecreator">開課教師</option>
    `;

        // 建立維度下拉選項 HTML
        function buildDimSelect(dimName, cssClass, currentIds) {
            const opts = dimOptions[dimName] || [];
            if (opts.length === 0) return '<td style="font-size:0.8rem;color:#999;">-</td>';
            const curId = (currentIds && currentIds.length > 0) ? currentIds[0] : '';
            let html = `<td><select class="${cssClass}" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:0.8rem;background:#fff;">`;
            html += `<option value="">-- 未指定 --</option>`;
            opts.forEach(o => {
                const sel = (o.cohort_id == curId) ? ' selected' : '';
                html += `<option value="${o.cohort_id}"${sel}>${escapeHtml(o.display)}</option>`;
            });
            html += '</select></td>';
            return html;
        }

        let rows = users.map(u => {
            const roleVal = u.role || 'student';
            return `
        <tr data-uid="${u.id}">
            <td style="color:#888; font-size:0.8rem; white-space:nowrap;">${u.id}</td>
            <td><input type="text" class="be-fullname" value="${escapeHtml(u.fullname || '')}" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:0.8rem;"></td>
            <td style="color:#64748b;font-size:0.8rem;white-space:nowrap;">${escapeHtml(u.username)}</td>
            <td><input type="email" class="be-email" value="${escapeHtml(u.email || '')}" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:0.8rem;"></td>
            <td><input type="password" class="be-password" placeholder="不修改" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:0.8rem;"></td>
            <td>
                <select class="be-role" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:0.8rem;background:#fff;">
                    ${roleOptions.replace(`value="${roleVal}"`, `value="${roleVal}" selected`)}
                </select>
            </td>
            ${buildDimSelect('職類', 'be-dim-job', u.dim_職類_ids)}
            ${buildDimSelect('所屬', 'be-dim-dept', u.dim_所屬_ids)}
            ${buildDimSelect('屬性', 'be-dim-attr', u.dim_屬性_ids)}
            <td><input type="text" class="be-tags" value="${escapeHtml(u.tags || '')}" placeholder="用;分隔" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:0.8rem;"></td>
        </tr>`;
        }).join('');

        // 建立 modal
        let modal = document.getElementById('batch-edit-modal');
        if (modal) modal.remove();

        modal = document.createElement('div');
        modal.id = 'batch-edit-modal';
        modal.className = 'modal-overlay';
        modal.style.display = 'flex';
        modal.innerHTML = `
        <div class="modal-content" style="max-width:95vw; width:1200px; padding:24px; max-height:85vh; display:flex; flex-direction:column;">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color:#3b82f6;"></i> 批次編輯 (${users.length} 人)</h3>
                <button class="modal-close" onclick="closeBatchEditModal()">&times;</button>
            </div>
            <div style="flex:1; overflow:auto; margin:16px 0;">
                <table class="member-table" style="font-size:0.8rem; white-space:nowrap;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th style="min-width:100px;">姓名</th>
                            <th>帳號</th>
                            <th style="min-width:140px;">Email</th>
                            <th style="min-width:100px;">新密碼</th>
                            <th style="min-width:90px;">角色</th>
                            <th style="color:#0e7490;min-width:130px;">職類</th>
                            <th style="color:#0f766e;min-width:130px;">所屬</th>
                            <th style="color:#7c3aed;min-width:130px;">屬性</th>
                            <th style="min-width:100px;">標籤</th>
                        </tr>
                    </thead>
                    <tbody id="batch-edit-body">
                        ${rows}
                    </tbody>
                </table>
            </div>
            <div class="form-actions" style="border-top:1px solid #e2e8f0; padding-top:16px;">
                <button type="button" class="btn-secondary" onclick="closeBatchEditModal()">取消</button>
                <button type="button" class="btn-primary" onclick="saveBatchEdit()">
                    <i class="fas fa-save"></i> 儲存全部
                </button>
            </div>
        </div>
    `;
        document.body.appendChild(modal);
    }

    function closeBatchEditModal() {
        const modal = document.getElementById('batch-edit-modal');
        if (modal) modal.remove();
    }

    async function saveBatchEdit() {
        const rows = document.querySelectorAll('#batch-edit-body tr');
        const updates = [];

        rows.forEach(row => {
            const uid = row.dataset.uid;
            const fullname = row.querySelector('.be-fullname').value.trim();
            const email = row.querySelector('.be-email').value.trim();
            const password = row.querySelector('.be-password').value;
            const role = row.querySelector('.be-role').value;
            const tags = row.querySelector('.be-tags').value.trim();

            // 維度群組
            const dimJob = row.querySelector('.be-dim-job');
            const dimDept = row.querySelector('.be-dim-dept');
            const dimAttr = row.querySelector('.be-dim-attr');

            updates.push({
                id: parseInt(uid),
                fullname: fullname,
                email: email || null,
                password: password || null,
                role: role || null,
                tags: tags || null,
                dim_job: dimJob ? dimJob.value : null,
                dim_dept: dimDept ? dimDept.value : null,
                dim_attr: dimAttr ? dimAttr.value : null
            });
        });

        if (updates.length === 0) return;

        try {
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=members/batch_update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ users: updates })
            });
            const data = await res.json();

            if (data.success) {
                showToast(`已更新 ${updates.length} 位成員`, 'success');
                closeBatchEditModal();
                clearBatchSelection();
                loadUsers();
            } else {
                showToast(data.error || '更新失敗', 'error');
            }
        } catch (e) {
            showToast('網路錯誤', 'error');
        }
    }

    // 批次刪除
    async function batchDeleteUsers() {
        const users = getSelectedUsers();
        if (users.length === 0) return;

        const names = users.map(u => u.fullname || u.username || u.id).join(', ');
        if (!confirm(`確定要刪除這 ${users.length} 位成員？\n${names}`)) return;
        if (!confirm('此操作無法復原，確定要繼續嗎？')) return;

        const userIds = users.map(u => u.id);

        try {
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=members/batch_delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_ids: userIds })
            });
            const data = await res.json();

            if (data.success) {
                showToast(`已刪除 ${users.length} 位成員`, 'success');
                clearBatchSelection();
                loadUsers();
            } else {
                showToast(data.error || '刪除失敗', 'error');
            }
        } catch (e) {
            showToast('網路錯誤', 'error');
        }
    }
</script>