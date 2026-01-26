<?php
/**
 * Validator Test Script
 * 
 * Run this script to verify the Validator class works correctly.
 * Usage: php tests/test_validator.php
 */

require_once __DIR__ . '/../includes/Validator.php';

echo "=== Validator 驗證測試 ===\n\n";

$tests_passed = 0;
$tests_failed = 0;

function test($name, $condition)
{
    global $tests_passed, $tests_failed;
    if ($condition) {
        echo "✅ PASS: {$name}\n";
        $tests_passed++;
    } else {
        echo "❌ FAIL: {$name}\n";
        $tests_failed++;
    }
}

// ============================================
// Test 1: Upload Form - All required fields empty
// ============================================
echo "--- 測試 1: 上傳表單必填欄位 ---\n";

$result = Validator::validate([], 'upload');
test('空表單應該驗證失敗', $result['valid'] === false);
test('應該有錯誤訊息', !empty($result['errors']));
test('第一個錯誤應該是演講標題', strpos(Validator::getFirstError($result['errors']), '演講標題') !== false);

// ============================================
// Test 2: Upload Form - Valid data
// ============================================
echo "\n--- 測試 2: 上傳表單有效資料 ---\n";

$valid_upload = [
    'title' => '測試演講標題',
    'campus_id' => '1',
    'event_date' => '2026-01-26',
    'speaker_name' => '測試講者'
];
$result = Validator::validate($valid_upload, 'upload');
test('有效資料應該驗證通過', $result['valid'] === true);
test('不應該有錯誤', empty($result['errors']));

// ============================================
// Test 3: Upload Form - Title too short
// ============================================
echo "\n--- 測試 3: 標題太短 ---\n";

$short_title = $valid_upload;
$short_title['title'] = '一'; // Only 1 character, min is 2
$result = Validator::validate($short_title, 'upload');
test('標題只有1個字應該失敗', $result['valid'] === false);
test('錯誤應該提到最少2個字', strpos($result['errors']['title'] ?? '', '2') !== false);

// ============================================
// Test 4: Upload Form - Invalid date format
// ============================================
echo "\n--- 測試 4: 日期格式錯誤 ---\n";

$invalid_date = $valid_upload;
$invalid_date['event_date'] = '2026/01/26'; // Wrong format
$result = Validator::validate($invalid_date, 'upload');
test('錯誤的日期格式應該失敗', $result['valid'] === false);
test('錯誤應該提到日期', strpos($result['errors']['event_date'] ?? '', '日期') !== false);

// ============================================
// Test 5: Announcement Form - Only title required
// ============================================
echo "\n--- 測試 5: 公告表單只需標題 ---\n";

$announcement = ['title' => '公告測試標題'];
$result = Validator::validate($announcement, 'add_announcement');
test('公告只填標題應該通過', $result['valid'] === true);

$empty_announcement = [];
$result = Validator::validate($empty_announcement, 'add_announcement');
test('公告空表單應該失敗', $result['valid'] === false);

// ============================================
// Test 6: Login Form
// ============================================
echo "\n--- 測試 6: 登入表單 ---\n";

$valid_login = ['username' => 'admin', 'password' => '123456'];
$result = Validator::validate($valid_login, 'login');
test('有效登入資料應該通過', $result['valid'] === true);

$short_password = ['username' => 'admin', 'password' => '123'];
$result = Validator::validate($short_password, 'login');
test('密碼太短應該失敗 (min 4)', $result['valid'] === false);

// ============================================
// Test 7: getRulesJson
// ============================================
echo "\n--- 測試 7: getRulesJson ---\n";

$json = Validator::getRulesJson('upload');
$rules = json_decode($json, true);
test('JSON 應該有效', $rules !== null);
test('JSON 應該包含 title 規則', isset($rules['title']));
test('title.required 應該是 true', ($rules['title']['required'] ?? false) === true);

// ============================================
// Summary
// ============================================
echo "\n=================================\n";
echo "總測試: " . ($tests_passed + $tests_failed) . "\n";
echo "✅ 通過: {$tests_passed}\n";
echo "❌ 失敗: {$tests_failed}\n";
echo "=================================\n";

exit($tests_failed > 0 ? 1 : 0);
