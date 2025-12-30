<?php
/**
 * Edit Upcoming Lecture Page Controller
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!is_manager()) {
    die("未授權。");
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM upcoming_lectures WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$lecture = $stmt->get_result()->fetch_assoc();

if (!$lecture) {
    die("找不到資料。");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = $_POST['title'];
        $speaker = $_POST['speaker_name'];
        $affiliation = $_POST['affiliation'];
        $date = $_POST['event_date'];
        $location = $_POST['location'];
        $campus_id = $_POST['campus_id'];
        $desc = $_POST['description'];

        $stmt = $conn->prepare("UPDATE upcoming_lectures SET title=?, speaker_name=?, affiliation=?, event_date=?, location=?, campus_id=?, description=? WHERE id=?");
        $stmt->bind_param("sssssisi", $title, $speaker, $affiliation, $date, $location, $campus_id, $desc, $id);

        if ($stmt->execute()) {
            header("Location: manage_upcoming.php?msg=updated");
            exit;
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        $error = "錯誤：" . $e->getMessage();
    }
}

$campuses = $conn->query("SELECT * FROM campuses")->fetch_all(MYSQLI_ASSOC);

$page_title = '編輯演講預告';
$page_css_files = ['forms.css'];

include 'templates/edit_upcoming.php';
