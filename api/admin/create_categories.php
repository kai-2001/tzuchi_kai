<?php
/**
 * Legacy Proxy: create_categories.php → CategoryController::batchCreate
 * 
 * 此檔案現為 V2 API 的轉接層。所有新程式碼請在 CategoryController 中實作。
 */
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../app/Controllers/Api/CategoryController.php';

$controller = new CategoryController();
$controller->batchCreate();
