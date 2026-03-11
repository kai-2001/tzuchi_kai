<?php
/**
 * Legacy Proxy: course_visibility.php → CourseController
 * 
 * 此檔案已遷移至 V2 API，保留作為向後相容 proxy。
 * 新代碼請使用: /api/v2/index.php?route=courses/visibility/[action]
 */

require_once __DIR__ . '/../../core/bootstrap.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$routeMap = [
    'add'            => 'courses/visibility/add',
    'remove'         => 'courses/visibility/remove',
    'list_by_course' => 'courses/visibility/list_by_course',
    'list_by_user'   => 'courses/visibility/list_by_user',
];

if (isset($routeMap[$action])) {
    $_GET['route'] = $routeMap[$action];
    require __DIR__ . '/../v2/index.php';
} else {
    ApiResponse::error('Unknown action: ' . ($action ?: 'none'));
}
