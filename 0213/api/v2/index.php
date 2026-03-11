<?php
/**
 * 新版 API 入口點
 * api/v2/index.php
 * 
 * 使用 Query String 路由（不依賴 mod_rewrite）
 * 用法：/api/v2/index.php?route=stats/health
 */

// 確保不輸出任何 PHP 警告到 JSON 回應
ob_start();
error_reporting(0);

// 載入啟動檔案
require_once __DIR__ . '/../../core/bootstrap.php';

// 清掉 bootstrap 過程中的任何意外輸出
ob_end_clean();
ob_start();

// 取得路由
$route = $_GET['route'] ?? '';

// 簡易路由對照表
$routes = [
    // 統計 API
    'stats' => ['StatsController', 'index'],
    'stats/health' => ['StatsController', 'health'],
    'stats/dashboard' => ['StatsController', 'dashboard'],

    // 課程標籤 API
    'tags/course/list' => ['CourseTagController', 'list'],
    'tags/course/add' => ['CourseTagController', 'add'],
    'tags/course/remove' => ['CourseTagController', 'remove'],
    'tags/course/set' => ['CourseTagController', 'set'],
    'tags/course/available' => ['CourseTagController', 'available'],
    'tags/course/create' => ['CourseTagController', 'create'],

    // 群組 API
    'cohorts' => ['CohortController', 'list'],
    'cohorts/list' => ['CohortController', 'list'],
    'cohorts/list_with_dimensions' => ['CohortController', 'listWithDimensions'],
    'cohorts/members' => ['CohortController', 'getMembers'],
    'cohorts/add_member' => ['CohortController', 'addMember'],
    'cohorts/remove_member' => ['CohortController', 'removeMember'],
    'cohorts/search_users' => ['CohortController', 'searchUsers'],
    'cohorts/create' => ['CohortController', 'create'],
    'cohorts/delete' => ['CohortController', 'delete'],
    'cohorts/update_dimension' => ['CohortController', 'updateDimension'],
    'cohorts/get_members_by_groups' => ['CohortController', 'getMembersByGroups'],
    'cohorts/get_common_members' => ['CohortController', 'getCommonMembers'],

    // 類別 API
    'categories' => ['CategoryController', 'list'],
    'categories/list' => ['CategoryController', 'list'],
    'categories/tree' => ['CategoryController', 'tree'],
    'categories/show' => ['CategoryController', 'show'],
    'categories/create' => ['CategoryController', 'create'],
    'categories/update' => ['CategoryController', 'update'],
    'categories/delete' => ['CategoryController', 'delete'],

    // 使用者 API
    'users' => ['UserController', 'list'],
    'users/list' => ['UserController', 'list'],
    'users/show' => ['UserController', 'show'],
    'users/search_moodle' => ['UserController', 'searchMoodle'],
    'users/courses' => ['UserController', 'courses'],
    'users/cohorts' => ['UserController', 'cohorts'],

    // 標籤 API
    'tags' => ['TagController', 'list'],
    'tags/list' => ['TagController', 'list'],
    'tags/templates' => ['TagController', 'templates'],
    'tags/create' => ['TagController', 'create'],
    'tags/update' => ['TagController', 'update'],
    'tags/delete' => ['TagController', 'delete'],
    'tags/find_or_create' => ['TagController', 'findOrCreate'],
    'tags/create_template' => ['TagController', 'createTemplate'],
    'tags/delete_template' => ['TagController', 'deleteTemplate'],

    // 院區管理員 API (New V2)
    'hospital/users' => ['HospitalAdminController', 'index'],
    'hospital/users/create' => ['HospitalAdminController', 'create'],
    'hospital/users/update' => ['HospitalAdminController', 'update'],
    'hospital/users/update_role' => ['HospitalAdminController', 'updateRole'],
    'hospital/users/reset_password' => ['HospitalAdminController', 'resetPassword'],
    'hospital/cohorts' => ['HospitalAdminController', 'getCohorts'],

    // 維度管理 API
    'dimensions/list_types' => ['DimensionController', 'listTypes'],
    'dimensions/create_type' => ['DimensionController', 'createType'],
    'dimensions/delete_type' => ['DimensionController', 'deleteType'],
    'dimensions/list_cohorts' => ['DimensionController', 'listCohorts'],
    'dimensions/add_cohort' => ['DimensionController', 'addCohort'],
    'dimensions/remove_cohort' => ['DimensionController', 'removeCohort'],
    'dimensions/get_grouped' => ['DimensionController', 'getGrouped'],

    // 類別管理 API (Phase 2C)
    'categories/list_children' => ['CategoryController', 'listChildren'],
    'categories/list_all' => ['CategoryController', 'listAll'],
    'categories/get_path' => ['CategoryController', 'getPath'],
    'categories/list_tree' => ['CategoryController', 'listTree'],
    'categories/create' => ['CategoryController', 'create'],
    'categories/update' => ['CategoryController', 'update'],
    'categories/delete' => ['CategoryController', 'delete'],
    'categories/get_settings' => ['CategoryController', 'getSettings'],
    'categories/update_settings' => ['CategoryController', 'updateSettings'],
    'categories/search_users_by_filter' => ['CategoryController', 'searchUsersByFilter'],
    'categories/save_mandatory_requirements' => ['CategoryController', 'saveMandatoryRequirements'],
    'categories/get_mandatory_categories' => ['CategoryController', 'getMandatoryCategories'],
    'categories/batch_create' => ['CategoryController', 'batchCreate'],

    // 課程管理 API (Phase 2D)
    'courses/list' => ['CourseController', 'list'],
    'courses/get' => ['CourseController', 'get'],
    'courses/get_categories' => ['CourseController', 'getCategories'],
    'courses/create' => ['CourseController', 'create'],
    'courses/update' => ['CourseController', 'update'],
    'courses/delete' => ['CourseController', 'delete'],
    'courses/toggle_visible' => ['CourseController', 'toggleVisible'],
    'courses/enrol_users' => ['CourseController', 'enrolUsers'],
    'courses/batch_enrol' => ['CourseController', 'batchEnrol'],
    'courses/enable_self_enrol' => ['CourseController', 'enableSelfEnrol'],
    'courses/set_mandatory' => ['CourseController', 'setMandatory'],
    'courses/get_mandatory' => ['CourseController', 'getMandatory'],
    'courses/get_category_mandatory' => ['CourseController', 'getCategoryMandatory'],
    'courses/visibility/add' => ['CourseController', 'visibilityAdd'],
    'courses/visibility/remove' => ['CourseController', 'visibilityRemove'],
    'courses/visibility/list_by_course' => ['CourseController', 'visibilityListByCourse'],
    'courses/visibility/list_by_user' => ['CourseController', 'visibilityListByUser'],
    'courses/enrolled_users' => ['CourseController', 'getEnrolledUsers'],
    'courses/unenrol_users' => ['CourseController', 'unenrolUsers'],
    'courses/get_mandatory_info' => ['CourseController', 'getMandatoryInfo'],

    // 成員管理 API (Phase 2E)
    'members/list' => ['MemberController', 'list'],
    'members/cohorts' => ['MemberController', 'getCohorts'],
    'members/create' => ['MemberController', 'create'],
    'members/update' => ['MemberController', 'update'],
    'members/delete' => ['MemberController', 'delete'],
    'members/change_role' => ['MemberController', 'changeRole'],
    'members/batch_update' => ['MemberController', 'batchUpdate'],
    'members/batch_delete' => ['MemberController', 'batchDelete'],

    // 教師課程管理 API
    'teacher/courses/list_categories' => ['TeacherCourseController', 'listCategories'],

    // 課程選修規則 API
    'courses/visibility/save_rules' => ['CourseController', 'saveVisibilityRules'],
    'courses/visibility/get_rules' => ['CourseController', 'getVisibilityRules'],
];


// 尋找匹配的路由
if (isset($routes[$route])) {
    [$controllerClass, $method] = $routes[$route];
    $controller = new $controllerClass();
    $controller->$method();
} else {
    // 預設顯示可用路由
    ApiResponse::success([
        'message' => 'API v2 入口',
        'available_routes' => array_keys($routes),
        'usage' => '/api/v2/index.php?route=stats/health'
    ]);
}
