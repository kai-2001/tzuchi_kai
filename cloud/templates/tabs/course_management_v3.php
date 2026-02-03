<div id="section-course-management" class="page-section">
    <div class="section-header d-flex justify-content-between align-items-center">
        <div>
            <h2><i class="fas fa-tasks"></i> 課程管理</h2>
            <p class="section-subtitle">管理您建立的課程、設定報名規則與課程包</p>
        </div>
        <div>
            <button class="btn btn-primary" onclick="openCreateCourseModal()">
                <i class="fas fa-plus"></i> 新增課程 V3
            </button>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="search-bar-container mb-4">
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="course-mgmt-search" placeholder="搜尋課程..." oninput="filterMyCourses(this.value)">
        </div>
        <button onclick="refreshMyCourses()" class="btn-refresh-large" title="重新載入">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>

    <!-- Course List -->
    <div class="widget-card">
        <div class="widget-body" id="my-courses-list">
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Create Course Wizard Modal -->
<div class="custom-modal" id="createCourseModal">
    <div class="custom-modal-content" style="max-width: 800px;">
        <div class="custom-modal-header">
            <h3 id="createCourseModalLabel">新增課程 (已更新 V3)</h3>
            <button type="button" class="close-btn" onclick="closeCreateCourseModal()">&times;</button>
        </div>
        <div class="custom-modal-body">
            <!-- Wizard Steps Indicator -->
            <div class="wizard-steps mb-4 d-flex gap-2">
                <button type="button" class="btn btn-outline-primary flex-fill tab-btn active" data-tab="1"
                    onclick="cw_switchTab(1)">
                    <i class="fas fa-info-circle me-1"></i> 1. 基本資訊 & 課程包
                </button>
                <button type="button" class="btn btn-outline-primary flex-fill tab-btn" data-tab="2"
                    onclick="cw_switchTab(2)">
                    <i class="fas fa-layer-group me-1"></i> 2. 報名規則
                </button>
            </div>

            <!-- Step 1: Basic Info -->
            <div class="wizard-pane active" id="step1">
                <div class="form-group mb-3">
                    <label class="form-label required">課程全名</label>
                    <input type="text" class="form-control" id="new-course-fullname" placeholder="例如：新進人員護理訓練">
                </div>
                <div class="form-group mb-3">
                    <label class="form-label required">課程簡稱 (Shortname)</label>
                    <input type="text" class="form-control" id="new-course-shortname" placeholder="例如：NURS-101 (需唯一)">
                </div>
                <div class="form-group mb-3">
                    <label class="form-label required">所屬類別</label>
                    <select class="form-select" id="new-course-category">
                        <option value="">載入中...</option>
                    </select>
                </div>

                <hr>

                <div class="form-group mb-3">
                    <label class="form-label">選擇課程包 (學習路徑)</label>
                    <select class="form-select" id="new-course-package" onchange="onPackageChange()">
                        <option value="0">不加入 (獨立課程)</option>
                    </select>
                    <div class="form-text">若選擇課程包，報名規則將自動繼承該課程包的設定。</div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="new-course-package-required" checked>
                    <label class="form-check-label" for="new-course-package-required">
                        此課程在課程包中為「必修」
                    </label>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="button" class="btn btn-primary" onclick="cw_nextStep()">
                        下一步 <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>

            <!-- Step 2: Enrollment Rules -->
            <div class="wizard-pane" id="step2" style="display:none;">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 設定誰可以報名此課程。（若不勾選任何條件，則僅限手動加入）
                </div>

                <!-- Policy Selector & Rules Content (Keep existing logic) -->
                <!-- Simplified for brevity in this replace, ensuring we keep the inner content structure -->
                <div class="mb-4">
                    <label class="form-label fw-bold mb-3">報名權限設定</label>
                    <div class="d-flex gap-3">
                        <div
                            class="form-check card-radio-check p-3 border rounded bg-white flex-fill position-relative">
                            <input class="form-check-input mt-1" type="radio" name="enroll_policy" id="policy_open"
                                value="open" checked onchange="toggleRuleUI()">
                            <label class="form-check-label d-block ms-2 stretched-link" for="policy_open">
                                <span class="fw-bold d-block text-dark">全體開放 (Open)</span>
                                <span class="text-muted small">所有系統使用者皆可報名，不設條件。</span>
                            </label>
                        </div>
                        <div
                            class="form-check card-radio-check p-3 border rounded bg-white flex-fill position-relative">
                            <input class="form-check-input mt-1" type="radio" name="enroll_policy"
                                id="policy_restricted" value="restricted" onchange="toggleRuleUI()">
                            <label class="form-check-label d-block ms-2 stretched-link" for="policy_restricted">
                                <span class="fw-bold d-block text-dark">限制對象 (Restricted)</span>
                                <span class="text-muted small">僅限符合特定條件 (交集) 的同仁報名。</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Restricted Rules Container -->
                <div id="restricted-rules-container" style="display:none;">
                    <!-- ... existing rule UI ... -->
                    <div class="card bg-light border-0">
                        <div class="card-body position-relative">
                            <!-- Section 1: Departments -->
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark mb-2">1. 指定部門範圍 (Base Scope) <span
                                        class="badge bg-secondary">可複選 OR</span></label>
                                <div id="rule-dept-list" class="d-flex flex-wrap gap-2 p-3 border rounded bg-white"
                                    style="max-height: 250px; overflow-y: auto;">
                                    <span class="text-muted small">請先等待載入...</span>
                                </div>
                                <div class="form-text">若勾選多個部門，則符合任一部門即可 (OR)。若未勾選，則視為「不限制部門」。</div>
                            </div>

                            <!-- Connector -->
                            <div class="d-flex align-items-center my-3">
                                <div class="flex-grow-1 border-bottom"></div>
                                <span class="badge bg-dark px-3 py-2 mx-2 rounded-pill">AND (且)</span>
                                <div class="flex-grow-1 border-bottom"></div>
                            </div>

                            <!-- Section 2: Criteria Groups -->
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark mb-2">2. 進階條件群組 (Advanced Criteria)</label>
                                <div class="alert alert-warning border-0 py-2 small">
                                    <i class="fas fa-exclamation-triangle me-1"></i> 不同群組之間是 <b>AND (且)</b>
                                    的關係；同一群組內的條件是 <b>OR (或)</b> 的關係。
                                </div>

                                <div id="rule-groups-container">
                                    <!-- Groups will be added here -->
                                </div>

                                <button type="button" class="btn btn-primary w-100" onclick="addNewCriteriaGroup()">
                                    <i class="fas fa-plus-circle me-1"></i> 增加新的條件群組 (AND)
                                </button>
                                <div class="form-text mt-2 text-center">例如：群組1 (醫師/護理師) <b>AND</b> 群組2 (新進員工)</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-outline-secondary" onclick="cw_prevStep()">
                        <i class="fas fa-arrow-left me-1"></i> 上一步
                    </button>
                    <button type="button" class="btn btn-primary" onclick="cw_nextStep()">
                        下一步 <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>


        </div>
        <div class="custom-modal-footer d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" onclick="closeCreateCourseModal()">取消</button>
            <button type="button" class="btn btn-primary" id="btn-submit" onclick="submitCreateCourse()">建立課程</button>
        </div>
    </div>
