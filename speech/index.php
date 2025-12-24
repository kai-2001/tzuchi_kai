<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Pagination settings
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Filter settings
$campus_id = isset($_GET['campus']) ? (int) $_GET['campus'] : 0;
$search = isset($_GET['q']) ? $_GET['q'] : '';

// Build Query
$query = "SELECT v.*, s.name as speaker_name, s.affiliation, c.name as campus_name 
          FROM videos v
          LEFT JOIN speakers s ON v.speaker_id = s.id
          LEFT JOIN campuses c ON v.campus_id = c.id
          WHERE 1=1";
$params = [];
$types = "";

if ($campus_id > 0) {
    $query .= " AND v.campus_id = ?";
    $params[] = $campus_id;
    $types .= "i";
}

if (!empty($search)) {
    $query .= " AND (v.title LIKE ? OR s.name LIKE ? OR s.affiliation LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// Get Total Count for Pagination
$count_query = str_replace("SELECT v.*, s.name as speaker_name, s.affiliation, c.name as campus_name", "SELECT COUNT(*)", $query);
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$total_items = $result->fetch_row()[0];
$total_pages = ceil($total_items / ITEMS_PER_PAGE);

// Get Results
$query .= " ORDER BY v.created_at DESC LIMIT ? OFFSET ?";
$params[] = ITEMS_PER_PAGE;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$videos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get Campuses for tabs
$campuses = $conn->query("SELECT * FROM campuses")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=Inter:wght@300;400;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="container">
        <header>
            <div class="header-top">
                <div class="header-left">
                    <a href="index.php" class="logo">
                        <h1 class="logo-text">學術演講影片平台</h1>
                    </a>
                </div>
                <div class="user-nav">
                    <?php if (is_logged_in()): ?>
                        <?php if (is_manager()): ?>
                            <a href="manage_videos.php" class="btn-admin"><i class="fa-solid fa-list-check"></i> 影片管理</a>
                            <a href="upload.php" class="btn-admin"><i class="fa-solid fa-cloud-arrow-up"></i> 上傳專區</a>
                        <?php endif; ?>

                        <div class="user-dropdown">
                            <div class="user-info" style="cursor: pointer; margin-right: 0;">
                                <i class="fa-solid fa-circle-user"></i>
                                <span><?php echo htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']); ?></span>
                                <i class="fa-solid fa-chevron-down"
                                    style="font-size: 0.7rem; margin-left: 5px; opacity:0.5;"></i>
                            </div>
                            <div class="dropdown-content">
                                <a href="logout.php" class="dropdown-item text-danger">
                                    <i class="fa-solid fa-right-from-bracket"></i> 登出系統
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn-admin"><i class="fa-solid fa-user-lock"></i> 會員登入</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="search-box">
                <form action="index.php" method="GET">
                    <input type="text" name="q" placeholder="搜尋標題、講者或單位..."
                        value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <?php if ($campus_id > 0): ?><input type="hidden" name="campus"
                            value="<?php echo $campus_id; ?>"><?php endif; ?>
                </form>
            </div>
        </header>

        <nav class="tabs">
            <a href="index.php?q=<?php echo urlencode($search); ?>"
                class="tab <?php echo $campus_id == 0 ? 'active' : ''; ?>">ALL</a>
            <?php foreach ($campuses as $c): ?>
                <a href="index.php?campus=<?php echo $c['id']; ?>&q=<?php echo urlencode($search); ?>"
                    class="tab <?php echo $campus_id == $c['id'] ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($c['name']); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <main class="video-grid">
            <?php if (empty($videos)): ?>
                <div
                    style="grid-column: 1/-1; text-align: center; padding: 50px; background: var(--glass-bg); border-radius: 20px;">
                    <i class="fa-solid fa-magnifying-glass"
                        style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 20px; display: block;"></i>
                    <p style="font-size: 1.2rem; margin-bottom: 10px;">沒有找到符合的影片。</p>
                    <p style="color: var(--text-secondary);">建議您調整搜尋關鍵字，或 <a href="index.php"
                            style="color: var(--primary-color); text-decoration: none;">瀏覽全部影片</a>。</p>
                </div>
            <?php else: ?>
                <?php foreach ($videos as $v): ?>
                    <div class="video-card" onclick="checkAuth(<?php echo $v['id']; ?>)">
                        <div class="thumbnail"
                            style="background-image: url('<?php echo htmlspecialchars($v['thumbnail_path'] ?: 'assets/images/placeholder.jpg'); ?>')">
                        </div>
                        <div class="video-info">
                            <div class="video-title"><?php echo htmlspecialchars($v['title']); ?></div>
                            <div class="meta">
                                <span><i class="fa-solid fa-user"></i>
                                    <?php echo htmlspecialchars($v['speaker_name']); ?></span>
                                <span><i class="fa-solid fa-building"></i>
                                    <?php echo htmlspecialchars($v['affiliation']); ?></span>
                                <span><i class="fa-solid fa-calendar"></i>
                                    <?php echo htmlspecialchars($v['event_date']); ?></span>
                                <span><i class="fa-solid fa-eye"></i>
                                    <?php echo number_format($v['views']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <?php if ($total_items > 0): // Show pagination if there are items ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&campus=<?php echo $campus_id; ?>&q=<?php echo urlencode($search); ?>"
                        class="page-link <?php echo $page == $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Login Prompt Modal -->
    <div id="loginModal" class="modal-overlay">
        <div class="modal">
            <i class="fa-solid fa-lock" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 20px;"></i>
            <h2>需要登入</h2>
            <p>請先登入後再觀看影片內容。</p>
            <a href="login.php" class="btn-login">前往登入</a>
            <p style="margin-top: 15px; cursor: pointer; color: var(--text-secondary);" onclick="closeModal()">關閉</p>
        </div>
    </div>

    <script>
        const isLoggedIn = <?php echo is_logged_in() ? 'true' : 'false'; ?>;

        function checkAuth(videoId) {
            if (isLoggedIn) {
                window.location.href = 'watch.php?id=' + videoId;
            } else {
                document.getElementById('loginModal').style.display = 'flex';
            }
        }

        function closeModal() {
            document.getElementById('loginModal').style.display = 'none';
        }
    </script>
</body>

</html>