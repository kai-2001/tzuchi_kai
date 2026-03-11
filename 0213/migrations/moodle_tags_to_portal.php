<?php
/**
 * 將 Moodle 標籤遷移到 Portal 標籤系統
 * migrations/moodle_tags_to_portal.php
 * 
 * 使用方式：在瀏覽器訪問此頁面，勾選要轉換的 Moodle 標籤，然後執行
 */
require_once __DIR__ . '/../core/bootstrap.php';

// 需要登入
if (!isset($_SESSION['user_id'])) {
    die('請先登入');
}

$db = Database::getInstance();
$institution = $_SESSION['institution'] ?? '';
$message = '';
$success = false;

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tag_ids'])) {
    $tagIds = $_POST['tag_ids'];
    $imported = 0;
    $skipped = 0;
    
    foreach ($tagIds as $tagId) {
        // 取得 Moodle tag 資訊
        $stmt = $db->prepare("SELECT rawname, name, description FROM moodle.mdl_tag WHERE id = ?");
        $stmt->bind_param('i', $tagId);
        $stmt->execute();
        $tag = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$tag) continue;
        
        $tagName = $tag['rawname'] ?: $tag['name'];
        
        // 檢查是否已存在
        $checkStmt = $db->prepare("SELECT id FROM portal_tags WHERE name = ? AND (institution_code = ? OR institution_code IS NULL)");
        $checkStmt->bind_param('ss', $tagName, $institution);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $skipped++;
            $checkStmt->close();
            continue;
        }
        $checkStmt->close();
        
        // 根據名稱決定顏色
        $color = '#3b82f6'; // 預設藍色
        if (stripos($tagName, 'PGY') !== false) {
            $color = '#8b5cf6'; // 紫色 for PGY
        } elseif (preg_match('/20\d{2}/', $tagName)) {
            $color = '#10b981'; // 綠色 for 年份
        } elseif (stripos($tagName, '新進') !== false) {
            $color = '#f59e0b'; // 橘色 for 新進
        }
        
        // 插入標籤
        $insertStmt = $db->prepare("INSERT INTO portal_tags (name, color, institution_code, is_template) VALUES (?, ?, ?, 0)");
        $insertStmt->bind_param('sss', $tagName, $color, $institution);
        $insertStmt->execute();
        $insertStmt->close();
        $imported++;
    }
    
    $message = "成功匯入 {$imported} 個標籤，跳過 {$skipped} 個已存在的";
    $success = true;
}

// 取得所有 Moodle 標籤
$moodleTags = [];
$sql = "SELECT t.id, t.rawname, t.name, t.description, t.flag,
        (SELECT COUNT(*) FROM moodle.mdl_tag_instance ti WHERE ti.tagid = t.id) as usage_count
        FROM moodle.mdl_tag t 
        WHERE t.isstandard = 1 OR t.flag = 0
        ORDER BY t.rawname";
$result = $db->query($sql);
while ($row = $result->fetch_assoc()) {
    $moodleTags[] = $row;
}

// 取得已存在的標籤名稱
$existingTags = [];
$tagResult = $db->query("SELECT name FROM portal_tags");
while ($row = $tagResult->fetch_assoc()) {
    $existingTags[] = $row['name'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Moodle 標籤轉 Portal 標籤</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; padding: 40px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 16px; padding: 32px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        h1 { color: #1e293b; margin-bottom: 8px; }
        .subtitle { color: #64748b; margin-bottom: 24px; }
        .message { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .message.info { background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; }
        .tag-list { display: flex; flex-direction: column; gap: 8px; max-height: 400px; overflow-y: auto; }
        .tag-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; }
        .tag-item:hover { background: #f1f5f9; }
        .tag-item.exists { opacity: 0.5; }
        .tag-item input { width: 20px; height: 20px; cursor: pointer; }
        .tag-name { font-weight: 500; color: #334155; flex: 1; }
        .tag-count { font-size: 0.85rem; color: #64748b; }
        .exists-badge { font-size: 0.75rem; background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 10px; }
        .actions { margin-top: 24px; display: flex; gap: 12px; }
        .btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-block; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .select-actions { display: flex; gap: 8px; margin-bottom: 16px; }
        .select-actions button { font-size: 0.85rem; padding: 6px 12px; }
        .empty { text-align: center; padding: 40px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏷️ Moodle 標籤轉 Portal 標籤</h1>
        <p class="subtitle">選擇要轉換的 Moodle 標籤（mdl_tag），將它們加入 Portal 標籤系統</p>
        
        <?php if ($message): ?>
        <div class="message <?= $success ? 'success' : 'info' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <?php if (empty($moodleTags)): ?>
        <div class="empty">
            <p>找不到 Moodle 標籤</p>
        </div>
        <?php else: ?>
        <form method="post">
            <div class="select-actions">
                <button type="button" class="btn btn-secondary" onclick="selectAll()">全選</button>
                <button type="button" class="btn btn-secondary" onclick="selectNone()">取消全選</button>
                <button type="button" class="btn btn-secondary" onclick="selectNew()">只選未存在的</button>
            </div>
            
            <div class="tag-list">
                <?php foreach ($moodleTags as $t): ?>
                <?php 
                $tagName = $t['rawname'] ?: $t['name'];
                $exists = in_array($tagName, $existingTags); 
                ?>
                <label class="tag-item <?= $exists ? 'exists' : '' ?>">
                    <input type="checkbox" name="tag_ids[]" value="<?= $t['id'] ?>" <?= $exists ? 'disabled' : '' ?>>
                    <span class="tag-name"><?= htmlspecialchars($tagName) ?></span>
                    <span class="tag-count"><?= $t['usage_count'] ?> 次使用</span>
                    <?php if ($exists): ?>
                    <span class="exists-badge">已存在</span>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
            
            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    轉換為 Portal 標籤
                </button>
                <a href="/hospital_admin" class="btn btn-secondary">返回管理後台</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
    
    <script>
    function selectAll() {
        document.querySelectorAll('.tag-item input:not([disabled])').forEach(cb => cb.checked = true);
    }
    function selectNone() {
        document.querySelectorAll('.tag-item input').forEach(cb => cb.checked = false);
    }
    function selectNew() {
        document.querySelectorAll('.tag-item input').forEach(cb => {
            cb.checked = !cb.disabled;
        });
    }
    </script>
</body>
</html>
