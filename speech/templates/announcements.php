<?php include 'templates/partials/header.php'; ?>

<header class="static-header">
    <div class="header-container">
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text" style="color: var(--primary-dark);">學術演講影片平台</h1>
            </a>
            <span class="breadcrumb-separator" style="color: #ccc;">/</span>
            <h2 class="page-title" style="color: var(--text-primary); font-size: 1.2rem; font-weight: 500; margin: 0;">近期演講預告</h2>
        </div>
        <div class="user-nav">
            <a href="index.php" class="btn-admin"><i class="fa-solid fa-house"></i> 返回首頁</a>
        </div>
    </div>
</header>

<div class="container" style="padding-top: 120px; padding-bottom: 60px; min-height: 80vh;">
    
    <?php if (empty($grouped_announcements)): ?>
        <div class="no-results" style="text-align: center; padding: 100px 0; background: white; border-radius: 20px; box-shadow: var(--shadow-sm);">
            <i class="fa-regular fa-calendar-xmark" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 20px;"></i>
            <p style="font-size: 1.2rem; color: var(--text-secondary); font-weight: 500;">目前沒有近期的演講預告。</p>
        </div>
    <?php else: ?>
        
        <?php foreach ($grouped_announcements as $campus => $months): ?>
            <div class="campus-section" style="margin-bottom: 60px;">
                <h2 style="font-size: 1.8rem; font-weight: 700; color: var(--primary-dark); margin-bottom: 30px; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0; display: flex; align-items: center;">
                    <i class="fa-solid fa-hospital" style="margin-right: 15px; opacity: 0.8;"></i>
                    <?= htmlspecialchars($campus) ?>
                </h2>

                <?php foreach ($months as $month => $items): ?>
                    <div class="month-group" style="margin-bottom: 40px;">
                        <h3 style="font-size: 1.3rem; color: var(--text-secondary); margin-bottom: 20px; padding-left: 15px; border-left: 4px solid var(--accent-color);">
                            <?= htmlspecialchars($month) ?>
                        </h3>

                        <div class="lectures-list" style="display: grid; gap: 20px;">
                            <?php foreach ($items as $item): ?>
                                <div class="lecture-card-row" 
                                     style="background: white; padding: 25px; border-radius: 16px; box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 30px; transition: transform 0.2s; border: 1px solid #f1f5f9;">
                                    
                                    <!-- Date Box -->
                                    <div class="date-box" style="flex-shrink: 0; width: 80px; text-align: center; background: #f8fafc; padding: 10px; border-radius: 12px; border: 1px solid #e2e8f0;">
                                        <div style="font-size: 0.9rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">
                                            <?= date('M', strtotime($item['event_date'])) ?>
                                        </div>
                                        <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary-dark); line-height: 1.2;">
                                            <?= date('d', strtotime($item['event_date'])) ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #94a3b8;">
                                            <?= date('D', strtotime($item['event_date'])) ?>
                                        </div>
                                    </div>

                                    <!-- Content -->
                                    <div class="content-box" style="flex-grow: 1;">
                                        <h4 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 8px; color: var(--text-primary);">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </h4>
                                        <div style="display: flex; flex-wrap: wrap; gap: 15px; color: var(--text-secondary); font-size: 0.95rem;">
                                            <?php if ($item['speaker_name']): ?>
                                                <span style="display: flex; align-items: center;">
                                                    <i class="fa-solid fa-user-tie" style="color: var(--primary-color); margin-right: 6px;"></i>
                                                    <?= htmlspecialchars($item['speaker_name']) ?> 
                                                    <?= $item['affiliation'] ? '<span style="opacity:0.7; font-size:0.9em; margin-left:5px;">(' . htmlspecialchars($item['affiliation']) . ')</span>' : '' ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($item['location']): ?>
                                                <span style="display: flex; align-items: center;">
                                                    <i class="fa-solid fa-location-dot" style="color: #ef4444; margin-right: 6px;"></i>
                                                    <?= htmlspecialchars($item['location']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Action (if details exist) -->
                                    <?php if ($item['description'] || $item['link_url']): ?>
                                        <div class="action-box" style="flex-shrink: 0;">
                                            <?php if ($item['link_url']): ?>
                                                <a href="<?= htmlspecialchars($item['link_url']) ?>" target="_blank" class="btn-outline">
                                                    詳細資訊 <i class="fa-solid fa-arrow-right" style="margin-left: 5px;"></i>
                                                </a>
                                            <?php elseif ($item['description']): ?>
                                                <!-- If description exists but no link, maybe show a modal or just tooltip? For now just simple text or ignore button -->
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        
    <?php endif; ?>
</div>

<style>
.lecture-card-row:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.06) !important;
    border-color: var(--primary-light) !important;
}
.btn-outline {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s;
}
.btn-outline:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: #f0f9ff;
}
@media (max-width: 768px) {
    .lecture-card-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    .date-box {
        display: flex;
        width: 100%;
        background: transparent;
        border: none;
        padding: 0;
        justify-content: flex-start;
        gap: 10px;
        align-items: baseline;
        text-align: left;
    }
    .date-box div {
         display: inline-block;
    }
}
</style>

<?php include 'templates/partials/footer.php'; ?>