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
    <title><?= htmlspecialchars($page_title ?? APP_NAME) ?> - <?= APP_NAME ?></title>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=Inter:wght@300;400;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (!empty($page_css_files)): ?>
        <?php foreach ($page_css_files as $css_file): ?>
            <link rel="stylesheet" href="assets/css/<?= htmlspecialchars($css_file) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>

<body>
    <div class="container">