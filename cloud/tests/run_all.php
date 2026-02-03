<?php
/**
 * 測試執行器 - 執行所有測試
 * tests/run_all.php
 * 
 * 執行方式：
 * - 瀏覽器：http://localhost/cloud/tests/run_all.php
 * - 命令列：php tests/run_all.php
 */

$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>測試報告</title>";
    echo "<style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; padding: 30px; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #38bdf8; border-bottom: 2px solid #334155; padding-bottom: 15px; }
        .test-suite { background: #1e293b; border-radius: 12px; padding: 20px; margin: 20px 0; }
        .test-suite h2 { color: #94a3b8; margin-top: 0; }
        .test-output { background: #0f172a; border-radius: 8px; padding: 15px; font-family: monospace; font-size: 13px; overflow-x: auto; white-space: pre-wrap; }
        .pass { color: #22c55e; }
        .fail { color: #ef4444; }
        .summary { background: linear-gradient(135deg, #1e3a5f 0%, #1e293b 100%); border-radius: 12px; padding: 25px; margin-top: 30px; }
        .summary h2 { color: #38bdf8; margin-top: 0; }
        .stat { display: inline-block; margin-right: 30px; }
        .stat-value { font-size: 2em; font-weight: bold; }
        .stat-label { color: #64748b; }
    </style></head><body><div class='container'>";
    echo "<h1>🧪 自動化測試報告</h1>";
    echo "<p style='color: #64748b;'>執行時間：" . date('Y-m-d H:i:s') . "</p>";
}

$tests = [
    ['name' => 'API 單元測試', 'file' => 'test_api.php'],
    ['name' => '整合測試', 'file' => 'test_integration.php']
];

$results = [];
$total_time = 0;

foreach ($tests as $test) {
    $start = microtime(true);

    // 執行測試並捕捉輸出
    ob_start();
    $exit_code = 0;

    // 使用獨立進程執行以隔離測試
    $command = 'php ' . __DIR__ . '/' . $test['file'] . ' 2>&1';
    $output = shell_exec($command);

    // 檢查是否有錯誤指標
    $passed = strpos($output, '失敗: 0') !== false || strpos($output, '所有測試通過') !== false;

    $end = microtime(true);
    $duration = round(($end - $start) * 1000);
    $total_time += $duration;

    $results[] = [
        'name' => $test['name'],
        'file' => $test['file'],
        'passed' => $passed,
        'duration' => $duration,
        'output' => $output
    ];

    if (!$is_cli) {
        $status_class = $passed ? 'pass' : 'fail';
        $status_text = $passed ? '✅ 通過' : '❌ 失敗';

        echo "<div class='test-suite'>";
        echo "<h2><span class='$status_class'>$status_text</span> {$test['name']}</h2>";
        echo "<p style='color: #64748b;'>檔案：{$test['file']} | 耗時：{$duration}ms</p>";
        echo "<details><summary style='cursor: pointer; color: #38bdf8;'>查看詳細輸出</summary>";
        echo "<div class='test-output'>" . htmlspecialchars($output) . "</div>";
        echo "</details></div>";
    } else {
        $status = $passed ? "\033[32m✓ PASS\033[0m" : "\033[31m✗ FAIL\033[0m";
        echo "$status {$test['name']} ({$duration}ms)\n";
    }
}

// 統計
$passed_count = count(array_filter($results, fn($r) => $r['passed']));
$failed_count = count($results) - $passed_count;
$all_passed = $failed_count === 0;

if (!$is_cli) {
    $overall_class = $all_passed ? 'pass' : 'fail';
    $overall_text = $all_passed ? '✅ 所有測試通過' : '❌ 有測試失敗';

    echo "<div class='summary'>";
    echo "<h2><span class='$overall_class'>$overall_text</span></h2>";
    echo "<div class='stat'><div class='stat-value pass'>$passed_count</div><div class='stat-label'>通過</div></div>";
    echo "<div class='stat'><div class='stat-value fail'>$failed_count</div><div class='stat-label'>失敗</div></div>";
    echo "<div class='stat'><div class='stat-value'>{$total_time}ms</div><div class='stat-label'>總耗時</div></div>";
    echo "</div>";
    echo "</div></body></html>";
} else {
    echo "\n========================================\n";
    echo $all_passed ? "\033[32m所有測試通過！\033[0m\n" : "\033[31m有測試失敗！\033[0m\n";
    echo "通過: $passed_count | 失敗: $failed_count | 總耗時: {$total_time}ms\n";
}

exit($all_passed ? 0 : 1);
?>