</div>

<style>
    /* ... (Keep existing styles) ... */
    /* Fixed Modal styles to ensure overlay */
    .custom-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
        /* Black w/ opacity */
    }

    .custom-modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        /* 5% from the top and centered */
        padding: 0;
        border: 1px solid #888;
        width: 80%;
        max-width: 800px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        position: relative;
    }

    .custom-modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .custom-modal-body {
        padding: 20px;
    }

    .custom-modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #eee;
        background-color: #fafafa;
        display: flex;
        justify-content: space-between;
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
    }

    .close-btn {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        background: none;
        border: none;
        cursor: pointer;
    }

    .close-btn:hover,
    .close-btn:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }

    /* Simple Wizard Styles */
    .wizard-steps {
        display: flex;
        justify-content: space-between;
        background: #f8f9fa;
        padding: 10px;
        border-radius: 8px;
    }

    .wizard-steps .step {
        flex: 1;
        text-align: center;
        color: #ccc;
        font-weight: bold;
    }

    .wizard-steps .step.active {
        color: var(--primary-color);
        border-bottom: 2px solid var(--primary-color);
    }

    /* Dept Checkbox Card Style */
    .dept-check-card {
        display: inline-flex;
        align-items: center;
        padding: 8px 12px;
        border: 1px solid #dee2e6;
        border-radius: 20px;
        background: #fff;
        cursor: pointer;
        user-select: none;
        transition: all 0.2s;
    }

    .dept-check-card:hover {
        background-color: #f1f3f5;
        border-color: #adb5bd;
    }

    .dept-check-card input:checked+span {
        color: var(--primary-color);
        font-weight: bold;
    }

    .dept-check-card input {
        margin-right: 8px;
    }

    /* Condition Row */
    .condition-row {
        background: #fff;
        padding: 10px;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
</style>

<script>
    // Global Variables
    let currentStep = 1;
    const totalSteps = 2; // Reduced to 2
    let availableAttributes = [];
    let isEditMode = false;
    let editingCourseId = 0;

    // Group Logic Counters
    let groupCount = 0;

    // Move Modal to Body to prevent Form Submission issues
    (function () {
        const modal = document.getElementById('createCourseModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    })();

    // -- Modal Management --

    function openCreateCourseModal() {
        const modal = document.getElementById('createCourseModal');
        if (!modal) return;
        modal.style.display = 'block';
        currentStep = 1;
        cw_switchTab(1); // Use new namespace

        // Load dependencies and return the Promise
        return Promise.all([
            loadMyCategories(),
            loadPackages(),
            loadAvailableAttributes(),
            loadMyDepartments()
        ]).then(() => {
            // Initial State Setup based on Mode (Only clear if NOT edit mode, or explicitly requested)
            if (!isEditMode) {
                document.getElementById('createCourseModalLabel').innerText = '建立新課程';
                document.getElementById('btn-submit').innerText = '建立課程';

                document.getElementById('new-course-fullname').value = '';
                document.getElementById('new-course-shortname').value = '';
                document.getElementById('new-course-category').value = '';
                document.getElementById('new-course-package').value = '0';
                document.getElementById('new-course-package-required').checked = true;

                // Unlock UI just in case
                enableRuleEditing(true);

                document.getElementById('policy_open').checked = true;
                toggleRuleUI();

                document.getElementById('rule-groups-container').innerHTML = '';
                groupCount = 0;
                addNewCriteriaGroup();

                document.querySelectorAll('.dept-checkbox').forEach(cb => cb.checked = false);
            } else {
                document.getElementById('createCourseModalLabel').innerText = '編輯課程設定';
                document.getElementById('btn-submit').innerText = '儲存設定';
            }
        });
    }

    function closeCreateCourseModal() {
        const modal = document.getElementById('createCourseModal');
        if (modal) modal.style.display = 'none';

        // Reset state after close to prevent lingering edit mode
        if (isEditMode) {
            isEditMode = false;
            editingCourseId = 0;
        }
    }

    // Renamed to cw_switchTab to avoid conflicts
    function cw_switchTab(tab) {
        console.log("Switching to tab:", tab);
        currentStep = tab;
        document.querySelectorAll('.wizard-pane').forEach(el => el.style.display = 'none');
        const pane = document.getElementById('step' + tab);
        if (pane) pane.style.display = 'block';

        // Update header buttons
        document.querySelectorAll('.tab-btn').forEach(el => {
            el.classList.remove('active', 'btn-primary');
            el.classList.add('btn-outline-primary');
        });
        const activeBtn = document.querySelector(`.tab-btn[data-tab="${tab}"]`);
        if (activeBtn) {
            activeBtn.classList.remove('btn-outline-primary');
            activeBtn.classList.add('active', 'btn-primary');
        }
    }

    function cw_nextStep() {
        if (currentStep < totalSteps) {
            // Optional: Add Validation here for Step 1
            if (currentStep === 1) {
                const fn = document.getElementById('new-course-fullname').value;
                const sn = document.getElementById('new-course-shortname').value;
                const cat = document.getElementById('new-course-category').value;
                if (!fn || !sn || !cat) {
                    alert('請填寫完整資訊 (全名、簡稱、類別) 才能前往下一步！');
                    return;
                }
            }
            cw_switchTab(currentStep + 1);
        }
    }

    function cw_prevStep() {
        if (currentStep > 1) {
            cw_switchTab(currentStep - 1);
        }
    }


    function onPackageChange() {
        const pkgId = document.getElementById('new-course-package').value;
        if (pkgId != '0') {
            // Fetch Package Rules
            fetch('api/learning_path/get.php?id=' + pkgId)
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        applyPackageRules(res.data);
                    }
                });
        } else {
            // Unlock UI
            toggleRuleUI(); // restore state based on radio
            enableRuleEditing(true);
        }
    }

    function applyPackageRules(pkgData) {
        // 1. Lock UI
        enableRuleEditing(false);

        // 2. Set Values
        const isRestricted = (pkgData.enroll_policy === 'restricted');
        document.getElementById('policy_open').checked = !isRestricted;
        document.getElementById('policy_restricted').checked = isRestricted;
        toggleRuleUI();

        if (isRestricted && pkgData.rules) {
            const r = pkgData.rules;
            // Clear existing
            document.querySelectorAll('.dept-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('rule-groups-container').innerHTML = '';
            groupCount = 0;

            // Set Depts
            if (r.depts) {
                r.depts.forEach(did => {
                    const cb = document.getElementById('dept_' + did);
                    if (cb) cb.checked = true;
                });
            }

            // Set Condition Groups
            // Reuse existing logic manually
            if (r.condition_groups) {
                r.condition_groups.forEach(g => {
                    addNewCriteriaGroup();
                    const lastGroup = document.getElementById('rule-groups-container').lastElementChild;
                    const lastGroupId = lastGroup.id;
                    g.conditions.forEach(c => {
                        addConditionToGroup(lastGroupId);
                        const lastRow = lastGroup.querySelector('.group-conditions-list').lastElementChild;
                        const sel = lastRow.querySelector('.cond-value');
                        if (sel) sel.value = c.value;
                    });
                });
            }
        }
    }

    function enableRuleEditing(enable) {
        const container = document.getElementById('step2');
        if (!container) return;

        // Disable/Enable inputs
        const inputs = container.querySelectorAll('input, select, button');
        inputs.forEach(el => {
            // Don't disable Next/Prev buttons!
            if (el.innerText.includes('下一步') || el.innerText.includes('上一步')) return;
            el.disabled = !enable;
        });

        // Visual cue
        if (!enable) {
            let msg = document.getElementById('pkg-inherit-msg');
            if (!msg) {
                msg = document.createElement('div');
                msg.id = 'pkg-inherit-msg';
                msg.className = 'alert alert-warning fw-bold';
                msg.innerHTML = '<i class="fas fa-lock"></i> 報名規則已鎖定：自動繼承自所選課程包。若需修改，請在第一步取消選擇課程包。';
                container.prepend(msg);
            }
        } else {
            const msg = document.getElementById('pkg-inherit-msg');
            if (msg) msg.remove();
        }
    }

    // -- Data Loading --

    function loadMyCategories() {
        const sel = document.getElementById('new-course-category');
        // Prevent clearing if checking current value in edit mode? 
        // Better to reload and re-select if needed.
        // But for Edit mode, we populate AFTER fetch.

        // If content is already loaded, maybe skip? 
        // But categories might change.

        return fetch('api/admin/moodle_categories.php?action=tree')
            .then(r => r.json())
            .then(res => {
                let currentVal = sel.value;
                if (res.success) {
                    let html = '<option value="">請選擇類別</option>';
                    html += renderCategoryOptions(res.data);
                    sel.innerHTML = html;
                    if (currentVal) sel.value = currentVal;
                } else {
                    sel.innerHTML = '<option value="">無法載入類別</option>';
                }
            })
            .catch(e => {
                console.error(e);
                sel.innerHTML = '<option value="">載入失敗</option>';
            });
    }

    function renderCategoryOptions(cats, level = 0) {
        let html = '';
        cats.forEach(c => {
            let prefix = '';
            if (level > 0) prefix = '└─ '.padStart(level * 3 + 3, '\u00A0');
            html += `<option value="${c.id}">${prefix}${c.name}</option>`;
            if (c.children && c.children.length > 0) {
                html += renderCategoryOptions(c.children, level + 1);
            }
        });
        return html;
    }

    function loadPackages() {
        const sel = document.getElementById('new-course-package');
        return fetch('api/learning_path/list.php')
            .then(r => r.json())
            .then(res => {
                let currentVal = sel.value;
                if (res.success) {
                    let html = '<option value="0">不加入 (獨立課程)</option>';
                    res.data.forEach(p => {
                        html += `<option value="${p.id}">📦 ${p.name}</option>`;
                    });
                    sel.innerHTML = html;
                    if (currentVal) sel.value = currentVal;
                }
            });
    }

    function loadAvailableAttributes() {
        if (availableAttributes.length > 0) return Promise.resolve();

        const p1 = fetch('api/admin/attribute_values.php?type_code=job_title')
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const jobs = res.data.map(d => ({ ...d, type_label: '職稱', type_code: 'job_title' }));
                    availableAttributes = availableAttributes.concat(jobs);
                }
            });

        const p2 = fetch('api/admin/attribute_values.php?type_code=system_role')
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const roles = res.data.map(d => ({ ...d, type_label: '角色', type_code: 'system_role' }));
                    availableAttributes = availableAttributes.concat(roles);
                }
            });

        return Promise.all([p1, p2]);
    }

    function loadMyDepartments() {
        const container = document.getElementById('rule-dept-list');
        container.innerHTML = '<div class="text-muted"><i class="fas fa-spinner fa-spin"></i> 載入中...</div>';

        return fetch('api/admin/attribute_values.php?type_code=department')
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (res.data.length === 0) {
                        container.innerHTML = '<div class="text-muted">無可用部門資料</div>';
                        return;
                    }
                    const tree = buildDeptTree(res.data);
                    const html = renderDeptTree(tree);
                    container.innerHTML = html;

                    // Re-tick if editing logic handled elsewhere (in callback?)
                } else {
                    container.innerHTML = '<div class="text-danger">載入失敗</div>';
                }
            })
            .catch(e => {
                container.innerHTML = '<div class="text-danger">載入錯誤</div>';
            });
    }

    function buildDeptTree(flatList) {
        const map = {};
        const roots = [];
        flatList.forEach(item => {
            map[item.id] = { ...item, children: [] };
        });
        flatList.forEach(item => {
            if (item.parent_id && map[item.parent_id]) {
                map[item.parent_id].children.push(map[item.id]);
            } else {
                roots.push(map[item.id]);
            }
        });
        return roots;
    }

    function renderDeptTree(nodes, level = 0) {
        if (!nodes || nodes.length === 0) return '';
        let html = '<ul class="list-unstyled mb-0" style="padding-left: ' + (level * 20) + 'px;">';
        nodes.forEach(node => {
            const hasChildren = node.children && node.children.length > 0;
            const toggleIcon = hasChildren ?
                `<i class="fas fa-caret-down text-muted me-1 toggle-icon" style="cursor:pointer;" onclick="toggleTreeVisibility(this)"></i>` :
                `<i class="fas fa-minus text-white me-1"></i>`;
            html += `
            <li class="mb-1">
                <div class="d-flex align-items-center">
                    ${toggleIcon}
                    <label class="form-check-label d-flex align-items-center" style="cursor:pointer;">
                        <input type="checkbox" class="form-check-input me-2 dept-checkbox" 
                               value="${node.id}" 
                               id="dept_${node.id}" 
                               data-parent="${node.parent_id || ''}"
                               onchange="toggleDeptCheck(this)">
                        <span>${node.name}</span>
                    </label>
                </div>
                ${renderDeptTree(node.children, level + 1)}
            </li>`;
        });
        html += '</ul>';
        return html;
    }

    function toggleTreeVisibility(icon) {
        const li = icon.closest('li');
        const ul = li.querySelector('ul');
        if (ul) {
            if (ul.style.display === 'none') {
                ul.style.display = 'block';
                icon.className = 'fas fa-caret-down text-muted me-1 toggle-icon';
            } else {
                ul.style.display = 'none';
                icon.className = 'fas fa-caret-right text-muted me-1 toggle-icon';
            }
        }
    }

    function toggleDeptCheck(checkbox) {
        const isChecked = checkbox.checked;
        const li = checkbox.closest('li');
        const children = li.querySelectorAll('.dept-checkbox');
        children.forEach(c => {
            if (c !== checkbox) c.checked = isChecked;
        });
    }

    // -- Rule UI Logic --

    function toggleRuleUI() {
        const isRestricted = document.getElementById('policy_restricted').checked;
        const container = document.getElementById('restricted-rules-container');
        if (container) container.style.display = isRestricted ? 'block' : 'none';
    }

    function addNewCriteriaGroup() {
        groupCount++;
        const groupId = 'group_' + Date.now() + '_' + groupCount; // Unique ID
        const container = document.getElementById('rule-groups-container');
        if (!container) return;

        const groupHtml = `
            <div id="${groupId}" class="card mb-3 border border-secondary criteria-group">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                    <span class="fw-bold text-primary"><i class="fas fa-layer-group me-1"></i> 條件群組 #${groupCount} (OR)</span>
                    <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none" onclick="removeCriteriaGroup('${groupId}')">
                        <i class="fas fa-trash-alt"></i> 刪除群組
                    </button>
                </div>
                <div class="card-body bg-white p-2">
                    <div class="group-conditions-list mb-2"></div>
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100 border-dashed" onclick="addConditionToGroup('${groupId}')">
                        <i class="fas fa-plus"></i> 加入條件
                    </button>
                </div>
            </div>`;

        const div = document.createElement('div');
        div.innerHTML = groupHtml.trim();
        container.appendChild(div.firstChild);
    }

    function removeCriteriaGroup(groupId) {
        if (confirm('確定要刪除此條件群組嗎？')) {
            const el = document.getElementById(groupId);
            if (el) el.remove();
        }
    }

    function addConditionToGroup(groupId) {
        const groupEl = document.getElementById(groupId);
        if (!groupEl) {
            console.error('Group not found: ' + groupId);
            return;
        }
        const container = groupEl.querySelector('.group-conditions-list');
        const rowId = 'cond_' + Date.now() + '_' + Math.floor(Math.random() * 1000);

        const row = document.createElement('div');
        row.className = 'condition-row d-flex align-items-center mb-2 gap-2';
        row.id = rowId;

        // Group options by Type
        let optionsHtml = '<option value="">請選擇...</option>';
        const groups = {};
        availableAttributes.forEach(attr => {
            if (!groups[attr.type_label]) groups[attr.type_label] = [];
            groups[attr.type_label].push(attr);
        });

        for (const [label, attrs] of Object.entries(groups)) {
            optionsHtml += `<optgroup label="${label}">`;
            attrs.forEach(a => {
                optionsHtml += `<option value="${a.id}" data-type="${a.type_code}">${a.name}</option>`;
            });
            optionsHtml += `</optgroup>`;
        }

        row.innerHTML = `
            <select class="form-select form-select-sm cond-value" style="flex:1;">
                ${optionsHtml}
            </select>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeConditionRow('${rowId}')">
                <i class="fas fa-minus"></i>
            </button>
        `;
        container.appendChild(row);
    }

    function removeConditionRow(id) {
        const row = document.getElementById(id);
        if (row) row.remove();
    }

    // -- Course List & Edit Logic --

    function refreshMyCourses() {
        const listEl = document.getElementById('my-courses-list');
        listEl.innerHTML = `
            <div class="loading-skeleton">
                <div class="skeleton-pulse" style="height: 60px; margin-bottom: 15px;"></div>
                <div class="skeleton-pulse" style="height: 60px;"></div>
            </div>`;

        fetch('api/course/list_my_courses.php')
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (res.data.length === 0) {
                        listEl.innerHTML = '<div class="text-center p-5 text-muted">您尚未建立或管理任何課程</div>';
                        return;
                    }

                    let html = '<div class="list-group">';
                    res.data.forEach(c => {
                        let badge = '<span class="badge bg-secondary">一般</span>';
                        if (c.portal_rules) badge = '<span class="badge bg-info text-dark">有規則</span>';

                        html += `
                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">${c.fullname}</h5>
                                <small class="text-muted"><i class="fas fa-folder"></i> ${c.shortname}</small>
                            </div>
                            <div>
                                ${badge}
                                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="editCourseSettings(${c.id})">
                                    <i class="fas fa-cog"></i> 設定
                                </button>
                                <button class="btn btn-sm btn-outline-primary ms-2" onclick="goToMoodleCourse(${c.id})">
                                    <i class="fas fa-external-link-alt"></i> 進入課程
                                </button>
                            </div>
                        </div>`;
                    });
                    html += '</div>';
                    listEl.innerHTML = html;
                } else {
                    listEl.innerHTML = '<div class="alert alert-danger">載入失敗: ' + res.message + '</div>';
                }
            });
    }

    function editCourseSettings(id) {
        console.log("Editing course:", id);
        isEditMode = true;
        editingCourseId = id;

        // 1. Open Modal and Wait for dependencies to load
        const readyPromise = openCreateCourseModal();

        // 2. Fetch Data and populate when ready
        Promise.all([readyPromise, fetch('api/course/get.php?id=' + id)]).then(vals => {
            const fetchRes = vals[1]; // Result of fetch
            return fetchRes.json();
        }).then(res => {
            if (res.success) {
                const d = res.data;
                document.getElementById('new-course-fullname').value = d.fullname || '';
                document.getElementById('new-course-shortname').value = d.shortname || '';
                document.getElementById('new-course-category').value = d.category || '';

                if (d.package_id) {
                    document.getElementById('new-course-package').value = d.package_id;
                    // Enforce Lock State if package selected
                    if (d.package_id != '0') {
                        enableRuleEditing(false);
                    } else {
                        enableRuleEditing(true);
                    }
                } else {
                    document.getElementById('new-course-package').value = '0';
                    enableRuleEditing(true);
                }

                if (d.rules) {
                    const r = d.rules;
                    document.getElementById('policy_open').checked = r.open_policy;
                    document.getElementById('policy_restricted').checked = !r.open_policy;
                    toggleRuleUI();

                    if (!r.open_policy) {
                        // Depts
                        if (r.depts) {
                            r.depts.forEach(did => {
                                const cb = document.getElementById('dept_' + did);
                                if (cb) cb.checked = true;
                            });
                        }

                        const groupsContainer = document.getElementById('rule-groups-container');
                        groupsContainer.innerHTML = '';
                        groupCount = 0;

                        if (r.condition_groups) {
                            r.condition_groups.forEach(g => {
                                addNewCriteriaGroup();
                                const lastGroup = groupsContainer.lastElementChild;
                                const lastGroupId = lastGroup.id;
                                g.conditions.forEach(c => {
                                    addConditionToGroup(lastGroupId);
                                    const lastRow = lastGroup.querySelector('.group-conditions-list').lastElementChild;
                                    const sel = lastRow.querySelector('.cond-value');
                                    if (sel) sel.value = c.value;
                                });
                            });
                        } else if (r.conditions && r.conditions.length > 0) {
                            addNewCriteriaGroup();
                            const lastGroup = groupsContainer.lastElementChild;
                            const lastGroupId = lastGroup.id;
                            r.conditions.forEach(c => {
                                addConditionToGroup(lastGroupId);
                                const lastRow = lastGroup.querySelector('.group-conditions-list').lastElementChild;
                                const sel = lastRow.querySelector('.cond-value');
                                if (sel) sel.value = c.value;
                            });
                        } else {
                            addNewCriteriaGroup();
                        }
                    }
                }
            } else {
                alert('無法載入課程設定');
                closeCreateCourseModal();
            }
        }).catch(e => {
            console.error(e);
            alert('載入錯誤: ' + e);
            closeCreateCourseModal();
        });
    }

    function submitCreateCourse() {
        const submitBtn = document.getElementById('btn-submit');

        // Basic Validation
        const fullname = document.getElementById('new-course-fullname').value;
        const shortname = document.getElementById('new-course-shortname').value;
        const category = document.getElementById('new-course-category').value;

        if (!fullname || !shortname || !category) {
            alert('請填寫完整「基本資訊」後再儲存！');
            showTab(1);
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 處理中...';
        const isOpen = document.getElementById('policy_open').checked;
        const rules = {
            open_policy: isOpen,
            conditions: []
        };

        if (!isOpen) {
            const depts = [];
            document.querySelectorAll('.dept-checkbox:checked').forEach(cb => {
                depts.push(cb.value);
            });
            if (depts.length > 0) {
                rules.depts = depts;
            }

            const condition_groups = [];
            document.querySelectorAll('.criteria-group').forEach(groupEl => {
                const groupConditions = [];
                groupEl.querySelectorAll('.condition-row').forEach(row => {
                    const valSelect = row.querySelector('.cond-value');
                    const val = valSelect.value;
                    if (val) {
                        const selectedOption = valSelect.options[valSelect.selectedIndex];
                        const type = selectedOption.getAttribute('data-type');
                        if (type) {
                            groupConditions.push({ attr: type, value: val });
                        }
                    }
                });
                if (groupConditions.length > 0) {
                    condition_groups.push({ conditions: groupConditions });
                }
            });
            if (condition_groups.length > 0) {
                rules.condition_groups = condition_groups;
            }
        }

        const packageId = document.getElementById('new-course-package').value;
        const packageReq = document.getElementById('new-course-package-required').checked;

        const payload = {
            id: isEditMode ? editingCourseId : null,
            fullname: fullname,
            shortname: shortname,
            category: category,
            rules: rules,
            package_id: packageId,
            package_required: packageReq
        };

        const endpoint = isEditMode ? 'api/course/update.php' : 'api/course/create.php';

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert(isEditMode ? '設定已儲存' : '課程建立成功！');
                    if (isEditMode) {
                        closeCreateCourseModal();
                        refreshMyCourses();
                    } else {
                        window.location.href = res.redirect_url;
                    }
                } else {
                    alert('失敗：' + res.message);
                }
            })
            .catch(e => {
                alert('發生錯誤：' + e);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = isEditMode ? '儲存設定' : '建立課程';
            });
    }

    function goToMoodleCourse(id) {
        window.open('<?php echo $moodle_url; ?>/course/view.php?id=' + id, '_blank');
    }

    // Initial Load
    refreshMyCourses();
</script>