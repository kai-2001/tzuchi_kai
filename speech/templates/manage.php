<?php
/**
 * Manage Videos Page Template
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
                影片管理中心</h2>
        </div>
        <div class="user-nav">
            <a href="index.php" class="btn-admin"><i class="fa-solid fa-house"></i> <span>返回首頁</span></a>
        </div>
    </div>
</header>

<div class="container" style="padding-top: 120px; margin-bottom: 60px;">
    <div class="upload-form">
        <div class="search-bar">
            <form action="manage_videos.php" method="GET" style="display:flex; width:100%; gap:10px;">
                <input type="text" name="q" placeholder="搜尋影片標題或講者..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-admin"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>

            <a href="process_queue.php" class="btn-queue" title="轉檔排程管理">
                <i class="fa-solid fa-list-check"></i> <span>轉檔排程</span>
            </a>

            <a href="upload.php" class="btn-admin btn-primary-gradient"
                style="white-space: nowrap; width: auto; padding: 0 25px; text-decoration: none; display: flex; align-items: center; border-radius: 12px;">
                <i class="fa-solid fa-plus me-2"></i> 新增影片
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
                            <th>狀態</th> <!-- Added column -->
                            <th>觀看次數</th>
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
                                <td>
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
                                <td><?= htmlspecialchars($v['campus_name']) ?></td> <!-- Restored Campus Name column -->
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
</div>

<script>
    function confirmDelete(id) {
        if (confirm('確定要刪除這部影片嗎？此操作無法復原。')) {
            window.location.href = 'delete_video.php?id=' + id;
        }
    }
</script>

<script>
    // Auto-refresh logic: If there are any "Pending" or "Processing" badges, refresh every 10 seconds
    (function () {
        const activeBadges = document.querySelectorAll('.badge.bg-warning, .badge.bg-info');
        if (activeBadges.length > 0) {
            console.log("Active jobs detected, starting auto-refresh interval...");
            setInterval(() => {
                // If the user isn't currently typing in the search box, refresh
                if (document.activeElement.tagName !== 'INPUT') {
                    window.location.reload();
                } else {
                    console.log("User is typing, skipping refresh this cycle.");
                }
            }, 10000); // Check every 10 seconds
        }
    })();

</script>

<?php include __DIR__ . '/partials/footer.php'; ?>