<?php
/**
 * Legacy Proxy: manage_users.php → MemberController
 * 
 * 此檔案已遷移至 V2 API，保留作為向後相容 proxy。
 * 新代碼請使用: /api/v2/index.php?route=members/[action]
 */

require_once __DIR__ . '/../../core/bootstrap.php';

$action = $_REQUEST['action'] ?? '';
// Handle JSON body
if (empty($action)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

// Action → V2 路由對照
$routeMap = [
    'list'          => 'members/list',
    'get_cohorts'   => 'members/cohorts',
    'batch_update'  => 'members/batch_update',
    'batch_delete'  => 'members/batch_delete',
];

if (isset($routeMap[$action])) {
    $_GET['route'] = $routeMap[$action];
    require __DIR__ . '/../v2/index.php';
} else {
    // 預設行為或錯誤處理
    ApiResponse::error('Unknown action: ' . $action);
}
