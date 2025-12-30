<?php
/**
 * Add Upcoming Lecture Page Controller
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!is_manager()) {
    die("未授權。");
}

$msg = '';
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

        $stmt = $conn->prepare("INSERT INTO upcoming_lectures (title, speaker_name, affiliation, event_date, location, campus_id, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssis", $title, $speaker, $affiliation, $date, $location, $campus_id, $desc);

        if ($stmt->execute()) {
            header("Location: manage_upcoming.php?msg=added");
            exit;
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        $error = "錯誤：" . $e->getMessage();
    }
}

$campuses = $conn->query("SELECT * FROM campuses")->fetch_all(MYSQLI_ASSOC);

$page_title = '新增演講預告';
$page_css_files = ['forms.css'];

include 'templates/add_upcoming.php';
