<?php
/**
 * BASE_URL 和 BASE_PATH 使用范例
 * 
 * 这个档案展示如何使用 config.php 中定义的常数，
 * 让专案可以轻松搬移到任何目录。
 */

require_once __DIR__ . '/../includes/config.php';

// ============================================
// 后端使用范例 (PHP)
// ============================================

// 1. 引入其他档案
require_once BASE_PATH . '/includes/functions.php';
// 等同于：require_once __DIR__ . '/../includes/functions.php';

// 2. 读取上传的档案
$upload_file = BASE_PATH . '/uploads/image.jpg';
// 等同于：$upload_file = __DIR__ . '/../uploads/image.jpg';

// 3. Include 模板
include BASE_PATH . '/templates/header.php';


// ============================================
// 前端使用范例 (在 PHP 中输出 JS/HTML)
// ============================================
?>
<!DOCTYPE html>
<html>

<head>
    <!-- CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <!-- 等同于：<link rel="stylesheet" href="/cloud/assets/css/style.css"> -->

    <!-- JS -->
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
    <!-- 等同于：<script src="/cloud/assets/js/main.js"> -->
</head>

<body>
    <!-- 图片 -->
    <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Logo">
    <!-- 等同于：<img src="/cloud/assets/img/logo.png"> -->

    <!-- 表单提交 -->
    <form action="<?= BASE_URL ?>/api/submit.php" method="POST">
        <!-- 等同于：<form action="/cloud/api/submit.php"> -->
    </form>

    <script>
        // JavaScript 中使用
        const BASE_URL = '<?= BASE_URL ?>';

        // AJAX 请求
        fetch(`${BASE_URL}/api/hospital_admin/list_members.php`)
            .then(res => res.json())
            .then(data => console.log(data));

        // 等同于：
        // fetch('/cloud/api/hospital_admin/list_members.php')

        // 跳转页面
        window.location.href = `${BASE_URL}/index.php`;
    </script>
</body>

</html>

<?php
// ============================================
// 专案搬移指南
// ============================================

/*
当专案从 `/cloud/` 搬到其他位置时：

方法 1：搬到根目录
-----------------
1. 修改 config.php：
   define('BASE_URL', '');  // 改为空字串

2. 专案位置：
   http://localhost/index.php


方法 2：搬到 `/myproject/`
---------------------------
1. 修改 config.php：
   define('BASE_URL', '/myproject');

2. 专案位置：
   http://localhost/myproject/index.php


方法 3：使用自动侦测（进阶）
-----------------------------
在 config.php 加入：

// 自动侦测根路径
$script_name = $_SERVER['SCRIPT_NAME'];
$base_dir = dirname(dirname($script_name));
define('BASE_URL', $base_dir === '/' ? '' : $base_dir);

这样无论专案放在哪里都会自动侦测！
*/
?>