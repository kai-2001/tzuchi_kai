<?php include __DIR__ . '/partials/header.php'; ?>

<?php
$navbar_mode = 'simple';
$page_title = '轉檔排程佇列';
include __DIR__ . '/partials/navbar.php';
?>

<div class="container" style="padding-top: 120px; max-width: 1000px;">

    <!-- Auto-Compression Settings Card -->
    <div class="queue-card settings-card">
        <div class="queue-settings-row">
            <div class="queue-settings-info">
                <h3 class="queue-section-header"><i class="fa-solid fa-robot"></i> 自動壓縮模式設定</h3>
                <p class="queue-section-desc">
                    <?php if (is_manager()): ?>
                        管理每個院區的自動壓縮設定。啟用後，該院區新上傳的影片將自動排程並通知轉檔主機。
                    <?php else: ?>
                        啟用後，新上傳的影片將自動排程並通知轉檔主機。關閉則需在此手動啟動。
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (is_manager()): ?>
            <!-- Manager: Show table with all campuses -->
            <div class="table-responsive mt-3"
                style="border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <table class="table mb-0" style="background: white;">
                    <thead style="background: linear-gradient(to right, #f8f9fa, #e9ecef);">
                        <tr>
                            <th style="padding: 15px; font-weight: 600; color: #495057;">院區</th>
                            <th style="padding: 15px; width: 180px; text-align: center; font-weight: 600; color: #495057;">
                                自動壓縮</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campus_settings as $setting): ?>
                            <tr style="border-bottom: 1px solid #f0f0f0;">
                                <td style="padding: 15px; vertical-align: middle;">
                                    <i class="fa-solid fa-building"
                                        style="color: var(--primary-color); margin-right: 10px; font-size: 1rem;"></i>
                                    <span style="font-size: 1rem;"><?= htmlspecialchars($setting['campus_name']) ?></span>
                                </td>
                                <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                    <a href="?toggle_auto=<?= $setting['auto_compression'] === '1' ? '0' : '1' ?>&campus_id=<?= $setting['campus_id'] ?>"
                                        class="btn-toggle <?= $setting['auto_compression'] === '1' ? 'active' : '' ?>"
                                        style="display: inline-block; text-decoration: none; padding: 8px 16px; border-radius: 20px; transition: all 0.3s;">
                                        <i class="fa-solid <?= $setting['auto_compression'] === '1' ? 'fa-toggle-on' : 'fa-toggle-off' ?>"
                                            style="font-size: 1.2rem;"></i>
                                        <span
                                            style="margin-left: 6px; font-weight: 500;"><?= $setting['auto_compression'] === '1' ? '已啟用' : '已停用' ?></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- Campus Admin: Show original two-column layout -->
            <?php $setting = $campus_settings[0]; ?>
            <div class="queue-settings-action">
                <a href="?toggle_auto=<?= $setting['auto_compression'] === '1' ? '0' : '1' ?>&campus_id=<?= $setting['campus_id'] ?>"
                    class="btn-toggle <?= $setting['auto_compression'] === '1' ? 'active' : '' ?>">
                    <i class="fa-solid <?= $setting['auto_compression'] === '1' ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                    <span><?= $setting['auto_compression'] === '1' ? '已啟用 (Auto)' : '已停用 (Manual)' ?></span>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Waiting Queue -->
    <div class="queue-section-title">
        <i class="fa-solid fa-hourglass-start" style="color: var(--primary-color);"></i>
        <span>待處理清單 (Waiting)</span>
        <span class="queue-counter-pill">
            <?= count($waiting_videos) ?>
        </span>
    </div>

    <?php if (empty($waiting_videos)): ?>
        <div class="alert alert-light text-center" style="border: 2px dashed #eee; padding: 40px; color: #aaa;">
            <i class="fa-solid fa-check-circle fa-2x mb-3"></i>
            <p>目前沒有等待中的影片。</p>
        </div>
    <?php else: ?>
        <form method="POST" action="process_queue.php" id="queueForm">
            <div class="table-responsive"
                style="background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden;">
                <table class="table mb-0" style="width: 100%;">
                    <thead style="background: #f8f9fa; border-bottom: 2px solid #eee;">
                        <tr>
                            <th width="50" class="text-center" style="padding: 15px;"><input type="checkbox" id="selectAll"
                                    style="cursor: pointer;"></th>
                            <th width="120" style="padding: 15px;">縮圖</th>
                            <th style="padding: 15px;">影片標題</th>
                            <th width="180" style="padding: 15px;">上傳時間</th>
                            <th width="100" style="padding: 15px;">狀態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($waiting_videos as $v): ?>
                            <tr style="border-bottom: 1px solid #f0f0f0;">
                                <td class="text-center align-middle" style="padding: 15px;">
                                    <input type="checkbox" name="video_ids[]" value="<?= $v['id'] ?>" class="video-checkbox"
                                        style="width: 18px; height: 18px; cursor: pointer;">
                                </td>
                                <td class="align-middle" style="padding: 10px 15px;">
                                    <div
                                        style="width: 100px; height: 56px; background: #eee; background-size: cover; background-position: center; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); background-image: url('<?= !empty($v['thumbnail_path']) ? htmlspecialchars($v['thumbnail_path']) : 'assets/images/placeholder.jpg' ?>');">
                                    </div>
                                </td>
                                <td class="align-middle" style="padding: 10px 15px;">
                                    <strong style="font-size: 1rem; color: #333; display: block; margin-bottom: 4px;">
                                        <?= htmlspecialchars($v['title']) ?>
                                    </strong>
                                </td>
                                <td class="align-middle" style="padding: 10px 15px; color: #666; font-size: 0.9rem;">
                                    <?= $v['created_at'] ?>
                                </td>
                                <td class="align-middle" style="padding: 10px 15px;">
                                    <span class="badge bg-secondary" style="font-weight: 500; padding: 6px 10px;">Waiting</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-end" style="margin-top: 25px;">
                <button type="submit" class="btn-admin btn-primary-gradient" id="btnProcess" disabled>
                    <i class="fa-solid fa-play"></i> 立即壓縮選取項目
                </button>
            </div>
        </form>
    <?php endif; ?>

    <hr style="margin: 40px 0; opacity: 0.1;">

    <!-- Active Jobs Monitor -->
    <div class="section-title" style="margin-bottom: 20px; font-weight: 600; color: var(--text-primary);">
        <i class="fa-solid fa-microchip"></i> 正在執行 / 排隊中 (Active Jobs)
    </div>

    <?php if (empty($active_jobs)): ?>
        <p class="text-muted">目前沒有正在轉檔的任務。</p>
    <?php else: ?>
        <div class="row">
            <?php foreach ($active_jobs as $job): ?>
                <div class="col-md-6 mb-3">
                    <div
                        style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid var(--primary); display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h5 style="font-size: 1rem; margin: 0 0 5px 0;">
                                <?= htmlspecialchars($job['title']) ?>
                            </h5>
                            <small class="text-muted">
                                <i class="fa-solid fa-spinner fa-spin"></i>
                                <?= $job['status'] === 'processing' ? '正在轉檔...' : '排隊中...' ?>
                            </small>
                        </div>
                        <div>
                            <span class="badge bg-<?= $job['status'] === 'processing' ? 'primary' : 'warning' ?>">
                                <?= strtoupper($job['status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
    // Checkbox Logic
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            const checkboxes = document.querySelectorAll('.video-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateButtonState();
        });
    }

    document.querySelectorAll('.video-checkbox').forEach(cb => {
        cb.addEventListener('change', updateButtonState);
    });

    function updateButtonState() {
        const checkedCount = document.querySelectorAll('.video-checkbox:checked').length;
        const btn = document.getElementById('btnProcess');
        btn.disabled = checkedCount === 0;
        // Optional: Update button text to reflect count
        if (checkedCount > 0) {
            btn.innerHTML = `<i class="fa-solid fa-play"></i> 立即壓縮選取項目 (${checkedCount})`;
        } else {
            btn.innerHTML = `<i class="fa-solid fa-play"></i> 立即壓縮選取項目`;
        }
    }

    // Auto-refresh: If there are ANY active job cards (look for the unique badge or container)
    (function () {
        // We look for the "Active Jobs" section content. 
        // In the PHP above, active jobs are rendered in .col-md-6 blocks.
        // We can simply check if the "Currently no active jobs" text is missing, or if we find badges.
        const activeBadges = document.querySelectorAll('.badge.bg-primary, .badge.bg-warning');

        // Filter out the 'Waiting' badges from the top list (which use bg-secondary)
        // actually top list uses bg-secondary. 
        // Active jobs use bg-primary (processing) or bg-warning (pending/排隊中).

        if (activeBadges.length > 0) {
            console.log("Active jobs detected via badges, starting auto-refresh interval...");
            setInterval(() => {
                // Don't refresh if user is selecting items
                const checkedCount = document.querySelectorAll('.video-checkbox:checked').length;
                if (checkedCount === 0) {
                    window.location.reload();
                } else {
                    console.log("User has items checked, skipping refresh this cycle.");
                }
            }, 10000);
        }
    })();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>