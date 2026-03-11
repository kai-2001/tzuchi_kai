<?php
/**
 * 取得院區統計數據（Legacy Proxy）
 * api/hospital_admin/get_stats.php
 * 
 * 已遷移至 V2 API：/api/v2/index.php?route=stats/dashboard
 * 此檔案保留為向後相容代理
 */
require_once __DIR__ . '/../../core/bootstrap.php';

$controller = new StatsController();
$controller->dashboard();