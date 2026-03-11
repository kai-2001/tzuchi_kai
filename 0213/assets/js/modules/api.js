/**
 * API 呼叫封裝模組
 * assets/js/modules/api.js
 * 
 * 統一處理所有 API 呼叫，包含錯誤處理和認證
 */

const API = {
    // 基礎設定
    baseUrl: PortalConfig.webRoot + '/api',
    v2Url: PortalConfig.webRoot + '/api/v2',

    /**
     * 發送 API 請求
     * @param {string} endpoint - API 端點
     * @param {object} options - fetch 選項
     * @returns {Promise<object>}
     */
    async request(endpoint, options = {}) {
        const defaultOptions = {
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
            },
        };

        const config = { ...defaultOptions, ...options };

        // 如果有 body 且是物件，轉成 JSON
        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }

        try {
            const response = await fetch(endpoint, config);
            const data = await response.json();

            if (!response.ok) {
                throw new APIError(data.error || '請求失敗', response.status, data);
            }

            return data;
        } catch (error) {
            if (error instanceof APIError) {
                throw error;
            }
            throw new APIError(error.message || '網路錯誤', 0);
        }
    },

    /**
     * GET 請求
     */
    async get(endpoint, params = {}) {
        const url = new URL(endpoint, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            if (value !== undefined && value !== null) {
                url.searchParams.append(key, value);
            }
        });
        return this.request(url.toString(), { method: 'GET' });
    },

    /**
     * POST 請求
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: data,
        });
    },

    /**
     * PUT 請求
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: data,
        });
    },

    /**
     * DELETE 請求
     */
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
};

/**
 * API 錯誤類別
 */
class APIError extends Error {
    constructor(message, status, data = null) {
        super(message);
        this.name = 'APIError';
        this.status = status;
        this.data = data;
    }

    isUnauthorized() {
        return this.status === 401;
    }

    isForbidden() {
        return this.status === 403;
    }

    isNotFound() {
        return this.status === 404;
    }

    isServerError() {
        return this.status >= 500;
    }
}

// ==================
// Hospital Admin API
// ==================

const HospitalAdminAPI = {
    // ==================
    // V2 API (新架構)
    // ==================
    v2: {
        baseUrl: PortalConfig.webRoot + '/api/v2/index.php',

        /**
         * 呼叫 v2 API
         */
        async call(route, params = {}) {
            const url = new URL(this.baseUrl, window.location.origin);
            url.searchParams.append('route', route);
            Object.entries(params).forEach(([key, value]) => {
                if (value !== undefined && value !== null) {
                    url.searchParams.append(key, value);
                }
            });
            return API.request(url.toString(), { method: 'GET' });
        },

        /**
         * 取得群組列表
         */
        async getCohorts() {
            return this.call('cohorts/list');
        },

        /**
         * 取得群組列表（含維度）
         */
        async getCohortsWithDimensions() {
            return this.call('cohorts/list_with_dimensions');
        },

        /**
         * 取得群組成員
         */
        async getCohortMembers(cohortId) {
            return this.call('cohorts/members', { cohort_id: cohortId });
        },

        /**
         * 健康檢查
         */
        async health() {
            return this.call('stats/health');
        }
    },

    // ==================
    // V1 API (舊版，相容用)
    // ==================

    /**
     * 取得統計資料
     */
    async getStats() {
        return API.get(PortalConfig.webRoot + '/api/v2/index.php?route=stats/dashboard');
    },

    // --- 群組 API ---

    /**
     * 取得群組列表（含維度）
     */
    async getCohorts() {
        return API.get(PortalConfig.webRoot + '/api/v2/index.php', { route: 'cohorts/list_with_dimensions' });
    },

    /**
     * 取得群組成員
     */
    async getCohortMembers(cohortId) {
        return API.get(PortalConfig.webRoot + '/api/v2/index.php', {
            route: 'cohorts/members',
            cohort_id: cohortId
        });
    },

    /**
     * 建立群組
     */
    async createCohort(data) {
        return API.post(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/create', {
            ...data
        });
    },

    /**
     * 更新群組
     */
    async updateCohort(cohortId, data) {
        return API.post(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/update_dimension', {
            cohort_id: cohortId,
            ...data
        });
    },

    /**
     * 刪除群組
     */
    async deleteCohort(cohortId) {
        return API.post(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/delete', {
            cohort_id: cohortId
        });
    },

    /**
     * 新增群組成員
     */
    async addCohortMember(cohortId, userId) {
        return API.post(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/add_member', {
            cohort_id: cohortId,
            user_id: userId
        });
    },

    /**
     * 移除群組成員
     */
    async removeCohortMember(cohortId, userId) {
        return API.post(PortalConfig.webRoot + '/api/v2/index.php?route=cohorts/remove_member', {
            cohort_id: cohortId,
            user_id: userId
        });
    },

    // --- 類別 API ---

    /**
     * 取得類別列表
     */
    async getCategories() {
        return API.get(PortalConfig.webRoot + '/api/v2/index.php', { route: 'categories/list_all' });
    },

    /**
     * 建立類別
     */
    async createCategory(data) {
        return API.post(PortalConfig.webRoot + '/api/v2/index.php?route=categories/create', data);
    },

    // --- 使用者 API ---

    /**
     * 取得使用者列表
     */
    async getUsers(params = {}) {
        return API.get(PortalConfig.webRoot + '/api/hospital_admin/manage_users.php', {
            action: 'list',
            ...params
        });
    },

    /**
     * 建立使用者
     */
    async createUser(data) {
        return API.post(PortalConfig.webRoot + '/api/hospital_admin/manage_users.php', {
            action: 'create',
            ...data
        });
    },

    // --- 維度 API ---

    /**
     * 取得維度類型
     */
    async getDimensionTypes() {
        return API.get(PortalConfig.webRoot + '/api/v2/index.php?route=dimensions/list_types');
    }
};

// 匯出給其他模組使用
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { API, APIError, HospitalAdminAPI };
}
