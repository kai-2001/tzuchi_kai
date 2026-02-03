<?php
/**
 * API 自動化單元測試
 * tests/test_api.php
 * 
 * 執行方式：
 * 1. 瀏覽器訪問 http://localhost/cloud/tests/test_api.php
 * 2. 或命令列 php tests/test_api.php
 */

// 設定
$base_url = 'http://localhost/cloud';  // 根據實際環境調整
$is_cli = php_sapi_name() === 'cli';

// 輸出格式
function output($text, $type = 'info')
{
    global $is_cli;
    $colors = [
        'info' => "\033[0m",
        'success' => "\033[32m",
        'error' => "\033[31m",
        'warning' => "\033[33m",
        'header' => "\033[1;36m"
    ];

    if ($is_cli) {
        echo $colors[$type] . $text . "\033[0m\n";
    } else {
        $html_colors = [
            'info' => '#333',
            'success' => '#22c55e',
            'error' => '#ef4444',
            'warning' => '#f59e0b',
            'header' => '#0ea5e9'
        ];
        $weight = $type === 'header' ? 'bold' : 'normal';
        echo "<div style='color: {$html_colors[$type]}; font-weight: {$weight}; font-family: monospace;'>{$text}</div>";
    }
}

// HTTP 請求函數
function http_request($url, $method = 'GET', $data = null, $cookies = [])
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // 設定 Cookie
    if (!empty($cookies)) {
        $cookie_string = implode('; ', array_map(function ($k, $v) {
            return "$k=$v";
        }, array_keys($cookies), $cookies));
        curl_setopt($ch, CURLOPT_COOKIE, $cookie_string);
    }

    // 方法與資料
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    } elseif ($method === 'PUT' || $method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => $error, 'http_code' => 0];
    }

    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);

    // 解析 Set-Cookie
    preg_match_all('/Set-Cookie:\s*([^;]+)/i', $header, $matches);
    $response_cookies = [];
    foreach ($matches[1] as $cookie) {
        list($name, $value) = explode('=', $cookie, 2);
        $response_cookies[trim($name)] = trim($value);
    }

    return [
        'http_code' => $http_code,
        'body' => $body,
        'json' => json_decode($body, true),
        'cookies' => $response_cookies
    ];
}

// 測試結果統計
$tests_passed = 0;
$tests_failed = 0;
$test_results = [];

function assert_test($name, $condition, $message = '')
{
    global $tests_passed, $tests_failed, $test_results;

    if ($condition) {
        $tests_passed++;
        output("  ✓ $name", 'success');
        $test_results[] = ['name' => $name, 'passed' => true];
    } else {
        $tests_failed++;
        output("  ✗ $name" . ($message ? " - $message" : ''), 'error');
        $test_results[] = ['name' => $name, 'passed' => false, 'message' => $message];
    }
}

// ============================================
// 開始測試
// ============================================

if (!$is_cli) {
    echo "<html><head><title>API 測試</title></head><body style='padding: 20px; background: #1e1e1e; color: #fff;'>";
    echo "<h1 style='color: #0ea5e9;'>🧪 API 自動化測試</h1>";
}

output("========================================", 'header');
output("🧪 跨醫院學習網 API 自動化測試", 'header');
output("========================================", 'header');
output("Base URL: $base_url");
output("");

// --------------------------------------------
// 測試 1: 資料庫連線
// --------------------------------------------
output("【測試 1】資料庫連線檢查", 'header');

require_once __DIR__ . '/../includes/config.php';
$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
assert_test("資料庫連線", !$conn->connect_error, $conn->connect_error ?? '');

if (!$conn->connect_error) {
    // 檢查表格是否存在
    $tables = ['hospitals', 'attribute_types', 'attribute_values', 'user_attributes', 'course_rules', 'rule_conditions'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        assert_test("表格 $table 存在", $result->num_rows > 0);
    }
    $conn->close();
}

output("");

// --------------------------------------------
// 測試 2: 醫院 API
// --------------------------------------------
output("【測試 2】醫院 API (api/admin/hospitals.php)", 'header');

// 2.1 GET - 取得列表
$resp = http_request("$base_url/api/admin/hospitals.php");
assert_test("GET 請求成功", $resp['http_code'] === 200);
assert_test("回應為 JSON", isset($resp['json']));
assert_test("回應包含 success", isset($resp['json']['success']));

