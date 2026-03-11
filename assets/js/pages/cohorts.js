/**
 * 群組管理頁面 JavaScript
 * assets/js/pages/cohorts.js
 * 
 * 從 hospital_admin_cohorts.php 抽離
 */

console.log('=== 群組管理 JavaScript 載入成功 ===');
// 全域變數
let allCohorts = [];
let filteredCohorts = [];
let currentDimension = 'all';
let currentCohortId = null;
let allUsersCache = [];
let allCategoriesCache = []; // 預載入所有類別

// 篩選器相關
let filterCategoryOptions = [];
let filterLocationOptions = [];
let filterAttributeOptions = [];
let filterTagOptions = [];
let filterDimensionsLoaded = false;
let filterGroupCounter = 0;

// 建構群組完整路徑（遞迴向上查找父群組）
function buildCohortPath(cohortId, separator = '/') {
    const pathParts = [];
    const visited = new Set();
    let currentId = cohortId;

    while (currentId && !visited.has(currentId)) {
        visited.add(currentId);
        const cohort = allCohorts.find(c => c.id == currentId);
        if (!cohort) break;

        pathParts.unshift(cohort.name);
        currentId = cohort.parent_cohort_id;
    }

    return pathParts.join(' ' + separator + ' ');
}

// Toast 通知
function showToast(message, type = 'success') {
    // 建立或重用 toast 容器
    let toast = document.getElementById('toast-notification');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast-notification';
        toast.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 24px;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 500;
            z-index: 9999;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        `;
        document.body.appendChild(toast);
    }

    toast.textContent = message;
    toast.style.background = type === 'error' ? '#fee2e2' : '#dcfce7';
    toast.style.color = type === 'error' ? '#dc2626' : '#16a34a';

    // 顯示
    setTimeout(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    }, 10);

    // 隱藏
    setTimeout(() => {
        toast.style.transform = 'translateY(100px)';
        toast.style.opacity = '0';
    }, 3000);
}

// 初始化
document.addEventListener('DOMContentLoaded', () => {
    // 確保從列表模式開始（避免 tab 切換後殘留成員面板）
    viewMode = 'list';
    currentCohortId = null;
    const membersView = document.getElementById('members-view');
    const listView = document.getElementById('cohorts-list-view');
    if (membersView) membersView.style.display = 'none';
    if (listView) listView.style.display = '';

    loadCohorts();
    // 載入新增群組的下拉選項
    loadDimensionOptions('new-cohort-dimension');
    // 預載入所有類別（背景執行，不阻塞）
    preloadCategories();
});

// 預載入所有類別
async function preloadCategories() {
    try {
        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=categories/list_all', {
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (data.success && data.data) {
            allCategoriesCache = data.data;
        }
    } catch (e) {
        console.error('Preload categories error:', e);
    }
}

// 新增群組 Modal 控制
function openNewCohortModal() {
    document.getElementById('new-cohort-modal').style.display = 'flex';
    document.getElementById('new-cohort-name').value = '';
    document.getElementById('new-cohort-name').focus();
    // 清空動態子類別容器
    document.getElementById('subcategory-container').innerHTML = '';
}

function closeNewCohortModal() {
    document.getElementById('new-cohort-modal').style.display = 'none';
}

// 建立新群組
async function createCohort(event) {
    event.preventDefault();

    const name = document.getElementById('new-cohort-name').value.trim();
    const dimensionSelect = document.getElementById('new-cohort-dimension');
    const dimensionValue = dimensionSelect.value;

    if (!name) {
        showToast('請輸入群組名稱', 'error');
        return;
    }

    // 解析維度值：dim_123 或 cat_123
    let dimensionTypeId = 0;
    let categoryId = 0;
    let parentCohortId = 0;

    if (dimensionValue.startsWith('dim_')) {
        dimensionTypeId = parseInt(dimensionValue.split('_')[1]);
    } else if (dimensionValue.startsWith('cat_')) {
        // 職類選項，取得 categoryId
        categoryId = parseInt(dimensionValue.split('_')[1]);

        // 找到與這個類別同名的群組作為父群組
        const selectedOpt = dimensionSelect.options[dimensionSelect.selectedIndex];
        const categoryName = selectedOpt ? selectedOpt.textContent.trim() : '';
        const matchingCohort = allCohorts.find(c => c.name === categoryName);
        if (matchingCohort) {
            parentCohortId = matchingCohort.id;
        }

        // 【重要】取得「職類」的 dimension_type_id
        try {
            const dimRes = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/list_types', {
                credentials: 'same-origin'
            });
            const dimData = await dimRes.json();
            if (dimData.success && dimData.data) {
                const jobDim = dimData.data.find(d => d.name === '職類');
                if (jobDim) dimensionTypeId = jobDim.id;
            }
        } catch (e) {
            console.error('Failed to get 職類 dimension:', e);
        }

        // 檢查級聯選單中最後選擇的項目
        const subcategorySelects = document.querySelectorAll('#subcategory-container .subcategory-select');
        subcategorySelects.forEach(sel => {
            if (sel.value) {
                if (sel.value.startsWith('cat_')) {
                    categoryId = parseInt(sel.value.split('_')[1]);
                    // 更新父群組為對應的群組
                    const opt = sel.options[sel.selectedIndex];
                    const catName = opt ? opt.textContent.trim() : '';
                    const cohort = allCohorts.find(c => c.name === catName);
                    if (cohort) parentCohortId = cohort.id;
                } else if (sel.value.startsWith('cohort_')) {
                    // 選了群組作為父群組
                    parentCohortId = parseInt(sel.value.split('_')[1]);
                }
            }
        });
    }

    try {
        const formData = new FormData();
        formData.append('action', 'create');
        formData.append('name', name);

        if (categoryId > 0) {
            formData.append('category_id', categoryId);
        }
        if (dimensionTypeId > 0) {
            formData.append('dimension_type_id', dimensionTypeId);
        }
        if (parentCohortId > 0) {
            formData.append('parent_cohort_id', parentCohortId);
        }

        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/create', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await res.json();
        if (data.success) {
            showToast('群組已建立');
            closeNewCohortModal();
            loadCohorts();
        } else {
            showToast(data.error || '建立失敗', 'error');
        }
    } catch (e) {
        console.error('Create cohort error:', e);
        showToast('建立失敗: ' + e.message, 'error');
    }
}

// 維度選擇變更時載入第一層子類別
async function onDimensionChange(selectEl) {
    const container = document.getElementById('subcategory-container');
    const selectedOption = selectEl.options[selectEl.selectedIndex];

    // 清空所有子類別層級
    container.innerHTML = '';

    // 取得 data-catid（職類對應的 Moodle 類別 ID）
    const categoryId = selectedOption ? selectedOption.getAttribute('data-catid') : null;

    // 如果沒有 categoryId，不載入子類別
    if (!categoryId) return;

    // 載入第一層子類別
    await loadSubcategoryLevel(categoryId, 0);
}

// 動態載入子類別層級（遞迴支援無限層級，同時載入類別和群組）
async function loadSubcategoryLevel(parentId, level) {
    const container = document.getElementById('subcategory-container');

    // 移除當前層級及之後的所有層級
    const existingLevels = container.querySelectorAll('.subcategory-level');
    existingLevels.forEach((el, idx) => {
        if (idx >= level) el.remove();
    });

    try {
        // 1. 從快取取得子類別（Moodle 課程類別）
        const childCategories = allCategoriesCache.filter(c => c.parent == parentId);

        // 2. 找這個類別對應的群組（名稱相同的群組）
        const parentCategory = allCategoriesCache.find(c => c.id == parentId);
        const parentCategoryName = parentCategory ? parentCategory.name : '';

        // 找名稱與類別相同的群組，然後找其子群組
        const matchingCohort = allCohorts.find(c => c.name === parentCategoryName);
        const parentCohortId = matchingCohort ? matchingCohort.id : null;

        // 3. 找子群組（用 parent_cohort_id）
        const childCohorts = parentCohortId
            ? allCohorts.filter(c => c.parent_cohort_id == parentCohortId)
            : [];

        // 如果都沒有子項，不顯示
        if (childCategories.length === 0 && childCohorts.length === 0) return;

        const levelDiv = document.createElement('div');
        levelDiv.className = 'subcategory-level form-group';
        levelDiv.setAttribute('data-level', level);
        levelDiv.style.marginBottom = '16px';

        levelDiv.innerHTML = `
            <label style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">
                ${level === 0 ? '下一層' : '第' + (level + 2) + '層'}
            </label>
            <select class="subcategory-select" data-level="${level}"
                style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px;">
                <option value="">選擇（選填）</option>
            </select>
        `;

        const select = levelDiv.querySelector('select');
        const addedNames = new Set(); // 防止重複

        // 先加類別（只加那些沒有對應群組的）
        childCategories.forEach(cat => {
            // 檢查是否已有同名群組
            const hasMatchingCohort = childCohorts.some(c => c.name === cat.name);
            if (!hasMatchingCohort && !addedNames.has(cat.name)) {
                select.innerHTML += `<option value="cat_${cat.id}" data-catid="${cat.id}" data-type="category">${cat.name}</option>`;
                addedNames.add(cat.name);
            }
        });

        // 再加群組
        childCohorts.forEach(cohort => {
            if (!addedNames.has(cohort.name)) {
                select.innerHTML += `<option value="cohort_${cohort.id}" data-cohortid="${cohort.id}" data-type="cohort">${cohort.name}</option>`;
                addedNames.add(cohort.name);
            }
        });


        select.addEventListener('change', async function () {
            const selectedOpt = this.options[this.selectedIndex];
            const type = selectedOpt ? selectedOpt.getAttribute('data-type') : null;

            if (type === 'category') {
                const catId = selectedOpt.getAttribute('data-catid');
                await loadSubcategoryLevel(catId, level + 1);
            } else if (type === 'cohort') {
                const cohortId = selectedOpt.getAttribute('data-cohortid');
                await loadChildCohortsLevel(cohortId, level + 1);
            } else {
                const allLevels = container.querySelectorAll('.subcategory-level');
                allLevels.forEach((el, idx) => { if (idx > level) el.remove(); });
            }
        });

        container.appendChild(levelDiv);
    } catch (e) {
        console.error('loadSubcategoryLevel error:', e);
    }
}

// 載入子群組層級
async function loadChildCohortsLevel(parentCohortId, level) {
    const container = document.getElementById('subcategory-container');

    const existingLevels = container.querySelectorAll('.subcategory-level');
    existingLevels.forEach((el, idx) => { if (idx >= level) el.remove(); });

    const childCohorts = allCohorts.filter(c => c.parent_cohort_id == parentCohortId);
    if (childCohorts.length === 0) return;

    const levelDiv = document.createElement('div');
    levelDiv.className = 'subcategory-level form-group';
    levelDiv.setAttribute('data-level', level);
    levelDiv.style.marginBottom = '16px';

    levelDiv.innerHTML = `
        <label style="display:block; margin-bottom:6px; font-weight:500; color:#4b5563;">子群組</label>
        <select class="subcategory-select" data-level="${level}"
            style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px;">
            <option value="">選擇（選填）</option>
        </select>
    `;

    const select = levelDiv.querySelector('select');
    childCohorts.forEach(c => {
        select.innerHTML += `<option value="cohort_${c.id}" data-cohortid="${c.id}" data-type="cohort">${c.name}</option>`;
    });

    select.addEventListener('change', async function () {
        const opt = this.options[this.selectedIndex];
        const cohortId = opt ? opt.getAttribute('data-cohortid') : null;
        if (cohortId) await loadChildCohortsLevel(cohortId, level + 1);
        else {
            const all = container.querySelectorAll('.subcategory-level');
            all.forEach((el, idx) => { if (idx > level) el.remove(); });
        }
    });

    container.appendChild(levelDiv);
}

// 編輯群組 Modal
function openEditCohortModal() {
    if (!currentCohortId) return;

    const cohort = allCohorts.find(c => c.id == currentCohortId);
    if (!cohort) return;

    // 設定表單值
    document.getElementById('edit-cohort-id').value = currentCohortId;
    document.getElementById('edit-cohort-name').value = cohort.name || '';

    // 載入維度選項（用 dimension_name 來匹配）
    loadDimensionOptions('edit-cohort-dimension', cohort.dimension_name);

    // 判斷是否為受保護群組
    // 主群組：受保護
    // 職類：有 parent_category_id 或 depth >= 2 才可刪除
    const deleteBtn = document.getElementById('delete-cohort-btn');
    const isMainGroup = cohort.dimension_name === '主群組';
    const isProtectedJobType = cohort.dimension_name === '職類' &&
        !cohort.parent_category_id &&
        (cohort.category_depth === null || cohort.category_depth <= 1);

    console.log('openEditCohortModal delete check:', {
        name: cohort.name,
        dimension: cohort.dimension_name,
        parent: cohort.parent_category_id,
        depth: cohort.category_depth,
        isMainGroup,
        isProtectedJobType,
        shouldHide: isMainGroup || isProtectedJobType
    });

    if (isMainGroup || isProtectedJobType) {
        deleteBtn.style.display = 'none';
    } else {
        deleteBtn.style.display = 'inline-flex';
    }

    document.getElementById('edit-cohort-modal').style.display = 'flex';
}

function closeEditCohortModal() {
    document.getElementById('edit-cohort-modal').style.display = 'none';
}

// 載入所屬選項（維度類型 + 具體職類）
// selectedValue 可以是: dimension_type_id（數字）或 dimension_name（字串）
async function loadDimensionOptions(selectId, selectedValue = null) {
    const select = document.getElementById(selectId);
    if (!select) return;

    try {
        // 載入維度類型（屬性、所屬等）
        const dimRes = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/list_types', {
            credentials: 'same-origin'
        });
        const dimData = await dimRes.json();

        // 載入職類類別（Moodle categories）
        const catRes = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=categories/list_children', {
            credentials: 'same-origin'
        });
        const catData = await catRes.json();

        select.innerHTML = '<option value="">請選擇</option>';

        const addedNames = new Set(); // 防止重複

        // 找出要選中的項目名稱
        let selectedName = null;
        if (selectedValue) {
            // 如果傳入的是數字 ID，從維度類型找對應名稱
            if (typeof selectedValue === 'number' || !isNaN(parseInt(selectedValue))) {
                const dimId = parseInt(selectedValue);
                if (dimData.success && dimData.data) {
                    const matchDim = dimData.data.find(d => d.id == dimId);
                    if (matchDim) selectedName = matchDim.name;
                }
            } else {
                // 直接使用名稱
                selectedName = selectedValue;
            }
        }

        // 先顯示具體職類選項（有 data-catid，可載入子類別）
        if (catData.success && catData.data && catData.data.length > 0) {
            catData.data.forEach(cat => {
                if (addedNames.has(cat.name)) return; // 跳過重複
                addedNames.add(cat.name);
                const selected = selectedName && cat.name === selectedName ? 'selected' : '';
                // data-catid 用於載入子類別
                select.innerHTML += `<option value="cat_${cat.id}" data-catid="${cat.id}" ${selected}>${cat.name}</option>`;
            });
        }

        // 再顯示維度類型（排除「職類」和「主群組」，這些沒有 data-catid）
        if (dimData.success && dimData.data) {
            dimData.data.forEach(dim => {
                if (dim.name === '職類' || dim.name === '主群組') return; // 跳過
                if (addedNames.has(dim.name)) return; // 跳過重複（如果已被職類加過）
                addedNames.add(dim.name);
                const selected = selectedName && dim.name === selectedName ? 'selected' : '';
                select.innerHTML += `<option value="dim_${dim.id}" ${selected}>${dim.name}</option>`;
            });
        }
    } catch (e) {
        console.error('Failed to load dimensions:', e);
    }
}

// 更新群組維度
async function updateCohort(event) {
    event.preventDefault();

    const cohortId = document.getElementById('edit-cohort-id').value;
    const selectEl = document.getElementById('edit-cohort-dimension');
    const rawValue = selectEl.value;
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const selectedName = selectedOpt ? selectedOpt.textContent.trim() : '';

    console.log('updateCohort 開始:', { cohortId, rawValue, selectedName });

    // 解析選項值
    let dimensionTypeId = 0;
    let parentCohortId = 0;

    if (rawValue.startsWith('dim_')) {
        dimensionTypeId = parseInt(rawValue.split('_')[1]);
        console.log('解析 dim_:', dimensionTypeId);
    } else if (rawValue.startsWith('cat_')) {
        // 職類：找同名的群組作為父群組
        const matchingCohort = allCohorts.find(c => c.name === selectedName);
        if (matchingCohort) {
            parentCohortId = matchingCohort.id;
        }
        console.log('cat_ 選項，父群組:', parentCohortId);

        // 需要找到「職類」維度的 ID
        try {
            const dimRes = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/list_types', {
                credentials: 'same-origin'
            });
            const dimData = await dimRes.json();
            console.log('維度類型列表:', dimData);
            if (dimData.success && dimData.data) {
                const jobDim = dimData.data.find(d => d.name === '職類');
                if (jobDim) {
                    dimensionTypeId = jobDim.id;
                    console.log('找到職類維度 ID:', dimensionTypeId);
                } else {
                    console.error('找不到職類維度!');
                }
            }
        } catch (e) {
            console.error('Failed to get dimension type:', e);
        }
    } else if (!rawValue || rawValue === '') {
        // 空值 - 清除維度
        console.log('清除維度');
    }

    console.log('準備發送:', { cohortId, dimensionTypeId, parentCohortId });

    try {
        const formData = new FormData();
        formData.append('action', 'update_dimension');
        formData.append('cohort_id', cohortId);
        formData.append('dimension_type_id', dimensionTypeId);
        if (parentCohortId > 0) {
            formData.append('parent_cohort_id', parentCohortId);
        }

        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/update_dimension', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await res.json();
        console.log('API 回應:', data);

        if (data.success) {
            showToast('群組設定已更新');
            closeEditCohortModal();
            loadCohorts();
        } else {
            showToast(data.error || '更新失敗', 'error');
        }
    } catch (e) {
        console.error('updateCohort error:', e);
        showToast('更新失敗', 'error');
    }
}

// 刪除群組
async function deleteCohort() {
    if (!currentCohortId) return;

    const cohort = allCohorts.find(c => c.id == currentCohortId);
    if (!confirm(`確定要刪除群組「${cohort?.name || ''}」嗎？此操作無法復原。`)) return;

    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', currentCohortId);

        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/delete', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await res.json();
        if (data.success) {
            showToast('群組已刪除');
            closeEditCohortModal();
            closeMembersPanel();
            loadCohorts();
        } else {
            showToast(data.error || '刪除失敗', 'error');
        }
    } catch (e) {
        showToast('刪除失敗', 'error');
    }
}

// 載入群組（含維度資訊）
async function loadCohorts() {
    try {
        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/list_with_dimensions', {
            credentials: 'same-origin'
        });
        const data = await res.json();

        console.log('Cohorts API response:', data);

        if (data.success) {
            allCohorts = data.data || [];
            updateCounts();
            filterByDimension(currentDimension);
        } else {
            // 顯示 API 錯誤訊息
            document.getElementById('cohorts-grid').innerHTML = `
                <div class="empty-cohorts" style="grid-column: 1/-1;">
                    <i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i>
                    <p style="color:#ef4444;">${data.error || '載入失敗'}</p>
                </div>
            `;
        }
    } catch (e) {
        console.error('Failed to load cohorts:', e);
        document.getElementById('cohorts-grid').innerHTML = `
            <div class="empty-cohorts">
                <i class="fas fa-exclamation-triangle"></i>
                <p>載入失敗: ${e.message}</p>
            </div>
        `;
    }
}

// 更新各維度數量
function updateCounts() {
    const counts = { all: allCohorts.length, '主群組': 0, '職類': 0, '所屬': 0, '屬性': 0, '未分類': 0 };

    allCohorts.forEach(c => {
        if (c.dimension_name === '主群組') {
            counts['主群組']++;
        } else if (c.dimension_name) {
            counts[c.dimension_name] = (counts[c.dimension_name] || 0) + 1;
        } else {
            counts['未分類']++;
        }
    });

    Object.keys(counts).forEach(key => {
        const el = document.getElementById(`count-${key}`);
        if (el) el.textContent = counts[key];
    });
}

// 按維度篩選
function filterByDimension(dimension) {
    currentDimension = dimension;
    currentSubCategory = null; // 重置子分類
    resetNavigation(); // 重置層級導航

    // 更新 Tab 狀態
    document.querySelectorAll('.dimension-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.dim === dimension);
    });

    // 篩選
    if (dimension === 'all') {
        filteredCohorts = allCohorts;
        hideSubTabs();
    } else if (dimension === '未分類') {
        filteredCohorts = allCohorts.filter(c => !c.dimension_name);
        hideSubTabs();
    } else if (dimension === '主群組') {
        filteredCohorts = allCohorts.filter(c => c.dimension_name === dimension);
        hideSubTabs();
    } else if (dimension === '職類') {
        // 職類需要顯示二級 Tab
        filteredCohorts = allCohorts.filter(c => c.dimension_name === dimension);
        showSubTabs(filteredCohorts);
    } else {
        filteredCohorts = allCohorts.filter(c => c.dimension_name === dimension);
        hideSubTabs();
    }

    renderCohorts();
}

let currentSubCategory = null;

// 隱藏二級 Tab
function hideSubTabs() {
    document.getElementById('sub-tabs').style.display = 'none';
}

// 顯示二級 Tab
function showSubTabs(cohorts) {
    const subTabsContainer = document.getElementById('sub-tabs');

    // 從 cohorts 提取子分類（用 category_path 的第一層）
    const subCategories = new Set();
    cohorts.forEach(c => {
        // category_path 可能是空字串（頂層）或 "護理職類" 或 "護理職類 > 內科"
        if (c.category_path) {
            const firstLevel = c.category_path.split(' > ')[0];
            subCategories.add(firstLevel);
        } else if (c.name) {
            // 沒有 path 的就是頂層群組本身
            subCategories.add(c.name);
        }
    });

    // 生成二級 Tab
    let html = `<button class="sub-tab active" onclick="filterBySubCategory(null)">全部</button>`;
    Array.from(subCategories).sort().forEach(cat => {
        html += `<button class="sub-tab" onclick="filterBySubCategory('${escapeHtml(cat)}')">${escapeHtml(cat)}</button>`;
    });

    subTabsContainer.innerHTML = html;
    subTabsContainer.style.display = 'flex';
}

// 二級分類篩選
function filterBySubCategory(subCat) {
    currentSubCategory = subCat;

    // 更新二級 Tab 狀態
    document.querySelectorAll('.sub-tab').forEach(tab => {
        if (subCat === null) {
            tab.classList.toggle('active', tab.textContent === '全部');
        } else {
            tab.classList.toggle('active', tab.textContent === subCat);
        }
    });

    // 篩選
    let base = allCohorts.filter(c => c.dimension_name === currentDimension);

    if (subCat) {
        // 篩選符合子分類的群組
        base = base.filter(c => {
            // 群組名稱就是子分類
            if (c.name === subCat) return true;
            // 或者 category_path 以該子分類開頭
            if (c.category_path && c.category_path.startsWith(subCat)) return true;
            return false;
        });
    }

    filteredCohorts = base;
    renderCohorts();
}

// 搜尋群組
function searchCohorts(term) {
    term = term.toLowerCase();
    const base = currentDimension === 'all' ? allCohorts : filteredCohorts;

    if (!term) {
        filterByDimension(currentDimension);
        return;
    }

    const results = base.filter(c => c.name.toLowerCase().includes(term));
    renderCohortsList(results);
}

// 渲染群組 - 新的層級導航版本
let navigationPath = []; // 導航路徑堆疊

function renderCohorts() {
    renderHierarchyView(filteredCohorts);
}

function renderHierarchyView(cohorts) {
    const listContainer = document.getElementById('cohorts-list');
    const subFoldersContainer = document.getElementById('sub-folders');
    const breadcrumbNav = document.getElementById('breadcrumb-nav');
    const breadcrumbPath = document.getElementById('breadcrumb-path');

    // 隱藏子分類卡片區
    subFoldersContainer.style.display = 'none';
    hideSubTabs();

    // 建立 parent_cohort_id 對應表
    const childrenMap = new Map(); // parent_id -> [children]
    const cohortById = new Map();

    cohorts.forEach(c => {
        cohortById.set(c.id, c);
        const parentId = c.parent_cohort_id || 0;
        if (!childrenMap.has(parentId)) {
            childrenMap.set(parentId, []);
        }
        childrenMap.get(parentId).push(c);
    });

    // 計算每個群組的子群組數量
    function countChildren(cohortId) {
        const children = childrenMap.get(cohortId) || [];
        return children.length;
    }

    // 導航邏輯
    let currentParentId = 0;
    if (navigationPath.length > 0) {
        // 找到當前路徑最後一項對應的群組 ID
        const lastNav = navigationPath[navigationPath.length - 1];
        const navCohort = cohorts.find(c => c.name === lastNav);
        if (navCohort) {
            currentParentId = navCohort.id;
        }
    }

    // 渲染麵包屑
    if (navigationPath.length > 0) {
        breadcrumbNav.style.display = 'flex';
        breadcrumbPath.innerHTML = navigationPath.map((p, i) =>
            `<span>${i > 0 ? ' / ' : ''}</span>${escapeHtml(p)}`
        ).join('');
    } else {
        breadcrumbNav.style.display = 'none';
    }

    // 取得當前層級的群組
    // 如果在根層級，顯示沒有父群組的，或父群組是主群組的
    // 如果在子層級，顯示 parent_cohort_id = currentParentId 的
    let currentLevelCohorts = [];

    if (currentParentId === 0) {
        // 根層級：顯示頂層群組（沒有父群組，或父群組不在當前維度篩選中）
        currentLevelCohorts = cohorts.filter(c => {
            const parentId = c.parent_cohort_id || 0;
            // 沒有父群組
            if (parentId === 0) return true;
            // 父群組是主群組 - 也算頂層
            const parent = cohortById.get(parentId);
            if (parent && parent.dimension_name === '主群組') return true;
            // 父群組不在當前篩選的 cohorts 中
            if (!cohortById.has(parentId)) return true;
            return false;
        });
    } else {
        // 子層級：顯示 parent_cohort_id = currentParentId 的群組
        currentLevelCohorts = childrenMap.get(currentParentId) || [];

        // 也添加當前導航的群組本身（如果還沒在列表中）
        const currentCohort = cohortById.get(currentParentId);
        if (currentCohort && !currentLevelCohorts.find(c => c.id === currentCohort.id)) {
            // 不加入，因為我們進入的是它的子層級
        }
    }

    // 構建顯示列表，標記哪些有子群組
    let allRows = currentLevelCohorts.map(c => {
        const childCount = countChildren(c.id);
        return {
            ...c,
            childCount: childCount,
            hasChildren: childCount > 0
        };
    });

    // 排序：主群組優先，有子群組的排前面，然後按名稱排序
    allRows.sort((a, b) => {
        // 主群組最優先
        if (a.dimension_name === '主群組' && b.dimension_name !== '主群組') return -1;
        if (b.dimension_name === '主群組' && a.dimension_name !== '主群組') return 1;
        // 有子群組的排前面
        if (a.hasChildren && !b.hasChildren) return -1;
        if (b.hasChildren && !a.hasChildren) return 1;
        // 同類按名稱排序
        return a.name.localeCompare(b.name, 'zh-TW');
    });

    // 渲染列表
    if (allRows.length === 0) {
        listContainer.innerHTML = `
            <div class="empty-cohorts">
                <i class="fas fa-folder-open"></i>
                <p>此分類沒有群組</p>
            </div>
        `;
    } else {
        listContainer.innerHTML = allRows.map(c => `
            <div class="cohort-row ${currentCohortId === c.id ? 'selected' : ''} ${c.hasChildren ? 'has-children' : ''}" 
                 data-id="${c.id}"
                 onclick="openMembersPanel(${c.id}, '${escapeHtml(c.name)}', ${c.hasChildren})">
                <div class="cohort-row-name">
                    ${c.hasChildren ? '<i class="fas fa-chevron-right cohort-row-arrow"></i>' : ''}
                    ${escapeHtml(c.name)}
                    ${c.childCount ? `<span class="cohort-child-count">(${c.childCount} 個子群組)</span>` : ''}
                </div>
                <div class="cohort-row-meta">
                    ${c.dimension_name ? `<span class="cohort-row-badge">${c.dimension_name}</span>` : ''}
                    <span><i class="fas fa-users"></i> ${c.member_count || 0}</span>
                </div>
            </div>
        `).join('');
    }
}

// 進入子分類
function navigateInto(categoryName) {
    navigationPath.push(categoryName);
    renderCohorts();
}

// navigateBack 定義在下方 (處理 viewMode)

// 重置導航（當切換維度時）
function resetNavigation() {
    navigationPath = [];
}

// 舊版渲染函數 - 保留向後兼容
function renderCohortsList(cohorts) {
    renderHierarchyView(cohorts);
}

// 開啟成員頁面（進入成員視圖）
let viewMode = 'list'; // 'list' | 'members'
let currentCohortName = '';
let currentCohortHasChildren = false;

function openMembersPanel(cohortId, cohortName, hasChildren = false) {
    currentCohortId = cohortId;
    currentCohortName = cohortName;
    currentCohortHasChildren = hasChildren;
    viewMode = 'members';

    // 判斷是否為受保護群組，控制設定按鈕顯示
    const cohort = allCohorts.find(c => c.id == cohortId);
    const settingsBtn = document.getElementById('settings-cohort-btn');
    console.log('openMembersPanel:', {
        cohortId, cohort, settingsBtn,
        dimension: cohort?.dimension_name,
        parent: cohort?.parent_category_id,
        depth: cohort?.category_depth
    });

    if (cohort && settingsBtn) {
        const isMainGroup = cohort.dimension_name === '主群組';
        // 隱藏職類第一層：維度是職類，且父群組是主群組
        const mainCohort = allCohorts.find(c => c.dimension_name === '主群組');
        const mainCohortId = mainCohort ? String(mainCohort.id) : null;
        const isTopLevelJobType = cohort.dimension_name === '職類' &&
            String(cohort.parent_cohort_id) === mainCohortId;

        if (isMainGroup || isTopLevelJobType) {
            settingsBtn.style.display = 'none';
        } else {
            settingsBtn.style.display = '';
        }
    }

    // 更新麵包屑
    const breadcrumbNav = document.getElementById('breadcrumb-nav');
    const breadcrumbPath = document.getElementById('breadcrumb-path');
    breadcrumbNav.style.display = 'flex';

    const pathText = navigationPath.length > 0
        ? navigationPath.join(' / ') + ' / ' + escapeHtml(cohortName)
        : escapeHtml(cohortName);
    breadcrumbPath.innerHTML = pathText;

    // 隱藏子分類區
    document.getElementById('sub-folders').style.display = 'none';

    // 渲染成員視圖
    renderMembersView();
    loadMembers(cohortId);
}

function closeMembersPanel() {
    viewMode = 'list';
    currentCohortId = null;
    currentCohortName = '';

    // 隱藏麵包屑
    document.getElementById('breadcrumb-nav').style.display = 'none';

    // 重新渲染群組列表
    renderCohorts();
}

// 返回按鈕
function navigateBack() {
    if (viewMode === 'members') {
        closeMembersPanel();
    } else if (navigationPath.length > 0) {
        // 返回上一層分類
        navigationPath.pop();
        renderCohorts();
    } else {
        // 返回根目錄
        document.getElementById('breadcrumb-nav').style.display = 'none';
        renderCohorts();
    }
}

// 子群組收合狀態
let childrenListCollapsed = false;

// 切換子群組顯示/隱藏
function toggleChildrenList() {
    const content = document.getElementById('children-list-content');
    const icon = document.getElementById('children-toggle-icon');
    const text = document.getElementById('children-toggle-text');
    const folderIcon = document.getElementById('children-folder-icon');

    if (!content) return;

    childrenListCollapsed = !childrenListCollapsed;

    if (childrenListCollapsed) {
        content.style.display = 'none';
        icon.className = 'fas fa-chevron-down';
        text.textContent = '展開';
        folderIcon.className = 'fas fa-folder';
    } else {
        content.style.display = '';
        icon.className = 'fas fa-chevron-up';
        text.textContent = '收合';
        folderIcon.className = 'fas fa-folder-open';
    }
}

// 渲染成員視圖
function renderMembersView() {
    const listContainer = document.getElementById('cohorts-list');

    // 判斷是否為受保護群組
    const cohort = allCohorts.find(c => c.id == currentCohortId);
    const isMainGroup = cohort?.dimension_name === '主群組';
    const mainCohort = allCohorts.find(c => c.dimension_name === '主群組');
    const mainCohortId = mainCohort ? String(mainCohort.id) : null;
    const isTopLevelJobType = cohort?.dimension_name === '職類' &&
        String(cohort?.parent_cohort_id) === mainCohortId;
    const isProtected = isMainGroup || isTopLevelJobType;

    console.log('renderMembersView protection check:', {
        cohortId: currentCohortId,
        cohortName: cohort?.name,
        dimension: cohort?.dimension_name,
        parentCohortId: cohort?.parent_cohort_id,
        isMainGroup,
        isTopLevelJobType,
        isProtected
    });

    // 取得子群組
    const childCohorts = allCohorts.filter(c => c.parent_cohort_id == currentCohortId);
    const hasChildren = childCohorts.length > 0;

    // 子群組列表 HTML（使用原本的 cohort-row 樣式，加入收合功能）
    let childrenHtml = '';
    if (hasChildren) {
        childrenHtml = `
            <div class="children-section">
                <div class="children-header" onclick="toggleChildrenList()" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: #f8fafc; border-radius: 10px; margin-bottom: 12px; cursor: pointer; user-select: none;">
                    <span style="font-weight: 600; color: #475569; font-size: 0.9rem;">
                        <i class="fas fa-folder-open" id="children-folder-icon"></i>
                        子群組 (${childCohorts.length})
                    </span>
                    <button id="toggle-children-btn" style="background: none; border: none; color: #64748b; cursor: pointer; padding: 4px 8px; border-radius: 6px; transition: background 0.2s;">
                        <i class="fas fa-chevron-up" id="children-toggle-icon"></i>
                        <span id="children-toggle-text">收合</span>
                    </button>
                </div>
                <div class="children-list" id="children-list-content">
                    ${childCohorts.map(child => {
            const childHasChildren = allCohorts.some(c => c.parent_cohort_id == child.id);
            const childChildCount = allCohorts.filter(c => c.parent_cohort_id == child.id).length;
            return `
                        <div class="cohort-row ${childHasChildren ? 'has-children' : ''}" 
                             data-id="${child.id}"
                             onclick="openMembersPanel(${child.id}, '${escapeHtml(child.name)}', ${childHasChildren})">
                            <div class="cohort-row-name">
                                ${childHasChildren ? '<i class="fas fa-chevron-right cohort-row-arrow"></i>' : ''}
                                ${escapeHtml(child.name)}
                                ${childChildCount ? `<span class="cohort-child-count">(${childChildCount} 個子群組)</span>` : ''}
                            </div>
                            <div class="cohort-row-meta">
                                ${child.dimension_name ? `<span class="cohort-row-badge">${child.dimension_name}</span>` : ''}
                                <span><i class="fas fa-users"></i> ${child.member_count || 0}</span>
                            </div>
                        </div>
                    `;
        }).join('')}
                </div>
            </div>
        `;
    }

    listContainer.innerHTML = `
        ${childrenHtml}
        <div style="background: white; border-radius: 12px; margin-top: ${hasChildren ? '32px' : '0'}; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
            <div class="members-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #e2e8f0;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-users" style="color: #3b82f6;"></i>
                    ${escapeHtml(currentCohortName)} 的成員
                </h3>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-compact primary" onclick="openAddMemberModal()">
                        <i class="fas fa-user-plus"></i> 加入成員
                    </button>
                    <button class="btn-compact secondary" onclick="openImportFromGroupModal()">
                        <i class="fas fa-file-import"></i> 從其他群組匯入
                    </button>
                    ${!isProtected ? `
                    <button class="btn-compact secondary" onclick="openEditCohortModal()" title="設定群組">
                        <i class="fas fa-cog"></i> 設定
                    </button>
                    ` : ''}
                </div>
            </div>
            <div class="members-grid" id="members-grid">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>載入成員中...</p>
                </div>
            </div>
        </div>
    `;
}

// 載入成員
async function loadMembers(cohortId) {
    const container = document.getElementById('members-grid');
    if (!container) return;
    if (!cohortId) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-info-circle"></i><p>請選擇一個群組</p></div>';
        return;
    }

    container.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>載入成員中...</p></div>';

    try {
        const res = await fetch(`${PortalConfig.webRoot}/api_get_members.php?cohort_id=${cohortId}`, {
            credentials: 'same-origin'
        });
        const data = await res.json();
        console.log('Members API response:', data);

        if (data.success) {
            // API 返回 {cohort_id, members, count}，取 members 陣列
            const members = data.data?.members || data.data || [];
            renderMembers(members);

            // 更新緩存
            const count = Array.isArray(members) ? members.length : 0;
            const cohort = allCohorts.find(c => c.id == cohortId);
            if (cohort) cohort.member_count = count;
        } else {
            container.innerHTML = '<div class="empty-state error"><i class="fas fa-exclamation-triangle"></i><p>載入失敗: ' + (data.error || data.message || '未知錯誤') + '</p></div>';
        }
    } catch (e) {
        console.error('loadMembers error:', e);
        container.innerHTML = '<div class="empty-state error"><i class="fas fa-exclamation-triangle"></i><p>載入失敗</p></div>';
    }
}

let allMembersCache_page = [];
let memberCurrentPage = 1;
const MEMBERS_PER_PAGE = 20;
let memberSearchTerm = '';

function renderMembers(members) {
    const container = document.getElementById('members-grid');
    if (!container) return;

    allMembersCache_page = members;
    memberCurrentPage = 1;
    memberSearchTerm = '';
    renderMembersPage();
}

function getFilteredMembers() {
    if (!memberSearchTerm) return allMembersCache_page;
    const term = memberSearchTerm.toLowerCase();
    return allMembersCache_page.filter(m =>
        (m.fullname || '').toLowerCase().includes(term) ||
        (m.username || '').toLowerCase().includes(term) ||
        (m.email || '').toLowerCase().includes(term)
    );
}

function renderMembersPage() {
    const container = document.getElementById('members-grid');
    if (!container) return;

    const filtered = getFilteredMembers();
    const totalCount = filtered.length;

    if (totalCount === 0 && !memberSearchTerm) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-user-friends"></i>
                <p>尚無成員</p>
                <button class="btn-compact primary" onclick="openAddMemberModal()">
                    <i class="fas fa-user-plus"></i> 加入第一位成員
                </button>
            </div>
        `;
        return;
    }

    const totalPages = Math.max(1, Math.ceil(totalCount / MEMBERS_PER_PAGE));
    if (memberCurrentPage > totalPages) memberCurrentPage = totalPages;
    const startIdx = (memberCurrentPage - 1) * MEMBERS_PER_PAGE;
    const pageMembers = filtered.slice(startIdx, startIdx + MEMBERS_PER_PAGE);

    // 搜尋列
    const searchHtml = `
        <div style="padding:12px 16px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-search" style="color:#94a3b8;"></i>
            <input type="text" id="member-search-input" placeholder="搜尋成員姓名、帳號、Email..."
                value="${escapeHtml(memberSearchTerm)}"
                oninput="onMemberSearch(this.value)"
                style="flex:1; border:none; outline:none; font-size:0.9rem; background:transparent;">
            ${memberSearchTerm ? `<button onclick="onMemberSearch('')" style="background:none;border:none;cursor:pointer;color:#94a3b8;"><i class="fas fa-times"></i></button>` : ''}
        </div>
    `;

    // 成員表格
    const tableHtml = pageMembers.length === 0
        ? `<div class="empty-state"><i class="fas fa-search"></i><p>沒有符合「${escapeHtml(memberSearchTerm)}」的成員</p></div>`
        : `<div class="members-table">
            <div class="members-table-header">
                <input type="checkbox" id="select-all-members" onchange="toggleSelectAllMembers(this)">
                <span>姓名</span>
                <span>帳號</span>
                <span>Email</span>
                <span>操作</span>
            </div>
            ${pageMembers.map(m => `
                <div class="member-row">
                    <input type="checkbox" class="member-checkbox" value="${m.id}">
                    <div class="member-name-cell">
                        <div class="member-avatar">${(m.fullname || m.username || '?').charAt(0).toUpperCase()}</div>
                        <span>${escapeHtml(m.fullname || m.username)}</span>
                    </div>
                    <span class="member-username">${escapeHtml(m.username || '-')}</span>
                    <span class="member-email">${escapeHtml(m.email || '-')}</span>
                    <button class="btn-icon danger" onclick="removeSingleMember(${m.id})" title="移除成員">
                        <i class="fas fa-user-minus"></i>
                    </button>
                </div>
            `).join('')}
        </div>`;

    // 分頁列
    let paginationHtml = '';
    if (totalPages > 1) {
        let pageButtons = '';
        const maxButtons = 5;
        let startPage = Math.max(1, memberCurrentPage - Math.floor(maxButtons / 2));
        let endPage = Math.min(totalPages, startPage + maxButtons - 1);
        if (endPage - startPage < maxButtons - 1) startPage = Math.max(1, endPage - maxButtons + 1);

        if (startPage > 1) pageButtons += `<button class="page-btn" onclick="goMemberPage(1)">1</button>`;
        if (startPage > 2) pageButtons += `<span style="color:#94a3b8;">...</span>`;
        for (let p = startPage; p <= endPage; p++) {
            pageButtons += `<button class="page-btn ${p === memberCurrentPage ? 'active' : ''}" onclick="goMemberPage(${p})">${p}</button>`;
        }
        if (endPage < totalPages - 1) pageButtons += `<span style="color:#94a3b8;">...</span>`;
        if (endPage < totalPages) pageButtons += `<button class="page-btn" onclick="goMemberPage(${totalPages})">${totalPages}</button>`;

        paginationHtml = `
            <div style="display:flex; align-items:center; justify-content:center; gap:6px; padding:12px 16px; border-top:1px solid #e2e8f0;">
                <button class="page-btn" onclick="goMemberPage(${memberCurrentPage - 1})" ${memberCurrentPage <= 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i>
                </button>
                ${pageButtons}
                <button class="page-btn" onclick="goMemberPage(${memberCurrentPage + 1})" ${memberCurrentPage >= totalPages ? 'disabled' : ''}>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        `;
    }

    container.innerHTML = `
        ${searchHtml}
        ${tableHtml}
        <div class="members-footer">
            <span>共 ${allMembersCache_page.length} 位成員${memberSearchTerm ? `，篩選顯示 ${totalCount} 位` : ''}（第 ${startIdx + 1}-${Math.min(startIdx + MEMBERS_PER_PAGE, totalCount)} 位）</span>
            <button class="btn-compact danger" onclick="removeSelectedMembers()" id="remove-selected-btn" disabled>
                <i class="fas fa-trash"></i> 移除所選
            </button>
        </div>
        ${paginationHtml}
    `;
}

