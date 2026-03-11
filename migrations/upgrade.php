<?php
/**
 * Portal 資料庫升級腳本
 * 自動比對表和欄位，缺什麼補什麼，不會破壞現有資料
 * 
 * 使用方式：php migrations/upgrade.php
 */

require_once __DIR__ . '/../includes/config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("❌ 連線失敗: " . $conn->connect_error . "\n");
}
$conn->set_charset('utf8mb4');

echo "=== Portal 資料庫升級腳本 ===\n\n";

// ============================================
// 定義所有表和欄位（完整 Schema）
// ============================================
$schema = [
    'institutions' => [
        'columns' => [
            'id'                      => 'INT AUTO_INCREMENT PRIMARY KEY',
            'name'                    => "VARCHAR(100) NOT NULL DEFAULT ''",
            'cohort_idnumber'         => 'VARCHAR(100) DEFAULT NULL',
            'management_category_id'  => 'INT DEFAULT 0',
            'created_at'              => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        ],
        'indexes' => [
            'uk_name' => 'UNIQUE KEY uk_name (name)',
        ],
    ],

    'users' => [
        'columns' => [
            'id'          => 'INT AUTO_INCREMENT PRIMARY KEY',
            'username'    => "VARCHAR(100) NOT NULL DEFAULT ''",
            'password'    => "VARCHAR(255) NOT NULL DEFAULT ''",
            'fullname'    => "VARCHAR(100) DEFAULT ''",
            'email'       => "VARCHAR(200) DEFAULT ''",
            'institution' => "VARCHAR(100) DEFAULT ''",
            'role'        => "VARCHAR(50) DEFAULT 'student'",
            'created_at'  => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at'  => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ],
        'indexes' => [
            'uk_username'     => 'UNIQUE KEY uk_username (username)',
            'idx_institution' => 'INDEX idx_institution (institution)',
            'idx_role'        => 'INDEX idx_role (role)',
        ],
    ],

    'dimension_types' => [
        'columns' => [
            'id'             => 'INT AUTO_INCREMENT PRIMARY KEY',
            'institution_id' => 'INT NOT NULL',
            'name'           => "VARCHAR(100) NOT NULL DEFAULT ''",
            'created_at'     => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        ],
        'indexes' => [
            'idx_institution' => 'INDEX idx_institution (institution_id)',
        ],
    ],

    'cohort_dimensions' => [
        'columns' => [
            'id'                 => 'INT AUTO_INCREMENT PRIMARY KEY',
            'dimension_type_id'  => 'INT NOT NULL',
            'moodle_cohort_id'   => 'INT NOT NULL',
            'display_name'       => "VARCHAR(200) DEFAULT ''",
            'parent_cohort_id'   => 'INT DEFAULT NULL',
            'parent_category_id' => 'INT DEFAULT NULL',
            'created_at'         => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        ],
        'indexes' => [
            'idx_dimension_type' => 'INDEX idx_dimension_type (dimension_type_id)',
            'idx_moodle_cohort'  => 'INDEX idx_moodle_cohort (moodle_cohort_id)',
        ],
    ],

    'portal_tags' => [
        'columns' => [
            'id'               => 'INT AUTO_INCREMENT PRIMARY KEY',
            'name'             => "VARCHAR(100) NOT NULL DEFAULT ''",
            'description'      => 'VARCHAR(255) DEFAULT NULL',
            'color'            => "VARCHAR(7) DEFAULT '#6b7280'",
            'institution_code' => 'VARCHAR(50) DEFAULT NULL',
            'is_template'      => 'BOOLEAN DEFAULT FALSE',
            'is_active'        => 'BOOLEAN DEFAULT TRUE',
            'sort_order'       => 'INT DEFAULT 0',
            'created_by'       => 'INT DEFAULT NULL',
            'created_at'       => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at'       => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ],
        'indexes' => [
            'idx_institution' => 'INDEX idx_institution (institution_code)',
            'idx_active'      => 'INDEX idx_active (is_active)',
            'idx_template'    => 'INDEX idx_template (is_template)',
        ],
    ],

    'course_tags' => [
        'columns' => [
            'id'          => 'INT AUTO_INCREMENT PRIMARY KEY',
            'course_id'   => 'INT NOT NULL',
            'tag_id'      => 'INT NOT NULL',
            'institution' => "VARCHAR(100) DEFAULT ''",
            'created_at'  => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        ],
        'indexes' => [
            'uk_course_tag' => 'UNIQUE KEY uk_course_tag (course_id, tag_id, institution)',
            'idx_course'    => 'INDEX idx_course (course_id)',
            'idx_tag'       => 'INDEX idx_tag (tag_id)',
        ],
    ],

    'portal_category_settings' => [
        'columns' => [
            'id'                    => 'INT AUTO_INCREMENT PRIMARY KEY',
            'moodle_category_id'    => 'INT NOT NULL',
            'is_mandatory_category' => 'BOOLEAN DEFAULT FALSE',
            'required_pass_count'   => 'INT DEFAULT 0',
            'period_months'         => 'INT DEFAULT 0',
            'require_order'         => 'BOOLEAN DEFAULT FALSE',
            'visibility'            => "VARCHAR(20) DEFAULT 'visible'",
            'created_at'            => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at'            => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ],
        'indexes' => [
            'uk_moodle_category' => 'UNIQUE KEY uk_moodle_category (moodle_category_id)',
        ],
    ],
];

// ============================================
// 執行升級
// ============================================
$changes = 0;

foreach ($schema as $table => $def) {
    // 檢查表是否存在
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    
    if ($result->num_rows === 0) {
        // 表不存在 → 整張建立
        $colDefs = [];
        foreach ($def['columns'] as $col => $colDef) {
            $colDefs[] = "`$col` $colDef";
        }
        foreach ($def['indexes'] ?? [] as $idx) {
            $colDefs[] = $idx;
        }
        
        $sql = "CREATE TABLE `$table` (\n  " . implode(",\n  ", $colDefs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql)) {
            echo "✅ 建立表 `$table`\n";
            $changes++;
        } else {
            echo "❌ 建立表 `$table` 失敗: " . $conn->error . "\n";
        }
    } else {
        // 表存在 → 逐欄比對
        $existingCols = [];
        $colResult = $conn->query("SHOW COLUMNS FROM `$table`");
        while ($row = $colResult->fetch_assoc()) {
            $existingCols[] = strtolower($row['Field']);
        }
        
        $prevCol = null;
        foreach ($def['columns'] as $col => $colDef) {
            if (strpos($colDef, 'PRIMARY KEY') !== false) {
                // 跳過 PK 欄位的新增（通常已存在）
                if (!in_array(strtolower($col), $existingCols)) {
                    echo "⚠️  表 `$table` 缺少主鍵欄位 `$col`，需手動處理\n";
                }
                $prevCol = $col;
                continue;
            }
            
            if (!in_array(strtolower($col), $existingCols)) {
                $position = $prevCol ? "AFTER `$prevCol`" : "FIRST";
                $sql = "ALTER TABLE `$table` ADD COLUMN `$col` $colDef $position";
                
                if ($conn->query($sql)) {
                    echo "✅ 表 `$table` 新增欄位 `$col`\n";
                    $changes++;
                } else {
                    echo "❌ 表 `$table` 新增 `$col` 失敗: " . $conn->error . "\n";
                }
            }
            $prevCol = $col;
        }
        
        // 檢查缺少的索引
        $existingIndexes = [];
        $idxResult = $conn->query("SHOW INDEX FROM `$table`");
        while ($row = $idxResult->fetch_assoc()) {
            $existingIndexes[] = strtolower($row['Key_name']);
        }
        
        foreach ($def['indexes'] ?? [] as $idxName => $idxDef) {
            if (!in_array(strtolower($idxName), $existingIndexes)) {
                $sql = "ALTER TABLE `$table` ADD $idxDef";
                if ($conn->query($sql)) {
                    echo "✅ 表 `$table` 新增索引 `$idxName`\n";
                    $changes++;
                } else {
                    // 索引可能因重複值無法建立，僅警告
                    echo "⚠️  表 `$table` 索引 `$idxName` 建立失敗: " . $conn->error . "\n";
                }
            }
        }
    }
}

// ============================================
// 插入預設標籤（如果還沒有）
// ============================================
$tagResult = $conn->query("SELECT COUNT(*) as c FROM portal_tags WHERE is_template = 1");
$tagCount = $tagResult ? (int)$tagResult->fetch_assoc()['c'] : 0;

if ($tagCount === 0) {
    $conn->query("INSERT INTO portal_tags (name, description, color, institution_code, is_template, sort_order) VALUES
        ('PGY', '畢業後一般醫學訓練', '#3b82f6', NULL, TRUE, 1),
        ('臨床教師', '臨床教學人員', '#10b981', NULL, TRUE, 2),
        ('進修中', '正在進修', '#f59e0b', NULL, TRUE, 3),
        ('新進人員', '新到職人員', '#8b5cf6', NULL, TRUE, 4),
        ('專科護理師', 'NP 訓練', '#ec4899', NULL, TRUE, 5)
    ");
    echo "✅ 插入預設模板標籤 (5 筆)\n";
    $changes++;
}

// ============================================
// 結果
// ============================================
echo "\n";
if ($changes === 0) {
    echo "✨ 資料庫已是最新，無需變更\n";
} else {
    echo "🎉 完成！共執行 $changes 項變更\n";
}

$conn->close();
