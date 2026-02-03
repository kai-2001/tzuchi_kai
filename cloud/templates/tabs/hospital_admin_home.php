<?php
/**
 * 院區管理員首頁 v2
 * templates/tabs/hospital_admin_home.php
 * 
 * 使用新設計系統，簡潔專業
 */
require_once __DIR__ . '/../../includes/config.php';
$institution = isset($_SESSION['institution']) ? $_SESSION['institution'] : '未設定';
$mgmt_cat_id = isset($_SESSION['management_category_id']) ? $_SESSION['management_category_id'] : 0;
?>
<div id="section-home" class="page-section active">
    <!-- 歡迎區塊 - 簡潔版 -->
    <div class="welcome-header">
        <div class="welcome-header__content">
            <h1 class="welcome-header__title">
                早安，<?php echo h($_SESSION['fullname']); ?>
            </h1>
            <p class="welcome-header__subtitle">
                <?php echo h($institution); ?> 院區管理控制台
            </p>
        </div>
        <div class="welcome-header__date">
            <span id="current-date"></span>
        </div>
    </div>

    <!-- 統計概覽 -->
    <div class="stats-grid-v2">
        <div class="stat-card-v2">
            <div class="stat-card-v2__label">院區成員</div>
            <div class="stat-card-v2__value" id="home-stat-total">--</div>
        </div>
        <div class="stat-card-v2">
            <div class="stat-card-v2__label">開課教師</div>
            <div class="stat-card-v2__value" id="home-stat-teachers">--</div>
        </div>
        <div class="stat-card-v2">
            <div class="stat-card-v2__label">學員人數</div>
            <div class="stat-card-v2__value" id="home-stat-students">--</div>
        </div>
        <div class="stat-card-v2">
            <div class="stat-card-v2__label">課程數</div>
            <div class="stat-card-v2__value" id="home-stat-courses">--</div>
        </div>
    </div>

    <!-- 快捷操作 -->
    <div class="quick-actions">
        <h2 class="quick-actions__title">快捷操作</h2>
        <div class="quick-actions__grid">
            <button class="quick-action-card" onclick="showTab('member-management')">
                <div class="quick-action-card__icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="quick-action-card__content">
                    <span class="quick-action-card__title">成員管理</span>
                    <span class="quick-action-card__desc">新增或編輯院區成員帳號</span>
                </div>
                <i class="fas fa-chevron-right quick-action-card__arrow"></i>
            </button>

            <button class="quick-action-card" onclick="showTab('admin-settings')">
                <div class="quick-action-card__icon quick-action-card__icon--teal">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="quick-action-card__content">
                    <span class="quick-action-card__title">系統設定</span>
                    <span class="quick-action-card__desc">醫院、部門、職稱管理</span>
                </div>
                <i class="fas fa-chevron-right quick-action-card__arrow"></i>
            </button>
        </div>
    </div>
</div>

<style>
    /* 歡迎區塊 */
    .welcome-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: var(--space-6);
        padding-bottom: var(--space-5);
        border-bottom: 1px solid var(--border-default);
    }

    .welcome-header__title {
        font-size: 28px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }

    .welcome-header__subtitle {
        font-size: 15px;
        color: var(--text-secondary);
        margin: 0;
    }

    .welcome-header__date {
        font-size: 14px;
        color: var(--text-muted);
        background: var(--bg-muted);
        padding: 8px 16px;
        border-radius: var(--radius-md);
    }

    /* 快捷操作 */
    .quick-actions {
        margin-top: var(--space-8);
    }

    .quick-actions__title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 var(--space-4) 0;
    }

    .quick-actions__grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: var(--space-4);
    }

    .quick-action-card {
        display: flex;
        align-items: center;
        gap: var(--space-4);
        padding: var(--space-5);
        background: var(--bg-surface);
        border: 1px solid var(--border-default);
        border-radius: var(--radius-lg);
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: left;
        width: 100%;
    }

    .quick-action-card:hover {
        border-color: var(--brand-primary);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        transform: translateY(-2px);
    }

    .quick-action-card__icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        flex-shrink: 0;
    }

    .quick-action-card__icon--teal {
        background: linear-gradient(135deg, #0d9488 0%, #06b6d4 100%);
    }

    .quick-action-card__icon--purple {
        background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);
    }

    .quick-action-card__icon--gray {
        background: linear-gradient(135deg, #475569 0%, #94a3b8 100%);
    }

    .quick-action-card__content {
        flex: 1;
        min-width: 0;
    }

    .quick-action-card__title {
        display: block;
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 2px;
    }

    .quick-action-card__desc {
        display: block;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .quick-action-card__arrow {
        color: var(--text-muted);
        font-size: 14px;
        transition: transform 0.2s;
    }

    .quick-action-card:hover .quick-action-card__arrow {
        transform: translateX(4px);
        color: var(--brand-primary);
    }

    /* RWD */
    @media (max-width: 768px) {
        .welcome-header {
            flex-direction: column;
            gap: var(--space-3);
        }

        .welcome-header__title {
            font-size: 24px;
        }

        .quick-actions__grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 顯示日期
        const dateEl = document.getElementById('current-date');
        if (dateEl) {
            const now = new Date();
            const options = { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' };
            dateEl.textContent = now.toLocaleDateString('zh-TW', options);
        }

        // 載入統計
        loadHomeStats();
    });

    function loadHomeStats() {
        fetch('<?= BASE_URL ?>/api/hospital_admin/get_stats.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('home-stat-total').textContent = data.total || 0;
                    document.getElementById('home-stat-teachers').textContent = data.teachers || 0;
                    document.getElementById('home-stat-students').textContent = data.students || 0;
                    document.getElementById('home-stat-courses').textContent = data.courses || 0;
                }
            })
            .catch(err => console.error('Stats error:', err));
    }
</script>