-- ============================================
-- 跨醫院學習網 - 資料庫結構
-- 執行前請先備份現有資料！
-- ============================================

-- 使用 portal_db
USE portal_db;

-- ============================================
-- 1. 清理現有測試資料（可選）
-- ============================================
-- 如果要保留現有表格結構，請註解掉以下區塊

-- 清理使用者相關資料
TRUNCATE TABLE users;

-- 刪除舊的 institutions 表（如果存在）
DROP TABLE IF EXISTS institutions;

-- ============================================
-- 2. 醫院表（取代舊的 institutions）
-- ============================================
CREATE TABLE IF NOT EXISTS hospitals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE,                   -- 醫院代碼（可選，如 TPE, KHH）
    name VARCHAR(100) NOT NULL,                -- 醫院名稱
    moodle_category_id INT DEFAULT NULL,       -- 對應 Moodle 的課程分類 ID
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. 屬性類型表（系統內建，不常變動）
-- ============================================
CREATE TABLE IF NOT EXISTS attribute_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,          -- hospital, department, job_title, unit
    name VARCHAR(100) NOT NULL,                -- 顯示名稱
    scope ENUM('global', 'hospital') DEFAULT 'global',  -- global=全域共用, hospital=各院自訂
    is_multi_select TINYINT(1) DEFAULT 1,      -- 是否可多選
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 預設屬性類型
INSERT INTO attribute_types (code, name, scope, is_multi_select, display_order) VALUES
('department', '部門', 'global', 1, 1),
('job_title', '職稱', 'global', 1, 2),
('unit', '單位/病房', 'hospital', 1, 3);

-- ============================================
-- 4. 屬性值表（管理員動態新增）
-- ============================================
CREATE TABLE IF NOT EXISTS attribute_values (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_id INT NOT NULL,                      -- 屬性類型 ID
    code VARCHAR(50) DEFAULT NULL,             -- 代碼（可選）
    name VARCHAR(100) NOT NULL,                -- 顯示名稱
    hospital_id INT DEFAULT NULL,              -- NULL=全域通用, 有值=特定醫院專用
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES attribute_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. 修改 users 表結構
-- ============================================
-- 移除舊的 institution 欄位依賴，改用 hospital_id

-- 先檢查並新增 hospital_id 欄位
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS hospital_id INT DEFAULT NULL,
    ADD CONSTRAINT fk_users_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL;

-- ============================================
-- 6. 使用者屬性關聯表（多對多）
-- ============================================
CREATE TABLE IF NOT EXISTS user_attributes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    attribute_value_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT DEFAULT NULL,              -- 指派者 user_id
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (attribute_value_id) REFERENCES attribute_values(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_attr (user_id, attribute_value_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. 課程報名規則表
-- ============================================
CREATE TABLE IF NOT EXISTS course_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    moodle_course_id INT NOT NULL,             -- 對應 Moodle course ID
    rule_name VARCHAR(100) DEFAULT NULL,       -- 規則描述（可選）
    logic_type ENUM('AND', 'OR') NOT NULL DEFAULT 'AND',  -- 條件邏輯
    is_active TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_course (moodle_course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. 規則條件表
-- ============================================
CREATE TABLE IF NOT EXISTS rule_conditions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rule_id INT NOT NULL,
    attribute_value_id INT NOT NULL,
    FOREIGN KEY (rule_id) REFERENCES course_rules(id) ON DELETE CASCADE,
    FOREIGN KEY (attribute_value_id) REFERENCES attribute_values(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. 建立索引優化查詢
-- ============================================
CREATE INDEX IF NOT EXISTS idx_attr_values_type ON attribute_values(type_id);
CREATE INDEX IF NOT EXISTS idx_attr_values_hospital ON attribute_values(hospital_id);
CREATE INDEX IF NOT EXISTS idx_user_attrs_user ON user_attributes(user_id);
CREATE INDEX IF NOT EXISTS idx_user_attrs_value ON user_attributes(attribute_value_id);

-- ============================================
-- 完成！
-- ============================================
SELECT '資料庫結構建立完成！' AS message;
