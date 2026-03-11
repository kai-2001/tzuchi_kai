<?php
/**
 * Legacy Proxy: add_member.php → MemberController::create
 * 
 * 此檔案已遷移至 V2 API，保留作為向後相容 proxy。
 * 新代碼請使用: /api/v2/index.php?route=members/create
 */

require_once __DIR__ . '/../../core/bootstrap.php';

$_GET['route'] = 'members/create';
require __DIR__ . '/../v2/index.php';