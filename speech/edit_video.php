<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Access Control
if (!is_manager()) {
    die("未授權。");
}

$video_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

// Ownership Check & Load Data
$stmt = $conn->prepare("SELECT v.*, s.name as speaker_name, s.affiliation, s.position 
                       FROM videos v 
                       LEFT JOIN speakers s ON v.speaker_id = s.id 
                       WHERE v.id = ? AND v.user_id = ?");
$stmt->bind_param("ii", $video_id, $user_id);
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if (!$video) {
    die("找不到影片或權限不足。");
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        $title = $_POST['title'];
        $campus_id = $_POST['campus_id'];
        $event_date = $_POST['event_date'];
        $speaker_name = $_POST['speaker_name'];
        $affiliation = $_POST['affiliation'];
        $position = $_POST['position'];

        // Update Speaker (or create new)
        $stmt = $conn->prepare("SELECT id FROM speakers WHERE name = ? AND affiliation = ?");
        $stmt->bind_param("ss", $speaker_name, $affiliation);
        $stmt->execute();
        $speaker_result = $stmt->get_result();
        $new_speaker_id = $speaker_result ? ($speaker_result->fetch_row()[0] ?? null) : null;

        if (!$new_speaker_id) {
            $stmt = $conn->prepare("INSERT INTO speakers (name, affiliation, position) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $speaker_name, $affiliation, $position);
            $stmt->execute();
            $new_speaker_id = $conn->insert_id;
        } else {
            // Update existing speaker position
            $stmt = $conn->prepare("UPDATE speakers SET position = ? WHERE id = ?");
            $stmt->bind_param("si", $position, $new_speaker_id);
            $stmt->execute();
        }

        // Handle Thumbnail Update
        $thumb_path = $video['thumbnail_path'];
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            // Delete old one
            if (file_exists(__DIR__ . '/' . $thumb_path))
                unlink(__DIR__ . '/' . $thumb_path);

            $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('thumb_') . '.' . $ext;
            move_uploaded_file($_FILES['thumbnail']['tmp_name'], UPLOAD_DIR_THUMBS . $filename);
            $thumb_path = 'uploads/thumbnails/' . $filename;
        }

        // Handle Video/Content Update
        $content_path = $video['content_path'];
        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
            // Delete old content (simple file only for now)
            // ... existing logic from upload.php can be reused ...
            // For brevity, using the same logic as upload.php
            $ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
            $temp_name = $_FILES['video_file']['tmp_name'];
            $file_id = uniqid('content_');

            if ($ext === 'mp4') {
                $filename = $file_id . '.mp4';
                move_uploaded_file($temp_name, UPLOAD_DIR_VIDEOS . $filename);
                $content_path = 'uploads/videos/' . $filename;
            } elseif ($ext === 'zip') {
                $zip = new ZipArchive;
                if ($zip->open($temp_name) === TRUE) {
                    $extract_dir = UPLOAD_DIR_VIDEOS . $file_id . '/';
                    mkdir($extract_dir, 0777, true);
                    $zip->extractTo($extract_dir);
                    $zip->close();
                    $content_path = 'uploads/videos/' . $file_id . '/index.html';
                }
            }
        }

        // Update Video Record
        $stmt = $conn->prepare("UPDATE videos SET title = ?, thumbnail_path = ?, content_path = ?, event_date = ?, campus_id = ?, speaker_id = ? WHERE id = ?");
        $stmt->bind_param("ssssiii", $title, $thumb_path, $content_path, $event_date, $campus_id, $new_speaker_id, $video_id);
        $stmt->execute();

        $conn->commit();
        header("Location: manage_videos.php?msg=updated");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

$campuses = $conn->query("SELECT * FROM campuses")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <title>編輯影片 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .full-width {
            grid-column: span 2;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
        }

        .btn-submit {
            padding: 15px 40px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            background: #006b75;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 132, 145, 0.2);
        }

        .preview-thumb {
            width: 150px;
            height: 85px;
            background-size: cover;
            background-position: center;
            border-radius: 12px;
            margin-bottom: 15px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        /* RWD */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: span 1;
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
                    <h2 class="page-title">編輯影片資料</h2>
                </div>
                <div class="user-nav">
                    <a href="manage_videos.php" class="btn-logout"><i class="fa-solid fa-rotate-left"></i> 返回列表</a>
                </div>
            </div>
        </header>

        <div class="upload-form">
            <?php if ($error): ?>
                <div style="color: #f87171; margin-bottom: 20px;"><?php echo $error; ?></div><?php endif; ?>

            <form action="edit_video.php?id=<?php echo $video_id; ?>" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>演講標題</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($video['title']); ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label>所屬院區</label>
                        <select name="campus_id" required>
                            <?php foreach ($campuses as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $video['campus_id']) ? 'selected' : ''; ?>><?php echo $c['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>演講日期</label>
                        <input type="date" name="event_date" value="<?php echo $video['event_date']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>講者姓名</label>
                        <input type="text" name="speaker_name"
                            value="<?php echo htmlspecialchars($video['speaker_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>服務單位</label>
                        <input type="text" name="affiliation"
                            value="<?php echo htmlspecialchars($video['affiliation']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>職務</label>
                        <input type="text" name="position" value="<?php echo htmlspecialchars($video['position']); ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label>目前縮圖</label>
                        <div class="preview-thumb"
                            style="background-image: url('<?php echo htmlspecialchars($video['thumbnail_path'] ?: 'assets/images/placeholder.jpg'); ?>')">
                        </div>
                        <label>更新縮圖 (留空則保持不變)</label>
                        <input type="file" name="thumbnail" accept="image/*">
                    </div>

                    <div class="form-group full-width">
                        <label>更新影片或 Zip 檔 (留空則保持不變)</label>
                        <input type="file" name="video_file" accept=".mp4,.zip">
                    </div>
                </div>
                <button type="submit" class="btn-submit">儲存修改</button>
            </form>
        </div>
    </div>
</body>

</html>