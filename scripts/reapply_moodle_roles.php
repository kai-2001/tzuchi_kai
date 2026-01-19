<?php
/**
 * 重新套用所有 coursecreator 角色到 Moodle
 * scripts/reapply_moodle_roles.php
 * 
 * 用於修復 Portal DB 與 Moodle DB 角色不一致的問題
 */
define('CLI_SCRIPT', true);
require_once __DIR__ . '/../includes/config.php';
require_once(dirname(__DIR__) . '/moodle/config.php'); // 需要 Moodle config 取得 DB 連線

// 1. 取得 Portal 所有 role = 'coursecreator' 的用戶
$portal_conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$portal_conn->set_charset('utf8mb4');

echo "Fetching coursecreators from Portal DB...\n";
$sql = "SELECT username, institution FROM users WHERE role = 'coursecreator'";
$result = $portal_conn->query($sql);

$users_to_sync = [];
while ($row = $result->fetch_assoc()) {
    $users_to_sync[] = $row;
}
$portal_conn->close();

echo "Found " . count($users_to_sync) . " users to check.\n";

// 2. 定義院區到 Category ID 的對映
// 這裡我們需要查詢 Moodle 的 mdl_course_categories
// 假設類別名稱跟 institution 一樣 (台北, 大林, 花蓮, 嘉義)

$cat_map = [];
$cats = $DB->get_records('course_categories');
foreach ($cats as $cat) {
    // 簡單對映：如果類別名稱包含院區名稱
    if (strpos($cat->name, '台北') !== false)
        $cat_map['台北'] = $cat->id;
    if (strpos($cat->name, '大林') !== false)
        $cat_map['大林'] = $cat->id;
    if (strpos($cat->name, '花蓮') !== false)
        $cat_map['花蓮'] = $cat->id;
    if (strpos($cat->name, '嘉義') !== false)
        $cat_map['嘉義'] = $cat->id;
}

print_r($cat_map);

// 3. 執行同步
foreach ($users_to_sync as $u) {
    if (isset($cat_map[$u['institution']])) {
        $cat_id = $cat_map[$u['institution']];
        echo "Syncing {$u['username']} ({$u['institution']}) to Category $cat_id... ";

        $script_path = __DIR__ . '/set_moodle_role.php';
        $cmd = "php " . escapeshellarg($script_path) . " " . escapeshellarg($u['username']) . " coursecreator " . escapeshellarg($cat_id);

        $output = [];
        $return_var = 0;
        exec($cmd, $output, $return_var);

        if ($return_var === 0) {
            echo "OK\n";
        } else {
            echo "FAILED: " . implode(" ", $output) . "\n";
        }
    } else {
        echo "SKIPPING {$u['username']}: Unknown category for institution {$u['institution']}\n";
    }
}
