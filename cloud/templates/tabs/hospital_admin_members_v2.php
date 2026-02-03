<?php
/**
 * 院區管理員 - 新版成員管理介面
 * templates/tabs/hospital_admin_members_v2.php
 * 
 * 使用新設計系統
 */
require_once __DIR__ . '/../../includes/config.php';
?>

<div id="section-member-management" class="page-section">
    <!-- 頁面標題 -->
    <div class="page-header-v2">
        <h1 class="page-header-v2__title">成員管理</h1>
        <p class="page-header-v2__subtitle">
            管理
            <?php echo h($_SESSION['institution'] ?? ''); ?> 院區的成員帳號與權限
        </p>
    </div>

    <!-- 統計卡片 -->
    <div class="stats-grid-v2">
        <div class="stat-card-v2">
            <div class="stat-card-v2__label">總成員數</div>
            <div class="stat-card-v2__value" id="stat-total">--</div>
        </div>
        <div class="stat-card-v2">
            <div class="stat-card-v2__label">開課教師</div>
            <div class="stat-card-v2__value" id="stat-teachers">--</div>
        </div>
        <div class="stat-card-v2">
            <div class="stat-card-v2__label">一般學員</div>
            <div class="stat-card-v2__value" id="stat-students">--</div>
        </div>
    </div>

    <!-- 工具列 -->
    <div class="toolbar-v2">
        <div class="toolbar-v2__search">
            <i class="fas fa-search toolbar-v2__search-icon"></i>
            <input type="text" class="toolbar-v2__search-input" id="member-search" placeholder="搜尋成員姓名或帳號..."
                oninput="filterMembers(this.value)">
        </div>
        <div class="toolbar-v2__actions">
            <button class="btn-v2 btn-v2--primary" onclick="openMemberModal('add')">
                <i class="fas fa-plus"></i>
                新增成員
            </button>
        </div>
    </div>

    <!-- 成員列表卡片 -->
    <div class="card-v2">
        <div class="card-v2__body" id="members-list" style="padding: 0;">
            <!-- 載入中狀態 -->
            <div class="empty-state-v2">
                <div class="empty-state-v2__icon">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
                <div class="empty-state-v2__title">載入中...</div>
            </div>
        </div>
    </div>
</div>

