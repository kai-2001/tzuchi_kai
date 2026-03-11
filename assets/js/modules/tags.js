/**
 * 標籤管理模組
 * assets/js/modules/tags.js
 * 
 * 處理標籤 CRUD 與 UI 整合
 */

const Tags = {
    // 快取標籤資料
    cache: {
        templates: [],
        custom: [],
        loaded: false
    },

    // API 基礎路徑
    apiBase: PortalConfig.webRoot + '/api/v2/index.php?route=',

    /**
     * 載入可見標籤
     */
    async load(institution) {
        try {
            const response = await fetch(`${this.apiBase}tags&institution=${encodeURIComponent(institution)}`);
            const data = await response.json();

            if (data.success) {
                this.cache.templates = data.data.templates || [];
                this.cache.custom = data.data.custom || [];
                this.cache.loaded = true;
                return data.data;
            } else {
                console.error('載入標籤失敗:', data.message);
                return null;
            }
        } catch (error) {
            console.error('載入標籤錯誤:', error);
            return null;
        }
    },

    /**
     * 取得所有可見標籤（合併）
     */
    getAll() {
        return [...this.cache.templates, ...this.cache.custom];
    },

    /**
     * 新增標籤
     */
    async create(name, institution, color = '#6b7280', description = '') {
        try {
            const response = await fetch(`${this.apiBase}tags/create`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, institution, color, description })
            });
            const data = await response.json();

            if (data.success) {
                // 更新快取
                this.cache.custom.push(data.data.tag);
                return data.data.tag;
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('新增標籤失敗:', error);
            throw error;
        }
    },

    /**
     * 更新標籤
     */
    async update(id, institution, updates) {
        try {
            const response = await fetch(`${this.apiBase}tags/update`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, institution, ...updates })
            });
            const data = await response.json();

            if (data.success) {
                // 更新快取
                const index = this.cache.custom.findIndex(t => t.id == id);
                if (index >= 0) {
                    this.cache.custom[index] = data.data.tag;
                }
                return data.data.tag;
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('更新標籤失敗:', error);
            throw error;
        }
    },

    /**
     * 刪除標籤
     */
    async delete(id, institution) {
        try {
            const response = await fetch(`${this.apiBase}tags/delete&id=${id}&institution=${encodeURIComponent(institution)}`, {
                method: 'POST'
            });
            const data = await response.json();

            if (data.success) {
                // 從快取移除
                this.cache.custom = this.cache.custom.filter(t => t.id != id);
                return true;
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('刪除標籤失敗:', error);
            throw error;
        }
    },

    /**
     * 開啟標籤管理 Modal
     */
    openManager(institution, onUpdate = null) {
        const modalId = 'tag-manager-modal';

        // 建立 Modal HTML
        const modalHtml = `
            <div id="${modalId}" class="modal-overlay" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">
                <div style="background: white; border-radius: 16px; width: 90%; max-width: 600px; max-height: 80vh; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                    <!-- Header -->
                    <div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #8b5cf6, #6366f1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-tags" style="color: white; font-size: 18px;"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin: 0;">標籤管理</h3>
                                <p style="font-size: 0.8rem; color: #64748b; margin: 0;">管理系統模板與院區專屬標籤</p>
                            </div>
                        </div>
                        <button onclick="Tags.closeManager()" style="width: 32px; height: 32px; border: none; background: #f1f5f9; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-times" style="color: #64748b;"></i>
                        </button>
                    </div>
                    
                    <!-- Content -->
                    <div style="padding: 24px; overflow-y: auto; max-height: 50vh;">
                        <!-- 系統模板 -->
                        <div style="margin-bottom: 24px;">
                            <h4 style="font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-bookmark"></i> 系統模板（所有院區可用）
                            </h4>
                            <div id="tag-manager-templates" style="display: flex; flex-wrap: wrap; gap: 8px;">
                                <!-- 動態填充 -->
                            </div>
                        </div>
                        
                        <!-- 院區專屬 -->
                        <div>
                            <h4 style="font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-hospital"></i> 本院區專屬
                            </h4>
                            <div id="tag-manager-custom" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px;">
                                <!-- 動態填充 -->
                            </div>
                            
                            <!-- 新增表單 -->
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="color" id="tag-new-color" value="#6b7280" style="width: 40px; height: 36px; border: none; border-radius: 6px; cursor: pointer;">
                                <input type="text" id="tag-new-name" placeholder="輸入新標籤名稱..." style="flex: 1; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem;">
                                <button onclick="Tags.handleCreate('${institution}')" style="padding: 10px 20px; background: #8b5cf6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                    <i class="fas fa-plus"></i> 新增
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // 移除現有 Modal
        const existing = document.getElementById(modalId);
        if (existing) existing.remove();

        // 插入 Modal
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // 載入並渲染標籤
        this.load(institution).then(() => {
            this.renderManagerContent();
        });

        // 儲存 callback
        this._onUpdate = onUpdate;
        this._currentInstitution = institution;
    },

    /**
     * 關閉 Modal
     */
    closeManager() {
        const modal = document.getElementById('tag-manager-modal');
        if (modal) modal.remove();

        // 觸發 callback
        if (this._onUpdate) {
            this._onUpdate(this.getAll());
        }
    },

    /**
     * 渲染 Modal 內容
     */
    renderManagerContent() {
        const templatesContainer = document.getElementById('tag-manager-templates');
        const customContainer = document.getElementById('tag-manager-custom');

        if (!templatesContainer || !customContainer) return;

        // 渲染系統模板（不可刪除，加鎖頭標示）
        templatesContainer.innerHTML = this.cache.templates.map(tag => `
            <span title="系統模板標籤（由系統管理員設定，無法刪除）" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: ${tag.color}15; border: 2px dashed ${tag.color}50; border-radius: 20px; font-size: 0.85rem; color: ${tag.color};">
                <i class="fas fa-lock" style="font-size: 0.7rem; opacity: 0.7;"></i>
                <span style="width: 8px; height: 8px; background: ${tag.color}; border-radius: 50%;"></span>
                ${this.escapeHtml(tag.name)}
            </span>
        `).join('') || '<span style="color: #94a3b8; font-size: 0.85rem;">尚無系統模板</span>';

        // 渲染院區專屬（可刪除）
        customContainer.innerHTML = this.cache.custom.map(tag => `
            <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: ${tag.color}20; border: 1px solid ${tag.color}40; border-radius: 20px; font-size: 0.85rem; color: ${tag.color};">
                <span style="width: 8px; height: 8px; background: ${tag.color}; border-radius: 50%;"></span>
                ${this.escapeHtml(tag.name)}
                <button onclick="Tags.handleDelete(${tag.id})" style="background: none; border: none; color: ${tag.color}; cursor: pointer; padding: 0; margin-left: 4px; opacity: 0.7;" title="刪除">
                    <i class="fas fa-times"></i>
                </button>
            </span>
        `).join('') || '<span style="color: #94a3b8; font-size: 0.85rem;">尚無院區專屬標籤</span>';
    },

    /**
     * 處理新增
     */
    async handleCreate(institution) {
        const nameInput = document.getElementById('tag-new-name');
        const colorInput = document.getElementById('tag-new-color');

        const name = nameInput.value.trim();
        const color = colorInput.value;

        if (!name) {
            alert('請輸入標籤名稱');
            return;
        }

        try {
            await this.create(name, institution, color);
            nameInput.value = '';
            this.renderManagerContent();
        } catch (error) {
            alert(error.message || '新增失敗');
        }
    },

    /**
     * 處理刪除
     */
    async handleDelete(id) {
        if (!confirm('確定要刪除此標籤嗎？')) return;

        try {
            await this.delete(id, this._currentInstitution);
            this.renderManagerContent();
        } catch (error) {
            alert(error.message || '刪除失敗');
        }
    },

    /**
     * 渲染標籤選擇器（供表單使用）
     */
    renderSelector(containerId, selectedIds = [], institution = null) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const renderTags = () => {
            const allTags = this.getAll();
            container.innerHTML = allTags.map(tag => {
                const isSelected = selectedIds.includes(tag.id);
                return `
                    <label style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: ${isSelected ? tag.color + '30' : '#f8fafc'}; border: 1px solid ${isSelected ? tag.color : '#e2e8f0'}; border-radius: 20px; cursor: pointer; transition: all 0.2s;">
                        <input type="checkbox" name="tags[]" value="${tag.id}" ${isSelected ? 'checked' : ''} style="display: none;" onchange="this.parentElement.style.background = this.checked ? '${tag.color}30' : '#f8fafc'; this.parentElement.style.borderColor = this.checked ? '${tag.color}' : '#e2e8f0';">
                        <span style="width: 8px; height: 8px; background: ${tag.color}; border-radius: 50%;"></span>
                        <span style="font-size: 0.85rem; color: #374151;">${this.escapeHtml(tag.name)}</span>
                    </label>
                `;
            }).join('');
        };

        if (this.cache.loaded) {
            renderTags();
        } else if (institution) {
            this.load(institution).then(renderTags);
        }
    },

    /**
     * 輔助：HTML 跳脫
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// 匯出
if (typeof window !== 'undefined') {
    window.Tags = Tags;
}