function goMemberPage(page) {
    const filtered = getFilteredMembers();
    const totalPages = Math.max(1, Math.ceil(filtered.length / MEMBERS_PER_PAGE));
    if (page < 1 || page > totalPages) return;
    memberCurrentPage = page;
    renderMembersPage();
}

function onMemberSearch(term) {
    memberSearchTerm = term;
    memberCurrentPage = 1;
    renderMembersPage();
    // 保持搜尋框 focus
    setTimeout(() => {
        const input = document.getElementById('member-search-input');
        if (input) { input.focus(); input.selectionStart = input.selectionEnd = input.value.length; }
    }, 10);
}

// 新增群組相關
// 成員選擇輔助函數
function toggleSelectAllMembers(checkbox) {
    const checkboxes = document.querySelectorAll('.member-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateRemoveSelectedButton();
}

function updateRemoveSelectedButton() {
    const checkedCount = document.querySelectorAll('.member-checkbox:checked').length;
    const btn = document.getElementById('remove-selected-btn');
    if (btn) {
        btn.disabled = checkedCount === 0;
        btn.innerHTML = checkedCount > 0
            ? `<i class="fas fa-trash"></i> 移除所選 (${checkedCount})`
            : `<i class="fas fa-trash"></i> 移除所選`;
    }
}

// 監聽成員複選框變化
document.addEventListener('change', function (e) {
    if (e.target.classList.contains('member-checkbox')) {
        updateRemoveSelectedButton();
    }
});

// 移除單個成員
async function removeSingleMember(userId) {
    if (!confirm('確定要移除這位成員嗎？')) return;

    try {
        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/remove_member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cohort_id: currentCohortId,
                user_ids: [userId]
            }),
            credentials: 'same-origin'
        });
        const data = await res.json();

        if (data.success) {
            loadMembers(currentCohortId);
        } else {
            alert('移除失敗: ' + (data.error || '未知錯誤'));
        }
    } catch (e) {
        alert('操作失敗');
    }
}