<!-- 新增/編輯成員 Modal -->
<div id="member-modal" class="modal-v2">
    <div class="modal-v2__backdrop" onclick="closeMemberModal()"></div>
    <div class="modal-v2__content">
        <div class="modal-v2__header">
            <h3 class="modal-v2__title" id="modal-title">新增成員</h3>
            <button class="modal-v2__close" onclick="closeMemberModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="member-form" onsubmit="saveMember(event)">
            <div class="modal-v2__body">
                <input type="hidden" id="member-id" name="id">
                <input type="hidden" id="modal-mode" name="mode" value="add">

                <div class="form-group-v2">
                    <label class="form-label-v2 form-label-v2--required">帳號</label>
                    <input type="text" class="form-input-v2" id="member-username" name="username" required
                        pattern="[a-zA-Z0-9_]+" placeholder="英文、數字或底線">
                </div>

                <div class="form-group-v2">
                    <label class="form-label-v2 form-label-v2--required">姓名</label>
                    <input type="text" class="form-input-v2" id="member-fullname" name="fullname" required
                        placeholder="請輸入真實姓名">
                </div>

                <div class="form-group-v2">
                    <label class="form-label-v2">Email</label>
                    <input type="email" class="form-input-v2" id="member-email" name="email" placeholder="選填">
                </div>

                <div class="form-group-v2">
                    <label class="form-label-v2" id="password-label">密碼</label>
                    <input type="password" class="form-input-v2" id="member-password" name="password"
                        placeholder="新增時必填">
                </div>

                <div class="form-group-v2">
                    <label class="form-label-v2 form-label-v2--required">角色</label>
                    <select class="form-input-v2 form-select-v2" id="member-role" name="role" required
                        onchange="onRoleChange(this.value)">
                        <option value="student">一般學員</option>
                        <option value="coursecreator">開課教師</option>
                    </select>
                </div>

                <!-- 開課類別選擇（開課教師時顯示） -->
                <div class="form-group-v2" id="category-selection-group" style="display: none;">
                    <label class="form-label-v2">開課類別（可多選）</label>
                    <div id="member-categories" class="checkbox-group-v2"
                        style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2);">
                        <div style="color: var(--text-muted);">載入中...</div>
                    </div>
                    <small style="color: var(--text-muted); font-size: 12px;">選擇可在哪些類別底下開課</small>
                </div>


                <!-- 屬性設定 -->
                <div class="form-group-v2">
                    <label class="form-label-v2">院區</label>
                    <select class="form-input-v2 form-select-v2" id="member-hospital" name="hospital_id" disabled>
                        <option value="">-- 目前院區 --</option>
                    </select>
                    <small style="color: var(--text-muted); font-size: 12px;">成員預設為目前管理的院區</small>
                </div>

                <div class="form-group-v2">
                    <label class="form-label-v2">部門（職類）</label>
                    <select class="form-input-v2 form-select-v2" id="member-department" name="department_id">
                        <option value="">-- 選擇部門 --</option>
                    </select>
                </div>

                <div class="form-group-v2">
                    <label class="form-label-v2">職稱（可多選）</label>
                    <div id="member-jobtitles" class="checkbox-group-v2"
                        style="max-height: 150px; overflow-y: auto; border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--space-2);">
                        <!-- 職稱選項會由 JavaScript 動態載入 -->
                        <div style="color: var(--text-muted);">載入中...</div>
                    </div>
                </div>
            </div>
            <div class="modal-v2__footer">
                <button type="button" class="btn-v2 btn-v2--secondary" onclick="closeMemberModal()">
                    取消
                </button>
                <button type="submit" class="btn-v2 btn-v2--primary" id="modal-submit-btn">
                    儲存
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 刪除確認 Modal -->
<div id="delete-modal" class="modal-v2">
    <div class="modal-v2__backdrop" onclick="closeDeleteModal()"></div>
    <div class="modal-v2__content" style="max-width: 400px;">
        <div class="modal-v2__header">
            <h3 class="modal-v2__title">
                <i class="fas fa-exclamation-triangle" style="color: var(--error);"></i>
                確認刪除
            </h3>
            <button class="modal-v2__close" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-v2__body" style="text-align: center;">
            <p style="margin-bottom: var(--space-3);">
                確定要刪除成員 <strong id="delete-member-name"></strong> 嗎？
            </p>
            <p style="color: var(--error); font-size: 13px;">
                此操作將同時刪除 Moodle 帳號，無法復原！
            </p>
        </div>
        <div class="modal-v2__footer">
            <button type="button" class="btn-v2 btn-v2--secondary" onclick="closeDeleteModal()">
                取消
            </button>
            <button type="button" class="btn-v2 btn-v2--danger" id="confirm-delete-btn" onclick="executeDelete()">
                確認刪除
            </button>
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';

        // 共享 BASE_URL
        if (typeof window.CLOUD_BASE_URL === 'undefined') {
            window.CLOUD_BASE_URL = '<?= BASE_URL ?>';
        }
        const BASE_URL = window.CLOUD_BASE_URL;
        const MANAGEMENT_CATEGORY_ID = <?php echo (int) ($_SESSION['management_category_id'] ?? 0); ?>;


        let allMembers = [];
        let deleteTargetId = null;
        let attrTypes = {};
        let attrValues = { hospital: [], department: [], job_title: [] };
        let currentHospitalId = null; // 目前院區 ID
        let subcategories = []; // Moodle 子類別
        let editingMemberId = null; // 目前編輯的成員 ID

        // 頁面載入
        document.addEventListener('DOMContentLoaded', function () {
            if (document.getElementById('section-member-management')) {
                loadMembers();
                loadAttributeOptions();
                loadSubcategories();
            }
        });

        // 載入屬性選項
        async function loadAttributeOptions() {
            try {
                // 先載入屬性類型
                const typesRes = await fetch(`${BASE_URL}/api/admin/attribute_types.php`);
                const typesData = await typesRes.json();
                if (typesData.success) {
                    typesData.data.forEach(t => { attrTypes[t.code] = t; });
                }

                // 載入院區
                if (attrTypes.hospital) {
                    const hospitalRes = await fetch(`${BASE_URL}/api/admin/attribute_values.php?type_id=${attrTypes.hospital.id}`);
                    const hospitalData = await hospitalRes.json();
                    if (hospitalData.success) {
                        attrValues.hospital = hospitalData.data || [];
                        populateSelect('member-hospital', attrValues.hospital);
                        // 設定目前院區（從 session 取得）
                        setCurrentHospital();
                    }
                }

                // 載入部門
                if (attrTypes.department) {
                    const deptRes = await fetch(`${BASE_URL}/api/admin/attribute_values.php?type_id=${attrTypes.department.id}`);
                    const deptData = await deptRes.json();
                    if (deptData.success) {
                        attrValues.department = deptData.data || [];
                        populateSelect('member-department', attrValues.department);
                    }
                }

                // 載入職稱（多選 checkbox）
                if (attrTypes.job_title) {
                    const jobRes = await fetch(`${BASE_URL}/api/admin/attribute_values.php?type_id=${attrTypes.job_title.id}`);
                    const jobData = await jobRes.json();
                    if (jobData.success) {
                        attrValues.job_title = jobData.data || [];
                        populateJobTitleCheckboxes(attrValues.job_title);
                    }
                }
            } catch (e) {
                console.error('Load attributes error:', e);
            }
        }

        // 設定目前院區
        function setCurrentHospital() {
            // 從頁面取得 session 中的機構名稱，找對應的院區屬性
            const institutionName = '<?= $_SESSION['institution'] ?? '' ?>';
            const hospitalSelect = document.getElementById('member-hospital');

            // 嘗試匹配院區名稱
            for (const h of attrValues.hospital) {
                if (institutionName.includes(h.name) || h.name.includes(institutionName)) {
                    hospitalSelect.value = h.id;
                    currentHospitalId = h.id;
                    break;
                }
            }

            // 如果只有一個院區，自動選擇
            if (!currentHospitalId && attrValues.hospital.length === 1) {
                hospitalSelect.value = attrValues.hospital[0].id;
                currentHospitalId = attrValues.hospital[0].id;
            }
        }

        // 載入 Moodle 子類別
        async function loadSubcategories() {
            try {
                const res = await fetch(`${BASE_URL}/api/hospital_admin/list_subcategories.php`);
                const data = await res.json();
                if (data.success) {
                    subcategories = data.data || [];
                    populateCategoryCheckboxes(subcategories);
                }
            } catch (e) {
                console.error('Load subcategories error:', e);
            }
        }

        // 填充類別 checkbox
        function populateCategoryCheckboxes(items, selectedIds = []) {
            const container = document.getElementById('member-categories');
            if (!container) return;

            container.innerHTML = '';

            // Add Whole Hospital Checkbox (Parent Category)
            if (MANAGEMENT_CATEGORY_ID > 0) {
                const globalLabel = document.createElement('label');
                globalLabel.className = 'checkbox-item-v2';
                globalLabel.style.display = 'flex';
                globalLabel.style.alignItems = 'center';
                globalLabel.style.padding = '4px 8px';
                globalLabel.style.cursor = 'pointer';
                globalLabel.style.borderBottom = '1px solid var(--border)';
                globalLabel.style.marginBottom = '4px';
                globalLabel.style.fontWeight = 'bold';

                const globalCheckbox = document.createElement('input');
                globalCheckbox.type = 'checkbox';
                globalCheckbox.name = 'category_ids[]';
                globalCheckbox.value = MANAGEMENT_CATEGORY_ID;
                globalCheckbox.style.marginRight = '8px';
                if (selectedIds.includes(MANAGEMENT_CATEGORY_ID)) {
                    globalCheckbox.checked = true;
                }

                const globalText = document.createTextNode('全院區 (包含所有部門)');

                globalLabel.appendChild(globalCheckbox);
                globalLabel.appendChild(globalText);
                container.appendChild(globalLabel);
            }

            if (items.length === 0) {

                container.innerHTML = '<div style="color: var(--text-muted);">尚無子類別</div>';
                return;
            }

            items.forEach(item => {
                const label = document.createElement('label');
                label.className = 'checkbox-item-v2';
                label.style.display = 'flex';
                label.style.alignItems = 'center';
                label.style.padding = '4px 8px';
                label.style.cursor = 'pointer';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'category_ids[]';
                checkbox.value = item.id;
                checkbox.style.marginRight = '8px';
                if (selectedIds.includes(item.id)) {
                    checkbox.checked = true;
                }

                const text = document.createTextNode(item.name);

                label.appendChild(checkbox);
                label.appendChild(text);
                container.appendChild(label);
            });
        }

        // 角色切換時顯示/隱藏類別選擇
        function onRoleChange(role) {
            const categoryGroup = document.getElementById('category-selection-group');
            if (role === 'coursecreator') {
                categoryGroup.style.display = 'block';
            } else {
                categoryGroup.style.display = 'none';
                // 清除選擇
                document.querySelectorAll('#member-categories input[type="checkbox"]').forEach(cb => cb.checked = false);
            }
        }

        // 填充職稱 checkbox
        function populateJobTitleCheckboxes(items) {
            const container = document.getElementById('member-jobtitles');
            if (!container) return;

            container.innerHTML = '';

            if (items.length === 0) {
                container.innerHTML = '<div style="color: var(--text-muted);">尚無職稱選項</div>';
                return;
            }

            items.forEach(item => {
                const label = document.createElement('label');
                label.className = 'checkbox-item-v2';
                label.style.display = 'flex';
                label.style.alignItems = 'center';
                label.style.padding = '4px 8px';
                label.style.cursor = 'pointer';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'job_title_ids[]';
                checkbox.value = item.id;
                checkbox.style.marginRight = '8px';

                const text = document.createTextNode(item.name);

                label.appendChild(checkbox);
                label.appendChild(text);
                container.appendChild(label);
            });
        }

        // 填充下拉選單
        function populateSelect(selectId, items) {
            const select = document.getElementById(selectId);
            if (!select) return;

            // 保留第一個選項
            const firstOption = select.options[0];
            select.innerHTML = '';
            select.appendChild(firstOption);

            items.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.name;
                select.appendChild(opt);
            });
        }

        // 載入成員列表
        function loadMembers() {
            fetch(`${BASE_URL}/api/hospital_admin/list_members.php`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        allMembers = data.data || [];
                        renderMembers(allMembers);
                        updateStats(allMembers);
                    } else {
                        showError(data.error || '無法載入成員列表');
                    }
                })
                .catch(err => {
                    console.error('Load members error:', err);
                    showError('網路錯誤，無法載入成員列表');
                });
        }

        // 更新統計
        function updateStats(members) {
            const teachers = members.filter(m => m.role === 'coursecreator' || m.role === 'teacherplus').length;
            const students = members.length - teachers;

            document.getElementById('stat-total').textContent = members.length;
            document.getElementById('stat-teachers').textContent = teachers;
            document.getElementById('stat-students').textContent = students;
        }

        // 渲染成員列表
        function renderMembers(members) {
            const container = document.getElementById('members-list');

            if (!members || members.length === 0) {
                container.innerHTML = `
            <div class="empty-state-v2">
                <div class="empty-state-v2__icon"><i class="fas fa-users"></i></div>
                <div class="empty-state-v2__title">尚無成員資料</div>
                <div class="empty-state-v2__desc">點擊「新增成員」開始建立帳號</div>
                <button class="btn-v2 btn-v2--primary" onclick="openMemberModal('add')">
                    <i class="fas fa-plus"></i> 新增第一位成員
                </button>
            </div>`;
                return;
            }

            let html = `<table class="table-v2">
        <thead>
            <tr>
                <th>帳號</th>
                <th>姓名</th>
                <th>Email</th>
                <th>角色</th>
                <th style="text-align: right;">操作</th>
            </tr>
        </thead>
        <tbody>`;

            members.forEach(m => {
                const isTeacher = m.role === 'teacherplus' || m.role === 'coursecreator';
                const roleBadge = isTeacher
                    ? '<span class="badge-v2 badge-v2--primary"><i class="fas fa-chalkboard-teacher"></i> 開課教師</span>'
                    : '<span class="badge-v2 badge-v2--default"><i class="fas fa-user"></i> 學員</span>';

                html += `<tr>
            <td>
                <code style="background: var(--bg-muted); padding: 2px 6px; border-radius: 4px; font-size: 13px;">${escapeHtml(m.username)}</code>
                ${!m.hospital_id ? '<span class="badge-v2 badge-v2--warning" style="margin-left:8px; font-size:11px;">未分配</span>' : ''}
            </td>
            <td><strong>${escapeHtml(m.fullname || m.username)}</strong></td>
            <td style="color: var(--text-secondary); font-size: 13px;">${m.email ? escapeHtml(m.email) : '<span style="color: var(--text-muted);">—</span>'}</td>
            <td>${roleBadge}</td>
            <td style="text-align: right;">
                <button class="btn-v2 btn-v2--ghost btn-v2--sm btn-v2--icon" onclick="openMemberModal('edit', ${m.id})" title="編輯">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-v2 btn-v2--ghost btn-v2--sm btn-v2--icon" onclick="openDeleteModal(${m.id}, '${escapeHtml(m.fullname || m.username)}')" title="刪除" style="color: var(--error);">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }



        // 搜尋過濾
        function filterMembers(query) {
            if (!query) {
                renderMembers(allMembers);
                return;
            }
            const q = query.toLowerCase();
            const filtered = allMembers.filter(m =>
                (m.username && m.username.toLowerCase().includes(q)) ||
                (m.fullname && m.fullname.toLowerCase().includes(q)) ||
                (m.email && m.email.toLowerCase().includes(q))
            );
            renderMembers(filtered);
        }

        // 開啟 Modal
        async function openMemberModal(mode, memberId) {
            const modal = document.getElementById('member-modal');
            const form = document.getElementById('member-form');
            const title = document.getElementById('modal-title');
            const usernameField = document.getElementById('member-username');
            const passwordLabel = document.getElementById('password-label');
            const passwordField = document.getElementById('member-password');

            form.reset();
            document.getElementById('modal-mode').value = mode;
            document.getElementById('member-id').value = memberId || '';

            // 重置屬性選擇
            document.getElementById('member-department').value = '';
            // 重置職稱 checkbox
            document.querySelectorAll('#member-jobtitles input[type="checkbox"]').forEach(cb => cb.checked = false);
            // 重置類別選擇
            document.querySelectorAll('#member-categories input[type="checkbox"]').forEach(cb => cb.checked = false);
            document.getElementById('category-selection-group').style.display = 'none';
            editingMemberId = memberId || null;

            if (mode === 'add') {
                title.textContent = '新增成員';
                usernameField.disabled = false;
                passwordField.required = true;
                passwordLabel.classList.add('form-label-v2--required');
                passwordField.placeholder = '請設定密碼';
            } else {
                title.textContent = '編輯成員';
                usernameField.disabled = true;
                passwordField.required = false;
                passwordLabel.classList.remove('form-label-v2--required');
                passwordField.placeholder = '留空表示不修改';

                const member = allMembers.find(m => m.id == memberId);
                if (member) {
                    document.getElementById('member-username').value = member.username;
                    document.getElementById('member-fullname').value = member.fullname || '';
                    document.getElementById('member-email').value = member.email || '';

                    // 判斷角色並設定
                    const isCoursecreator = member.role === 'coursecreator' || member.role === 'teacherplus';
                    document.getElementById('member-role').value = isCoursecreator ? 'coursecreator' : 'student';

                    // 如果是開課教師，顯示類別選擇
                    if (isCoursecreator) {
                        document.getElementById('category-selection-group').style.display = 'block';
                    }

                    // 載入使用者屬性
                    try {
                        const res = await fetch(`${BASE_URL}/api/admin/user_attributes.php?user_id=${memberId}`);
                        const data = await res.json();
                        if (data.success && data.data) {
                            // API 回傳的是以 type_code 為 key 的物件
                            const attrs = data.data;

                            // 載入部門
                            if (attrs.department && attrs.department.values && attrs.department.values.length > 0) {
                                document.getElementById('member-department').value = attrs.department.values[0].id;
                            }

                            // 載入院區
                            if (attrs.hospital && attrs.hospital.values && attrs.hospital.values.length > 0) {
                                document.getElementById('member-hospital').value = attrs.hospital.values[0].id;
                                document.getElementById('member-hospital').disabled = true; // 已分配則鎖定
                            } else {
                                // 未分配，允許此管理員認領
                                document.getElementById('member-hospital').disabled = false;
                                // 預設選中目前院區
                                if (currentHospitalId) {
                                    document.getElementById('member-hospital').value = currentHospitalId;
                                }
                            }

                            // 載入職稱 (多選)
                            if (attrs.job_title && attrs.job_title.values) {
                                attrs.job_title.values.forEach(val => {
                                    const checkbox = document.querySelector(`#member-jobtitles input[value="${val.id}"]`);
                                    if (checkbox) checkbox.checked = true;
                                });
                            }
                        }
                    } catch (e) {
                        console.error('Load user attributes error:', e);
                    }

                    // 載入使用者的類別權限
                    if (isCoursecreator) {
                        try {
                            const catRes = await fetch(`${BASE_URL}/api/hospital_admin/get_user_categories.php?user_id=${memberId}`);
                            const catData = await catRes.json();
                            if (catData.success && catData.data) {
                                catData.data.forEach(catId => {
                                    const checkbox = document.querySelector(`#member-categories input[value="${catId}"]`);
                                    if (checkbox) checkbox.checked = true;
                                });
                            }
                        } catch (e) {
                            console.error('Load user categories error:', e);
                        }
                    }
                }
            }

            modal.classList.add('is-open');
            setTimeout(() => {
                (mode === 'add' ? usernameField : document.getElementById('member-fullname')).focus();
            }, 100);
        }

        function closeMemberModal() {
            document.getElementById('member-modal').classList.remove('is-open');
        }

        // 儲存成員
        async function saveMember(event) {
            event.preventDefault();

            const form = document.getElementById('member-form');
            const formData = new FormData(form);

            // 由於院區欄位是 disabled 的，FormData 不會包含它，需手動加入
            if (!formData.has('hospital_id') && currentHospitalId) {
                formData.append('hospital_id', currentHospitalId);
            }

            const mode = formData.get('mode');
            const memberId = formData.get('id');
            const role = formData.get('role');
            const endpoint = mode === 'add'
                ? `${BASE_URL}/api/hospital_admin/add_member.php`
                : `${BASE_URL}/api/hospital_admin/update_member.php`;

            const submitBtn = document.getElementById('modal-submit-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 處理中...';

            try {
                // 儲存成員基本資料
                const res = await fetch(endpoint, { method: 'POST', body: formData });
                const data = await res.json();

                if (!data.success) {
                    throw new Error(data.error || '操作失敗');
                }

                // 取得成員 ID（新增時從回傳取得）
                const userId = mode === 'add' ? data.user_id : memberId;

                // 如果選擇開課教師，儲存類別權限
                if (role === 'coursecreator' && userId) {
                    const selectedCategories = [];
                    document.querySelectorAll('#member-categories input[type="checkbox"]:checked').forEach(cb => {
                        selectedCategories.push(parseInt(cb.value));
                    });

                    const catFormData = new FormData();
                    catFormData.append('user_id', userId);
                    catFormData.append('category_ids', JSON.stringify(selectedCategories));

                    const catRes = await fetch(`${BASE_URL}/api/hospital_admin/set_user_categories.php`, {
                        method: 'POST',
                        body: catFormData
                    });
                    const catData = await catRes.json();

                    if (!catData.success) {
                        console.warn('類別權限儲存失敗:', catData.error);
                    }
                } else if (role === 'student' && userId) {
                    // 如果變成學生，清除所有類別權限
                    const catFormData = new FormData();
                    catFormData.append('user_id', userId);
                    catFormData.append('category_ids', JSON.stringify([]));

                    await fetch(`${BASE_URL}/api/hospital_admin/set_user_categories.php`, {
                        method: 'POST',
                        body: catFormData
                    });
                }

                closeMemberModal();
                loadMembers();
                showToast(mode === 'add' ? '成員已新增' : '成員已更新', 'success');

            } catch (err) {
                console.error('Save error:', err);
                showToast(err.message || '網路錯誤', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '儲存';
            }
        }

        // 切換角色
        function toggleRole(memberId, currentRole) {
            const newRole = currentRole === 'student' ? 'coursecreator' : 'student';
            const roleLabel = newRole === 'coursecreator' ? '開課教師' : '學員';

            if (!confirm(`確定要將此成員的角色變更為「${roleLabel}」嗎？`)) return;

            fetch(`${BASE_URL}/api/hospital_admin/change_role.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${memberId}&role=${newRole}`
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        loadMembers();
                        showToast('角色已變更', 'success');
                    } else {
                        showToast(data.error || '變更失敗', 'error');
                    }
                })
                .catch(err => showToast('網路錯誤', 'error'));
        }

        // 刪除相關
        function openDeleteModal(memberId, memberName) {
            deleteTargetId = memberId;
            document.getElementById('delete-member-name').textContent = memberName;
            document.getElementById('delete-modal').classList.add('is-open');
        }

        function closeDeleteModal() {
            document.getElementById('delete-modal').classList.remove('is-open');
            deleteTargetId = null;
        }

        function executeDelete() {
            if (!deleteTargetId) return;

            const btn = document.getElementById('confirm-delete-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 刪除中...';

            fetch(`${BASE_URL}/api/hospital_admin/delete_member.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${deleteTargetId}`
            })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '確認刪除';

                    if (data.success) {
                        closeDeleteModal();
                        loadMembers();
                        showToast('成員已刪除', 'success');
                    } else {
                        showToast(data.error || '刪除失敗', 'error');
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = '確認刪除';
                    showToast('網路錯誤', 'error');
                });
        }

        // 工具函數
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showError(msg) {
            document.getElementById('members-list').innerHTML = `
        <div class="empty-state-v2">
            <div class="empty-state-v2__icon" style="color: var(--error);"><i class="fas fa-exclamation-circle"></i></div>
            <div class="empty-state-v2__title">${escapeHtml(msg)}</div>
            <button class="btn-v2 btn-v2--secondary" onclick="loadMembers()">
                <i class="fas fa-sync-alt"></i> 重試
            </button>
        </div>`;
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
                closeMemberModal();
                closeDeleteModal();
            }
        });

        // 暴露需要的函數到全域
        window.openMemberModal = openMemberModal;
        window.closeMemberModal = closeMemberModal;
        window.filterMembers = filterMembers;
        window.toggleRole = toggleRole;
        window.openDeleteModal = openDeleteModal;
        window.closeDeleteModal = closeDeleteModal;
        window.executeDelete = executeDelete;
        window.saveMember = saveMember;
        window.loadMembers = loadMembers;
        window.onRoleChange = onRoleChange;
    })();
</script>