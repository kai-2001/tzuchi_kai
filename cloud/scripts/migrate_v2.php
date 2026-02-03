<?php
/**
 * 資料庫結構升級腳本
 * 執行方式：在瀏覽器訪問此檔案，或 php scripts/migrate_v2.php
 */

require_once __DIR__ . '/../includes/config.php';

echo "<pre>\n";
echo "========================================\n";
echo "跨醫院學習網 - 資料庫結構升級\n";
echo "========================================\n\n";

// 連接資料庫
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("資料庫連線失敗: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// 執行 SQL 語句的輔助函數
function run_sql($conn, $sql, $description) {
    echo "▶ $description... ";
    if ($conn->query($sql) === TRUE) {
        echo "✓ 成功\n";
        return true;
    } else {
        // 忽略某些錯誤（如表已存在）
        if (strpos($conn->error, 'already exists') !== false || 
            strpos($conn->error, 'Duplicate') !== false) {
            echo "⚠ 已存在，跳過\n";
            return true;
        }
        echo "✗ 失敗: " . $conn->error . "\n";
        return false;
    }
}

// ============================================
// Step 1: 建立 hospitals 表
// ============================================
echo "\n【Step 1】建立 hospitals 表\n";

$sql = "CREATE TABLE IF NOT EXISTS hospitals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE,
    name VARCHAR(100) NOT NULL,
    moodle_category_id INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
run_sql($conn, $sql, "建立 hospitals 表");

// ============================================
// Step 2: 建立 attribute_types 表
// ============================================
echo "\n【Step 2】建立 attribute_types 表\n";

$sql = "CREATE TABLE IF NOT EXISTS attribute_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    scope ENUM('global', 'hospital') DEFAULT 'global',
    is_multi_select TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
run_sql($conn, $sql, "建立 attribute_types 表");

// 插入預設屬性類型
$types = [
    ['department', '部門', 'global', 1, 1],
    ['job_title', '職稱', 'global', 1, 2],
    ['unit', '單位/病房', 'hospital', 1, 3]
];

foreach ($types as $type) {
    $sql = "INSERT IGNORE INTO attribute_types (code, name, scope, is_multi_select, display_order) 
            VALUES ('{$type[0]}', '{$type[1]}', '{$type[2]}', {$type[3]}, {$type[4]})";
    run_sql($conn, $sql, "插入屬性類型: {$type[1]}");
}

// ============================================
// Step 3: 建立 attribute_values 表
// ============================================
echo "\n【Step 3】建立 attribute_values 表\n";

$sql = "CREATE TABLE IF NOT EXISTS attribute_values (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_id INT NOT NULL,
    code VARCHAR(50) DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    hospital_id INT DEFAULT NULL,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES attribute_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
run_sql($conn, $sql, "建立 attribute_values 表");

// ============================================
// Step 4: 修改 users 表，新增 hospital_id 欄位
// ============================================
echo "\n【Step 4】修改 users 表\n";

// 檢查 hospital_id 欄位是否存在
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'hospital_id'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN hospital_id INT DEFAULT NULL";
    run_sql($conn, $sql, "新增 hospital_id 欄位");
    
    // 嘗試加外鍵（可能失敗如果有不合法資料）
    $sql = "ALTER TABLE users ADD CONSTRAINT fk_users_hospital 
            FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL";
    run_sql($conn, $sql, "新增 hospital_id 外鍵");
} else {
    echo "▶ hospital_id 欄位... ⚠ 已存在，跳過\n";
}

// ============================================
// Step 5: 建立 user_attributes 表
// ============================================
echo "\n【Step 5】建立 user_attributes 表\n";

$sql = "CREATE TABLE IF NOT EXISTS user_attributes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    attribute_value_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (attribute_value_id) REFERENCES attribute_values(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_attr (user_id, attribute_value_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
run_sql($conn, $sql, "建立 user_attributes 表");

// ============================================
// Step 6: 建立 course_rules 表
// ============================================
echo "\n【Step 6】建立 course_rules 表\n";

$sql = "CREATE TABLE IF NOT EXISTS course_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    moodle_course_id INT NOT NULL,
    rule_name VARCHAR(100) DEFAULT NULL,
    logic_type ENUM('AND', 'OR') NOT NULL DEFAULT 'AND',
    is_active TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_course (moodle_course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
run_sql($conn, $sql, "建立 course_rules 表");

// ============================================
// Step 7: 建立 rule_conditions 表
// ============================================
echo "\n【Step 7】建立 rule_conditions 表\n";

$sql = "CREATE TABLE IF NOT EXISTS rule_conditions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rule_id INT NOT NULL,
    attribute_value_id INT NOT NULL,
    FOREIGN KEY (rule_id) REFERENCES course_rules(id) ON DELETE CASCADE,
    FOREIGN KEY (attribute_value_id) REFERENCES attribute_values(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
run_sql($conn, $sql, "建立 rule_conditions 表");

// ============================================
// Step 8: 建立索引
// ============================================
echo "\n【Step 8】建立索引\n";

$indexes = [
    ["attribute_values", "idx_attr_values_type", "type_id"],
    ["attribute_values", "idx_attr_values_hospital", "hospital_id"],
    ["user_attributes", "idx_user_attrs_user", "user_id"],
    ["user_attributes", "idx_user_attrs_value", "attribute_value_id"]
];

foreach ($indexes as $idx) {
    $sql = "CREATE INDEX IF NOT EXISTS {$idx[1]} ON {$idx[0]}({$idx[2]})";
    // MySQL 不支援 IF NOT EXISTS for INDEX，改用 try-catch 方式
    $check = $conn->query("SHOW INDEX FROM {$idx[0]} WHERE Key_name = '{$idx[1]}'");
    if ($check->num_rows == 0) {
        $sql = "CREATE INDEX {$idx[1]} ON {$idx[0]}({$idx[2]})";
        run_sql($conn, $sql, "建立索引 {$idx[1]}");
    } else {
        echo "▶ 建立索引 {$idx[1]}... ⚠ 已存在，跳過\n";
    }
}

// ============================================
// 完成
// ============================================
echo "\n========================================\n";
echo "✅ 資料庫結構升級完成！\n";
echo "========================================\n";

// 顯示目前表格狀態
echo "\n目前資料庫表格：\n";
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    echo "  - " . $row[0] . "\n";
}

$conn->close();
echo "</pre>";
?>
