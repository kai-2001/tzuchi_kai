/**
 * Speech Portal - Frontend Form Validator
 * 
 * Provides real-time form validation matching backend rules.
 * Used in conjunction with PHP Validator class.
 */

const FormValidator = {
    rules: {},
    form: null,

    /**
     * Initialize form validation
     * @param {string} formId - Form element ID
     * @param {object} rules - Validation rules from backend
     */
    init(formId, rules) {
        this.form = document.getElementById(formId);
        if (!this.form) {
            console.warn(`FormValidator: Form #${formId} not found`);
            return;
        }
        this.rules = rules;

        // Bind submit event
        this.form.addEventListener('submit', (e) => {
            if (!this.validateAll()) {
                e.preventDefault();
            }
        });

        // Bind blur event for real-time validation
        Object.keys(rules).forEach(field => {
            const input = this.form.querySelector(`[name="${field}"]`);
            if (input) {
                input.addEventListener('blur', () => {
                    this.validateField(field);
                });
                // Clear error on focus
                input.addEventListener('focus', () => {
                    this.clearError(input);
                });
            }
        });
    },

    /**
     * Validate all fields
     * @returns {boolean}
     */
    validateAll() {
        let isValid = true;
        let firstErrorField = null;

        Object.keys(this.rules).forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
                if (!firstErrorField) {
                    firstErrorField = this.form.querySelector(`[name="${field}"]`);
                }
            }
        });

        // Focus on first error field
        if (firstErrorField) {
            firstErrorField.focus();
        }

        return isValid;
    },

    /**
     * Validate single field
     * @param {string} fieldName
     * @returns {boolean}
     */
    validateField(fieldName) {
        const rule = this.rules[fieldName];
        if (!rule) return true;

        const input = this.form.querySelector(`[name="${fieldName}"]`);
        if (!input) return true;

        const value = input.value.trim();
        const label = rule.label || fieldName;

        // Required check
        if (rule.required && !value) {
            this.showError(input, `${label} 為必填項目`);
            return false;
        }

        // Skip further checks if empty and not required
        if (!value) {
            this.clearError(input);
            return true;
        }

        // Min length check
        if (rule.min && value.length < rule.min) {
            this.showError(input, `${label} 至少需要 ${rule.min} 個字元`);
            return false;
        }

        // Max length check
        if (rule.max && value.length > rule.max) {
            this.showError(input, `${label} 不可超過 ${rule.max} 個字元`);
            return false;
        }

        // Type-specific validation
        if (rule.type) {
            const typeError = this.validateType(value, rule.type, label);
            if (typeError) {
                this.showError(input, typeError);
                return false;
            }
        }

        this.clearError(input);
        return true;
    },

    /**
     * Type-specific validation
     */
    validateType(value, type, label) {
        switch (type) {
            case 'date':
                if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                    return `${label} 日期格式錯誤`;
                }
                break;
            case 'email':
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    return `${label} Email 格式錯誤`;
                }
                break;
            case 'select':
                if (value === '0' || value === '') {
                    return `${label} 請選擇有效選項`;
                }
                break;
        }
        return null;
    },

    /**
     * Show error on input
     */
    showError(input, message) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');

        // Find or create feedback element
        let feedback = input.parentElement.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            input.parentElement.appendChild(feedback);
        }
        feedback.textContent = message;
        feedback.style.display = 'block';
    },

    /**
     * Clear error from input
     */
    clearError(input) {
        input.classList.remove('is-invalid');
        const feedback = input.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.style.display = 'none';
        }
    }
};

/**
 * Unified AJAX Response Handler
 * Matches PHP ApiResponse class
 */
function handleApiResponse(response) {
    if (!response) {
        showToast('網路連線失敗', 'error');
        return false;
    }

    switch (response.status) {
        case 'ok':
            if (response.msg) {
                showToast(response.msg, 'success');
            }
            if (response.redirect) {
                window.location.href = response.redirect;
            }
            return true;

        case 'login_required':
            // Try to open login modal if exists
            const modal = document.getElementById('loginModal');
            if (modal) {
                modal.style.display = 'flex';
            } else if (response.redirect) {
                window.location.href = response.redirect;
            }
            return false;

        case 'validation_error':
            if (response.errors) {
                // Show first error
                const firstError = Object.values(response.errors)[0];
                showToast(firstError || '驗證失敗', 'error');
            }
            return false;

        case 'error':
        default:
            showToast(response.msg || '發生錯誤', 'error');
            return false;
    }
}

/**
 * Simple toast notification
 */
function showToast(message, type = 'info') {
    // Check if toast container exists
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        background: ${type === 'error' ? '#dc3545' : type === 'success' ? '#28a745' : '#17a2b8'};
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        margin-bottom: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease;
        font-weight: 500;
    `;
    toast.textContent = message;
    container.appendChild(toast);

    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Unified API POST request
 */
async function apiPost(url, data) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        return { status: 'error', msg: '網路連線失敗，請稍後再試' };
    }
}
