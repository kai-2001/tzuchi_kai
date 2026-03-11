<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * 影片壓縮管理頁面
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/videoprogress/lib.php');

use mod_videoprogress\service\compression_service;

// =========================================================================
// 權限檢查
// =========================================================================
require_login();
require_capability('moodle/site:config', context_system::instance());

// =========================================================================
// AJAX 處理
// =========================================================================
if (isset($_GET['ajax'])) {
    // [Fix] CSRF protection: Require sesskey for all AJAX actions
    require_sesskey();

    header('Content-Type: application/json; charset=utf-8');

    switch ($_GET['ajax']) {
        case 'compress':
            try {
                require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');
                $item = $DB->get_record('videoprogress_compress_queue', ['id' => required_param('queue_id', PARAM_INT)]);
                if (!$item) {
                    die(json_encode(['success' => false, 'error' => get_string('error:queue_not_found', 'mod_videoprogress')]));
                }

                // [Fix] 釋放 Session 鎖，允許其他請求（如 Reset）並行執行
                \core\session\manager::write_close();

                set_time_limit(900);
                ini_set('max_execution_time', 900);
                compression_service::execute_compression($item);
                echo json_encode(['success' => true, 'message' => get_string('manage:compress_complete', 'mod_videoprogress')]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'reset':
            require_once($CFG->dirroot . '/mod/videoprogress/classes/controller/compression_controller.php');
            $controller = new \mod_videoprogress\controller\compression_controller();
            $controller->handle_reset_request();
            exit;

        case 'recover':
            require_once($CFG->dirroot . '/mod/videoprogress/classes/controller/compression_controller.php');
            $controller = new \mod_videoprogress\controller\compression_controller();
            require_sesskey();
            $controller->handle_recovery_request();
            exit;

        case 'cancel':
            $queueId = required_param('queue_id', PARAM_INT);

            // 取得項目資訊
            $item = $DB->get_record('videoprogress_compress_queue', ['id' => $queueId]);
            if ($item) {
                // 1. 如果該項目正在處理中，嘗試終止 FFmpeg 進程
                if ($item->status === 'processing' && !empty($item->pid)) {
                    if (PHP_OS_FAMILY === 'Windows') {
                        @exec('taskkill /PID ' . intval($item->pid) . ' /F /T 2>&1');
                    } else {
                        @exec('kill -9 ' . intval($item->pid) . ' 2>&1');
                    }
                }

                // 2. 清除對應的 Moodle ad-hoc 任務 (使用 PHP 遍歷確保清除)
                $tasks = $DB->get_records('task_adhoc', ['classname' => '\\mod_videoprogress\\task\\compress_video']);
                foreach ($tasks as $task) {
                    $messagedata = json_decode($task->customdata);
                    if ($messagedata && isset($messagedata->fileid)) {
                        if ($messagedata->fileid == $item->fileid) {
                            $DB->delete_records('task_adhoc', ['id' => $task->id]);
                        }
                    }
                }

                // 3. 從佇列中刪除
                $DB->delete_records('videoprogress_compress_queue', ['id' => $queueId]);
            }

            echo json_encode(['success' => true, 'message' => get_string('manage:removed_complete', 'mod_videoprogress')]);
            exit;

        case 'add_to_queue':
            require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');
            $fileId = required_param('file_id', PARAM_INT);
            $contextId = required_param('context_id', PARAM_INT);
            $filename = required_param('filename', PARAM_TEXT);
            videoprogress_queue_add($contextId, $fileId, $filename);
            echo json_encode(['success' => true, 'message' => get_string('manage:added_to_queue', 'mod_videoprogress')]);
            exit;

        case 'priority':
            $queueId = required_param('queue_id', PARAM_INT);

            // [Fix] 改用 priority 權重排序 (方案 A：數字越大越優先)
            // 取得目前「Pending」中最大的 priority
            $maxPriority = $DB->get_field_sql(
                "SELECT MAX(priority) FROM {videoprogress_compress_queue} WHERE status = 'pending'"
            );

            // 如果沒資料，maxPriority 可能是 null 或 0，我們目標是 +1
            $newPriority = ((int) $maxPriority) + 1;

            $DB->execute(
                "UPDATE {videoprogress_compress_queue} 
                 SET priority = :priority, timemodified = :timemodified 
                 WHERE id = :id",
                [
                    'priority' => $newPriority,
                    'timemodified' => time(),
                    'id' => $queueId
                ]
            );

            echo json_encode(['success' => true, 'message' => get_string('manage:priority_complete', 'mod_videoprogress')]);
            exit;
    }
}

// =========================================================================
// 頁面設定
// =========================================================================
$PAGE->set_url('/mod/videoprogress/manage_compression.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('compression_management', 'mod_videoprogress'));
$PAGE->set_heading(get_string('compression_management', 'mod_videoprogress'));
$PAGE->set_pagelayout('admin');

// 註冊 JS 需要的字串
$PAGE->requires->string_for_js('manage:confirm_reset', 'mod_videoprogress');
$PAGE->requires->string_for_js('manage:confirm_remove', 'mod_videoprogress');
$PAGE->requires->string_for_js('manage:max_limit', 'mod_videoprogress');
$PAGE->requires->string_for_js('manage:finish_success', 'mod_videoprogress');
$PAGE->requires->string_for_js('manage:processing_step', 'mod_videoprogress');
$PAGE->requires->string_for_js('manage:status_processing', 'mod_videoprogress');
$PAGE->requires->string_for_js('manage:btn_add', 'mod_videoprogress');
$PAGE->requires->string_for_js('manage:btn_recover', 'mod_videoprogress');
$PAGE->requires->string_for_js('manage:recovering', 'mod_videoprogress');
$PAGE->requires->string_for_js('manage:confirm_recover', 'mod_videoprogress');
$PAGE->requires->string_for_js('manage:recover_failed', 'mod_videoprogress');

// =========================================================================
// 取得資料
// =========================================================================
$ffmpegpath = get_config('mod_videoprogress', 'ffmpegpath');
$compressionEnabled = get_config('mod_videoprogress', 'enablecompression');
$ffmpegOk = !empty($ffmpegpath) && file_exists($ffmpegpath);

$pendingItems = compression_service::get_pending_items();
$stats = compression_service::get_statistics();
$completedLogs = compression_service::get_completed_logs(20);
$hasProcessing = !empty(array_filter($pendingItems, fn($i) => $i->status === 'processing'));

// 查詢可壓縮但不在佇列中、也沒壓縮過的影片（>50MB）
$minSize = 50 * 1024 * 1024;
$queuedFileIds = array_column($pendingItems, 'fileid');
$queuedFileIdsPlaceholder = !empty($queuedFileIds) ? implode(',', $queuedFileIds) : '0';

// 取得已經壓縮過的檔案 contextid（因為壓縮後 fileid 會變）
$dbman = $DB->get_manager();
$logTable = new xmldb_table('videoprogress_compression_log');
$compressedContextIds = [];
if ($dbman->table_exists($logTable)) {
    $compressedContextIds = $DB->get_fieldset_sql("SELECT DISTINCT contextid FROM {videoprogress_compression_log}");
}
$compressedContextIdsPlaceholder = !empty($compressedContextIds) ? implode(',', $compressedContextIds) : '0';

$compressibleVideos = $DB->get_records_sql("
    SELECT f.id as fileid, f.filename, f.filesize, f.contextid, 
           ctx.instanceid as cmid, c.fullname as course_name, vp.name as activity_name
    FROM {files} f
    JOIN {context} ctx ON ctx.id = f.contextid AND ctx.contextlevel = " . CONTEXT_MODULE . "
    JOIN {course_modules} cm ON cm.id = ctx.instanceid
    JOIN {modules} m ON m.id = cm.module AND m.name = 'videoprogress'
    JOIN {videoprogress} vp ON vp.id = cm.instance
    JOIN {course} c ON c.id = cm.course
    WHERE f.component = 'mod_videoprogress'
      AND f.filearea IN ('video', 'package')
      AND f.filesize > ?
      AND f.id NOT IN ($queuedFileIdsPlaceholder)
      AND f.contextid NOT IN ($compressedContextIdsPlaceholder)
      AND LOWER(f.filename) REGEXP '\.(mp4|avi|mkv|mov|webm)$'
    ORDER BY f.timemodified DESC
", [$minSize]);

// =========================================================================
// 渲染頁面
// =========================================================================
echo $OUTPUT->header();

$settingsUrl = (new moodle_url('/admin/settings.php', ['section' => 'modsettingvideoprogress']))->out();
?>

<?php if (!$ffmpegOk || !$compressionEnabled): ?>
    <div class="alert alert-warning">
        <i class="fa fa-exclamation-triangle"></i>
        <?= !$ffmpegOk ? get_string('ffmpeg_not_detected', 'mod_videoprogress') : get_string('compression_skipped', 'mod_videoprogress') ?>
        <a href="<?= $settingsUrl ?>"
            class="alert-link"><?= get_string('open_compression_management', 'mod_videoprogress') ?></a>
    </div>
<?php endif; ?>

<?php if ($stats && $stats->total_count > 0): ?>
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0"><i class="fa fa-bar-chart"></i> <?= get_string('manage:stats_title', 'mod_videoprogress') ?>
            </h4>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="h1 text-primary"><?= $stats->total_count ?></div>
                    <div class="text-muted"><?= get_string('manage:total_compressed', 'mod_videoprogress') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="h1 text-secondary"><?= $stats->total_original_gb ?> GB</div>
                    <div class="text-muted"><?= get_string('manage:total_original', 'mod_videoprogress') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="h1 text-success"><?= $stats->total_saved_gb ?> GB</div>
                    <div class="text-muted"><?= get_string('manage:total_saved', 'mod_videoprogress') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="h1 text-warning">-<?= $stats->avg_percent ?>%</div>
                    <div class="text-muted"><?= get_string('manage:avg_rate', 'mod_videoprogress') ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h4 class="mb-0"><i class="fa fa-list"></i> <?= get_string('manage:queue_title', 'mod_videoprogress') ?></h4>
    </div>
    <div class="card-body">
        <?php if (empty($pendingItems)): ?>
            <div class="alert alert-success mb-0">
                <i class="fa fa-check"></i> <?= get_string('manage:queue_empty', 'mod_videoprogress') ?>
            </div>
        <?php else: ?>
            <p class="text-muted"><?= get_string('manage:queue_desc', 'mod_videoprogress') ?></p>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th width="40"><input type="checkbox" id="select-all"></th>
                        <th><?= get_string('manage:table_filename', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:table_course', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:table_activity', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:table_size', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:table_status', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:table_time', 'mod_videoprogress') ?></th>
                        <th width="80"><?= get_string('manage:table_action', 'mod_videoprogress') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $statusMap = [
                        'pending' => ['badge' => get_string('manage:status_pending', 'mod_videoprogress'), 'class' => 'bg-secondary'],
                        'processing' => ['badge' => get_string('manage:status_processing', 'mod_videoprogress'), 'class' => 'bg-primary'],
                        'failed' => ['badge' => get_string('manage:status_failed', 'mod_videoprogress'), 'class' => 'bg-danger'],
                    ];
                    foreach ($pendingItems as $item):
                        $s = $statusMap[$item->status] ?? $statusMap['pending'];
                        $disabled = $item->status === 'processing' ? 'disabled' : '';
                        ?>
                        <tr>
                            <td><input type="checkbox" class="queue-checkbox" value="<?= $item->id ?>" <?= $disabled ?>></td>
                            <td><?= s($item->filename) ?></td>
                            <td><?= s($item->course_name) ?></td>
                            <td><a href="<?= $item->activity_url ?>"><?= s($item->activity_name) ?></a></td>
                            <td><?= compression_service::format_filesize($item->filesize ?? 0) ?></td>
                            <td><span class="badge <?= $s['class'] ?>"><?= $s['badge'] ?></span></td>
                            <td><?= userdate($item->timecreated, '%Y-%m-%d %H:%M') ?></td>
                            <td>
                                <?php if ($item->status === 'pending'): ?>
                                    <button class="btn btn-sm btn-outline-success priority-btn" data-id="<?= $item->id ?>"
                                        title="<?= get_string('manage:btn_priority', 'mod_videoprogress') ?>"><i
                                            class="fa fa-arrow-up"></i></button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-danger cancel-item-btn" data-id="<?= $item->id ?>"
                                    title="<?= get_string('manage:removed_complete', 'mod_videoprogress') ?>"><i
                                        class="fa fa-times"></i></button>
                            </td>
                        </tr>
                        <?php if ($item->status === 'failed' && !empty($item->last_error)): ?>
                            <tr class="table-danger">
                                <td colspan="8" class="small text-danger">
                                    <i class="fa fa-exclamation-circle"></i> Error: <?= s($item->last_error) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="d-flex gap-2">
                <button id="start-compression-btn" class="btn btn-primary" disabled>
                    <i class="fa fa-play"></i> <?= get_string('manage:btn_start', 'mod_videoprogress') ?> (<span
                        id="selected-count">0</span>/3)
                </button>
                <button id="reset-btn" class="btn btn-outline-secondary">
                    <i class="fa fa-refresh"></i> <?= get_string('manage:btn_reset', 'mod_videoprogress') ?>
                </button>
                <button id="recover-btn" class="btn btn-success"
                    title="<?= get_string('manage:confirm_recover', 'mod_videoprogress') ?>">
                    <i class="fa fa-medkit"></i> <?= get_string('manage:btn_recover', 'mod_videoprogress') ?>
                </button>
            </div>
            <div id="compression-progress" style="display:none;" class="mt-3">
                <div class="progress" style="height:25px;">
                    <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated"
                        style="width:0%;">0%</div>
                </div>
                <p id="progress-text" class="text-muted mt-2"></p>
            </div>
            <div id="compression-result" class="mt-3"></div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($completedLogs)): ?>
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0"><i class="fa fa-check-circle"></i> <?= get_string('manage:logs_title', 'mod_videoprogress') ?>
            </h4>
        </div>
        <div class="card-body">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th><?= get_string('manage:table_filename', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:table_course', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:table_activity', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:log_original', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:log_compressed', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:log_saved', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:table_time', 'mod_videoprogress') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completedLogs as $log):
                        $savedPct = $log->original_size > 0 ? round((($log->original_size - $log->compressed_size) / $log->original_size) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?= s($log->filename) ?></td>
                            <td><?= s($log->course_name ?? 'Unknown') ?></td>
                            <td><a href="<?= $log->activity_url ?? '#' ?>"><?= s($log->activity_name ?? 'Unknown') ?></a></td>
                            <td><?= compression_service::format_filesize($log->original_size) ?></td>
                            <td><?= compression_service::format_filesize($log->compressed_size) ?></td>
                            <td class="text-success">-<?= $savedPct ?>%</td>
                            <td><?= userdate($log->timecreated, '%Y-%m-%d %H:%M') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($compressibleVideos)): ?>
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0"><i class="fa fa-film"></i> <?= get_string('manage:videos_title', 'mod_videoprogress') ?></h4>
        </div>
        <div class="card-body">
            <p class="text-muted"><?= get_string('manage:videos_desc', 'mod_videoprogress') ?></p>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th><?= get_string('manage:table_filename', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:table_course', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:table_activity', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:table_size', 'mod_videoprogress') ?></th>
                        <th><?= get_string('manage:table_action', 'mod_videoprogress') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compressibleVideos as $video):
                        $activityUrl = new moodle_url('/mod/videoprogress/view.php', ['id' => $video->cmid]);
                        ?>
                        <tr>
                            <td><?= s($video->filename) ?></td>
                            <td><?= s($video->course_name) ?></td>
                            <td><a href="<?= $activityUrl ?>"><?= s($video->activity_name) ?></a></td>
                            <td><?= compression_service::format_filesize($video->filesize) ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary add-to-queue-btn" data-file-id="<?= $video->fileid ?>"
                                    data-context-id="<?= $video->contextid ?>" data-filename="<?= s($video->filename) ?>">
                                    <i class="fa fa-plus"></i> <?= get_string('manage:btn_add', 'mod_videoprogress') ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
    var isWindows = <?php echo (PHP_OS_FAMILY === 'Windows') ? 'true' : 'false'; ?>;
    document.addEventListener('DOMContentLoaded', function () {
        var isBatchProcessing = false;
        var isResetting = false; // [Fix] 防止重設時顯示成功訊息
        var updateTimer = null; // [Fix] 儲存計時器 ID 以便取消
        var checkboxes = document.querySelectorAll('.queue-checkbox');
        var selectAll = document.getElementById('select-all');
        var startBtn = document.getElementById('start-compression-btn');
        var resetBtn = document.getElementById('reset-btn');
        var selectedCount = document.getElementById('selected-count');
        var progressDiv = document.getElementById('compression-progress');
        var progressBar = document.getElementById('progress-bar');
        var progressText = document.getElementById('progress-text');
        var resultDiv = document.getElementById('compression-result');

        function updateSelectedCount() {
            if (isBatchProcessing) return; // [Fix] 處理中不更新按鈕狀態
            var count = document.querySelectorAll('.queue-checkbox:checked:not(:disabled)').length;
            if (selectedCount) selectedCount.textContent = count;
            if (startBtn) startBtn.disabled = count === 0 || count > 3;
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                var checked = 0;
                checkboxes.forEach(function (cb) {
                    if (!cb.disabled) {
                        if (checked < 3) {
                            cb.checked = selectAll.checked;
                            if (selectAll.checked) checked++;
                        } else {
                            cb.checked = false;
                        }
                    }
                });
                updateSelectedCount();
            });
        }

        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', function () {
                var count = document.querySelectorAll('.queue-checkbox:checked:not(:disabled)').length;
                if (count > 3) {
                    this.checked = false;
                    alert(M.util.get_string('manage:max_limit', 'mod_videoprogress'));
                }
                updateSelectedCount();
            });
        });

        if (startBtn) {
            startBtn.addEventListener('click', function () {
                var selectedIds = [];
                document.querySelectorAll('.queue-checkbox:checked:not(:disabled)').forEach(function (cb) {
                    selectedIds.push(cb.value);
                });

                if (selectedIds.length === 0) return;

                isBatchProcessing = true;
                if (updateTimer) clearTimeout(updateTimer); // [Fix] 取消已排程的自動重整

                // [Fix] 防止使用者意外離開頁面
                window.onbeforeunload = function () {
                    return '壓縮正在進行中，確定要離開嗎？';
                };

                startBtn.disabled = true;
                // [Fix] 鎖定所有 checkbox 避免用戶在處理中修改
                checkboxes.forEach(function (cb) { cb.disabled = true; });
                if (selectAll) selectAll.disabled = true;

                progressDiv.style.display = 'block';
                resultDiv.innerHTML = '';

                var completed = 0;
                var total = selectedIds.length;
                var results = [];

                function processNext() {
                    // [Fix] 如果正在重設，立即停止前端處理，防止顯示錯誤的成功訊息
                    if (isResetting) return;

                    if (completed >= total) {
                        // 全部完成
                        var successCount = results.filter(function (r) { return r.success; }).length;
                        var failCount = results.filter(function (r) { return !r.success; }).length;

                        progressBar.style.width = '100%';
                        progressBar.textContent = '100%';

                        var html = '<div class="alert alert-info">';
                        var totalMsg = M.util.get_string('manage:finish_success', 'mod_videoprogress')
                            .replace('{$a->success}', successCount)
                            .replace('{$a->fail}', failCount);
                        html += '<strong>' + totalMsg + '</strong>';
                        html += '</div>';

                        if (failCount > 0) {
                            html += '<div class="alert alert-danger"><ul class="mb-0">';
                            results.filter(function (r) { return !r.success; }).forEach(function (r) {
                                html += '<li>' + r.error + '</li>';
                            });
                            html += '</ul></div>';
                        }

                        resultDiv.innerHTML = html;

                        isBatchProcessing = false;
                        window.onbeforeunload = null; // [Fix] 完成後移除離開警告
                        // [Fix] 處理完成後解鎖
                        checkboxes.forEach(function (cb) { cb.disabled = false; });
                        if (selectAll) selectAll.disabled = false;
                        updateSelectedCount(); // 更新按鈕狀態

                        setTimeout(function () { location.reload(); }, 3000);
                        return;
                    }

                    var queueId = selectedIds[completed];
                    var percent = Math.round((completed / total) * 100);
                    progressBar.style.width = percent + '%';
                    progressBar.textContent = percent + '%';
                    var msg = M.util.get_string('manage:processing_step', 'mod_videoprogress')
                        .replace('{$a->current}', (completed + 1))
                        .replace('{$a->total}', total);
                    progressText.textContent = msg;

                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', 'manage_compression.php?ajax=compress&queue_id=' + queueId + '&sesskey=' + M.cfg.sesskey, true);
                    xhr.timeout = 3600000;

                    xhr.onload = function () {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            results.push(data);
                        } catch (e) {
                            results.push({ success: false, error: '解析回應失敗' });
                        }
                        completed++;
                        processNext();
                    };

                    xhr.onerror = function () {
                        results.push({ success: false, error: '網路錯誤' });
                        completed++;
                        processNext();
                    };

                    xhr.ontimeout = function () {
                        results.push({ success: false, error: '請求超時' });
                        completed++;
                        processNext();
                    };

                    xhr.send();
                }

                processNext();
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (!confirm(M.util.get_string('manage:confirm_reset', 'mod_videoprogress'))) return;

                isResetting = true; // [Fix] 鎖定狀態，阻止 processNext 繼續執行

                var xhr = new XMLHttpRequest();
                xhr.open('GET', 'manage_compression.php?ajax=reset&sesskey=' + M.cfg.sesskey, true);
                xhr.onload = function () {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        alert(data.message || data.error);
                        window.onbeforeunload = null; // [Fix] 清除離開警告
                        location.reload();
                    } catch (e) {
                        alert('操作失敗');
                    }
                };
                xhr.send();
            });
        }

        // 單獨取消某個項目
        document.querySelectorAll('.cancel-item-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm(M.util.get_string('manage:confirm_remove', 'mod_videoprogress'))) return;

                var queueId = this.dataset.id;
                var row = this.closest('tr');

                var xhr = new XMLHttpRequest();
                xhr.open('GET', 'manage_compression.php?ajax=cancel&queue_id=' + queueId + '&sesskey=' + M.cfg.sesskey, true);
                xhr.onload = function () {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            if (isWindows) {
                                location.reload();
                            } else {
                                row.remove();
                                // 如果有錯誤訊息行也一併移除
                                var nextRow = row.nextElementSibling;
                                if (nextRow && nextRow.classList.contains('table-danger')) {
                                    nextRow.remove();
                                }
                            }
                        } else {
                            alert(data.error || '操作失敗');
                        }
                    } catch (e) {
                        alert('操作失敗');
                    }
                };
                xhr.send();
            });
        });

        // [New] 救援按鈕
        var recoverBtn = document.getElementById('recover-btn');
        if (recoverBtn) {
            recoverBtn.addEventListener('click', function () {
                if (!confirm(M.util.get_string('manage:confirm_recover', 'mod_videoprogress'))) return;

                recoverBtn.disabled = true;
                recoverBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + M.util.get_string('manage:recovering', 'mod_videoprogress');

                var xhr = new XMLHttpRequest();
                xhr.open('GET', 'manage_compression.php?ajax=recover&sesskey=' + M.cfg.sesskey, true);
                xhr.onload = function () {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        alert(data.message);
                        location.reload();
                    } catch (e) {
                        alert(M.util.get_string('manage:recover_failed', 'mod_videoprogress', e.message));
                        recoverBtn.disabled = false;
                        recoverBtn.innerHTML = '<i class="fa fa-medkit"></i> ' + M.util.get_string('manage:btn_recover', 'mod_videoprogress');
                    }
                };
                xhr.send();
            });
        }


        // 優先處理按鈕
        document.querySelectorAll('.priority-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var queueId = this.dataset.id;
                var button = this;

                button.disabled = true;
                button.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

                var xhr = new XMLHttpRequest();
                xhr.open('GET', 'manage_compression.php?ajax=priority&queue_id=' + queueId + '&sesskey=' + M.cfg.sesskey, true);
                xhr.onload = function () {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.error || '操作失敗');
                            button.disabled = false;
                            button.innerHTML = '<i class="fa fa-arrow-up"></i>';
                        }
                    } catch (e) {
                        alert('操作失敗');
                        button.disabled = false;
                        button.innerHTML = '<i class="fa fa-arrow-up"></i>';
                    }
                };
                xhr.send();
            });
        });

        // 加入佇列按鈕
        document.querySelectorAll('.add-to-queue-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var fileId = this.dataset.fileId;
                var contextId = this.dataset.contextId;
                var filename = this.dataset.filename;
                var row = this.closest('tr');
                var button = this;

                button.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + M.util.get_string('manage:status_processing', 'mod_videoprogress') + '...';

                var xhr = new XMLHttpRequest();
                xhr.open('GET', 'manage_compression.php?ajax=add_to_queue&file_id=' + fileId + '&context_id=' + contextId + '&filename=' + encodeURIComponent(filename) + '&sesskey=' + M.cfg.sesskey, true);
                xhr.onload = function () {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            row.remove();
                            location.reload(); // 重整以顯示新加入的項目
                        } else {
                            alert(data.error || '操作失敗');
                            button.disabled = false;
                            button.innerHTML = '<i class="fa fa-plus"></i> ' + M.util.get_string('manage:btn_add', 'mod_videoprogress');
                        }
                    } catch (e) {
                        alert('操作失敗');
                        button.disabled = false;
                        button.innerHTML = '<i class="fa fa-plus"></i> ' + M.util.get_string('manage:btn_add', 'mod_videoprogress');
                    }
                };
                xhr.send();
            });
        });

        // 自動刷新：檢查是否有處理中的項目
        function checkForUpdates() {
            if (isBatchProcessing) return;

            var processingItems = document.querySelectorAll('.badge.bg-primary');
            if (processingItems.length > 0) {
                updateTimer = setTimeout(function () {
                    location.reload();
                }, 10000);
            }
        }

        checkForUpdates();
    });
</script>
<?php

echo $OUTPUT->footer();