// 2.2 POST - 新增醫院（需要管理員權限，預期失敗）
$resp = http_request("$base_url/api/admin/hospitals.php", 'POST', [
    'code' => 'TEST',
    'name' => '測試醫院'
]);
assert_test("新增醫院需要權限 (403)", $resp['http_code'] === 403);

output("");

// --------------------------------------------
// 測試 3: 屬性類型 API
// --------------------------------------------
output("【測試 3】屬性類型 API (api/admin/attribute_types.php)", 'header');

$resp = http_request("$base_url/api/admin/attribute_types.php");
assert_test("GET 請求成功", $resp['http_code'] === 200);
assert_test("回應包含 data", isset($resp['json']['data']));
assert_test("預設有 3 種屬性類型", count($resp['json']['data'] ?? []) >= 3);

// 檢查預設類型
$types = $resp['json']['data'] ?? [];
$type_codes = array_column($types, 'code');
assert_test("包含 department 類型", in_array('department', $type_codes));
assert_test("包含 job_title 類型", in_array('job_title', $type_codes));
assert_test("包含 unit 類型", in_array('unit', $type_codes));

output("");

// --------------------------------------------
// 測試 4: 屬性值 API
// --------------------------------------------
output("【測試 4】屬性值 API (api/admin/attribute_values.php)", 'header');

$resp = http_request("$base_url/api/admin/attribute_values.php");
assert_test("GET 請求成功", $resp['http_code'] === 200);
assert_test("回應包含 data", isset($resp['json']['data']));

// 帶參數查詢
$resp = http_request("$base_url/api/admin/attribute_values.php?type_code=department");
assert_test("依類型查詢成功", $resp['http_code'] === 200);

output("");

// --------------------------------------------
// 測試 5: 使用者屬性 API
// --------------------------------------------
output("【測試 5】使用者屬性 API (api/admin/user_attributes.php)", 'header');

// 無權限應該被拒絕
$resp = http_request("$base_url/api/admin/user_attributes.php?user_id=1");
assert_test("無權限被拒絕 (403)", $resp['http_code'] === 403);

output("");

// --------------------------------------------
// 測試 6: 輔助函數
// --------------------------------------------
output("【測試 6】輔助函數 (includes/)", 'header');

// 測試 attribute_helper.php
require_once __DIR__ . '/../includes/attribute_helper.php';
assert_test("get_hospitals() 可呼叫", function_exists('get_hospitals'));
assert_test("get_attribute_values() 可呼叫", function_exists('get_attribute_values'));
assert_test("get_user_attribute_ids() 可呼叫", function_exists('get_user_attribute_ids'));

// 測試 rule_engine.php
require_once __DIR__ . '/../includes/rule_engine.php';
assert_test("check_course_eligibility() 可呼叫", function_exists('check_course_eligibility'));
assert_test("estimate_course_audience() 可呼叫", function_exists('estimate_course_audience'));
assert_test("get_course_rules() 可呼叫", function_exists('get_course_rules'));
assert_test("save_course_rules() 可呼叫", function_exists('save_course_rules'));

output("");

// --------------------------------------------
// 測試 7: 規則引擎邏輯
// --------------------------------------------
output("【測試 7】規則引擎邏輯測試", 'header');

// 測試預估人數（空規則）
$result = estimate_course_audience([]);
assert_test("空規則預估為 0", $result['total'] === 0);

output("");

// ============================================
// 測試結果摘要
// ============================================
output("========================================", 'header');
output("📊 測試結果摘要", 'header');
output("========================================", 'header');

$total = $tests_passed + $tests_failed;
$pass_rate = $total > 0 ? round(($tests_passed / $total) * 100, 1) : 0;

output("總測試數: $total");
output("通過: $tests_passed", 'success');
output("失敗: $tests_failed", $tests_failed > 0 ? 'error' : 'success');
output("通過率: $pass_rate%", $pass_rate === 100 ? 'success' : 'warning');

if (!$is_cli) {
    echo "</body></html>";
}

// 回傳結果（供 CI/CD 使用）
exit($tests_failed > 0 ? 1 : 0);
?>