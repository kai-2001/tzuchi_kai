<!-- templates/tabs/admin_dimensions.php -->
<!-- 維度管理頁面 -->
<div id="section-dimensions" class="page-section">
    <div class="tab-header">
        <h2><i class="fas fa-layer-group"></i> 群組維度管理</h2>
        <p>將群組分類到不同維度（職類、層級、屬性等），以便在招生時使用 AND 條件篩選。</p>
    </div>
    <style>
        .dim-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            min-height: 500px;
        }

        .dim-sidebar {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }

        .dim-main {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }

        .dim-type-item {
            padding: 12px 16px;
            margin: 8px 0;
            background: #fff;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }

        .dim-type-item:hover {
            border-color: #3b82f6;
        }

        .dim-type-item.active {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .dim-type-item .delete-btn {
            opacity: 0;
            color: #ef4444;
            cursor: pointer;
        }

        .dim-type-item:hover .delete-btn {
            opacity: 1;
        }

        .cohort-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }

        .cohort-card {
            padding: 16px;
            background: #f1f5f9;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cohort-card.in-dimension {
            background: #dcfce7;
            border: 1px solid #86efac;
        }

        .add-dim-form {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .add-dim-form input {
            flex: 1;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 16px;
        }

        .inst-select {
            margin-bottom: 16px;
        }

        .inst-select select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
        }
    </style>

    <div class="dim-container">
        <!-- 左側：維度類型列表 -->
        <div class="dim-sidebar">
            <div class="inst-select">
                <label>選擇院區：</label>
                <select id="dim-institution-select" onchange="loadDimensionTypes()">
                    <option value="">載入中...</option>
                </select>
            </div>

            <div class="section-title">維度類型</div>

            <div id="dim-types-list">
                <div class="empty-state">請先選擇院區</div>
            </div>

            <div style="margin-top:16px; padding-top:16px; border-top:1px solid #e2e8f0;">
                <input type="text" id="new-dim-name" placeholder="輸入新維度名稱"
                    style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:6px; margin-bottom:8px;">
                <button class="btn btn-primary" onclick="createDimensionType()" style="width:100%; padding:10px;">＋
                    新增維度</button>
            </div>
        </div>

        <!-- 右側：群組分配 -->
        <div class="dim-main">
            <div class="section-title">
                <span id="current-dim-name">請選擇維度</span>
                <span id="dim-hint" style="font-weight:normal; color:#64748b; font-size:0.9rem;"> - 點擊群組將其加入此維度</span>
            </div>

            <div id="cohorts-in-dim" style="margin-bottom:20px;">
                <h4 style="color:#16a34a;">已加入此維度的群組</h4>
                <div id="cohorts-in-list" class="cohort-grid">
                    <div class="empty-state">尚未選擇維度</div>
                </div>
            </div>

            <div id="cohorts-available">
                <h4 style="color:#64748b;">可加入的群組</h4>
                <div id="cohorts-available-list" class="cohort-grid">
                    <div class="empty-state">尚未選擇維度</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentInstitutionId = 0;
        let currentDimensionTypeId = 0;
        let allCohorts = []; // 院區所有群組

        // 載入院區列表
        async function loadInstitutions() {
            try {
                const res = await fetch(PortalConfig.webRoot + '/api/admin/list_institutions.php');
                const data = await res.json();
                const select = document.getElementById('dim-institution-select');
                select.innerHTML = '<option value="">請選擇院區</option>';
                if (data.success && data.data) {
                    data.data.forEach(inst => {
                        select.innerHTML += `<option value="${inst.id}">${inst.name}</option>`;
                    });
                }
            } catch (e) {
                console.error(e);
            }
        }

        // 載入維度類型
        async function loadDimensionTypes() {
            const instId = document.getElementById('dim-institution-select').value;
            currentInstitutionId = instId;
            currentDimensionTypeId = 0;

            if (!instId) {
                document.getElementById('dim-types-list').innerHTML = '<div class="empty-state">請先選擇院區</div>';
                return;
            }

            try {
                // 載入維度類型
                const res = await fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=dimensions/list_types&institution_id=${instId}`);
                const data = await res.json();

                const list = document.getElementById('dim-types-list');
                if (!data.success || !data.data.length) {
                    list.innerHTML = '<div class="empty-state">尚無維度，請新增</div>';
                } else {
                    list.innerHTML = data.data.map(t => `
                <div class="dim-type-item" onclick="selectDimensionType(${t.id}, '${t.name}')">
                    <span>${t.name}${t.is_protected ? ' 🔒' : ''}</span>
                    ${!t.is_protected ? `<span class="delete-btn" onclick="event.stopPropagation(); deleteDimensionType(${t.id})">✕</span>` : ''}
                </div>
            `).join('');
                }

                // 載入院區群組
                await loadInstitutionCohorts(instId);

            } catch (e) {
                console.error(e);
            }
        }

        // 載入院區的所有群組
        async function loadInstitutionCohorts(instId) {
            try {
                // 這裡需要用院區的 moodle_category_id 去取群組
                const url = `${PortalConfig.webRoot}/api/v2/index.php?route=cohorts/list&institution_id=${instId}`;
                console.log('Fetching cohorts from:', url);
                const res = await fetch(url);
                const data = await res.json();
                console.log('Cohort API response:', data);
                allCohorts = data.success ? data.data : [];
                console.log('allCohorts set to:', allCohorts);
            } catch (e) {
                console.error('Cohort load error:', e);
                allCohorts = [];
            }
        }

        // 選擇維度類型
        async function selectDimensionType(typeId, typeName) {
            currentDimensionTypeId = typeId;
            document.getElementById('current-dim-name').textContent = typeName;

            // 更新選中樣式
            document.querySelectorAll('.dim-type-item').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');

            // 載入已加入此維度的群組
            const res = await fetch(`${PortalConfig.webRoot}/api/v2/index.php?route=dimensions/list_cohorts&type_id=${typeId}&institution_id=${currentInstitutionId}`);
            const data = await res.json();
            const inDimCohortIds = data.success ? data.data.map(c => c.moodle_cohort_id) : [];

            // 顯示已加入的群組
            const inList = document.getElementById('cohorts-in-list');
            if (data.success && data.data.length) {
                inList.innerHTML = data.data.map(c => `
            <div class="cohort-card in-dimension">
                <span>${c.display_name || '群組#' + c.moodle_cohort_id}</span>
                <button class="btn-icon" onclick="removeCohortFromDimension(${c.id})" title="移除">✕</button>
            </div>
        `).join('');
            } else {
                inList.innerHTML = '<div class="empty-state">尚無群組</div>';
            }

            // 顯示可加入的群組（排除已加入的）
            const availList = document.getElementById('cohorts-available-list');
            const available = allCohorts.filter(c => !inDimCohortIds.includes(c.id));
            if (available.length) {
                availList.innerHTML = available.map(c => `
            <div class="cohort-card" onclick="addCohortToDimension(${c.id}, '${c.name}')">
                <span>${c.name}</span>
                <span style="color:#3b82f6;">+ 加入</span>
            </div>
        `).join('');
            } else {
                availList.innerHTML = '<div class="empty-state">沒有可加入的群組</div>';
            }
        }

        // 新增維度類型
        async function createDimensionType() {
            const name = document.getElementById('new-dim-name').value.trim();
            if (!name) return alert('請輸入維度名稱');
            if (!currentInstitutionId) return alert('請先選擇院區');

            const formData = new FormData();
            formData.append('action', 'create_type');
            formData.append('institution_id', currentInstitutionId);
            formData.append('name', name);

            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/create_type', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                document.getElementById('new-dim-name').value = '';
                loadDimensionTypes();
            } else {
                alert(data.error || '建立失敗');
            }
        }

        // 刪除維度類型
        async function deleteDimensionType(id) {
            if (!confirm('確定刪除此維度？其下的群組對照也會被刪除。')) return;

            const formData = new FormData();
            formData.append('action', 'delete_type');
            formData.append('id', id);
            formData.append('institution_id', currentInstitutionId);

            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/delete_type', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                loadDimensionTypes();
            } else {
                alert(data.error || '刪除失敗');
            }
        }

        // 將群組加入維度
        async function addCohortToDimension(cohortId, cohortName) {
            if (!currentDimensionTypeId) {
                alert('請先在左側選擇一個維度類型');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'add_cohort');
            formData.append('type_id', currentDimensionTypeId);
            formData.append('cohort_id', cohortId);
            formData.append('display_name', cohortName);

            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/add_cohort', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                selectDimensionType(currentDimensionTypeId, document.getElementById('current-dim-name').textContent);
            }
        }

        // 從維度移除群組
        async function removeCohortFromDimension(cdId) {
            const formData = new FormData();
            formData.append('action', 'remove_cohort');
            formData.append('id', cdId);

            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/remove_cohort', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                selectDimensionType(currentDimensionTypeId, document.getElementById('current-dim-name').textContent);
            }
        }

        // 初始化
        loadInstitutions();
    </script>
</div> <!-- end section-dimensions -->