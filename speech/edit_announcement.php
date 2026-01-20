<?php
/**
 * Edit Announcement Controller
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!is_manager()) {
    die("未授權");
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    die("Invalid ID");
}

// Fetch existing data
$stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$announcement = $stmt->get_result()->fetch_assoc();

if (!$announcement) {
    die("公告不存在");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $speaker_name = $_POST['speaker_name'] ?? '';
    // $event_date = $_POST['event_date'] ?: null; // Handle empty date carefully
    $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
    $campus_id = (int) $_POST['campus_id'];
    $link_url = $_POST['link_url'] ?? '';
    $is_hero = (int) ($_POST['is_hero'] ?? 0);
    $hero_start_date = !empty($_POST['hero_start_date']) ? $_POST['hero_start_date'] : null;
    $hero_end_date = !empty($_POST['hero_end_date']) ? $_POST['hero_end_date'] : null;
    $sort_order = (int) ($_POST['sort_order'] ?? 0);
    $location = $_POST['location'] ?? '';
    $affiliation = $_POST['affiliation'] ?? '';
    $description = $_POST['description'] ?? '';
    $current_image = $_POST['current_image'] ?? '';

    // Image Upload
    $image_url = $current_image; // Default keep old
    if (isset($_FILES['slide_image']) && $_FILES['slide_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/hero/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $ext = pathinfo($_FILES['slide_image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('hero_') . '.' . $ext;
        $target_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['slide_image']['tmp_name'], $target_path)) {
            $image_url = $target_path;
        }
    }

    if (empty($title)) {
        $error = '標題為必填欄位';
    } else {
        $sql = "UPDATE announcements SET 
                title=?, speaker_name=?, event_date=?, campus_id=?, link_url=?, 
                image_url=?, is_hero=?, hero_start_date=?, hero_end_date=?, sort_order=?, location=?, affiliation=?, description=? 
                WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisssssssssi", $title, $speaker_name, $event_date, $campus_id, $link_url, $image_url, $is_hero, $hero_start_date, $hero_end_date, $sort_order, $location, $affiliation, $description, $id);

        if ($stmt->execute()) {
            header("Location: manage_announcements.php?msg=updated");
            exit;
        } else {
            $error = '更新失敗：' . $conn->error;
        }
    }
}

// Fetch campuses
$campuses = $conn->query("SELECT * FROM campuses ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$page_title = '編輯公告';
$page_css_files = ['manage.css'];
include 'templates/edit_announcement.php';
?>