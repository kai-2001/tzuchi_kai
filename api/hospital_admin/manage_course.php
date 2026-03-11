<?php
/**
 * Legacy Proxy: manage_course.php → CourseController
 * 
 * 此檔案已遷移至 V2 API，保留作為向後相容 proxy。
 * 新代碼請使用: /api/v2/index.php?route=courses/[action]
 */

require_once __DIR__ . '/../../core/bootstrap.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Action → V2 路由對照
$routeMap = [
    'list'                  => 'courses/list',
    'get'                   => 'courses/get',
    'get_categories'        => 'courses/get_categories',
    'create'                => 'courses/create',
    'update'                => 'courses/update',
    'delete'                => 'courses/delete',
    'toggle_visible'        => 'courses/toggle_visible',
    'enrol_users'           => 'courses/enrol_users',
    'batch_enrol'           => 'courses/batch_enrol',
    'enable_self_enrol'     => 'courses/enable_self_enrol',
    'set_mandatory'         => 'courses/set_mandatory',
    'get_mandatory'         => 'courses/get_mandatory',
    'get_category_mandatory' => 'courses/get_category_mandatory',
];

if (isset($routeMap[$action])) {
    $_GET['route'] = $routeMap[$action];
    require __DIR__ . '/../v2/index.php';
} else {
    // 嘗試從 JSON body 讀取 action
    $input = json_decode(file_get_contents('php://input'), true);
    $jsonAction = $input['action'] ?? '';
    if (isset($routeMap[$jsonAction])) {
        $_GET['route'] = $routeMap[$jsonAction];
        require __DIR__ . '/../v2/index.php';
    } else {
        ApiResponse::error('Unknown action: ' . ($action ?: $jsonAction ?: 'none'));
    }
}