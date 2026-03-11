-- =============================================
-- Portal Tags 資料表
-- 用於院區隔離的標籤管理
-- =============================================

CREATE TABLE IF NOT EXISTS portal_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#6b7280',
    institution_code VARCHAR(50) DEFAULT NULL COMMENT 'NULL = 全域模板',
    is_template BOOLEAN DEFAULT FALSE COMMENT '是否為系統模板',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_institution (institution_code),
    INDEX idx_active (is_active),
    INDEX idx_template (is_template)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 插入預設系統模板標籤
-- =============================================

INSERT INTO portal_tags (name, description, color, institution_code, is_template, sort_order) VALUES
('PGY', '畢業後一般醫學訓練', '#3b82f6', NULL, TRUE, 1),
('臨床教師', '臨床教學人員', '#10b981', NULL, TRUE, 2),
('進修中', '正在進修', '#f59e0b', NULL, TRUE, 3),
('新進人員', '新到職人員', '#8b5cf6', NULL, TRUE, 4),
('專科護理師', 'NP 訓練', '#ec4899', NULL, TRUE, 5);
