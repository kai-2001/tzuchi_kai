<?php
/**
 * 取得群組成員 API（簡化路徑代理）
 * /api_get_members.php
 * 
 * 實際調用 CohortController->getMembers()
 */

// 先 start session（讓能讀取登入狀態）
session_start();

// 載入核心
require_once __DIR__ . '/core/bootstrap.php';

// 取得 cohort_id
$cohortId = isset($_GET['cohort_id']) ? (int)$_GET['cohort_id'] : 0;

if ($cohortId <= 0) {
    ApiResponse::error('缺少 cohort_id');
    exit;
}

// 設定 GET 參數供 Controller 使用
$_GET['cohort_id'] = $cohortId;

// 執行 Controller
$controller = new CohortController();
$controller->getMembers();
