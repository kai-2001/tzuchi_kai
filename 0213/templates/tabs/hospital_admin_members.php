<?php
/**
 * 院區管理員 - 成員管理介面
 * templates/tabs/hospital_admin_members.php
 */
?>
<div id="section-member-management" class="page-section">
    <div class="section-header">
        <h2><i class="fas fa-users-cog"></i> 成員管理</h2>
        <p class="section-subtitle">管理
            <?php echo h($_SESSION['institution'] ?? ''); ?> 院區的所有成員
        </p>
    </div>

    <!-- 工具列 -->
    <div class="toolbar-container">
        <div class="search-bar-container">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="member-search" placeholder="搜尋成員姓名或帳號..." oninput="filterMembers(this.value)">
            </div>
        </div>
        <button class="btn-secondary" onclick="openBatchModal()" style="margin-right: 8px;">
            <i class="fas fa-file-import"></i> 批次匯入
        </button>
        <button class="btn-primary" onclick="openMemberModal('add')">
            <i class="fas fa-user-plus"></i> 新增成員
        </button>
    </div>

    <!-- 成員列表 -->
    <div class="widget-card">
        <div class="widget-body" id="members-list">
            <!-- 載入骨架 -->
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- 新增/編輯成員 Modal (Compact UI) -->
<div id="member-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 480px; padding: 24px; border-radius: 12px;">
        <div class="modal-header" style="border-bottom: 1px solid #f0f0f0; padding-bottom: 16px; margin-bottom: 20px;">
            <h3 id="modal-title" style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;">新增成員</h3>
            <button class="modal-close" onclick="closeMemberModal()">&times;</button>
        </div>
        <form id="member-form" onsubmit="saveMember(event)">
            <input type="hidden" id="member-id" name="id">
            <input type="hidden" id="modal-mode" name="mode" value="add">

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="member-username"
                    style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">帳號 <span
                        style="color:#ef4444">*</span></label>
                <input type="text" id="member-username" name="username" required pattern="[a-zA-Z0-9_]+"
                    title="只能使用英文、數字和底線" placeholder="英文、數字或底線"
                    style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="member-fullname"
                    style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">姓名 <span
                        style="color:#ef4444">*</span></label>
                <input type="text" id="member-fullname" name="fullname" required placeholder="請輸入真實姓名"
                    style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="member-email"
                    style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">Email</label>
                <input type="email" id="member-email" name="email" placeholder="選填，預設為 username@院區.example.com"
                    style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="member-password"
                    style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">密碼</label>
                <input type="password" id="member-password" name="password" placeholder="新增時必填"
                    style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="member-role" style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">角色
                    <span style="color:#ef4444">*</span></label>
                <select id="member-role" name="role" required
                    style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background-color: white;">
                    <option value="student">學生</option>
                    <option value="coursecreator">開課教師</option>
                </select>
            </div>

            <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 10px;">
                <button type="button" class="btn-secondary" onclick="closeMemberModal()"
                    style="padding: 10px 18px; border-radius: 8px; background: #f3f4f6; color: #374151; border: none; cursor: pointer; font-weight: 500;">
                    取消
                </button>
                <button type="submit" class="btn-primary" id="modal-submit-btn"
                    style="padding: 10px 18px; border-radius: 8px; background: #3b82f6; color: white; border: none; cursor: pointer; font-weight: 500;">
                    儲存
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 刪除確認 Modal (保持輕量) -->
<div id="delete-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 400px; padding: 24px; border-radius: 12px; text-align: center;">
        <div style="margin-bottom: 16px; color: #ef4444; font-size: 48px;">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <h3 style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin-bottom: 12px;">確認刪除</h3>
        <p style="color: #4b5563; margin-bottom: 8px;">確定要刪除成員 <strong id="delete-member-name"></strong> 嗎？</p>
        <p style="color: #ef4444; font-size: 0.875rem; margin-bottom: 24px;">⚠️ 此操作將同時刪除 Moodle 帳號，無法復原！</p>

    </div>
</div>
</div>

