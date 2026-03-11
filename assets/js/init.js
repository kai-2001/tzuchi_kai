/**
 * init.js
 * Entry point for Portal Frontend Modules
 */

import { courseManager } from './modules/CourseManager.js';
import { api } from './modules/ApiClient.js';

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Course Manager (Exposes globals for backward compat)
    courseManager.init();

    // Check if we are on a page that needs dashboard data
    // The original async_loader.php was checking for specific containers or just running
    // It was included in footer, so it ran everywhere?
    // Actually async_loader check isAdmin inside. courseManager.loadAllData() also checks roles.

    // We can just call it. It will check roles and containers internally.
    courseManager.loadAllData();

    // Expose API for debugging
    window.portalApi = api;

    console.log('Portal Frontend Modules Initialized');
});
