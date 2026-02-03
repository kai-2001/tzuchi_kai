<?php
// 提取 JavaScript 並檢查語法

function extractJS($file)
{
    $content = file_get_contents($file);
    preg_match('/<script>(.*?)<\/script>/s', $content, $matches);
    return $matches[1] ?? '';
}

// 簡單輸出讓 node 檢查
$js1 = extractJS(__DIR__ . '/templates/tabs/hospital_admin_members_v2.php');
$js2 = extractJS(__DIR__ . '/templates/tabs/admin_settings.php');

// 替換 PHP 標籤
$js1 = preg_replace('/<\?=.*?\?>/', '"/cloud"', $js1);
$js2 = preg_replace('/<\?=.*?\?>/', '"/cloud"', $js2);

file_put_contents(__DIR__ . '/temp_js1.js', $js1);
file_put_contents(__DIR__ . '/temp_js2.js', $js2);

echo "JS files extracted. Run node --check on them.\n";
