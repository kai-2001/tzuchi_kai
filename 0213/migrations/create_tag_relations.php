<?php
/**
 * 建立標籤關聯表
 * migrations/create_tag_relations.php
 */
require_once __DIR__ . '/../core/bootstrap.php';

$db = Database::getInstance();

// 建立課程標籤關聯表
$sql1 = "CREATE TABLE IF NOT EXISTS kai_course_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL COMMENT 'Moodle course ID',
    tag_id INT NOT NULL COMMENT '標籤 ID',
    institution VARCHAR(50) NOT NULL COMMENT '院區',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_course_tag (course_id, tag_id),
    INDEX idx_course (course_id),
    INDEX idx_tag (tag_id),
    INDEX idx_institution (institution)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='課程-標籤關聯'";

// 建立使用者標籤關聯表
$sql2 = "CREATE TABLE IF NOT EXISTS kai_user_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL COMMENT 'Moodle user ID',
    tag_id INT NOT NULL COMMENT '標籤 ID',
    institution VARCHAR(50) NOT NULL COMMENT '院區',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_tag (user_id, tag_id),
    INDEX idx_user (user_id),
    INDEX idx_tag (tag_id),
    INDEX idx_institution (institution)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='使用者-標籤關聯'";

echo "建立標籤關聯表...\n";

try {
    $db->query($sql1);
    echo "✓ kai_course_tags 建立成功\n";
} catch (Exception $e) {
    echo "✗ kai_course_tags 建立失敗: " . $e->getMessage() . "\n";
}

try {
    $db->query($sql2);
    echo "✓ kai_user_tags 建立成功\n";
} catch (Exception $e) {
    echo "✗ kai_user_tags 建立失敗: " . $e->getMessage() . "\n";
}

echo "\n完成！\n";
