<?php
// api/public/get_structure.php
// Public API: 回傳 "院區 (Category)" -> "單位 (Cohort)" 的階層結構
// 供註冊頁面動態選單使用

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/moodle_api.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // 1. 取得這台機器上 `institutions` 資料表定義的合法院區
    global $db_host, $db_user, $db_pass, $db_name;
    $local_db = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $local_db->set_charset("utf8mb4");
    
    $valid_institutions = [];
    $res = $local_db->query("SELECT * FROM institutions");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $valid_institutions[] = $row; // [id, name, cohort_idnumber...]
        }
    }
    $local_db->close();

    $structure = [];

    // 3. 精準比對：透過 cohort_idnumber 找到對應的 Category
    // institutions.cohort_idnumber → mdl_cohort.idnumber → mdl_context → Category ID
    
    $moodle_db_name = 'moodle';
    $moodle_prefix = 'mdl_';
    $moodle_db = new mysqli($db_host, $db_user, $db_pass, $moodle_db_name);
    $moodle_db->set_charset("utf8mb4");
    
    foreach ($valid_institutions as $inst) {
        $inst_name = $inst['name'];
        $cohort_idnumber = $inst['cohort_idnumber'] ?? '';
        
        if (empty($cohort_idnumber)) {
            // 沒有設定 cohort_idnumber，跳過或使用舊的名稱比對方式
            continue;
        }
        
        // 透過 cohort_idnumber 找到 Cohort，再從 context 找到 Category
        $sql = "
            SELECT c.id as cohort_id, c.name as cohort_name, ctx.instanceid as category_id, cat.name as category_name
            FROM {$moodle_prefix}cohort c
            JOIN {$moodle_prefix}context ctx ON ctx.id = c.contextid
            JOIN {$moodle_prefix}course_categories cat ON cat.id = ctx.instanceid
            WHERE c.idnumber = ? AND ctx.contextlevel = 40
        ";
        
        $stmt = $moodle_db->prepare($sql);
        $stmt->bind_param("s", $cohort_idnumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (!$row) {
            // 找不到對應的 Cohort，跳過
            continue;
        }
        
        $cat_id = $row['category_id'];
        $cat_name = $row['category_name'];
        
        // 抓取該類別下的所有 Cohorts (作為部門選項)
        $cohort_params = [
            'query' => '',
            'context' => [
                'contextid' => 0,
                'contextlevel' => 'coursecat',
                'instanceid' => $cat_id
            ],
            'includes' => 'all'
        ];

        $cohort_result = call_moodle($moodle_url, $moodle_token, 'core_cohort_search_cohorts', $cohort_params);
        $cohorts = [];

        if (!isset($cohort_result['exception']) && isset($cohort_result['cohorts'])) {
            foreach ($cohort_result['cohorts'] as $c) {
                $cohorts[] = [
                    'id' => $c['id'],
                    'name' => $c['name'],
                    'idnumber' => $c['idnumber']
                ];
            }
        }

        $structure[] = [
            'id' => $cat_id,
            'name' => $inst_name,
            'full_name' => $cat_name,
            'departments' => $cohorts
        ];
    }
    
    $moodle_db->close();

    echo json_encode(['success' => true, 'data' => $structure]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
