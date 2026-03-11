<?php
/**
 * Legacy Proxy: update_member.php → MemberController::update
 * 
 * 此檔案已遷移至 V2 API，保留作為向後相容 proxy。
 * 新代碼請使用: /api/v2/index.php?route=members/update
 */

require_once __DIR__ . '/../../core/bootstrap.php';

$_GET['route'] = 'members/update';
require __DIR__ . '/../v2/index.php';