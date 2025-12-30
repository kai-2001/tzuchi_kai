<!-- templates/dashboard.php - 現代化儀表板 (模組化版本) -->
<?php $is_teacherplus = isset($_SESSION['is_teacherplus']) ? $_SESSION['is_teacherplus'] : false; ?>
<div class="main-content">

    <?php if ($is_admin): ?>
        <?php include 'tabs/admin_console.php'; ?>
    <?php elseif ($is_teacherplus): ?>
        <!-- 開課教師介面 -->
        <?php include 'tabs/teacher_home.php'; ?>
        <?php include 'tabs/teacher_management.php'; ?>
    <?php else: ?>
        <!-- 學生介面 -->
        <?php include 'tabs/student_home.php'; ?>

        <!-- 功能頁面區塊 -->
        <div id="section-features" class="page-section">
            <div class="tab-content">
                <?php include 'tabs/student_history.php'; ?>
                <?php include 'tabs/student_my_courses.php'; ?>
                <?php include 'tabs/student_explore.php'; ?>
                <?php include 'tabs/student_curriculum.php'; ?>
            </div>
        </div>
    <?php endif; ?>
</div>