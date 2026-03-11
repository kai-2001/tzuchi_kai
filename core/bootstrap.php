<?php
/**
 * 應用程式啟動檔案
 * core/bootstrap.php
 * 
 * 載入所有必要的檔案並初始化應用程式
 */

// 錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定義根目錄
define('ROOT_PATH', dirname(__DIR__));
define('CORE_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');

// 載入設定
require_once ROOT_PATH . '/includes/config.php';

// 載入核心類別
require_once CORE_PATH . '/Autoloader.php';
require_once CORE_PATH . '/Database.php';
require_once CORE_PATH . '/ApiResponse.php';
require_once CORE_PATH . '/Router.php';
require_once CORE_PATH . '/Controller.php';
require_once CORE_PATH . '/Model.php';

// 註冊自動載入器
Autoloader::register();

// Session 啟動（如果尚未啟動）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 載入舊版函式（向後相容）
require_once ROOT_PATH . '/includes/functions.php';