// 從其他群組匯入成員
let importSourceCohortId = null;

function openImportFromGroupModal() {
    document.getElementById('import-from-group-modal').style.display = 'flex';
    initImportFilter();
}

function closeImportFromGroupModal() {
    document.getElementById('import-from-group-modal').style.display = 'none';
    importSourceCohortId = null;
}

async function loadCohortsForImport() {
    const select = document.getElementById('import-source-cohort');
    select.innerHTML = '<option value="">選擇來源群組...</option>';

    // 過濾掉當前群組
    const otherCohorts = allCohorts.filter(c => c.id !== currentCohortId);
    otherCohorts.forEach(c => {
        select.innerHTML += `<option value="${c.id}">${escapeHtml(c.name)} (${c.member_count || 0} 成員)</option>`;
    });
}

async function loadSourceGroupMembers() {
    const cohortId = document.getElementById('import-source-cohort').value;
    if (!cohortId) return;

    importSourceCohortId = cohortId;
    const container = document.getElementById('import-members-list');
    container.innerHTML = '<p style="text-align:center; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> 載入中...</p>';

    try {
        const res = await fetch(`${PortalConfig.webRoot}/api_get_members.php?cohort_id=${cohortId}`);
        const data = await res.json();

        if (data.success && data.data.length > 0) {
            container.innerHTML = data.data.map(m => `
                <div class="import-member-item">
                    <input type="checkbox" class="import-member-checkbox" value="${m.id}">
                    <div class="member-avatar" style="width:28px;height:28px;font-size:0.75rem;">
                        ${(m.fullname || m.username || '?').charAt(0).toUpperCase()}
                    </div>
                    <span>${escapeHtml(m.fullname || m.username)}</span>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p style="text-align:center; color:#94a3b8;">該群組沒有成員</p>';
        }
    } catch (e) {
        container.innerHTML = '<p style="text-align:center; color:#ef4444;">載入失敗</p>';
    }
}

function selectAllImportMembers() {
    const checkboxes = document.querySelectorAll('.import-member-checkbox');
    checkboxes.forEach(cb => cb.checked = true);
}

async function importSelectedMembers() {
    const selectedIds = Array.from(document.querySelectorAll('.import-member-checkbox:checked')).map(cb => parseInt(cb.value));

    if (selectedIds.length === 0) {
        alert('請選擇要匯入的成員');
        return;
    }

    try {
        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/add_member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cohort_id: currentCohortId,
                user_ids: selectedIds
            }),
            credentials: 'same-origin'
        });
        const data = await res.json();

        if (data.success) {
            closeImportFromGroupModal();
            loadMembers(currentCohortId);
            alert(`成功匯入 ${selectedIds.length} 位成員`);
        } else {
            alert('匯入失敗: ' + (data.error || '未知錯誤'));
        }
    } catch (e) {
        alert('操作失敗');
    }
}


// 加入成員相關（以下舊版重複函數已刪除，使用上方正確版本）
function openAddMemberModal() {
    document.getElementById('add-member-modal').style.display = 'flex';
    loadAvailableUsers();
}

function closeAddMemberModal() {
    document.getElementById('add-member-modal').style.display = 'none';
}

async function loadAvailableUsers() {
    const container = document.getElementById('member-list');
    container.innerHTML = '<p style="text-align:center; color:#94a3b8;">載入中...</p>';

    // 每次都重新載入，不使用快取
    try {
        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/search_users', {
            credentials: 'same-origin'
        });
        const data = await res.json();
        console.log('Users API response:', data);

        if (data.success) {
            const raw = data.data;
            allUsersCache = Array.isArray(raw) ? raw : (raw?.users || []);
            renderUserList(allUsersCache);
        } else {
            container.innerHTML = '<p style="color:#ef4444;">載入失敗: ' + (data.error || '未知錯誤') + '</p>';
        }
    } catch (e) {
        console.error('Load users error:', e);
        container.innerHTML = '<p style="color:#ef4444;">載入失敗: ' + e.message + '</p>';
    }
}

function renderUserList(users) {
    const container = document.getElementById('member-list');

    // Validate users is an array
    if (!Array.isArray(users)) {
        container.innerHTML = '<p style="color:#ef4444;">載入失敗: users.map is not a function</p>';
        return;
    }

    if (users.length === 0) {
        container.innerHTML = '<p style="text-align:center; color:#94a3b8;">沒有可用的成員</p>';
        return;
    }

    container.innerHTML = users.map(u => `
        <div class="member-item">
            <input type="checkbox" class="add-user-checkbox" value="${u.id}">
            <div class="member-avatar">${(u.fullname || u.username || '?').charAt(0).toUpperCase()}</div>
            <div class="member-info">
                <div class="member-name">${escapeHtml(u.fullname || u.username)}</div>
                <div class="member-email">${escapeHtml(u.email || '')}</div>
            </div>
        </div>
    `).join('');
}

function filterMemberList(term) {
    term = term.toLowerCase();
    const filtered = allUsersCache.filter(u =>
        (u.fullname || '').toLowerCase().includes(term) ||
        (u.username || '').toLowerCase().includes(term)
    );
    renderUserList(filtered);
}

async function addSelectedMembers() {
    const selected = Array.from(document.querySelectorAll('.add-user-checkbox:checked')).map(c => c.value);

    if (selected.length === 0) {
        showToast('請選擇成員', 'warning');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'add_members');
        formData.append('cohort_id', currentCohortId);
        formData.append('user_ids', JSON.stringify(selected));

        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/add_member', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            closeAddMemberModal();
            loadMembers(currentCohortId);
            loadCohorts(); // 更新成員數
            showToast(`已加入 ${selected.length} 位成員`);
        } else {
            showToast(data.error || '加入失敗', 'error');
        }
    } catch (e) {
        showToast('網路錯誤', 'error');
    }
}

async function removeSelectedMembers() {
    const selected = Array.from(document.querySelectorAll('.member-checkbox:checked')).map(c => c.value);

    if (selected.length === 0) {
        showToast('請選擇要移除的成員', 'warning');
        return;
    }

    if (!confirm(`確定要移除 ${selected.length} 位成員？`)) return;

    try {
        const formData = new FormData();
        formData.append('action', 'remove_members');
        formData.append('cohort_id', currentCohortId);
        formData.append('user_ids', JSON.stringify(selected));

        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/remove_member', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            loadMembers(currentCohortId);
            loadCohorts();
            showToast('已移除成員');
        } else {
            showToast(data.error || '移除失敗', 'error');
        }
    } catch (e) {
        showToast('網路錯誤', 'error');
    }
}

async function removeMember(userId, e) {
    e.stopPropagation();
    if (!confirm('確定要移除此成員？')) return;

    try {
        const formData = new FormData();
        formData.append('action', 'remove_members');
        formData.append('cohort_id', currentCohortId);
        formData.append('user_ids', JSON.stringify([userId]));

        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/remove_member', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            loadMembers(currentCohortId);
            loadCohorts();
        }
    } catch (e) {
        showToast('移除失敗', 'error');
    }
}

// 工具函數
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ====== 篩選器功能（匯入 Modal 用） ======

let importFilterGroupCounter = 0;

// 開啟匯入 Modal 時初始化篩選器
async function initImportFilter() {
    await loadFilterDimensions();
    importFilterGroupCounter = 0;
    const container = document.getElementById('import-filter-groups');
    if (container) {
        container.innerHTML = '';
        addImportFilterGroup();
    }
    const results = document.getElementById('import-filter-results');
    if (results) results.innerHTML = '<p style="text-align:center; color:#94a3b8; padding:40px 0;">請設定篩選條件後點擊搜尋</p>';
}

// 新增匯入條件組
function addImportFilterGroup() {
    importFilterGroupCounter++;
    const gid = importFilterGroupCounter;
    const container = document.getElementById('import-filter-groups');
    if (!container) return;

    if (container.children.length > 0) {
        const opDiv = document.createElement('div');
        opDiv.className = 'filter-operator';
        opDiv.id = `impFilterOp${gid}`;
        opDiv.dataset.operator = 'or';
        opDiv.innerHTML = `<button class="op-btn or" onclick="toggleImportFilterOp(${gid})" title="點擊切換 AND/OR">OR</button>`;
        container.appendChild(opDiv);
    }

    const groupHtml = `
        <div class="filter-group-box" id="impFilterGroupBox${gid}" data-gid="${gid}">
            <div class="cohort-filter-grid">
                <div class="cohort-filter-item">
                    <label><i class="fas fa-briefcase"></i> 職類</label>
                    <select id="impFCat${gid}">
                        <option value="">全部</option>
                        ${filterCategoryOptions.map(o => `<option value="${o.id}">${escapeHtml(o.name)}</option>`).join('')}
                    </select>
                </div>
                <div class="cohort-filter-item">
                    <label><i class="fas fa-map-marker-alt"></i> 所屬</label>
                    <select id="impFLoc${gid}">
                        <option value="">全部</option>
                        ${filterLocationOptions.map(o => `<option value="${o.id}">${escapeHtml(o.name)}</option>`).join('')}
                    </select>
                </div>
                <div class="cohort-filter-item">
                    <label><i class="fas fa-tags"></i> 屬性</label>
                    <select id="impFAttr${gid}">
                        <option value="">全部</option>
                        ${filterAttributeOptions.map(o => `<option value="${o.id}">${escapeHtml(o.name)}</option>`).join('')}
                    </select>
                </div>
            </div>
            ${gid > 1 ? `<button class="btn-compact danger" style="margin-top:4px;font-size:0.75rem;padding:4px 10px;" onclick="removeImportFilterGroup(${gid})"><i class="fas fa-times"></i> 移除此組</button>` : ''}
        </div>
    `;
    container.insertAdjacentHTML('beforeend', groupHtml);
}

function toggleImportFilterOp(gid) {
    const opDiv = document.getElementById(`impFilterOp${gid}`);
    if (!opDiv) return;
    const cur = opDiv.dataset.operator;
    const next = cur === 'or' ? 'and' : 'or';
    opDiv.dataset.operator = next;
    const btn = opDiv.querySelector('.op-btn');
    btn.textContent = next.toUpperCase();
    btn.className = `op-btn ${next}`;
}

function removeImportFilterGroup(gid) {
    const group = document.getElementById(`impFilterGroupBox${gid}`);
    const op = document.getElementById(`impFilterOp${gid}`);
    if (group) group.remove();
    if (op) op.remove();
}

function resetImportFilter() {
    importFilterGroupCounter = 0;
    const container = document.getElementById('import-filter-groups');
    if (container) container.innerHTML = '';
    addImportFilterGroup();
    const results = document.getElementById('import-filter-results');
    if (results) results.innerHTML = '<p style="text-align:center; color:#94a3b8; padding:40px 0;">請設定篩選條件後點擊搜尋</p>';
}

async function searchImportFilteredUsers() {
    const groups = document.querySelectorAll('#import-filter-groups .filter-group-box');
    const filterGroups = [];
    const operators = [];

    groups.forEach((group, index) => {
        const gid = group.dataset.gid;
        const catId = document.getElementById(`impFCat${gid}`)?.value || '';
        const locId = document.getElementById(`impFLoc${gid}`)?.value || '';
        const attrId = document.getElementById(`impFAttr${gid}`)?.value || '';

        const cohortIds = [catId, locId, attrId].filter(id => id);
        if (cohortIds.length > 0) {
            filterGroups.push(cohortIds);
            if (index > 0) {
                const opDiv = document.getElementById(`impFilterOp${gid}`);
                operators.push(opDiv?.dataset?.operator || 'or');
            }
        }
    });

    if (filterGroups.length === 0) {
        showToast('請至少選一個篩選條件', 'warning');
        return;
    }

    const resultsDiv = document.getElementById('import-filter-results');
    resultsDiv.innerHTML = '<div class="loading-state" style="padding:20px;"><i class="fas fa-spinner fa-spin"></i> 搜尋中...</div>';

    try {
        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/get_members_by_groups', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                filter_groups: filterGroups,
                operators: operators
            }),
            credentials: 'same-origin'
        });
        const data = await res.json();

        if (data.success) {
            const users = data.data?.users || data.data || [];
            renderImportFilterResults(Array.isArray(users) ? users : []);
        } else {
            resultsDiv.innerHTML = `<p style="color:#ef4444;text-align:center;padding:20px;">搜尋失敗: ${data.error || '未知錯誤'}</p>`;
        }
    } catch (e) {
        resultsDiv.innerHTML = '<p style="color:#ef4444;text-align:center;padding:20px;">網路錯誤</p>';
    }
}

function renderImportFilterResults(users) {
    const resultsDiv = document.getElementById('import-filter-results');
    if (users.length === 0) {
        resultsDiv.innerHTML = '<p style="text-align:center;color:#94a3b8;padding:20px;">沒有符合條件的使用者</p>';
        return;
    }

    resultsDiv.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <span style="font-size:0.9rem;color:#475569;">找到 <strong>${users.length}</strong> 人</span>
            <button class="btn-compact secondary" onclick="toggleImportSelectAll()" style="font-size:0.8rem;">
                <i class="fas fa-check-double"></i> 全選/取消
            </button>
        </div>
        <div style="max-height:280px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;">
            ${users.map(u => `
                <div class="result-item">
                    <input type="checkbox" class="import-result-cb" value="${u.id}">
                    <div class="member-avatar" style="width:32px;height:32px;font-size:0.8rem;">
                        ${(u.fullname || u.username || '?').charAt(0).toUpperCase()}
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:500;font-size:0.9rem;color:#1e293b;">${escapeHtml(u.fullname || u.username)}</div>
                        <div style="font-size:0.8rem;color:#94a3b8;">${escapeHtml(u.email || u.username || '')}</div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function toggleImportSelectAll() {
    const cbs = document.querySelectorAll('.import-result-cb');
    const allChecked = Array.from(cbs).every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allChecked);
}

async function importFilteredUsersToGroup() {
    const selected = Array.from(document.querySelectorAll('.import-result-cb:checked')).map(cb => cb.value);
    if (selected.length === 0) {
        showToast('請勾選要匯入的成員', 'warning');
        return;
    }

    try {
        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/add_member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cohort_id: currentCohortId,
                user_ids: selected
            }),
            credentials: 'same-origin'
        });
        const data = await res.json();

        if (data.success) {
            showToast(`已匯入 ${selected.length} 位成員`);
            closeImportFromGroupModal();
            loadMembers(currentCohortId);
            loadCohorts();
        } else {
            showToast(data.error || '匯入失敗', 'error');
        }
    } catch (e) {
        showToast('網路錯誤', 'error');
    }
}

