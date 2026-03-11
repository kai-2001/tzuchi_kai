<?php
/**
 * 執行 portal_tags 資料表建立
 * 使用方式：在瀏覽器開啟 http://localhost/migrations/run_portal_tags.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 使用現有的連線
require_once __DIR__ . '/../includes/db_connect.php';
// $conn 已經由 db_connect.php 建立

echo "<h2>🏷️ Portal Tags 資料表建立</h2>";

// 建立資料表
$createTableSql = "
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

if ($conn->query($createTableSql)) {
    echo "<p>✅ 資料表 portal_tags 建立成功</p>";
} else {
    echo "<p>❌ 建立失敗: " . $conn->error . "</p>";
}

echo "<p>ℹ️ 請透過「標籤管理」介面自行新增模板標籤</p>";

// 顯示目前資料
$result = $conn->query("SELECT * FROM portal_tags ORDER BY is_template DESC, sort_order ASC");

echo "<h3>目前標籤列表：</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr style='background:#f1f5f9;'><th>ID</th><th>名稱</th><th>顏色</th><th>院區</th><th>模板</th></tr>";

if ($result && $result->num_rows > 0) {
    while ($tag = $result->fetch_assoc()) {
        $colorBox = "<span style='display:inline-block;width:20px;height:20px;background:{$tag['color']};border-radius:4px;'></span>";
        $inst = $tag['institution_code'] ?? '全域';
        $tpl = $tag['is_template'] ? '✓' : '';
        echo "<tr><td>{$tag['id']}</td><td>{$tag['name']}</td><td>{$colorBox}</td><td>{$inst}</td><td>{$tpl}</td></tr>";
    }
} else {
    echo "<tr><td colspan='5' style='text-align:center;color:#94a3b8;'>尚無資料</td></tr>";
}
echo "</table>";

echo "<p style='margin-top:20px;color:#16a34a;'>🎉 Migration 完成！</p>";
echo "<p><a href='/admin/manage_template_tags.php'>👉 前往新增模板標籤</a></p>";
