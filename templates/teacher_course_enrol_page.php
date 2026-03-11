<?php
/**
 * Portal èª²ç??›ç??é¢ - ?…è??é¢
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_api.php';

// ç¢ºè??»å…¥
if (!isset($_SESSION['user_id'])) {
    header("Location: $web_root/index.php?page=login");
    exit;
}

// ?–å?èª²ç? ID
$course_id = $_GET['course_id'] ?? 0;
$batch_ids = $_GET['batch_ids'] ?? ($_GET['course_ids'] ?? '');
if (!$course_id && !$batch_ids) {
    echo '<script>alert("ç¼ºå?èª²ç? ID"); history.back();</script>';
    exit;
}
?>
<div class="page-container section-animate-fade-in">
    <?php include __DIR__ . '/hospital_admin_course_enrol.php'; ?>
</div>
