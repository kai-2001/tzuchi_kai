<?php
/**
 * Manage Videos Page Template
 */
include __DIR__ . '/partials/header.php';
?>

<?php
$navbar_mode = 'simple';
$page_title = '影片管理';
$nav_actions = [
    ['label' => '公告管理', 'url' => 'manage_announcements.php', 'icon' => 'fa-solid fa-bullhorn'],
    ['label' => '返回首頁', 'url' => 'index.php', 'icon' => 'fa-solid fa-house']
];
include __DIR__ . '/partials/navbar.php';
?>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form">
        <div class="search-bar">
            <form action="manage_videos.php" method="GET">
                <input type="text" name="q" placeholder="搜尋影片標題或講者..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-admin"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>

            <a href="process_queue.php" class="btn-queue" title="轉檔排程管理">
                <i class="fa-solid fa-list-check"></i> <span>轉檔排程管理</span>
            </a>

            <a href="upload.php" class="btn-add-video">
                <i class="fa-solid fa-plus"></i> 新增影片
            </a>
        </div>

        <?php if (!empty($_GET['msg'])): ?>
            <div
                style="background: rgba(74, 222, 128, 0.1); border: 1px solid #4ade80; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center;">
                <i class="fa-solid fa-circle-check" style="margin-right: 10px; font-size: 1.2rem;"></i>
                <span>
                    <?php
                    if ($_GET['msg'] === 'updated')
                        echo "影片更新成功！";
                    elseif ($_GET['msg'] === 'deleted')
                        echo "影片已刪除。";
                    elseif ($_GET['msg'] === 'uploaded')
                        echo "新影片上傳成功！";
                    else
                        echo htmlspecialchars($_GET['msg']);
                    ?>
                </span>
            </div>
        <?php endif; ?>

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
                            <th>主講人</th>
                            <th class="d-none d-lg-table-cell">狀態</th>
                            <th class="d-none d-xl-table-cell">院區</th>
                            <th class="d-none d-md-table-cell">日期</th>
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
                                <td class="d-none d-lg-table-cell">
                                    <?php
                                    $status = $v['status'] ?? 'ready';
                                    $badgeClass = 'bg-secondary';
                                    $statusText = '未知';

                                    switch ($status) {
                                        case 'pending':
                                            $badgeClass = 'bg-warning text-dark';
                                            $statusText = '排隊中';
                                            break;
                                        case 'waiting':
                                            $badgeClass = 'bg-secondary';
                                            $statusText = '待處理';
                                            break;
                                        case 'processing':
                                            $badgeClass = 'bg-info text-dark';
                                            $statusText = '轉檔中';
                                            break;
                                        case 'ready':
                                            $badgeClass = 'bg-success';
                                            $statusText = '已完成';
                                            break;
                                        case 'error':
                                            $badgeClass = 'bg-danger';
                                            $statusText = '錯誤';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span>

                                    <?php if ($status == 'error' && !empty($v['process_msg'])): ?>
                                        <i class="fa-solid fa-circle-exclamation text-danger ms-1"
                                            title="<?= htmlspecialchars($v['process_msg']) ?>"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-xl-table-cell"><?= htmlspecialchars($v['campus_name']) ?></td>
                                <td class="d-none d-md-table-cell"><?= htmlspecialchars($v['event_date']) ?></td>
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
</div>


<?php include __DIR__ . '/partials/footer.php'; ?>