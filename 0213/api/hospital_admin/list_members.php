<?php
/**
 * Legacy Proxy: list_members.php → MemberController::list
 * 
 * 此檔案已遷移至 V2 API，保留作為向後相容 proxy。
 * 新代碼請使用: /api/v2/index.php?route=members/list
 */

require_once __DIR__ . '/../../core/bootstrap.php';

$_GET['route'] = 'members/list';
require __DIR__ . '/../v2/index.php';