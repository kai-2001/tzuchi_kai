<?php
/**
 * 院區管理員首頁 (v2)
 * templates/tabs/hospital_admin_home.php
 */
$institution = isset($_SESSION['institution']) ? $_SESSION['institution'] : '未設定';

// ── 直接查 DB 取統計 ──
$stat_total = $stat_teachers = $stat_students = $stat_courses = 0;
try {
    global $db_host, $db_user, $db_pass, $db_name;
    $pconn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$pconn->connect_error) {
        $pconn->set_charset('utf8mb4');
        $inst = $pconn->real_escape_string($institution);
        $r = $pconn->query("SELECT COUNT(*) as c FROM users WHERE institution = '{$inst}'");
        if ($r)
            $stat_total = (int) $r->fetch_assoc()['c'];
        $r = $pconn->query("SELECT COUNT(*) as c FROM users WHERE institution = '{$inst}' AND role IN ('teacher','coursecreator')");
        if ($r)
            $stat_teachers = (int) $r->fetch_assoc()['c'];
        $r = $pconn->query("SELECT COUNT(*) as c FROM users WHERE institution = '{$inst}' AND role = 'student'");
        if ($r)
            $stat_students = (int) $r->fetch_assoc()['c'];
        $pconn->close();
    }
} catch (Exception $e) {
}
try {
    global $db_host, $db_user, $db_pass;
    $mconn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
    if (!$mconn->connect_error) {
        $mconn->set_charset('utf8mb4');
        $mgmt_cat = isset($_SESSION['management_category_id']) ? (int) $_SESSION['management_category_id'] : 0;
        if ($mgmt_cat > 0) {
            $r = $mconn->query("SELECT COUNT(*) as c FROM mdl_course WHERE category IN (SELECT id FROM mdl_course_categories WHERE path LIKE '%/{$mgmt_cat}/%' OR id = {$mgmt_cat}) AND id > 1");
            if ($r)
                $stat_courses = (int) $r->fetch_assoc()['c'];
        }
        $mconn->close();
    }
} catch (Exception $e) {
}
?>

<div class="page-header-v2" style="margin-bottom: var(--space-4);">
    <h1 class="page-header-v2__title">院區管理控制台</h1>
    <p class="page-header-v2__subtitle"><?php echo h($institution); ?> 院區</p>
</div>

<!-- 統計卡片 — 原始置中版（縮小） -->
<div class="ha-stats-grid">
    <div class="ha-stat-card">
        <div class="ha-stat-card__icon" style="background: rgba(37,99,235,0.1); color: var(--brand-primary);">
            <i class="fas fa-users"></i>
        </div>
        <div class="ha-stat-card__value"><?php echo number_format($stat_total); ?></div>
        <div class="ha-stat-card__label">院區成員</div>
    </div>
    <div class="ha-stat-card">
        <div class="ha-stat-card__icon" style="background: rgba(34,197,94,0.1); color: var(--success);">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="ha-stat-card__value"><?php echo number_format($stat_teachers); ?></div>
        <div class="ha-stat-card__label">開課教師</div>
    </div>
    <div class="ha-stat-card">
        <div class="ha-stat-card__icon" style="background: rgba(6,182,212,0.1); color: var(--brand-accent);">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="ha-stat-card__value"><?php echo number_format($stat_students); ?></div>
        <div class="ha-stat-card__label">學生</div>
    </div>
    <div class="ha-stat-card">
        <div class="ha-stat-card__icon" style="background: rgba(139,92,246,0.1); color: #8b5cf6;">
            <i class="fas fa-book"></i>
        </div>
        <div class="ha-stat-card__value"><?php echo number_format($stat_courses); ?></div>
        <div class="ha-stat-card__label">課程數</div>
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
            <a href="<?php echo $web_root; ?>/index.php?page=users" class="ha-quick-card">
                <div class="ha-quick-card__icon"
                    style="background: rgba(37, 99, 235, 0.1); color: var(--brand-primary);">
                    <i class="fas fa-users-cog"></i>
                </div>
                <div class="ha-quick-card__title">成員管理</div>
                <div class="ha-quick-card__desc">管理院區成員與角色</div>
            </a>
            <a href="<?php echo $web_root; ?>/index.php?page=management" class="ha-quick-card">
                <div class="ha-quick-card__icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                    <i class="fas fa-chalkboard"></i>
                </div>
                <div class="ha-quick-card__title">課程管理</div>
                <div class="ha-quick-card__desc">建立與管理課程</div>
            </a>
            <a href="<?php echo $web_root; ?>/index.php?page=cohorts" class="ha-quick-card">
                <div class="ha-quick-card__icon"
                    style="background: rgba(6, 182, 212, 0.1); color: var(--brand-accent);">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="ha-quick-card__title">群組管理</div>
                <div class="ha-quick-card__desc">管理學習群組</div>
            </a>
            <a href="<?php echo $web_root; ?>/index.php?page=tags" class="ha-quick-card">
                <div class="ha-quick-card__icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="ha-quick-card__title">標籤管理</div>
                <div class="ha-quick-card__desc">管理課程標籤</div>
            </a>
        </div>
    </div>
</div>