<!-- 批次匯入 Modal -->
<div id="batch-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content"
        style="max-width: 650px; padding: 24px; border-radius: 12px; height: 85vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="border-bottom: 1px solid #f0f0f0; padding-bottom: 16px; margin-bottom: 15px;">
            <h3 style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;">批次匯入成員 (CSV)</h3>
            <button class="modal-close" onclick="closeBatchModal()">&times;</button>
        </div>

        <div style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
            <p style="font-size: 0.9rem; color: #6b7280; margin-bottom: 12px;">
                請貼上 CSV 格式內容，或上傳檔案。欄位順序：<br>
                <code>帳號,姓名,Email,密碼,角色,群組名稱</code> (角色可填 student 或 coursecreator)
            </p>

            <textarea id="batch-csv-text" placeholder="A123,王小明,xm@tzuchi.com,Pass123!,student,護理部"
                style="flex: 1; width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: monospace; font-size: 13px; margin-bottom: 15px; resize: none;"></textarea>

            <div
                style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e2e8f0;">
                <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                    <div style="flex: 1;">
                        <label style="display:block; font-size:0.85rem; color:#64748b; margin-bottom:4px;">預設角色
                            (若CSV未指定)</label>
                        <select id="batch-default-role"
                            style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1;">
                            <option value="student">學生</option>
                            <option value="coursecreator">開課教師</option>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-size:0.85rem; color:#64748b; margin-bottom:4px;">預設群組
                            (可選)</label>
                        <select id="batch-default-cohort"
                            style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1;">
                            <option value="">不指定</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button class="btn-secondary btn-sm" onclick="document.getElementById('batch-file-input').click()">
                        <i class="fas fa-file-upload"></i> 上傳 CSV/TXT 檔案
                    </button>
                    <input type="file" id="batch-file-input" accept=".csv,.txt" style="display:none;"
                        onchange="handleBatchFileSelect(this)">
                </div>
            </div>

            <div id="batch-result-log"
                style="display:none; height: 120px; overflow-y: auto; background: #1e293b; color: #f8fafc; padding: 10px; border-radius: 6px; font-size: 12px; margin-bottom: 15px;">
                <!-- Result log items -->
            </div>
        </div>

        <div class="form-actions"
            style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 10px; border-top: 1px solid #f0f0f0;">
            <button type="button" class="btn-secondary" onclick="closeBatchModal()">取消</button>
            <button type="button" class="btn-primary" id="batch-run-btn" onclick="runBatchImport()">
                <i class="fas fa-play"></i> 開始匯入
            </button>
        </div>
    </div>
</div>

