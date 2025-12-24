<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Access Control
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $conn->prepare("SELECT v.*, s.name as speaker_name, s.affiliation, s.position, c.name as campus_name 
                      FROM videos v
                      LEFT JOIN speakers s ON v.speaker_id = s.id
                      LEFT JOIN campuses c ON v.campus_id = c.id
                      WHERE v.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if (!$video) {
    die("未找到該演講。");
}

$is_html = (pathinfo($video['content_path'], PATHINFO_EXTENSION) === 'html');
?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($video['title']); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .watch-container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .video-player-container {
            background: #000;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--glass-border);
            margin-bottom: 25px;
        }

        .player-wrapper {
            position: relative;
            padding-top: 56.25%;
            background: #000;
        }

        .player-wrapper iframe,
        .player-wrapper video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        .video-details-section {
            background: white;
            padding: 35px;
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            margin-bottom: 40px;
        }

        .campus-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #f1f5f9;
            color: var(--text-secondary);
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 12px;
            border: 1px solid var(--glass-border);
        }

        .video-title-main {
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 25px;
            line-height: 1.3;
        }

        .meta-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
            border-top: 1px solid #f1f5f9;
            padding-top: 25px;
        }

        .meta-info-list li {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .meta-info-list li i {
            width: 20px;
            color: var(--primary-color);
            text-align: center;
        }

        .meta-info-list li strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .watch-header-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn-open-popup {
            padding: 8px 15px;
            background: white;
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-open-popup:hover {
            color: var(--primary-color);
            border-color: var(--primary-color);
            background: #f0f9ff;
        }

        @media (max-width: 768px) {
            .video-details-section {
                padding: 25px;
            }

            .video-title-main {
                font-size: 1.4rem;
            }
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
                    <h2 class="page-title"><?php echo htmlspecialchars($video['title']); ?></h2>
                </div>
                <div class="user-nav">
                    <a href="javascript:history.back()" class="btn-logout">
                        <i class="fa-solid fa-rotate-left"></i> 返回上一頁
                    </a>
                </div>
            </div>
        </header>

        <div class="watch-container">
            <div class="video-player-container">
                <div class="player-wrapper">
                    <?php if ($is_html): ?>
                        <iframe src="<?php echo htmlspecialchars($video['content_path']); ?>"
                            allow="fullscreen; autoplay; encrypted-media; picture-in-picture" allowfullscreen
                            sandbox="allow-forms allow-scripts allow-same-origin" style="background: white;"></iframe>
                    <?php else: ?>
                        <video controls src="<?php echo htmlspecialchars($video['content_path']); ?>"
                            controlsList="nodownload" autoplay></video>
                    <?php endif; ?>
                </div>
            </div>

            <div class="video-details-section">
                <div class="campus-badge">
                    <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($video['campus_name']); ?>
                </div>
                <h2 class="video-title-main"><?php echo htmlspecialchars($video['title']); ?></h2>

                <ul class="meta-info-list">
                    <li>
                        <i class="fa-solid fa-user"></i>
                        <span><strong>主講人：</strong><?php echo htmlspecialchars($video['speaker_name']); ?>
                            <small>(<?php echo htmlspecialchars($video['affiliation']); ?> -
                                <?php echo htmlspecialchars($video['position']); ?>)</small></span>
                    </li>
                    <li>
                        <i class="fa-solid fa-calendar"></i>
                        <span><strong>演講日期：</strong><?php echo htmlspecialchars($video['event_date']); ?></span>
                    </li>
                    <li>
                        <i class="fa-solid fa-file-video"></i>
                        <span><strong>內容格式：</strong><?php echo $is_html ? '互動式 HTML 資料集' : 'MP4 串流影片'; ?></span>
                    </li>
                </ul>

                <?php if ($is_html): ?>
                    <div class="watch-header-actions">
                        <a href="<?php echo htmlspecialchars($video['content_path']); ?>" target="_blank"
                            class="btn-open-popup">
                            <i class="fa-solid fa-up-right-from-square"></i> 在新視窗中開啟
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>