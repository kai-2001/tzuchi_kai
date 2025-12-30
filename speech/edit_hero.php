<?php
/**
 * Edit Hero Slide Controller
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!is_manager()) {
    die("未授權");
}

$id = (int) $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $speaker_name = $_POST['speaker_name'];
    $event_date = $_POST['event_date'];
    $campus_id = (int) $_POST['campus_id'];
    $link_url = $_POST['link_url'];
    $sort_order = (int) $_POST['sort_order'];
    $is_hero = isset($_POST['is_hero']) ? 1 : 0;
    $image_url = $_POST['current_image'];

    // Handle Image Upload
    if (isset($_FILES['slide_image']) && $_FILES['slide_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/hero/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_ext = pathinfo($_FILES['slide_image']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid('hero_') . '.' . $file_ext;
        $target_path = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['slide_image']['tmp_name'], $target_path)) {
            // Delete old image if exists
            if (!empty($image_url) && file_exists($image_url)) {
                unlink($image_url);
            }
            $image_url = $target_path;
        }
    }

    $stmt = $conn->prepare("UPDATE hero_slides SET title=?, speaker_name=?, event_date=?, campus_id=?, link_url=?, sort_order=?, image_url=?, is_hero=? WHERE id=?");
    $stmt->bind_param("sssisisii", $title, $speaker_name, $event_date, $campus_id, $link_url, $sort_order, $image_url, $is_hero, $id);
    $stmt->execute();

    header("Location: manage_hero.php?msg=updated");
    exit;
}

$slide = $conn->query("SELECT * FROM hero_slides WHERE id = $id")->fetch_assoc();
$campuses = $conn->query("SELECT * FROM campuses")->fetch_all(MYSQLI_ASSOC);

$page_title = '編輯橫幅';
$page_css_files = ['manage.css'];

include 'templates/edit_hero.php';
?>