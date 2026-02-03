
    (function () {
        'use strict';

        // 共享 BASE_URL
        if (typeof window.CLOUD_BASE_URL === 'undefined') {
            window.CLOUD_BASE_URL = '"/cloud"';
        }
        const BASE_URL = window.CLOUD_BASE_URL;

        let allMembers = [];
        let deleteTargetId = null;
        let attrTypes = {};
        let attrValues = { department: [], job_title: [] };

        // 頁面載入
        document.addEventListener('DOMContentLoaded', function () {
            if (document.getElementById('section-member-management')) {
                loadMembers();
                loadAttributeOptions();
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

                // 載入部門
                if (attrTypes.department) {
                    const deptRes = await fetch(`${BASE_URL}/api/admin/attribute_values.php?type_id=${attrTypes.department.id}`);
                    const deptData = await deptRes.json();
                    if (deptData.success) {
                        attrValues.department = deptData.data || [];
                        populateSelect('member-department', attrValues.department);
                    }
                }

                // 載入職稱  
                if (attrTypes.job_title) {
                    const jobRes = await fetch(`${BASE_URL}/api/admin/attribute_values.php?type_id=${attrTypes.job_title.id}`);
                    const jobData = await jobRes.json();
                    if (jobData.success) {
                        attrValues.job_title = jobData.data || [];
                        populateSelect('member-jobtitle', attrValues.job_title);
                    }
                }
            } catch (e) {
                console.error('Load attributes error:', e);
            }
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
            <td><code style="background: var(--bg-muted); padding: 2px 6px; border-radius: 4px; font-size: 13px;">${escapeHtml(m.username)}</code></td>
            <td><strong>${escapeHtml(m.fullname || m.username)}</strong></td>
            <td style="color: var(--text-secondary); font-size: 13px;">${m.email ? escapeHtml(m.email) : '<span style="color: var(--text-muted);">—</span>'}</td>
            <td>${roleBadge}</td>
            <td style="text-align: right;">
                <button class="btn-v2 btn-v2--ghost btn-v2--sm btn-v2--icon" onclick="openMemberModal('edit', ${m.id})" title="編輯">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-v2 btn-v2--ghost btn-v2--sm btn-v2--icon" onclick="toggleRole(${m.id}, '${m.role}')" title="切換角色">
                    <i class="fas fa-exchange-alt"></i>
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
            document.getElementById('member-jobtitle').value = '';

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
                    document.getElementById('member-role').value = member.role || 'student';

                    // 載入使用者屬性
                    try {
                        const res = await fetch(`${BASE_URL}/api/admin/user_attributes.php?user_id=${memberId}`);
                        const data = await res.json();
                        if (data.success && data.data) {
                            data.data.forEach(attr => {
                                if (attr.type_code === 'department') {
                                    document.getElementById('member-department').value = attr.value_id;
                                } else if (attr.type_code === 'job_title') {
                                    document.getElementById('member-jobtitle').value = attr.value_id;
                                }
                            });
                        }
                    } catch (e) {
                        console.error('Load user attributes error:', e);
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
        function saveMember(event) {
            event.preventDefault();

            const form = document.getElementById('member-form');
            const formData = new FormData(form);
            const mode = formData.get('mode');
            const endpoint = mode === 'add'
                ? `${BASE_URL}/api/hospital_admin/add_member.php`
                : `${BASE_URL}/api/hospital_admin/update_member.php`;

            const submitBtn = document.getElementById('modal-submit-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 處理中...';

            fetch(endpoint, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '儲存';

                    if (data.success) {
                        closeMemberModal();
                        loadMembers();
                        showToast(mode === 'add' ? '成員已新增' : '成員已更新', 'success');
                    } else {
                        showToast(data.error || '操作失敗', 'error');
                    }
                })
                .catch(err => {
                    console.error('Save error:', err);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '儲存';
                    showToast('網路錯誤', 'error');
                });
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
        window.searchMembers = searchMembers;
        window.quickChangeRole = quickChangeRole;
        window.confirmDelete = confirmDelete;
        window.closeDeleteModal = closeDeleteModal;
        window.executeDelete = executeDelete;
        window.saveMember = saveMember;
    })();
