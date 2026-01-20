<?php
/**
 * Manage Announcements Template
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
                公告管理</h2>
        </div>
        <div class="user-nav">
            <a href="manage_videos.php" class="btn-admin"><i class="fa-solid fa-video"></i> 影片管理</a>
            <a href="index.php" class="btn-admin"><i class="fa-solid fa-house"></i> 返回首頁</a>
        </div>
    </div>
</header>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form">
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'deleted'): ?>
                <div class="alert alert-success">公告已成功刪除。</div>
            <?php elseif ($_GET['msg'] === 'updated'): ?>
                <div class="alert alert-success">狀態已更新。</div>
            <?php elseif ($_GET['msg'] === 'added'): ?>
                <div class="alert alert-success">公告已成功新增。</div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="search-bar">
            <form action="manage_announcements.php" method="GET" style="display:flex; width:100%; gap:10px;">
                <input type="text" name="q" placeholder="搜尋標題、講者或地點..." value="<?= htmlspecialchars($search ?? '') ?>">
                <button type="submit" class="btn-admin" style="width: auto; padding: 0 20px;"><i
                        class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <a href="batch_upload_announcements.php" class="btn-admin" style="white-space: nowrap; width: auto; padding: 0 20px; text-decoration: none; display: flex; align-items: center; border-radius: 12px; margin-right: 10px; background: white; color: var(--primary-color); border: 1px solid var(--primary-color);">
                <i class="fa-solid fa-file-csv me-2"></i> 批次上傳
            </a>
            <a href="add_announcement.php" class="btn-admin btn-primary-gradient"
                style="white-space: nowrap; width: auto; padding: 0 25px; text-decoration: none; display: flex; align-items: center; border-radius: 12px;">
                <i class="fa-solid fa-plus me-2"></i> 新增公告
            </a>
        </div>

        <?php if (empty($announcements)): ?>
            <div style="text-align: center; padding: 40px;">
                <i class="fa-solid fa-bullhorn" style="font-size: 3rem; opacity: 0.2; margin-bottom: 20px;"></i>
                <p>目前還沒有任何公告。</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="glass-table">
                    <thead>
                        <tr>
                            <th style="width: 110px;">日期</th>
                            <th>標題</th>
                            <th>講者</th>
                            <th style="width: 90px;">分院</th>
                            <th style="width: 120px; text-align: center;">橫幅顯示</th>
                            <th style="width: 80px; text-align: center;">排序</th>
                            <th style="width: 100px; text-align: center;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($announcements as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['event_date']) ?></td>
                                <td>
                                    <?= htmlspecialchars($a['title']) ?>
                                    <?php if ($a['image_url']): ?>
                                        <i class="fa-regular fa-image" style="color: #64748b; margin-left: 5px;" title="包含圖片"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($a['speaker_name']) ?></td>
                                <td><?= htmlspecialchars($a['campus_name'] ?? '全部') ?></td>
                                <td style="text-align: center;">
                                    <a href="manage_announcements.php?action=toggle_hero&id=<?= $a['id'] ?>"
                                        class="btn-status <?= $a['is_hero'] ? 'active' : '' ?>" style="<?= $a['is_hero'] ? 'background: #0ea5e9; color: white;' : 'background: #e2e8f0; color: #94a3b8;' ?> 
                                               white-space: nowrap; padding: 6px 15px; border-radius: 20px; display: inline-block; font-size: 0.9rem;
                                               text-decoration: none; transition: all 0.3s ease;" title="點擊切換首頁橫幅顯示">
                                        <?= $a['is_hero'] ? '顯示中' : '隱藏' ?>
                                    </a>
                                </td>
                                <td style="text-align: center;">
                                    <input type="number" value="<?= $a['sort_order'] ?>"
                                        onchange="updateSortOrder(<?= $a['id'] ?>, this.value)"
                                                <?= $a['is_hero'] ? '' : 'disabled' ?>
                                        style="width: 60px; text-align: center; padding: 4px; border: 1px solid #cbd5e1; border-radius: 6px; <?= $a['is_hero'] ? '' : 'opacity: 0.5; cursor: not-allowed; background: #f1f5f9;' ?>">
                                </td>
                                <td>
                                    <div class="actions-wrapper" style="justify-content: center;">
                                        <a href="edit_announcement.php?id=<?= $a['id'] ?>" class="btn-edit" title="編輯"><i
                                                class="fa-solid fa-pen-to-square"></i></a>
                                        <a href="#" onclick="confirmDeleteAnnouncement(<?= $a['id'] ?>)" class="btn-delete"
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
    function confirmDeleteAnnouncement(id) {
        if (confirm('確定要刪除這個公告嗎？')) {
            window.location.href = 'manage_announcements.php?action=delete&id=' + id;
        }
    }

    function updateSortOrder(id, newOrder) {
        fetch('manage_announcements.php?action=update_order&id=' + id + '&order=' + newOrder)
            .then(response => {
                if (response.ok) {
                    // Optional: Show a small toast or just console log
                    console.log('Order updated');
                } else {
                    alert('排序更新失敗');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('排序更新發生錯誤');
            });
    }
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>