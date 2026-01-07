<?php
/**
 * Manage Upcoming Lectures Template
 * 
 * Variables:
 * - $lectures (array)
 * - $search (string)
 * - $page (int)
 * - $total_pages (int)
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
                近期預告管理</h2>
        </div>
        <div class="user-nav">
            <a href="manage_hero.php" class="btn-admin"><i class="fa-solid fa-image"></i> 橫幅管理</a>
            <a href="manage_videos.php" class="btn-admin"><i class="fa-solid fa-video"></i> 影片管理</a>
            <a href="index.php" class="btn-admin"><i class="fa-solid fa-house"></i> 返回首頁</a>
        </div>
    </div>
</header>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form">
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'deleted'): ?>
                <div class="alert alert-success">預告已成功刪除。</div>
            <?php elseif ($_GET['msg'] === 'updated'): ?>
                <div class="alert alert-success">預告已成功更新。</div>
            <?php elseif ($_GET['msg'] === 'added'): ?>
                <div class="alert alert-success">預告已成功新增。</div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="search-bar">
            <form action="manage_upcoming.php" method="GET" style="display:flex; width:100%; gap:10px;">
                <input type="text" name="q" placeholder="搜尋預告標題或講者..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-admin" style="width: auto; padding: 0 20px;"><i
                        class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <a href="add_upcoming.php" class="btn-admin btn-primary-gradient"
                style="white-space: nowrap; width: auto; padding: 0 25px; text-decoration: none; display: flex; align-items: center; border-radius: 12px;">
                <i class="fa-solid fa-plus me-2"></i> 新增預告
            </a>
        </div>

        <?php if (empty($lectures)): ?>
            <div style="text-align: center; padding: 40px;">
                <i class="fa-solid fa-folder-open" style="font-size: 3rem; opacity: 0.2; margin-bottom: 20px;"></i>
                <p>目前還沒有任何預告資訊。</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="glass-table">
                    <thead>
                        <tr>
                            <th style="width: 120px;">日期</th>
                            <th>標題</th>
                            <th>講者</th>
                            <th>院區</th>
                            <th>地點</th>
                            <th style="width: 100px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lectures as $l): ?>
                            <tr>
                                <td><?= htmlspecialchars($l['event_date']) ?></td>
                                <td><?= htmlspecialchars($l['title']) ?></td>
                                <td><?= htmlspecialchars($l['speaker_name']) ?></td>
                                <td><?= htmlspecialchars($l['campus_name']) ?></td>
                                <td><?= htmlspecialchars($l['location']) ?></td>
                                <td>
                                    <div class="actions-wrapper">
                                        <a href="edit_upcoming.php?id=<?= $l['id'] ?>" class="btn-edit" title="編輯"><i
                                                class="fa-solid fa-pen-to-square"></i></a>
                                        <a href="#" onclick="confirmDeleteUpcoming(<?= $l['id'] ?>)" class="btn-delete"
                                            title="刪除"><i class="fa-solid fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&q=' . urlencode($search) : '' ?>"
                        class="page-link <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?><?= !empty($search) ? '&q=' . urlencode($search) : '' ?>"
                            class="page-link <?= ($i == $page) ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&q=' . urlencode($search) : '' ?>"
                        class="page-link <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    function confirmDeleteUpcoming(id) {
        if (confirm('確定要刪除這筆預告嗎？')) {
            window.location.href = 'manage_upcoming.php?action=delete&id=' + id;
        }
    }
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>