<?php
/**
 * Manage Videos Page Template
 * 
 * Variables available from controller:
 * - $videos (array) - List of videos
 * - $search (string) - Current search term
 * - $page (int) - Current page
 * - $total_pages (int) - Total pages
 */
include __DIR__ . '/partials/header.php';
?>

<header>
    <div class="header-top">
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text">學術演講影片平台</h1>
            </a>
            <span class="breadcrumb-separator">/</span>
            <h2 class="page-title">影片管理中心</h2>
        </div>
        <div class="user-nav">
            <a href="index.php" class="btn-logout"><i class="fa-solid fa-house"></i> 返回首頁</a>
        </div>
    </div>
</header>

<div class="upload-form" style="margin-top: 20px;">
    <div class="search-bar">
        <form action="manage_videos.php" method="GET" style="display:flex; width:100%; gap:10px;">
            <input type="text" name="q" placeholder="搜尋影片標題或講者..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn-admin" style="width: auto; padding: 0 20px;"><i
                    class="fa-solid fa-magnifying-glass"></i></button>
        </form>
        <a href="upload.php" class="btn-admin btn-primary-gradient"
            style="white-space: nowrap; width: auto; padding: 0 25px; text-decoration: none; display: flex; align-items: center; border-radius: 12px;">
            <i class="fa-solid fa-plus me-2"></i> 新增影片
        </a>
    </div>

    <?php if (empty($videos)): ?>
        <div style="text-align: center; padding: 40px;">
            <i class="fa-solid fa-folder-open" style="font-size: 3rem; opacity: 0.2; margin-bottom: 20px;"></i>
            <p>目前還沒有任何影片。</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>縮圖</th>
                        <th>標題</th>
                        <th>講者</th>
                        <th>院區</th>
                        <th>日期</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($videos as $v): ?>
                        <tr>
                            <td>
                                <div class="video-thumb-small"
                                    style="background-image: url('<?= htmlspecialchars($v['thumbnail_path'] ?: 'assets/images/placeholder.jpg') ?>')">
                                </div>
                            </td>
                            <td><?= htmlspecialchars($v['title']) ?></td>
                            <td><?= htmlspecialchars($v['speaker_name']) ?></td>
                            <td><?= htmlspecialchars($v['campus_name']) ?></td>
                            <td><?= htmlspecialchars($v['event_date']) ?></td>
                            <td>
                                <div class="actions-wrapper">
                                    <a href="edit_video.php?id=<?= $v['id'] ?>" class="btn-edit" title="編輯"><i
                                            class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="#" onclick="confirmDelete(<?= $v['id'] ?>)" class="btn-delete" title="刪除"><i
                                            class="fa-solid fa-trash"></i></a>
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

<?php include __DIR__ . '/partials/footer.php'; ?>