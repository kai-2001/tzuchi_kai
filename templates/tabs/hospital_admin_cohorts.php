<?php
/**
 * 院區管理員 - Cohort 管理介面
 * templates/tabs/hospital_admin_cohorts.php
 */
?>
<div id="section-cohort-management" class="page-section">
    <div class="section-header">
        <h2><i class="fas fa-users-class"></i> Cohort 群組管理</h2>
        <p class="section-subtitle">管理
            <?php echo h($_SESSION['institution'] ?? ''); ?> 院區的 Moodle 群組 (Cohorts)
        </p>
    </div>

    <!-- 工具列 -->
    <div class="toolbar-container">
        <div class="search-bar-container">
            <!-- 未來可做搜尋 -->
        </div>
        <div style="margin-left: auto; display:flex; gap:10px;">
            <button class="btn-secondary" onclick="loadCohorts()">
                <i class="fas fa-sync-alt"></i> 重新載入
            </button>
            <button class="btn-primary" onclick="openCohortModal('add')">
                <i class="fas fa-plus"></i> 新增群組
            </button>
        </div>
    </div>

    <!-- 群組列表 -->
    <div class="widget-card">
        <div class="widget-body" id="cohorts-list">
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- 新增群組 Modal -->
<div id="cohort-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 480px; padding: 24px; border-radius: 12px;">
        <div class="modal-header" style="border-bottom: 1px solid #f0f0f0; padding-bottom: 16px; margin-bottom: 20px;">
            <h3 style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;">新增群組</h3>
            <button class="modal-close" onclick="closeCohortModal()">&times;</button>
        </div>
        <form id="cohort-form" onsubmit="saveCohort(event)">
            <input type="hidden" name="action" value="create">

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="cohort-name" style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">群組名稱
                    <span style="color:#ef4444">*</span></label>
                <input type="text" id="cohort-name" name="name" required placeholder="例如：2024 實習醫師群組"
                    style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px;">
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="cohort-idnumber"
                    style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">群組 ID Number</label>
                <input type="text" id="cohort-idnumber" name="idnumber" placeholder="選填，系統識別碼"
                    style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px;">
            </div>

            <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 10px;">
                <button type="button" class="btn-secondary" onclick="closeCohortModal()"
                    style="padding: 10px 18px; border-radius: 8px; background: #f3f4f6; color: #374151; border: none; cursor: pointer; font-weight: 500;">
                    取消
                </button>
                <button type="submit" class="btn-primary" id="cohort-submit-btn"
                    style="padding: 10px 18px; border-radius: 8px; background: #3b82f6; color: white; border: none; cursor: pointer; font-weight: 500;">
                    建立
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 成員管理 Modal -->
<div id="cohort-members-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 600px; padding: 24px; border-radius: 12px;">
        <div class="modal-header" style="border-bottom: 1px solid #f0f0f0; padding-bottom: 16px; margin-bottom: 20px;">
            <h3 id="cohort-members-title" style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin: 0;">
                群組成員管理</h3>
            <button class="modal-close" onclick="closeCohortMembersModal()">&times;</button>
        </div>

        <!-- 加入成員區塊 -->
        <div style="background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
            <h4 style="font-size: 0.95rem; font-weight: 600; margin-bottom: 10px; color: #374151;">加入新成員</h4>
            <div style="display: flex; gap: 10px;">
                <input type="text" id="add-cohort-username" placeholder="輸入使用者帳號 (Username)"
                    style="flex: 1; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px;">
                <button class="btn-primary" onclick="addCohortMember()" id="add-member-btn" style="padding: 8px 16px;">
                    <i class="fas fa-plus"></i> 加入
                </button>
            </div>
        </div>

        <div id="cohort-members-list" style="max-height: 400px; overflow-y: auto;">
            <div class="loading-spinner" style="text-align:center; padding: 20px;">
                <i class="fas fa-spinner fa-spin"></i> 載入中...
            </div>
        </div>

        <input type="hidden" id="current-cohort-id">
    </div>
</div>

