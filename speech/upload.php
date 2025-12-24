<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Access Control
if (!is_manager()) {
    die("未授權：僅管理員可進入此頁面。");
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Detect if post_max_size was exceeded (resulting in empty $_POST)
        if (empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
            $maxPostSize = ini_get('post_max_size');
            throw new Exception("上傳失敗：檔案總大小超過了伺服器限制 ($maxPostSize)。請縮小檔案或聯絡管理員調整 php.ini。");
        }

        $conn->begin_transaction();

        $title = $_POST['title'];
        $campus_id = $_POST['campus_id'];
        $event_date = $_POST['event_date'];

        // Speaker handling
        $speaker_name = $_POST['speaker_name'];
        $affiliation = $_POST['affiliation'];
        $position = $_POST['position'];

        $stmt = $conn->prepare("SELECT id FROM speakers WHERE name = ? AND affiliation = ?");
        $stmt->bind_param("ss", $speaker_name, $affiliation);
        $stmt->execute();
        $result = $stmt->get_result();
        $speaker_id = $result ? ($result->fetch_row()[0] ?? null) : null;

        if (!$speaker_id) {
            $stmt = $conn->prepare("INSERT INTO speakers (name, affiliation, position) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $speaker_name, $affiliation, $position);
            $stmt->execute();
            $speaker_id = $conn->insert_id;
        }

        // Handle Thumbnail
        $thumb_path = '';
        if (isset($_FILES['thumbnail'])) {
            if ($_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('thumb_') . '.' . $ext;
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], UPLOAD_DIR_THUMBS . $filename);
                $thumb_path = 'uploads/thumbnails/' . $filename;
            } elseif ($_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
                throw new Exception("縮圖上傳錯誤，錯誤代碼：" . $_FILES['thumbnail']['error']);
            }
        }

        // Handle Content (MP4 or ZIP)
        $content_path = '';
        if (isset($_FILES['video_file'])) {
            if ($_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
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

                        // Allowed extensions whitelist
                        $allowed_inner_exts = ['html', 'htm', 'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'mp4', 'json'];
                        $index_found = false;
                        $total_uncompressed_size = 0;
                        $max_uncompressed_size = 512 * 1024 * 1024; // 512MB

                        // 1. Pre-check total size to prevent decompression bombs
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $stat = $zip->statIndex($i);
                            $total_uncompressed_size += $stat['size'];
                        }

                        if ($total_uncompressed_size > $max_uncompressed_size) {
                            $zip->close();
                            deleteDir($extract_dir);
                            throw new Exception("上傳失敗：ZIP 內容解壓縮後預計佔用 " . round($total_uncompressed_size / 1024 / 1024, 2) . "MB，超過系統限制 (100MB)。");
                        }

                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);

                            // 2. Path Traversal Protection (Zip Slip)
                            if (strpos($filename, '..') !== false || strpos($filename, '/') === 0 || strpos($filename, '\\') === 0) {
                                continue; // Skip dangerous paths
                            }

                            // 3. Extension Whitelisting
                            $inner_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            // Skip directories and disallowed extensions
                            if (empty($inner_ext) || !in_array($inner_ext, $allowed_inner_exts)) {
                                if (substr($filename, -1) !== '/') { // If not a directory
                                    continue;
                                }
                            }

                            // 4. Extract safe files individually
                            $zip->extractTo($extract_dir, $filename);

                            if (basename($filename) === 'index.html') {
                                $index_found = true;
                            }
                        }
                        $zip->close();

                        if ($index_found) {
                            $content_path = 'uploads/videos/' . $file_id . '/index.html';
                        } else {
                            // Cleanup on failure
                            deleteDir($extract_dir);
                            throw new Exception("ZIP 檔案中未找到 index.html 檔案，或所有檔案皆因安全限制被攔截。");
                        }
                    } else {
                        throw new Exception("無法開啟 ZIP 檔案。");
                    }
                } else {
                    throw new Exception("不支援的檔案格式，僅限 MP4 或 ZIP。");
                }
            } else {
                $errorCode = $_FILES['video_file']['error'];
                $errorMsg = "影片上傳失敗。";
                if ($errorCode === UPLOAD_ERR_INI_SIZE)
                    $errorMsg = "影片大小超過伺服器 php.ini 的限制 (upload_max_filesize)。";
                if ($errorCode === UPLOAD_ERR_PARTIAL)
                    $errorMsg = "影片僅部分上傳完成。";
                throw new Exception($errorMsg . " (代碼: $errorCode)");
            }
        }

        // Save Video with ownership
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO videos (title, content_path, thumbnail_path, event_date, campus_id, speaker_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiii", $title, $content_path, $thumb_path, $event_date, $campus_id, $speaker_id, $user_id);
        $stmt->execute();

        $conn->commit();
        $msg = "演講上傳成功！";
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

$campuses = $conn->query("SELECT * FROM campuses")->fetch_all(MYSQLI_ASSOC);

function deleteDir($dirPath)
{
    if (!is_dir($dirPath))
        return;
    $files = array_diff(scandir($dirPath), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dirPath/$file")) ? deleteDir("$dirPath/$file") : unlink("$dirPath/$file");
    }
    return rmdir($dirPath);
}
?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <title>上傳演講 - <?php echo APP_NAME; ?></title>
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
                    <h2 class="page-title">上傳新演講</h2>
                </div>
                <div class="user-nav">
                    <a href="index.php" class="btn-logout"><i class="fa-solid fa-house"></i> 返回首頁</a>
                </div>
            </div>
        </header>

        <div class="upload-form">
            <?php if ($msg): ?>
                <div style="color: #4ade80; margin-bottom: 20px;"><?php echo $msg; ?></div><?php endif; ?>
            <?php if ($error): ?>
                <div style="color: #f87171; margin-bottom: 20px;"><?php echo $error; ?></div><?php endif; ?>

            <form action="upload.php" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>演講標題</label>
                        <input type="text" name="title" required>
                    </div>

                    <div class="form-group">
                        <label>所屬院區</label>
                        <select name="campus_id" required>
                            <?php foreach ($campuses as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>演講日期</label>
                        <input type="date" name="event_date" required>
                    </div>

                    <div class="form-group">
                        <label>講者姓名</label>
                        <input type="text" name="speaker_name" required>
                    </div>

                    <div class="form-group">
                        <label>服務單位</label>
                        <input type="text" name="affiliation" required>
                    </div>

                    <div class="form-group">
                        <label>職務 (如醫師、護理師)</label>
                        <input type="text" name="position" required>
                    </div>

                    <div class="form-group">
                        <label>上傳縮圖 (JPG/PNG)</label>
                        <input type="file" name="thumbnail" accept="image/*" required>
                    </div>

                    <div class="form-group full-width">
                        <label>上傳影片或 Zip 檔 (包含 index.html)</label>
                        <input type="file" name="video_file" accept=".mp4,.zip" required>
                    </div>
                </div>
                <button type="submit" class="btn-submit">開始上傳</button>
            </form>
        </div>
    </div>
</body>

</html>