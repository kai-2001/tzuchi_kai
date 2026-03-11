<?php
/**
 * 將現有 Cohort 遷移到標籤系統
 * migrations/cohort_to_tags.php
 * 
 * 使用方式：在瀏覽器訪問此頁面，勾選要轉換的 cohort，然後執行
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cohort_ids'])) {
    $cohortIds = $_POST['cohort_ids'];
    $imported = 0;
    $skipped = 0;
    
    foreach ($cohortIds as $cohortId) {
        // 取得 cohort 資訊
        $stmt = $db->prepare("SELECT name, description FROM moodle.mdl_cohort WHERE id = ?");
        $stmt->bind_param('i', $cohortId);
        $stmt->execute();
        $cohort = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$cohort) continue;
        
        // 檢查是否已存在
        $checkStmt = $db->prepare("SELECT id FROM portal_tags WHERE name = ? AND (institution_code = ? OR institution_code IS NULL)");
        $checkStmt->bind_param('ss', $cohort['name'], $institution);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $skipped++;
            $checkStmt->close();
            continue;
        }
        $checkStmt->close();
        
        // 根據名稱決定顏色
        $color = '#3b82f6'; // 預設藍色
        if (strpos($cohort['name'], 'PGY') !== false) {
            $color = '#8b5cf6'; // 紫色 for PGY
        } elseif (strpos($cohort['name'], '2025') !== false || strpos($cohort['name'], '2026') !== false) {
            $color = '#10b981'; // 綠色 for 年份
        }
        
        // 插入標籤
        $insertStmt = $db->prepare("INSERT INTO portal_tags (name, color, institution_code, is_template) VALUES (?, ?, ?, 0)");
        $insertStmt->bind_param('sss', $cohort['name'], $color, $institution);
        $insertStmt->execute();
        $insertStmt->close();
        $imported++;
    }
    
    $message = "成功匯入 {$imported} 個標籤，跳過 {$skipped} 個已存在的";
    $success = true;
}

// 取得所有 cohort
$cohorts = [];
$sql = "SELECT c.id, c.name, c.description, c.idnumber,
        (SELECT COUNT(*) FROM moodle.mdl_cohort_members cm WHERE cm.cohortid = c.id) as member_count
        FROM moodle.mdl_cohort c 
        ORDER BY c.name";
$result = $db->query($sql);
while ($row = $result->fetch_assoc()) {
    $cohorts[] = $row;
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
    <title>Cohort 轉標籤</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; padding: 40px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 16px; padding: 32px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        h1 { color: #1e293b; margin-bottom: 8px; }
        .subtitle { color: #64748b; margin-bottom: 24px; }
        .message { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .message.info { background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; }
        .cohort-list { display: flex; flex-direction: column; gap: 8px; max-height: 400px; overflow-y: auto; }
        .cohort-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; }
        .cohort-item:hover { background: #f1f5f9; }
        .cohort-item.exists { opacity: 0.5; }
        .cohort-item input { width: 20px; height: 20px; cursor: pointer; }
        .cohort-name { font-weight: 500; color: #334155; flex: 1; }
        .cohort-count { font-size: 0.85rem; color: #64748b; }
        .exists-badge { font-size: 0.75rem; background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 10px; }
        .actions { margin-top: 24px; display: flex; gap: 12px; }
        .btn { padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .select-actions { display: flex; gap: 8px; margin-bottom: 16px; }
        .select-actions button { font-size: 0.85rem; padding: 6px 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏷️ Cohort 轉標籤工具</h1>
        <p class="subtitle">選擇要轉換為標籤的 Cohort，轉換後可在標籤管理中使用</p>
        
        <?php if ($message): ?>
        <div class="message <?= $success ? 'success' : 'info' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="select-actions">
                <button type="button" class="btn btn-secondary" onclick="selectAll()">全選</button>
                <button type="button" class="btn btn-secondary" onclick="selectNone()">取消全選</button>
                <button type="button" class="btn btn-secondary" onclick="selectNew()">只選未存在的</button>
            </div>
            
            <div class="cohort-list">
                <?php foreach ($cohorts as $c): ?>
                <?php $exists = in_array($c['name'], $existingTags); ?>
                <label class="cohort-item <?= $exists ? 'exists' : '' ?>">
                    <input type="checkbox" name="cohort_ids[]" value="<?= $c['id'] ?>" <?= $exists ? 'disabled' : '' ?>>
                    <span class="cohort-name"><?= htmlspecialchars($c['name']) ?></span>
                    <span class="cohort-count"><?= $c['member_count'] ?> 人</span>
                    <?php if ($exists): ?>
                    <span class="exists-badge">已存在</span>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
            
            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-file-import"></i> 轉換為標籤
                </button>
                <a href="/hospital_admin" class="btn btn-secondary">返回管理後台</a>
            </div>
        </form>
    </div>
    
    <script>
    function selectAll() {
        document.querySelectorAll('.cohort-item input:not([disabled])').forEach(cb => cb.checked = true);
    }
    function selectNone() {
        document.querySelectorAll('.cohort-item input').forEach(cb => cb.checked = false);
    }
    function selectNew() {
        document.querySelectorAll('.cohort-item input').forEach(cb => {
            cb.checked = !cb.disabled;
        });
    }
    </script>
</body>
</html>
