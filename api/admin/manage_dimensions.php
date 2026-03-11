<?php
/**
 * 維度管理 API（Legacy Proxy - Admin 版）
 * api/admin/manage_dimensions.php
 * 
 * 已遷移至 V2 API：/api/v2/index.php?route=dimensions/{action}
 * 此檔案保留為向後相容代理
 */
require_once __DIR__ . '/../../core/bootstrap.php';

$action = $_POST['action'] ?? $_GET['action'] ?? 'list_types';

$actionMap = [
    'list_types'     => 'listTypes',
    'create_type'    => 'createType',
    'delete_type'    => 'deleteType',
    'list_cohorts'   => 'listCohorts',
    'add_cohort'     => 'addCohort',
    'remove_cohort'  => 'removeCohort',
    'get_grouped'    => 'getGrouped',
];

if (!isset($actionMap[$action])) {
    ApiResponse::error('未知操作: ' . $action);
}

$controller = new DimensionController();
$method = $actionMap[$action];
$controller->$method();
