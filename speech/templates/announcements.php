<?php include 'templates/partials/header.php'; ?>

<header class="static-header">
    <div class="header-container">
        <div class="header-left">
            <a href="index.php" class="logo">
                <h1 class="logo-text" style="color: var(--primary-dark);">學術演講影片平台</h1>
            </a>
            <span class="breadcrumb-separator" style="color: #ccc;">/</span>
            <h2 class="page-title" style="color: var(--text-primary); font-size: 1.2rem; font-weight: 500; margin: 0;">
                公告</h2>
        </div>
        <div class="user-nav">
            <a href="index.php" class="btn-admin"><i class="fa-solid fa-house"></i> 返回首頁</a>
        </div>
    </div>
</header>

<div class="container" style="padding-top: 100px; padding-bottom: 60px; min-height: 80vh;">

    <?php if (empty($announcements_flat)): ?>
        <div class="no-results"
            style="text-align: center; padding: 100px 0; background: white; border-radius: 20px; box-shadow: var(--shadow-sm);">
            <i class="fa-regular fa-calendar-xmark" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 20px;"></i>
            <p style="font-size: 1.2rem; color: var(--text-secondary); font-weight: 500;">目前沒有相關公告。</p>
        </div>
    <?php else: ?>

        <?php
        // Get unique campuses from the flat list for tabs
        $tabs = ['all' => '全部'];
        foreach ($announcements_flat as $item) {
            $c_name = $item['campus_name'] ?? '全院活動';
            $c_id = $item['campus_id_val'] ?? 0; // Use campus_id_val from updated query
            if ($c_id == 0)
                continue; 
            $tabs[$c_id] = $c_name;
        }
        ?>

        <!-- Campus Filter Tabs -->
        <nav class="tabs" style="justify-content: center; margin-bottom: 15px; padding: 5px 0;">
            <a href="javascript:void(0)" class="tab active" onclick="filterAnnouncements('all', this)">全部</a>
            <?php foreach ($tabs as $tid => $tname): ?>
                <?php if ($tid === 'all')
                    continue; ?>
                <a href="javascript:void(0)" class="tab"
                    onclick="filterAnnouncements('<?= $tid ?>', this)"><?= htmlspecialchars($tname) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="table-responsive">
            <table class="glass-table announcement-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">日期</th>
                        <th style="width: 15%;">院區</th>
                        <th style="width: 40%;">主題 / 講者</th>
                        <th style="width: 15%;">地點</th>
                        <th style="width: 15%;">詳細資訊</th>
                    </tr>
                </thead>
                <tbody id="announcement-body">
                    <?php foreach ($announcements_flat as $item):
                        // data-campus-id: for filtering. 
                        // If item.campus_id == 0, it shows on ALL tabs? 
                        // Or only on 'All' tab? Generally '0' means Global.
                        $row_campus_id = $item['campus_id_val'] ?? 0;
                        ?>
                        <tr class="announcement-row" data-campus-id="<?= $row_campus_id ?>">
                            <td>
                                <div class="table-date">
                                    <span class="date-day"><?= date('Y-m-d', strtotime($item['event_date'])) ?></span>
                                    <span class="date-weekday">(<?= date('D', strtotime($item['event_date'])) ?>)</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge-campus"><?= htmlspecialchars($item['campus_name'] ?? '全院') ?></span>
                            </td>
                            <td>
                                <div class="table-topic">
                                    <div class="topic-title"><?= htmlspecialchars($item['title']) ?></div>
                                    <?php if ($item['speaker_name']): ?>
                                        <div class="topic-speaker">
                                            <i class="fa-solid fa-user-tie"></i>
                                            <?= htmlspecialchars($item['speaker_name']) ?>
                                            <?= $item['affiliation'] ? '<span class="affiliation">| ' . htmlspecialchars($item['affiliation']) . '</span>' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($item['location']): ?>
                                    <div class="table-location">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <?= htmlspecialchars($item['location']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['link_url']): ?>
                                    <a href="<?= htmlspecialchars($item['link_url']) ?>" target="_blank" class="btn-table-action">
                                        前往 <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<script>
    function filterAnnouncements(campusId, tabElement) {
        // 1. Update Tab Active State
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        tabElement.classList.add('active');

        // 2. Filter Rows
        const rows = document.querySelectorAll('.announcement-row');
        const isAll = (campusId === 'all');

        rows.forEach(row => {
            const rowCId = row.getAttribute('data-campus-id');
            // Logic: 
            // If viewing 'All' -> Show Everything.
            // If viewing Specific Campus -> Show rows matching that Campus ID OR rows with ID 0 (Global).
            // Let's assume ID 0 is Global announcements which appear everywhere.
            if (isAll) {
                row.style.display = '';
            } else {
                if (rowCId == campusId || rowCId == 0) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    }
</script>

<style>
    /* Announcement Table Styles */
    .announcement-table {
        width: 100%;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        border-collapse: separate;
        border-spacing: 0;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
    }

    .announcement-table th {
        background: #f8fafc;
        padding: 15px 20px;
        font-weight: 600;
        color: var(--text-secondary);
        border-bottom: 2px solid #e2e8f0;
        text-align: left;
    }

    .announcement-table td {
        padding: 15px 20px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        color: var(--text-primary);
    }

    .announcement-table tr:last-child td {
        border-bottom: none;
    }

    .announcement-table tr:hover {
        background-color: #f8fafc;
    }

    /* Date Cell */
    .table-date {
        display: flex;
        flex-direction: column;
        line-height: 1.3;
    }

    .date-day {
        font-weight: 700;
        color: var(--primary-dark);
        font-size: 1rem;
    }

    .date-weekday {
        font-size: 0.85rem;
        color: #94a3b8;
    }

    /* Topic Cell */
    .topic-title {
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .topic-speaker {
        font-size: 0.9rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .topic-speaker i {
        color: var(--primary-color);
        font-size: 0.85rem;
    }

    .affiliation {
        opacity: 0.8;
        font-size: 0.85rem;
    }

    /* Location Cell */
    .table-location {
        color: var(--text-secondary);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .table-location i {
        color: #ef4444;
    }

    .badge-campus {
        display: inline-block;
        padding: 4px 10px;
        background: #e0f2f1;
        color: var(--primary-dark);
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        border: 1px solid rgba(0, 132, 145, 0.2);
    }

    /* Action Cell */
    .btn-table-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        color: var(--primary-color);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .btn-table-action:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    @media (max-width: 768px) {
        .announcement-table thead {
            display: none;
        }

        .announcement-table,
        .announcement-table tbody,
        .announcement-table tr,
        .announcement-table td {
            display: block;
            width: 100%;
        }

        .announcement-table tr {
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .announcement-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #f1f5f9;
            text-align: left;
        }

        .table-date {
            flex-direction: row;
            gap: 10px;
            align-items: center;
        }
    }
</style>

<?php include 'templates/partials/footer.php'; ?>