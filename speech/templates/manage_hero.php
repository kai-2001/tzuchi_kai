<?php
/**
 * Manage Hero Slides Template
 */
include __DIR__ . '/partials/header.php';
?>

<header class="static-header">
    <div class="header-container">
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text" style="color: var(--primary-dark);">學術演講影片平台</h1>
            </a>
            <span class="breadcrumb-separator" style="color: #ccc;">/</span>
            <h2 class="page-title" style="color: var(--text-primary); font-size: 1.2rem; font-weight: 500; margin: 0;">
                公告與橫幅管理</h2>
        </div>
        <div class="user-nav">
            <a href="manage_upcoming.php" class="btn-admin"><i class="fa-solid fa-bullhorn"></i> 預告管理</a>
            <a href="index.php" class="btn-admin"><i class="fa-solid fa-house"></i> 返回首頁</a>
        </div>
    </div>
</header>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form">
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'deleted'): ?>
                <div class="alert alert-success">橫幅已成功刪除。</div>
            <?php elseif ($_GET['msg'] === 'updated'): ?>
                <div class="alert alert-success">狀態已更新。</div>
            <?php elseif ($_GET['msg'] === 'added'): ?>
                <div class="alert alert-success">橫幅已成功新增。</div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="search-bar">
            <form action="manage_hero.php" method="GET" style="display:flex; width:100%; gap:10px;">
                <input type="text" name="q" placeholder="搜尋公告標題或講者..." value="<?= htmlspecialchars($search ?? '') ?>">
                <button type="submit" class="btn-admin" style="width: auto; padding: 0 20px;"><i
                        class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <a href="add_hero.php" class="btn-admin btn-primary-gradient"
                style="white-space: nowrap; width: auto; padding: 0 25px; text-decoration: none; display: flex; align-items: center; border-radius: 12px;">
                <i class="fa-solid fa-plus me-2"></i> 新增橫幅公告
            </a>
        </div>

        <?php if (empty($slides)): ?>
            <div style="text-align: center; padding: 40px;">
                <i class="fa-solid fa-image" style="font-size: 3rem; opacity: 0.2; margin-bottom: 20px;"></i>
                <p>目前還沒有相關公告。</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="glass-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">排序</th>
                            <th>標題</th>
                            <th>講者</th>
                            <th>日期</th>
                            <th>分院</th>
                            <th style="width: 120px; text-align: center;">橫幅顯示</th>
                            <th style="width: 100px; text-align: center;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slides as $s): ?>
                            <tr>
                                <td><?= $s['sort_order'] ?></td>
                                <td><?= htmlspecialchars($s['title']) ?></td>
                                <td><?= htmlspecialchars($s['speaker_name']) ?></td>
                                <td><?= htmlspecialchars($s['event_date']) ?></td>
                                <td><?= htmlspecialchars($s['campus_name'] ?? '全部') ?></td>
                                <td style="text-align: center;">
                                    <a href="manage_hero.php?action=toggle_hero&id=<?= $s['id'] ?>"
                                        class="btn-status <?= $s['is_hero'] ? 'active' : '' ?>" style="<?= $s['is_hero'] ? 'background: #0ea5e9; color: white;' : 'background: #e2e8f0; color: #94a3b8;' ?> 
                                               white-space: nowrap; padding: 6px 15px; border-radius: 20px; display: inline-block; font-size: 0.9rem;
                                               text-decoration: none; transition: all 0.3s ease;" title="點擊切換首頁橫幅顯示">
                                        <?= $s['is_hero'] ? '顯示中' : '隱藏' ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="actions-wrapper" style="justify-content: center;">
                                        <a href="edit_hero.php?id=<?= $s['id'] ?>" class="btn-edit" title="編輯"><i
                                                class="fa-solid fa-pen-to-square"></i></a>
                                        <a href="#" onclick="confirmDelete(<?= $s['id'] ?>)" class="btn-delete" title="刪除"><i
                                                class="fa-solid fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function confirmDelete(id) {
        if (confirm('確定要刪除這個公告嗎？')) {
            window.location.href = 'manage_hero.php?action=delete&id=' + id;
        }
    }
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>