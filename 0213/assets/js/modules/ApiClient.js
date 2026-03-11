/**
 * ApiClient.js
 * Standardized API client for Portal
 */

class ApiClient {
    constructor() {
        this.config = window.PortalConfig || {};
        this.baseUrl = this.config.baseUrl || '';
    }

    /**
     * Generic fetch wrapper
     * @param {string} endpoint - API endpoint (e.g., '/hospital-admin/users')
     * @param {object} options - Fetch options
     */
    async request(endpoint, options = {}) {
        const url = this.resolveUrl(endpoint);

        const defaultHeaders = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };

        const config = {
            ...options,
            headers: {
                ...defaultHeaders,
                ...options.headers
            }
        };

        try {
            const response = await fetch(url, config);

            // Handle HTTP errors
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `HTTP Error ${response.status}`);
            }

            // Parse JSON
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('API Request Failed:', error);
            throw error;
        }
    }

    /**
     * GET request
     */
    get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    }

    /**
     * POST request
     */
    post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    /**
     * Resolve full URL based on version
     * @param {string} endpoint 
     */
    resolveUrl(endpoint) {
        if (endpoint.startsWith('http')) return endpoint;

        // If endpoint starts with /api/v2, use it directly (backward compat)
        if (endpoint.startsWith('/api/')) return '/0213' + endpoint;

        // Default to v2 if simple path provided (future proofing)
        // For now, let's just stick to absolute paths validation
        return endpoint;
    }
}

// Export singleton
export const api = new ApiClient();
