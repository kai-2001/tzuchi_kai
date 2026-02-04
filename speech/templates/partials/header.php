<?php
/**
 * Shared Header Partial
 * 
 * Variables available:
 * - $page_title (string) - Page title
 * - $page_css_files (array) - Page-specific CSS files to load
 */
?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? '學術演講影片平台') ?> - 學術演講影片平台</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/apple-touch-icon.png">

    <!-- Local CSS Resources -->
    <link rel="stylesheet" href="assets/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/css/swiper-bundle.min.css" />
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() + 5 ?>">
    <?php if (!empty($page_css_files)): ?>
        <?php foreach ($page_css_files as $css_file): ?>
            <link rel="stylesheet" href="assets/css/<?= htmlspecialchars($css_file) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>

<body>