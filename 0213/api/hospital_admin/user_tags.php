<?php
/**
 * 使用者標籤 API
 * api/hospital_admin/user_tags.php
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
    'list' => 'list',
    'add' => 'add',
    'remove' => 'remove',
    'batch_set' => 'batchSet',
    'users_by_tag' => 'usersByTag',
];

if (!isset($actionMap[$action])) {
    ApiResponse::error('未知操作: ' . $action);
}

$controller = new UserTagController();
$method = $actionMap[$action];
$controller->$method();