<script>
    function openBatchModal() {
        document.getElementById('batch-modal').style.display = 'flex';
        document.getElementById('batch-csv-text').value = '';
        document.getElementById('batch-result-log').style.display = 'none';
        document.getElementById('batch-result-log').innerHTML = '';
        loadBatchOptions();
    }

    function closeBatchModal() {
        document.getElementById('batch-modal').style.display = 'none';
    }

    function loadBatchOptions() {
        // Load cohorts for default select
        fetch(PortalConfig.webRoot + '/api/hospital_admin/manage_users.php?action=get_cohorts')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('batch-default-cohort');
                    let html = '<option value="">不指定</option>';
                    data.data.forEach(c => {
                        html += `<option value="${c.id}">${escapeHtml(c.name)}</option>`;
                    });
                    select.innerHTML = html;
                }
            });
    }

    function handleBatchFileSelect(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById('batch-csv-text').value = e.target.result;
        };
        reader.readAsText(file);
    }

    function runBatchImport() {
        const text = document.getElementById('batch-csv-text').value.trim();
        if (!text) return alert('請先貼上內容或上傳檔案');

        const btn = document.getElementById('batch-run-btn');
        const log = document.getElementById('batch-result-log');
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 處理中...';
        log.style.display = 'block';
        log.innerHTML = '<div>正在準備資料...</div>';

        const formData = new FormData();
        formData.append('csv_text', text);
        formData.append('default_role', document.getElementById('batch-default-role').value);
        formData.append('default_cohort_id', document.getElementById('batch-default-cohort').value);

        fetch(PortalConfig.webRoot + '/api/hospital_admin/batch_add_members.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;

                if (data.success) {
                    let html = '';
                    data.results.forEach(r => {
                        const color = r.status === 'success' ? '#4ade80' : '#f87171';
                        html += `<div style="margin-bottom:4px; color:${color}">Row ${r.row}: [${r.status.toUpperCase()}] ${escapeHtml(r.message)}</div>`;
                    });
                    log.innerHTML = html;
                    loadMembers(); // Refresh list
                    showToast(`匯入完成！成功: ${data.summary.success}, 失敗: ${data.summary.fail}`, 'success');
                } else {
                    log.innerHTML = `<div style="color:#f87171">發生錯誤: ${escapeHtml(data.error)}</div>`;
                    showToast(data.error, 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                log.innerHTML = `<div style="color:#f87171">網路錯誤</div>`;
                console.error(err);
            });
    }
</script>

<script>
    // ============================================
    // 院區成員管理 - JavaScript
    // ============================================

    let allMembers = [];
    let deleteTargetId = null;

    // 頁面載入時取得成員列表
    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('section-member-management')) {
            loadMembers();
        }
    });

    // 載入成員列表
    function loadMembers() {
        const container = document.getElementById('members-list');
        container.innerHTML = `
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px;"></div>
            </div>
        `;

        // Switch to V2 API
        fetch(PortalConfig.webRoot + '/api/v2/index.php?route=members/list')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    allMembers = data.data || [];
                    renderMembers(allMembers);
                } else {
                    showMembersError(data.error || '無法載入成員列表');
                }
            })
            .catch(err => {
                console.error('Load members error:', err);
                showMembersError('網路錯誤，無法載入成員列表');
            });
    }

    // 渲染成員列表
    function renderMembers(members) {
        const container = document.getElementById('members-list');

        if (!members || members.length === 0) {
            container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-users fa-3x"></i>
                <p>尚無成員資料</p>
                <button class="btn-primary" onclick="openMemberModal('add')">
                    <i class="fas fa-user-plus"></i> 新增第一位成員
                </button>
            </div>`;
            return;
        }

        let html = '<table class="member-table"><thead><tr>';
        html += '<th>帳號</th><th>姓名</th><th>Email</th><th>角色</th><th style="text-align:center">操作</th>';
        html += '</tr></thead><tbody>';

        members.forEach(m => {
            const roleBadge = (m.role === 'teacherplus' || m.role === 'coursecreator')
                ? '<span class="role-badge role-teacher"><i class="fas fa-chalkboard-teacher"></i> 開課教師</span>'
                : '<span class="role-badge role-student"><i class="fas fa-user-graduate"></i> 學生</span>';

            const name = escapeHtml(m.fullname || m.username);
            const email = m.email ? escapeHtml(m.email) : '<span style="color:#9ca3af">-</span>';

            html += `<tr data-id="${m.id}">
            <td><code>${escapeHtml(m.username)}</code></td>
            <td><strong>${name}</strong></td>
            <td style="font-size:13px">${email}</td>
            <td>${roleBadge}</td>
            <td class="action-cell" style="text-align:center">
                <button class="btn-icon btn-edit" onclick="openMemberModal('edit', ${m.id})" title="編輯資料">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon btn-role" onclick="toggleRole(${m.id}, '${m.role}')" title="切換角色">
                    <i class="fas fa-exchange-alt"></i>
                </button>
                <button class="btn-icon btn-delete" onclick="openDeleteModal(${m.id}, '${escapeHtml(m.fullname || m.username)}')" title="刪除成員">
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

    // 開啟新增/編輯 Modal
    function openMemberModal(mode, memberId) {
        const modal = document.getElementById('member-modal');
        const form = document.getElementById('member-form');
        const title = document.getElementById('modal-title');
        const usernameField = document.getElementById('member-username');
        const passwordField = document.getElementById('member-password');

        form.reset();
        document.getElementById('modal-mode').value = mode;
        document.getElementById('member-id').value = memberId || '';

        if (mode === 'add') {
            title.innerHTML = '新增成員'; // 簡潔標題
            usernameField.disabled = false;
            // usernameField.placeholder = '請輸入帳號'; // 已經在 HTML 設定
            passwordField.required = true;
            passwordField.placeholder = '請設定密碼';
        } else {
            title.innerHTML = '編輯成員';
            usernameField.disabled = true;
            passwordField.required = false;
            passwordField.placeholder = '留空表示不修改密碼';

            // 填入現有資料
            const member = allMembers.find(m => m.id == memberId);
            if (member) {
                document.getElementById('member-username').value = member.username;
                document.getElementById('member-fullname').value = member.fullname || '';
                document.getElementById('member-email').value = member.email || '';
                document.getElementById('member-role').value = member.role || 'student';
            }
        }

        modal.style.display = 'flex';

        // 自動 focus
        setTimeout(() => {
            if (mode === 'add') {
                usernameField.focus();
            } else {
                document.getElementById('member-fullname').focus();
            }
        }, 100);
    }

    // 關閉 Modal
    function closeMemberModal() {
        document.getElementById('member-modal').style.display = 'none';

        // 清除表單錯誤提示樣式（如果有）
        const inputs = document.querySelectorAll('.form-group input');
        inputs.forEach(input => input.style.borderColor = '#d1d5db');
    }

    // 儲存成員
    function saveMember(event) {
        event.preventDefault();

        const form = document.getElementById('member-form');
        const formData = new FormData(form);
        const mode = formData.get('mode');
        const endpoint = mode === 'add'
            ? PortalConfig.webRoot + '/api/v2/index.php?route=members/create'
            : PortalConfig.webRoot + '/api/v2/index.php?route=members/update';

        const submitBtn = document.getElementById('modal-submit-btn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 處理中...';

        fetch(endpoint, {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;

                if (data.success) {
                    // ✅ 成功
                    closeMemberModal();
                    loadMembers();
                    refreshStats();
                    showToast(mode === 'add' ? '成員已新增' : '成員已更新', 'success');
                } else {
                    // ❌ 失敗
                    showToast(data.error || '操作失敗', 'error');
                }
            })
            .catch(err => {
                console.error('Save member error:', err);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                showToast('網路錯誤，請稍後再試', 'error');
            });
    }

    // 切換角色
    function toggleRole(memberId, currentRole) {
        const newRole = currentRole === 'student' ? 'coursecreator' : 'student';
        const roleLabel = newRole === 'coursecreator' ? '開課教師' : '學生';

        if (!confirm(`確定要將此成員的角色變更為「${roleLabel}」嗎？\n\n角色變更會同步到 Moodle。`)) {
            return;
        }

        showToast('正在變更角色...', 'info');

        fetch(PortalConfig.webRoot + '/api/v2/index.php?route=members/change_role', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${memberId}&role=${newRole}`
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadMembers();
                    refreshStats();
                    showToast(data.message || '角色已變更', 'success');
                } else {
                    showToast(data.error || '角色變更失敗', 'error');
                }
            })
            .catch(err => {
                console.error('Change role error:', err);
                showToast('網路錯誤', 'error');
            });
    }

    // 開啟刪除確認
    function openDeleteModal(memberId, memberName) {
        deleteTargetId = memberId;
        document.getElementById('delete-member-name').textContent = memberName;
        document.getElementById('delete-modal').style.display = 'flex';
    }

    // 關閉刪除 Modal
    function closeDeleteModal() {
        document.getElementById('delete-modal').style.display = 'none';
        deleteTargetId = null;
    }

    // 執行刪除
    function executeDelete() {
        if (!deleteTargetId) return;

        const btn = document.getElementById('confirm-delete-btn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 刪除中...';

        fetch(PortalConfig.webRoot + '/api/v2/index.php?route=members/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${deleteTargetId}`
        })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;

                if (data.success) {
                    closeDeleteModal();
                    loadMembers();
                    refreshStats();
                    showToast(data.message || '成員已刪除', 'success');
                } else {
                    showToast(data.error || '刪除失敗', 'error');
                }
            })
            .catch(err => {
                console.error('Delete member error:', err);
                btn.disabled = false;
                btn.innerHTML = originalText;
                showToast('網路錯誤', 'error');
            });
    }

    // 刷新統計數據
    function refreshStats() {
        if (typeof loadHospitalStats === 'function') {
            loadHospitalStats();
        }
    }

    // 顯示錯誤
    function showMembersError(msg) {
        document.getElementById('members-list').innerHTML = `
        <div class="error-state">
            <i class="fas fa-exclamation-circle fa-3x"></i>
            <p>${escapeHtml(msg)}</p>
            <button class="btn-secondary" onclick="loadMembers()">
                <i class="fas fa-sync-alt"></i> 重試
            </button>
        </div>`;
    }

    // HTML 轉義
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Toast 通知
    function showToast(message, type) {
        // 移除舊的 toast
        const oldToast = document.querySelector('.hospital-admin-toast');
        if (oldToast) oldToast.remove();

        const toast = document.createElement('div');
        toast.className = `hospital-admin-toast toast-${type || 'info'}`;

        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            info: 'info-circle'
        };
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            info: '#3b82f6'
        };

        toast.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i> ${escapeHtml(message)}`;
        toast.style.cssText = `
            position: fixed; bottom: 24px; right: 24px; 
            padding: 14px 24px; border-radius: 10px; z-index: 10000;
            background: ${colors[type] || colors.info};
            color: white; font-weight: 500; font-size: 14px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            display: flex; align-items: center; gap: 10px;
            animation: slideInRight 0.3s ease;
        `;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // 移除點擊 Modal 外部關閉的行為 (防止誤觸) - 只有刪除確認可以點外部關閉
    document.addEventListener('click', function (e) {
        if (e.target.id === 'delete-modal') {
            closeDeleteModal();
        }
    });

    // ESC 鍵關閉 Modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeMemberModal();
            closeDeleteModal();
        }
    });
</script>

<style>
    /* Toast 動畫 */
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }

        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }
</style>