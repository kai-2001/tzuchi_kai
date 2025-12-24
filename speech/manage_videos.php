<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Access Control
if (!is_manager()) {
    die("未授權：僅管理員可進入此頁面。");
}

$user_id = $_SESSION['user_id'];
$search = isset($_GET['q']) ? $_GET['q'] : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// 1. Get total count for pagination
$count_query = "SELECT COUNT(*) as total 
               FROM videos v
               LEFT JOIN speakers s ON v.speaker_id = s.id
               WHERE v.user_id = ?";
$count_params = [$user_id];
$count_types = "i";

if (!empty($search)) {
    $count_query .= " AND (v.title LIKE ? OR s.name LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_types .= "ss";
}

$stmt_count = $conn->prepare($count_query);
$stmt_count->bind_param($count_types, ...$count_params);
$stmt_count->execute();
$total_items = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $limit);

// 2. Fetch records for current page
$query = "SELECT v.*, s.name as speaker_name, c.name as campus_name 
          FROM videos v
          LEFT JOIN speakers s ON v.speaker_id = s.id
          LEFT JOIN campuses c ON v.campus_id = c.id
          WHERE v.user_id = ?";

$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $query .= " AND (v.title LIKE ? OR s.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " ORDER BY v.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$videos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <title>影片管理 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .actions-wrapper {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-edit {
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .btn-delete {
            color: #ef4444;
            font-size: 1.1rem;
        }

        .video-thumb-small {
            width: 80px;
            height: 45px;
            background-size: cover;
            background-position: center;
            border-radius: 8px;
            display: block;
            border: 1px solid var(--glass-border);
        }
    </style>
</head>

<body>
    <div class="container">
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
                    <input type="text" name="q" placeholder="搜尋影片標題或講者..."
                        value="<?php echo htmlspecialchars($search); ?>">
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
                                            style="background-image: url('<?php echo htmlspecialchars($v['thumbnail_path'] ?: 'assets/images/placeholder.jpg'); ?>')">
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($v['title']); ?></td>
                                    <td><?php echo htmlspecialchars($v['speaker_name']); ?></td>
                                    <td><?php echo htmlspecialchars($v['campus_name']); ?></td>
                                    <td><?php echo htmlspecialchars($v['event_date']); ?></td>
                                    <td>
                                        <div class="actions-wrapper">
                                            <a href="edit_video.php?id=<?php echo $v['id']; ?>" class="btn-edit" title="編輯"><i
                                                    class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="#" onclick="confirmDelete(<?php echo $v['id']; ?>)" class="btn-delete"
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
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&q=' . urlencode($search) : ''; ?>"
                            class="page-link <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&q=' . urlencode($search) : ''; ?>"
                                class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&q=' . urlencode($search) : ''; ?>"
                            class="page-link <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmDelete(id) {
            if (confirm('確定要刪除這部影片嗎？此動作無法復原。')) {
                window.location.href = 'delete_video.php?id=' + id;
            }
        }
    </script>
</body>

</html>