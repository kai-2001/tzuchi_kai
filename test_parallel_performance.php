<?php
// test_parallel_performance.php - 測試平行 API 請求性能
// 使用方法: 在瀏覽器訪問 http://localhost/test_parallel_performance.php

require_once 'includes/config.php';
require_once 'includes/functions.php';

// 測試用的 API 請求
$test_requests = [
    [
        'key' => 'req1',
        'func' => 'core_course_search_courses',
        'params' => ['criterianame' => 'search', 'criteriavalue' => '']
    ],
    [
        'key' => 'req2',
        'func' => 'core_course_get_courses_by_field',
        'params' => ['field' => 'category', 'value' => 2]
    ],
    [
        'key' => 'req3',
        'func' => 'core_course_get_courses_by_field',
        'params' => ['field' => 'category', 'value' => 4]
    ]
];

echo "<h1>Moodle API 平行請求性能測試</h1>";

// 測試 1: 連續請求 (Sequential)
echo "<h2>測試 1: 連續請求 (Sequential)</h2>";
$start_time = microtime(true);

foreach ($test_requests as $req) {
    call_moodle($moodle_url, $moodle_token, $req['func'], $req['params']);
}

$sequential_time = microtime(true) - $start_time;
echo "<p><strong>連續請求時間:</strong> " . number_format($sequential_time * 1000, 2) . " ms</p>";

// 測試 2: 平行請求 (Parallel)
echo "<h2>測試 2: 平行請求 (Parallel)</h2>";
$start_time = microtime(true);

$results = call_moodle_parallel($moodle_url, $moodle_token, $test_requests);

$parallel_time = microtime(true) - $start_time;
echo "<p><strong>平行請求時間:</strong> " . number_format($parallel_time * 1000, 2) . " ms</p>";

// 計算改進
$improvement = (($sequential_time - $parallel_time) / $sequential_time) * 100;
$speedup = $sequential_time / $parallel_time;

echo "<h2>性能改進</h2>";
echo "<p><strong>時間節省:</strong> " . number_format($improvement, 1) . "%</p>";
echo "<p><strong>速度提升:</strong> " . number_format($speedup, 2) . "x 倍</p>";

// 驗證資料
echo "<h2>資料驗證</h2>";
if (isset($results['req1']) && is_array($results['req1'])) {
    echo "<p>✓ req1 資料正常</p>";
} else {
    echo "<p>✗ req1 資料異常</p>";
}

if (isset($results['req2']) && is_array($results['req2'])) {
    echo "<p>✓ req2 資料正常</p>";
} else {
    echo "<p>✗ req2 資料異常</p>";
}

if (isset($results['req3']) && is_array($results['req3'])) {
    echo "<p>✓ req3 資料正常</p>";
} else {
    echo "<p>✗ req3 資料異常</p>";
}

echo "<hr>";
echo "<p><em>測試完成於 " . date('Y-m-d H:i:s') . "</em></p>";
?>