<?php
/**
 * Legacy Proxy: manage_category.php → CategoryController
 * 
 * 此檔案現為 V2 API 的轉接層。所有新程式碼請在 CategoryController 中實作。
 */
require_once __DIR__ . '/../../core/bootstrap.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

require_once __DIR__ . '/../../app/Controllers/Api/CategoryController.php';
$controller = new CategoryController();

$actionMap = [
    'list'                      => 'listChildren',
    'list_all'                  => 'listAll',
    'list_tree'                 => 'listTree',
    'create'                    => 'create',
    'update'                    => 'update',
    'delete'                    => 'delete',
    'get_settings'              => 'getSettings',
    'update_settings'           => 'updateSettings',
    'search_users_by_filter'    => 'searchUsersByFilter',
    'save_mandatory_requirements' => 'saveMandatoryRequirements',
    'get_mandatory_categories'  => 'getMandatoryCategories',
];

if (isset($actionMap[$action])) {
    $method = $actionMap[$action];
    $controller->$method();
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => "Unknown action: $action"]);
}