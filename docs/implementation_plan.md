# Implementation Plan - Frontend-Backend Separation

The goal is to refactor the current "Hybrid Monolith" into a clean **API-First Architecture**, separating the frontend (View) from the business logic (API/Service).

## User Review Required
> [!IMPORTANT]
> This refactoring involves moving logic from legacy `api/` scripts to the new `core/` MVC framework. This will change API endpoints used by the frontend. We will need to update `main.js` and other JS files to point to the new `api/v2/` endpoints.

## Proposed Changes

### Phase 1: Backend API Unification (The "Clean Core")

#### [NEW] `app/Services/MoodleSyncService.php`
*   Encapsulate Moodle synchronization logic from `includes/moodle_api.php`.
*   Methods: `syncUser()`, `syncCohorts()`, `getCourseProgress()`.

#### [NEW] `app/Controllers/Api/HospitalAdminController.php`
*   Migrate logic from `api/hospital_admin/manage_users.php` and `manage_course.php`.
*   Implement strict input validation and JSON responses.

#### [NEW] `app/Controllers/Api/CourseController.php`
*   Migrate logic from `api/hospital_admin/manage_course.php`.

#### [MODIFY] `api/v2/index.php`
*   Register new routes:
    *   `GET /hospital-admin/users`
    *   `POST /hospital-admin/users`
    *   `GET /hospital-admin/courses`

### Phase 2: Frontend Decoupling

#### [MODIFY] `templates/header.php` & `footer.php`
*   Inject a global `window.PortalConfig` object containing PHP session data (username, role, moodle_url).
*   Remove inline `<?php echo ... ?>` from JS blocks.

#### [NEW] `assets/js/modules/`
*   Create ES6 modules to replace `async_loader.php` logic.
    *   `course-card.js`: Pure JS function to render course cards.
    *   `user-list.js`: Pure JS function to render user lists.

#### [MODIFY] `assets/js/main.js`
*   Update AJAX calls to use the new `api/v2/` endpoints.

## Verification Plan

### Automated Tests
*   Create a test script `tests/test_api_v2.php` to verify the new API endpoints return correct JSON structure.

### Manual Verification
*   **Hospital Admin**: Verify User Management and Course Management tabs still load data correctly.
*   **Student**: Verify Course List and Enrollment still work.
