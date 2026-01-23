<?php
/**
 * 院區管理員首頁
 * templates/tabs/hospital_admin_home.php
 */
$institution = isset($_SESSION['institution']) ? $_SESSION['institution'] : '未設定';
$mgmt_cat_id = isset($_SESSION['management_category_id']) ? $_SESSION['management_category_id'] : 0;
?>
<div id="section-home" class="page-section active">
    <div class="hero-section">
        <i class="fas fa-hospital-user hero-icon"></i>
        <h1>院區管理控制台</h1>
        <p>歡迎回來，
            <?php echo h($_SESSION['fullname']); ?>！您正在管理 <strong>
                <?php echo h($institution); ?>
            </strong> 院區。
        </p>

        <div class="hero-buttons">
            <button class="btn-hero btn-hero-primary" onclick="showTab('member-management')">
                <i class="fas fa-users-cog"></i> 成員管理
            </button>
            <button class="btn-hero btn-hero-secondary" onclick="showTab('course-management')">
                <i class="fas fa-chalkboard"></i> 課程管理
            </button>
            <button class="btn-hero btn-hero-info" onclick="showTab('cohort-management')">
                <i class="fas fa-users-class"></i> 群組管理
            </button>
            <button class="btn-hero btn-hero-outline" onclick="showTab('category-management')">
                <i class="fas fa-folder-tree"></i> 類別管理
            </button>
        </div>
    </div>

    <!-- 快速統計卡片 -->
    <div class="stats-grid" style="margin-top: 40px;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-content">
                <span class="stat-value" id="stat-total-members">--</span>
                <span class="stat-label">院區成員</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="stat-content">
                <span class="stat-value" id="stat-teachers">--</span>
                <span class="stat-label">開課教師</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="stat-content">
                <span class="stat-value" id="stat-students">--</span>
                <span class="stat-label">學生</span>
            </div>
        </div>
    </div>
</div>

<script>
    // 載入統計數據
    document.addEventListener('DOMContentLoaded', function () {
        loadHospitalStats();
    });

    function loadHospitalStats() {
        fetch('/api/hospital_admin/get_stats.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('stat-total-members').textContent = data.total || 0;
                    document.getElementById('stat-teachers').textContent = data.teachers || 0;
                    document.getElementById('stat-students').textContent = data.students || 0;
                }
            })
            .catch(err => console.error('Stats load error:', err));
    }
</script>