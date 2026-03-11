/**
 * UI 工具模組
 * assets/js/modules/ui.js
 * 
 * 通用 UI 元件：Toast 通知、Modal、Loading 等
 */

const UI = {
    // ==================
    // Toast 通知
    // ==================

    /**
     * 顯示 Toast 通知
     * @param {string} message - 訊息內容
     * @param {string} type - 類型: success, error, warning, info
     * @param {number} duration - 顯示時間（毫秒）
     */
    toast(message, type = 'success', duration = 3000) {
        // 移除已存在的 toast
        const existing = document.querySelector('.ui-toast');
        if (existing) {
            existing.remove();
        }

        // 建立 toast 元素
        const toast = document.createElement('div');
        toast.className = `ui-toast ui-toast-${type}`;
        toast.innerHTML = `
            <div class="ui-toast-content">
                <i class="${this.getToastIcon(type)}"></i>
                <span>${this.escapeHtml(message)}</span>
            </div>
        `;

        // 加入 DOM
        document.body.appendChild(toast);

        // 觸發動畫
        requestAnimationFrame(() => {
            toast.classList.add('ui-toast-show');
        });

        // 自動移除
        setTimeout(() => {
            toast.classList.remove('ui-toast-show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    success(message) {
        this.toast(message, 'success');
    },

    error(message) {
        this.toast(message, 'error');
    },

    warning(message) {
        this.toast(message, 'warning');
    },

    info(message) {
        this.toast(message, 'info');
    },

    getToastIcon(type) {
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-times-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        return icons[type] || icons.info;
    },

    // ==================
    // Loading 狀態
    // ==================

    /**
     * 顯示 Loading
     */
    showLoading(container, message = '載入中...') {
        const el = typeof container === 'string'
            ? document.querySelector(container)
            : container;

        if (!el) return;

        el.innerHTML = `
            <div class="ui-loading">
                <div class="ui-loading-spinner"></div>
                <p>${this.escapeHtml(message)}</p>
            </div>
        `;
    },

    /**
     * 顯示空狀態
     */
    showEmpty(container, message = '沒有資料', icon = 'fas fa-inbox') {
        const el = typeof container === 'string'
            ? document.querySelector(container)
            : container;

        if (!el) return;

        el.innerHTML = `
            <div class="ui-empty">
                <i class="${icon}"></i>
                <p>${this.escapeHtml(message)}</p>
            </div>
        `;
    },

    /**
     * 顯示錯誤狀態
     */
    showError(container, message = '發生錯誤', retryCallback = null) {
        const el = typeof container === 'string'
            ? document.querySelector(container)
            : container;

        if (!el) return;

        let html = `
            <div class="ui-error">
                <i class="fas fa-exclamation-triangle"></i>
                <p>${this.escapeHtml(message)}</p>
        `;

        if (retryCallback) {
            html += `<button class="btn btn-sm btn-outline-primary ui-retry-btn">重試</button>`;
        }

        html += `</div>`;
        el.innerHTML = html;

        if (retryCallback) {
            el.querySelector('.ui-retry-btn').addEventListener('click', retryCallback);
        }
    },

    // ==================
    // Modal 對話框
    // ==================

    /**
     * 確認對話框
     */
    confirm(message, title = '確認') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'ui-modal-backdrop';
            modal.innerHTML = `
                <div class="ui-modal">
                    <div class="ui-modal-header">
                        <h5>${this.escapeHtml(title)}</h5>
                    </div>
                    <div class="ui-modal-body">
                        <p>${this.escapeHtml(message)}</p>
                    </div>
                    <div class="ui-modal-footer">
                        <button class="btn btn-secondary ui-modal-cancel">取消</button>
                        <button class="btn btn-primary ui-modal-confirm">確認</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            requestAnimationFrame(() => modal.classList.add('show'));

            const close = (result) => {
                modal.classList.remove('show');
                setTimeout(() => modal.remove(), 300);
                resolve(result);
            };

            modal.querySelector('.ui-modal-cancel').addEventListener('click', () => close(false));
            modal.querySelector('.ui-modal-confirm').addEventListener('click', () => close(true));
            modal.addEventListener('click', (e) => {
                if (e.target === modal) close(false);
            });
        });
    },

    // ==================
    // 工具方法
    // ==================

    /**
     * HTML 跳脫
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * 格式化日期
     */
    formatDate(date, format = 'YYYY-MM-DD') {
        const d = new Date(date);
        const map = {
            'YYYY': d.getFullYear(),
            'MM': String(d.getMonth() + 1).padStart(2, '0'),
            'DD': String(d.getDate()).padStart(2, '0'),
            'HH': String(d.getHours()).padStart(2, '0'),
            'mm': String(d.getMinutes()).padStart(2, '0'),
            'ss': String(d.getSeconds()).padStart(2, '0')
        };

        let result = format;
        Object.entries(map).forEach(([key, value]) => {
            result = result.replace(key, value);
        });
        return result;
    }
};

// 全域 showToast 函式（向後相容）
window.showToast = (message, type = 'success') => UI.toast(message, type);

// 匯出
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { UI };
}