// 載入篩選器維度數據
async function loadFilterDimensions() {
    if (filterDimensionsLoaded) return;
    try {
        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/get_grouped');
        const data = await res.json();
        if (data.success && data.data) {
            data.data.forEach(dim => {
                const opts = (dim.cohorts || []).map(c => ({
                    id: c.cohort_id || c.id,
                    name: c.full_path || c.display_name || c.name
                }));
                if (dim.name === '職類') filterCategoryOptions = opts;
                else if (dim.name === '所屬') filterLocationOptions = opts;
                else if (dim.name === '屬性') filterAttributeOptions = opts;
            });
        }
        // 載入標籤
        const tagRes = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=tags/course/available');
        const tagData = await tagRes.json();
        if (tagData.success) {
            filterTagOptions = tagData.data || [];
        }
        filterDimensionsLoaded = true;
    } catch (e) {
        console.error('載入篩選器維度失敗', e);
    }
}

// 初始化篩選器（每次進入成員視圖時呼叫）
async function initCohortFilter() {
    await loadFilterDimensions();
    filterGroupCounter = 0;
    const container = document.getElementById('cohort-filter-groups');
    if (container) {
        container.innerHTML = '';
        addCohortFilterGroup();
    }
}

// 新增一組篩選條件
function addCohortFilterGroup() {
    filterGroupCounter++;
    const gid = filterGroupCounter;
    const container = document.getElementById('cohort-filter-groups');
    if (!container) return;

    // 在非第一組前加 AND/OR 分隔
    if (container.children.length > 0) {
        const opDiv = document.createElement('div');
        opDiv.className = 'filter-operator';
        opDiv.id = `filterOp${gid}`;
        opDiv.dataset.operator = 'or';
        opDiv.innerHTML = `<button class="op-btn or" onclick="toggleCohortFilterOp(${gid})" title="點擊切換 AND/OR">OR</button>`;
        container.appendChild(opDiv);
    }

    const groupHtml = `
        <div class="filter-group-box" id="filterGroupBox${gid}" data-gid="${gid}">
            <div class="cohort-filter-grid">
                <div class="cohort-filter-item">
                    <label><i class="fas fa-briefcase"></i> 職類</label>
                    <select id="fCat${gid}">
                        <option value="">全部</option>
                        ${filterCategoryOptions.map(o => `<option value="${o.id}">${escapeHtml(o.name)}</option>`).join('')}
                    </select>
                </div>
                <div class="cohort-filter-item">
                    <label><i class="fas fa-map-marker-alt"></i> 所屬</label>
                    <select id="fLoc${gid}">
                        <option value="">全部</option>
                        ${filterLocationOptions.map(o => `<option value="${o.id}">${escapeHtml(o.name)}</option>`).join('')}
                    </select>
                </div>
                <div class="cohort-filter-item">
                    <label><i class="fas fa-tags"></i> 屬性</label>
                    <select id="fAttr${gid}">
                        <option value="">全部</option>
                        ${filterAttributeOptions.map(o => `<option value="${o.id}">${escapeHtml(o.name)}</option>`).join('')}
                    </select>
                </div>
            </div>
            ${gid > 1 ? `<button class="btn-compact danger" style="margin-top:4px;font-size:0.75rem;padding:4px 10px;" onclick="removeCohortFilterGroup(${gid})"><i class="fas fa-times"></i> 移除此組</button>` : ''}
        </div>
    `;
    container.insertAdjacentHTML('beforeend', groupHtml);
}

