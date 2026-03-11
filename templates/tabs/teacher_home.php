<?php
/**
 * 開課教師首頁
 * templates/tabs/teacher_home.php
 * 
 * 仿照 hospital_admin_home.php 的格式
 */
$institution = isset($_SESSION['institution']) ? $_SESSION['institution'] : '未設定';
$teacher_cat_ids = $_SESSION['coursecreator_category_ids'] ?? [];

// ── 統計：教師管理的課程數 ──
$stat_courses = 0;
$stat_students = 0;
try {
    global $db_host, $db_user, $db_pass;
    if (!empty($teacher_cat_ids)) {
        $mconn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
        if (!$mconn->connect_error) {
            $mconn->set_charset('utf8mb4');
            // 取得教師類別及子類別的所有課程數
            $cat_ids_str = implode(',', array_map('intval', $teacher_cat_ids));
            // 先展開子類別
            $expanded_cats = $cat_ids_str;
            $r = $mconn->query("SELECT id FROM mdl_course_categories WHERE parent IN ({$cat_ids_str})");
            if ($r) {
                while ($row = $r->fetch_assoc()) {
                    $expanded_cats .= ',' . (int)$row['id'];
                }
            }
            // 課程數
            $r = $mconn->query("SELECT COUNT(*) as c FROM mdl_course WHERE category IN ({$expanded_cats}) AND id > 1");
            if ($r) $stat_courses = (int) $r->fetch_assoc()['c'];
            // 學生數（去重）
            $r = $mconn->query("SELECT COUNT(DISTINCT ue.userid) as c FROM mdl_user_enrolments ue JOIN mdl_enrol e ON ue.enrolid = e.id WHERE e.courseid IN (SELECT id FROM mdl_course WHERE category IN ({$expanded_cats}) AND id > 1)");
            if ($r) $stat_students = (int) $r->fetch_assoc()['c'];
            $mconn->close();
        }
    }
} catch (Exception $e) {
}
?>

<div class="page-header-v2" style="margin-bottom: var(--space-4);">
    <h1 class="page-header-v2__title">教師控制台</h1>
    <p class="page-header-v2__subtitle"><?php echo h($_SESSION['fullname']); ?> 老師 · <?php echo h($institution); ?></p>
</div>

<!-- 統計卡片 -->
<div class="ha-stats-grid">
    <div class="ha-stat-card">
        <div class="ha-stat-card__icon" style="background: rgba(139,92,246,0.1); color: #8b5cf6;">
            <i class="fas fa-book"></i>
        </div>
        <div class="ha-stat-card__value"><?php echo number_format($stat_courses); ?></div>
        <div class="ha-stat-card__label">管理課程</div>
    </div>
    <div class="ha-stat-card">
        <div class="ha-stat-card__icon" style="background: rgba(6,182,212,0.1); color: var(--brand-accent);">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="ha-stat-card__value"><?php echo number_format($stat_students); ?></div>
        <div class="ha-stat-card__label">學生人數</div>
    </div>
    <div class="ha-stat-card">
        <div class="ha-stat-card__icon" style="background: rgba(34,197,94,0.1); color: var(--success);">
            <i class="fas fa-folder-tree"></i>
        </div>
        <div class="ha-stat-card__value"><?php echo count($teacher_cat_ids); ?></div>
        <div class="ha-stat-card__label">管理類別</div>
    </div>
</div>

<!-- 快速入口 -->
<div class="card-v2">
    <div class="card-v2__header" style="padding: var(--space-3) var(--space-5);">
        <h2 class="card-v2__title" style="font-size: 15px;">
            <i class="fas fa-th-large"></i>
            快速入口
        </h2>
    </div>
    <div class="card-v2__body" style="padding: var(--space-3) var(--space-5) var(--space-4);">
        <div class="ha-quick-grid">
            <a href="<?php echo $web_root; ?>/index.php?page=management" class="ha-quick-card">
                <div class="ha-quick-card__icon" style="background: rgba(37, 99, 235, 0.1); color: var(--brand-primary);">
                    <i class="fas fa-chalkboard"></i>
                </div>
                <div class="ha-quick-card__title">課程管理</div>
                <div class="ha-quick-card__desc">管理您負責的課程</div>
            </a>
            <a href="<?php echo $web_root; ?>/index.php?page=teacher_course_create" class="ha-quick-card">
                <div class="ha-quick-card__icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="ha-quick-card__title">新增課程</div>
                <div class="ha-quick-card__desc">建立新的課程</div>
            </a>
        </div>
    </div>
</div>