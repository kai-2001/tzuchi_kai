<?php
/**
 * Add New Announcement
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/Validator.php';

if (!is_manager() && !is_campus_admin()) {
    die("未授權");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Backend validation using Validator class
    $validation = Validator::validate($_POST, 'add_announcement');
    if (!$validation['valid']) {
        $error = Validator::getFirstError($validation['errors']);
    } else {
        $title = $_POST['title'];
        $speaker_name = $_POST['speaker_name'] ?? '';
        $affiliation = $_POST['affiliation'] ?? '';
        $position = $_POST['position'] ?? '';
        $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
        $campus_id = (int) $_POST['campus_id'];
        $link_url = $_POST['link_url'] ?? '';
        $location = $_POST['location'] ?? '';
        $description = $_POST['description'] ?? '';
        $is_hero = (int) ($_POST['is_hero'] ?? 0);
        $hero_start_date = !empty($_POST['hero_start_date']) ? $_POST['hero_start_date'] : null;
        $hero_end_date = !empty($_POST['hero_end_date']) ? $_POST['hero_end_date'] : null;
        $sort_order = (int) ($_POST['sort_order'] ?? 0);

        // Image Upload
        $image_url = '';
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


        $stmt = $conn->prepare("INSERT INTO announcements (title, speaker_name, event_date, campus_id, link_url, image_url, is_hero, hero_start_date, hero_end_date, sort_order, location, affiliation, position, description, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssississsssss", $title, $speaker_name, $event_date, $campus_id, $link_url, $image_url, $is_hero, $hero_start_date, $hero_end_date, $sort_order, $location, $affiliation, $position, $description);

        if ($stmt->execute()) {
            header("Location: manage_announcements.php?msg=added");
            exit;
        } else {
            $error = '新增失敗：' . $conn->error;
        }
    }
}

// Fetch campuses
$campuses = $conn->query("SELECT * FROM campuses ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$page_title = '新增公告';
$page_css_files = ['forms.css', 'manage.css'];
include 'templates/add_announcement.php';
?>