// 切換 AND/OR
function toggleCohortFilterOp(gid) {
    const opDiv = document.getElementById(`filterOp${gid}`);
    if (!opDiv) return;
    const cur = opDiv.dataset.operator;
    const next = cur === 'or' ? 'and' : 'or';
    opDiv.dataset.operator = next;
    const btn = opDiv.querySelector('.op-btn');
    btn.textContent = next.toUpperCase();
    btn.className = `op-btn ${next}`;
}

// 移除條件組
function removeCohortFilterGroup(gid) {
    const group = document.getElementById(`filterGroupBox${gid}`);
    const op = document.getElementById(`filterOp${gid}`);
    if (group) group.remove();
    if (op) op.remove();
}

// 重置篩選器
function resetCohortFilter() {
    filterGroupCounter = 0;
    const container = document.getElementById('cohort-filter-groups');
    if (container) container.innerHTML = '';
    addCohortFilterGroup();
    const results = document.getElementById('cohort-filter-results');
    if (results) {
        results.style.display = 'none';
        results.innerHTML = '';
    }
}

// 搜尋篩選結果
async function searchFilteredUsers() {
    const groups = document.querySelectorAll('.filter-group-box');
    const filterGroups = [];
    const operators = [];

    groups.forEach((group, index) => {
        const gid = group.dataset.gid;
        const catId = document.getElementById(`fCat${gid}`)?.value || '';
        const locId = document.getElementById(`fLoc${gid}`)?.value || '';
        const attrId = document.getElementById(`fAttr${gid}`)?.value || '';

        const cohortIds = [catId, locId, attrId].filter(id => id);
        if (cohortIds.length > 0) {
            filterGroups.push(cohortIds);
            if (index > 0) {
                const opDiv = document.getElementById(`filterOp${gid}`);
                operators.push(opDiv?.dataset?.operator || 'or');
            }
        }
    });

    if (filterGroups.length === 0) {
        showToast('請至少選一個篩選條件', 'warning');
        return;
    }

    const resultsDiv = document.getElementById('cohort-filter-results');
    resultsDiv.style.display = 'block';
    resultsDiv.innerHTML = '<div class="loading-state" style="padding:20px;"><i class="fas fa-spinner fa-spin"></i> 搜尋中...</div>';

    try {
        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/get_members_by_groups', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                filter_groups: filterGroups,
                operators: operators
            }),
            credentials: 'same-origin'
        });
        const data = await res.json();

        if (data.success) {
            const users = data.data || [];
            renderFilterResults(users);
        } else {
            resultsDiv.innerHTML = `<p style="color:#ef4444;">搜尋失敗: ${data.error || '未知錯誤'}</p>`;
        }
    } catch (e) {
        resultsDiv.innerHTML = `<p style="color:#ef4444;">網路錯誤</p>`;
    }
}