<script>
    let allCohorts = [];

    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('section-cohort-management')) {
            loadCohorts();
        }
    });

    function loadCohorts() {
        const container = document.getElementById('cohorts-list');
        container.innerHTML = `
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px;"></div>
            </div>`;

        fetch('/api/hospital_admin/manage_cohort.php?action=list')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    allCohorts = data.data || [];
                    renderCohorts(allCohorts);
                } else {
                    container.innerHTML = `<div class="error-message">無法載入群組: ${data.error}</div>`;
                }
            })
            .catch(err => {
                console.error('Load cohorts error:', err);
                container.innerHTML = `<div class="error-message">網路錯誤</div>`;
            });
    }

    function renderCohorts(cohorts) {
        const container = document.getElementById('cohorts-list');
        if (!cohorts || cohorts.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-users fa-3x"></i>
                    <p>尚無 Cohort 群組</p>
                    <button class="btn-primary" onclick="openCohortModal('add')">
                        <i class="fas fa-plus"></i> 建立第一個群組
                    </button>
                </div>`;
            return;
        }

        let html = '<table class="member-table"><thead><tr>';
        html += '<th style="width: 50px;">ID</th><th>群組名稱</th><th>ID Number</th><th style="text-align:center">操作</th>';
        html += '</tr></thead><tbody>';

        cohorts.forEach(c => {
            html += `<tr>
                <td style="color:#6b7280;">${c.id}</td>
                <td><strong>${escapeHtml(c.name)}</strong></td>
                <td>${escapeHtml(c.idnumber || '-')}</td>
                <td class="action-cell" style="text-align:center">
                    <button class="btn-icon btn-edit" onclick="openCohortMembers(${c.id}, '${escapeHtml(c.name)}')" title="管理成員">
                        <i class="fas fa-users-cog"></i>
                    </button>
                    <button class="btn-icon btn-delete" onclick="deleteCohort(${c.id}, '${escapeHtml(c.name)}')" title="刪除群組">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function openCohortModal(mode) {
        const modal = document.getElementById('cohort-modal');
        const form = document.getElementById('cohort-form');
        form.reset();
        modal.style.display = 'flex';
        setTimeout(() => document.getElementById('cohort-name').focus(), 100);
    }

    function closeCohortModal() {
        document.getElementById('cohort-modal').style.display = 'none';
    }

    function saveCohort(e) {
        e.preventDefault();
        const form = document.getElementById('cohort-form');
        const formData = new FormData(form);
        const btn = document.getElementById('cohort-submit-btn');
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 處理中...';

        fetch('/api/hospital_admin/manage_cohort.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                if (data.success) {
                    closeCohortModal();
                    loadCohorts();
                    showToast(data.message || '群組已建立', 'success');
                } else {
                    showToast(data.error || '操作失敗', 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showToast('網路錯誤', 'error');
            });
    }

    function deleteCohort(id, name) {
        if (!confirm(`確定要刪除群組「${name}」嗎？\n\n警告：此操作不可復原，且會移除所有成員關聯！`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        fetch('/api/hospital_admin/manage_cohort.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadCohorts();
                    showToast('群組已刪除', 'success');
                } else {
                    showToast(data.error || '刪除失敗', 'error');
                }
            });
    }

    // --- 成員管理 ---

    function openCohortMembers(id, name) {
        document.getElementById('current-cohort-id').value = id;
        document.getElementById('cohort-members-title').textContent = `成員管理 - ${name}`;
        document.getElementById('cohort-members-modal').style.display = 'flex';

        loadCohortMembers(id);
    }

    function closeCohortMembersModal() {
        document.getElementById('cohort-members-modal').style.display = 'none';
    }

    function loadCohortMembers(cohortId) {
        const listDiv = document.getElementById('cohort-members-list');
        listDiv.innerHTML = '<div class="loading-spinner" style="text-align:center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> 載入中...</div>';

        fetch(`/api/hospital_admin/manage_cohort.php?action=get_members&cohort_id=${cohortId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderCohortMembers(data.data || []);
                } else {
                    listDiv.innerHTML = `<div class="error-message">${data.error}</div>`;
                }
            })
            .catch(err => listDiv.innerHTML = `<div class="error-message">網路錯誤</div>`);
    }

    function renderCohortMembers(members) {
        const listDiv = document.getElementById('cohort-members-list');
        if (members.length === 0) {
            listDiv.innerHTML = '<div class="empty-state" style="padding: 20px;"><p>此群組尚無成員</p></div>';
            return;
        }

        let html = '<table class="member-table" style="width:100%;"><thead><tr><th>姓名</th><th>帳號</th><th>Email</th><th>操作</th></tr></thead><tbody>';
        members.forEach(m => {
            html += `<tr>
                <td>${escapeHtml(m.fullname)}</td>
                <td>${escapeHtml(m.username)}</td>
                <td><small>${escapeHtml(m.email)}</small></td>
                <td>
                    <button class="btn-icon btn-delete" onclick="removeCohortMember(${m.id})" title="移除成員">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>`;
        });
        html += '</tbody></table>';
        listDiv.innerHTML = html;
    }

    function addCohortMember() {
        const cohortId = document.getElementById('current-cohort-id').value;
        const usernameInput = document.getElementById('add-cohort-username');
        const username = usernameInput.value.trim();

        if (!username) return;

        const btn = document.getElementById('add-member-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const formData = new FormData();
        formData.append('action', 'add_member');
        formData.append('cohort_id', cohortId);
        formData.append('username', username);

        fetch('/api/hospital_admin/manage_cohort.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-plus"></i> 加入';

                if (data.success) {
                    usernameInput.value = '';
                    loadCohortMembers(cohortId);
                    showToast('成員已加入', 'success');
                } else {
                    showToast(data.error || '加入失敗，請確認帳號是否存在', 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.textContent = '加入';
                showToast('網路錯誤', 'error');
            });
    }

    function removeCohortMember(userId) {
        const cohortId = document.getElementById('current-cohort-id').value;
        if (!confirm('確定要移除此成員嗎？')) return;

        const formData = new FormData();
        formData.append('action', 'remove_member');
        formData.append('cohort_id', cohortId);
        formData.append('user_id', userId);

        fetch('/api/hospital_admin/manage_cohort.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadCohortMembers(cohortId);
                    showToast('成員已移除', 'success');
                } else {
                    showToast(data.error || '移除失敗', 'error');
                }
            });
    }
</script>