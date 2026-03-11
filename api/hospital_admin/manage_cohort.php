<?php
/**
 * 群組管理 API（向後相容代理）
 * api/hospital_admin/manage_cohort.php
 * 
 * ⚠️ 已棄用 - 此檔案現在只是 v2 API 的代理
 * 前端應逐步遷移至 /api/v2/index.php?route=cohorts/xxx
 * 
 * 舊 API 映射表：
 * action=list           → cohorts/list
 * action=list_with_dimensions → cohorts/list_with_dimensions
 * action=members        → cohorts/members
 * action=get_users      → cohorts/search_users
 * action=add_member     → cohorts/add_member
 * action=add_members    → cohorts/add_member
 * action=remove_member  → cohorts/remove_member
 * action=remove_members → cohorts/remove_member
 * action=create         → cohorts/create
 * action=delete         → cohorts/delete
 * action=update_dimension → cohorts/update_dimension
 */

require_once __DIR__ . '/../../core/bootstrap.php';

// 取得 action（支援 GET、POST form data、JSON body）
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 如果 action 為空，嘗試從 JSON body 讀取
if (empty($action)) {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
    }
}

// action 映射表
$actionMap = [
    'list' => 'list',
    'list_with_dimensions' => 'listWithDimensions',
    'members' => 'getMembers',
    'get_members' => 'getMembers',
    'get_users' => 'searchUsers',
    'add_member' => 'addMember',
    'add_members' => 'addMember',
    'remove_member' => 'removeMember',
    'remove_members' => 'removeMember',
    'create' => 'create',
    'delete' => 'delete',
    'update_dimension' => 'updateDimension',
    'get_members_by_groups' => 'getMembersByGroups',
    'get_common_members' => 'getCommonMembers',
];

// 檢查 action 是否支援
if (!isset($actionMap[$action])) {
    ApiResponse::error('未知操作: ' . $action);
}

// 轉發到新 Controller
$controller = new CohortController();
$method = $actionMap[$action];

// 執行對應方法
$controller->$method();