// 渲染篩選結果
function renderFilterResults(users) {
    const resultsDiv = document.getElementById('cohort-filter-results');
    if (users.length === 0) {
        resultsDiv.innerHTML = '<p style="text-align:center;color:#94a3b8;padding:20px;">沒有符合條件的使用者</p>';
        return;
    }

    resultsDiv.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <span style="font-size:0.9rem;color:#475569;">找到 <strong>${users.length}</strong> 人</span>
            <div style="display:flex;gap:8px;">
                <button class="btn-compact secondary" onclick="toggleFilterSelectAll()" style="font-size:0.8rem;">
                    <i class="fas fa-check-double"></i> 全選/取消
                </button>
                <button class="btn-compact primary" onclick="addFilteredUsersToGroup()" style="font-size:0.8rem;">
                    <i class="fas fa-user-plus"></i> 加入群組
                </button>
            </div>
        </div>
        <div style="max-height:300px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;">
            ${users.map(u => `
                <div class="result-item">
                    <input type="checkbox" class="filter-result-cb" value="${u.id}">
                    <div class="member-avatar" style="width:32px;height:32px;font-size:0.8rem;">
                        ${(u.fullname || u.username || '?').charAt(0).toUpperCase()}
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:500;font-size:0.9rem;color:#1e293b;">${escapeHtml(u.fullname || u.username)}</div>
                        <div style="font-size:0.8rem;color:#94a3b8;">${escapeHtml(u.email || u.username || '')}</div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

// 全選/取消篩選結果
function toggleFilterSelectAll() {
    const cbs = document.querySelectorAll('.filter-result-cb');
    const allChecked = Array.from(cbs).every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allChecked);
}

// 將篩選結果的使用者加入當前群組
async function addFilteredUsersToGroup() {
    const selected = Array.from(document.querySelectorAll('.filter-result-cb:checked')).map(cb => cb.value);
    if (selected.length === 0) {
        showToast('請勾選要加入的成員', 'warning');
        return;
    }

    try {
        const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/add_member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cohort_id: currentCohortId,
                user_ids: selected
            }),
            credentials: 'same-origin'
        });
        const data = await res.json();

        if (data.success) {
            showToast(`已加入 ${selected.length} 位成員`);
            loadMembers(currentCohortId);
            loadCohorts();
            // 清空篩選結果
            const resultsDiv = document.getElementById('cohort-filter-results');
            if (resultsDiv) { resultsDiv.style.display = 'none'; resultsDiv.innerHTML = ''; }
        } else {
            showToast(data.error || '加入失敗', 'error');
        }
    } catch (e) {
        showToast('網路錯誤', 'error');
    }
}