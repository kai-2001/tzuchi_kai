<?php
/**
 * 課程標籤 API（Legacy Proxy）
 * api/hospital_admin/course_tags.php
 * 
 * 已遷移至 V2 API：/api/v2/index.php?route=tags/course/{action}
 * 此檔案保留為向後相容代理
 */
require_once __DIR__ . '/../../core/bootstrap.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 支援 JSON body
if (empty($action)) {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
    }
}

$actionMap = [
    'list'      => 'list',
    'add'       => 'add',
    'remove'    => 'remove',
    'set'       => 'set',
    'available' => 'available',
    'create'    => 'create',
];

if (!isset($actionMap[$action])) {
    ApiResponse::error('未知操作: ' . $action);
}

$controller = new CourseTagController();
$method = $actionMap[$action];
$controller->$method();
