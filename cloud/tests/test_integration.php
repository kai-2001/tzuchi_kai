<?php
/**
 * 整合測試 - 模擬完整使用流程
 * tests/test_integration.php
 * 
 * 測試流程：
 * 1. 新增醫院
 * 2. 新增屬性值（部門、職稱）
 * 3. 新增使用者並設定屬性
 * 4. 設定課程規則
 * 5. 檢查報名資格
 * 6. 清理測試資料
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/attribute_helper.php';
require_once __DIR__ . '/../includes/rule_engine.php';

$is_cli = php_sapi_name() === 'cli';

function output($text, $type = 'info')
{
    global $is_cli;
    $icons = ['info' => '📌', 'success' => '✅', 'error' => '❌', 'warning' => '⚠️', 'header' => '📋'];

    if ($is_cli) {
        $colors = ['info' => "\033[0m", 'success' => "\033[32m", 'error' => "\033[31m", 'warning' => "\033[33m", 'header' => "\033[1;36m"];
        echo $colors[$type] . $icons[$type] . " $text\033[0m\n";
    } else {
        $html_colors = ['info' => '#64748b', 'success' => '#22c55e', 'error' => '#ef4444', 'warning' => '#f59e0b', 'header' => '#0ea5e9'];
        echo "<div style='color: {$html_colors[$type]}; font-family: monospace; padding: 4px 0;'>{$icons[$type]} $text</div>";
    }
}

// ============================================
// 開始測試
// ============================================

if (!$is_cli) {
    echo "<html><head><title>整合測試</title></head><body style='padding: 20px; background: #0f172a; color: #e2e8f0;'>";
    echo "<h1 style='color: #38bdf8;'>🔗 整合測試 - 完整使用流程</h1><hr style='border-color: #334155;'>";
}

output("========================================", 'header');
output("整合測試開始", 'header');
output("========================================", 'header');

// 連接資料庫
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    output("資料庫連線失敗: " . $conn->connect_error, 'error');
    exit(1);
}
$conn->set_charset('utf8mb4');
output("資料庫連線成功", 'success');

$test_hospital_id = null;
$test_attr_ids = [];
$test_user_id = null;
$test_rule_id = null;

try {
    // ----------------------------------------
    // Step 1: 新增測試醫院
    // ----------------------------------------
    output("");
    output("【Step 1】新增測試醫院", 'header');

    $stmt = $conn->prepare("INSERT INTO hospitals (code, name) VALUES (?, ?)");
    $code = 'TEST_' . time();
    $name = '測試醫院_' . date('His');
    $stmt->bind_param("ss", $code, $name);
    $stmt->execute();
    $test_hospital_id = $conn->insert_id;
    $stmt->close();

    output("醫院已建立: ID=$test_hospital_id, Code=$code, Name=$name", 'success');

    // 驗證
    $result = $conn->query("SELECT * FROM hospitals WHERE id = $test_hospital_id");
    $hospital = $result->fetch_assoc();
    if ($hospital && $hospital['name'] === $name) {
        output("驗證通過: 醫院資料正確", 'success');
    } else {
        throw new Exception("驗證失敗: 醫院資料不符");
    }

    // ----------------------------------------
    // Step 2: 新增測試屬性值
    // ----------------------------------------
    output("");
    output("【Step 2】新增測試屬性值", 'header');

    // 取得屬性類型
    $result = $conn->query("SELECT id, code FROM attribute_types");
    $type_map = [];
    while ($row = $result->fetch_assoc()) {
        $type_map[$row['code']] = (int) $row['id'];
    }

    // 新增部門
    $stmt = $conn->prepare("INSERT INTO attribute_values (type_id, code, name) VALUES (?, ?, ?)");
    $dept_code = 'TEST_DEPT';
    $dept_name = '測試部門';
    $stmt->bind_param("iss", $type_map['department'], $dept_code, $dept_name);
    $stmt->execute();
    $test_attr_ids['department'] = $conn->insert_id;
    output("部門已建立: ID={$test_attr_ids['department']}", 'success');

    // 新增職稱
    $job_code = 'TEST_JOB';
    $job_name = '測試職稱';
    $stmt->bind_param("iss", $type_map['job_title'], $job_code, $job_name);
    $stmt->execute();
    $test_attr_ids['job_title'] = $conn->insert_id;
    output("職稱已建立: ID={$test_attr_ids['job_title']}", 'success');

    // 新增院區專屬單位
    $stmt2 = $conn->prepare("INSERT INTO attribute_values (type_id, code, name, hospital_id) VALUES (?, ?, ?, ?)");
    $unit_code = 'TEST_UNIT';
    $unit_name = '測試病房';
    $stmt2->bind_param("issi", $type_map['unit'], $unit_code, $unit_name, $test_hospital_id);
    $stmt2->execute();
    $test_attr_ids['unit'] = $conn->insert_id;
    output("單位已建立 (院區專屬): ID={$test_attr_ids['unit']}", 'success');
    $stmt->close();
    $stmt2->close();

    // ----------------------------------------
    // Step 3: 新增測試使用者
    // ----------------------------------------
    output("");
    output("【Step 3】新增測試使用者", 'header');

    $stmt = $conn->prepare("INSERT INTO users (username, fullname, email, password, hospital_id) VALUES (?, ?, ?, ?, ?)");
    $username = 'test_user_' . time();
    $fullname = '測試使用者';
    $email = $username . '@test.com';
    $password = password_hash('test123', PASSWORD_DEFAULT);
    $stmt->bind_param("ssssi", $username, $fullname, $email, $password, $test_hospital_id);
    $stmt->execute();
    $test_user_id = $conn->insert_id;
    $stmt->close();

    output("使用者已建立: ID=$test_user_id, Username=$username", 'success');

    // 設定使用者屬性
    $attr_ids_to_set = array_values($test_attr_ids);
    $result = set_user_attributes($test_user_id, $attr_ids_to_set, null, $conn);
    if ($result) {
        output("使用者屬性已設定: " . implode(', ', $attr_ids_to_set), 'success');
    } else {
        throw new Exception("設定使用者屬性失敗");
    }

    // 驗證屬性
    $user_attrs = get_user_attribute_ids($test_user_id, $conn);
    if (count(array_intersect($user_attrs, $attr_ids_to_set)) === count($attr_ids_to_set)) {
        output("驗證通過: 使用者屬性正確設定", 'success');
    } else {
        throw new Exception("驗證失敗: 使用者屬性不符");
    }

    // ----------------------------------------
    // Step 4: 建立課程報名規則
    // ----------------------------------------
    output("");
    output("【Step 4】建立課程報名規則", 'header');

    $moodle_course_id = 99999; // 測試用課程 ID

    // 規則 1: 必須同時有「測試部門」AND「測試職稱」
    $rules = [
        [
            'rule_name' => '測試規則 - AND',
            'logic_type' => 'AND',
            'conditions' => [$test_attr_ids['department'], $test_attr_ids['job_title']]
        ]
    ];

    $result = save_course_rules($moodle_course_id, $rules, null, $conn);
    if ($result) {
        output("課程規則已儲存", 'success');
    } else {
        throw new Exception("儲存課程規則失敗");
    }

    // 驗證規則
    $saved_rules = get_course_rules($moodle_course_id, $conn);
    if (count($saved_rules) === 1 && count($saved_rules[0]['conditions']) === 2) {
        output("驗證通過: 規則儲存正確", 'success');
    } else {
        throw new Exception("驗證失敗: 規則儲存不符");
    }

    // ----------------------------------------
    // Step 5: 測試報名資格檢查
    // ----------------------------------------
    output("");
    output("【Step 5】測試報名資格檢查", 'header');

    // 測試使用者應該符合資格（有部門+職稱）
    $eligibility = check_course_eligibility($test_user_id, $moodle_course_id, $conn);
    if ($eligibility['eligible']) {
        output("符合資格檢查通過 (使用者符合 AND 規則)", 'success');
    } else {
        throw new Exception("資格檢查失敗: 應該符合但顯示不符合");
    }

    // 移除一個屬性後應該不符合
    set_user_attributes($test_user_id, [$test_attr_ids['department']], null, $conn);
    $eligibility = check_course_eligibility($test_user_id, $moodle_course_id, $conn);
    if (!$eligibility['eligible']) {
        output("不符合資格檢查通過 (使用者不符合 AND 規則)", 'success');
    } else {
        throw new Exception("資格檢查失敗: 應該不符合但顯示符合");
    }

    // ----------------------------------------
    // Step 6: 測試 OR 規則
    // ----------------------------------------
    output("");
    output("【Step 6】測試 OR 規則", 'header');

    $rules = [
        [
            'rule_name' => '測試規則 - OR',
            'logic_type' => 'OR',
            'conditions' => [$test_attr_ids['department'], $test_attr_ids['job_title']]
        ]
    ];
    save_course_rules($moodle_course_id, $rules, null, $conn);

    // 只有部門也應該符合
    $eligibility = check_course_eligibility($test_user_id, $moodle_course_id, $conn);
    if ($eligibility['eligible']) {
        output("OR 規則檢查通過 (只有一個屬性也符合)", 'success');
    } else {
        throw new Exception("OR 規則檢查失敗");
    }

    // ----------------------------------------
    // Step 7: 測試預估人數
    // ----------------------------------------
    output("");
    output("【Step 7】測試預估人數功能", 'header');

    // 還原使用者屬性
    set_user_attributes($test_user_id, array_values($test_attr_ids), null, $conn);

    $estimate = estimate_course_audience([
        ['logic_type' => 'AND', 'conditions' => [$test_attr_ids['department'], $test_attr_ids['job_title']]]
    ], $conn);

    if ($estimate['total'] >= 1) {
        output("預估人數功能正常: 符合人數 = {$estimate['total']}", 'success');
    } else {
        output("預估人數可能有誤: 符合人數 = {$estimate['total']}", 'warning');
    }

    output("");
    output("========================================", 'header');
    output("所有測試通過！", 'success');
    output("========================================", 'header');

} catch (Exception $e) {
    output("");
    output("測試失敗: " . $e->getMessage(), 'error');

} finally {
    // ----------------------------------------
    // 清理測試資料
    // ----------------------------------------
    output("");
    output("【清理】刪除測試資料", 'header');

    // 刪除規則
    if (isset($moodle_course_id)) {
        $conn->query("DELETE FROM course_rules WHERE moodle_course_id = $moodle_course_id");
        output("已刪除測試規則", 'info');
    }

    // 刪除使用者（會連帶刪除 user_attributes）
    if ($test_user_id) {
        $conn->query("DELETE FROM users WHERE id = $test_user_id");
        output("已刪除測試使用者", 'info');
    }

    // 刪除屬性值
    if (!empty($test_attr_ids)) {
        $ids = implode(',', $test_attr_ids);
        $conn->query("DELETE FROM attribute_values WHERE id IN ($ids)");
        output("已刪除測試屬性值", 'info');
    }

    // 刪除醫院
    if ($test_hospital_id) {
        $conn->query("DELETE FROM hospitals WHERE id = $test_hospital_id");
        output("已刪除測試醫院", 'info');
    }

    $conn->close();
    output("測試資料已清理完畢", 'success');
}

if (!$is_cli) {
    echo "</body></html>";
}
?>