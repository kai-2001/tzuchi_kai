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
require_once($CFG->dirroot.'/mod/videoprogress/lib.php');

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
    header('Content-Type: application/json; charset=utf-8');
    
    switch ($_GET['ajax']) {
        case 'compress':
            try {
                require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');
                $item = $DB->get_record('videoprogress_compress_queue', ['id' => required_param('queue_id', PARAM_INT)]);
                if (!$item) {
                    die(json_encode(['success' => false, 'error' => '找不到佇列項目']));
                }
                set_time_limit(900);
                ini_set('max_execution_time', 900);
                compression_service::execute_compression($item);
                echo json_encode(['success' => true, 'message' => '壓縮完成']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'reset':
            // 先殺掉正在跑的 FFmpeg
            if (PHP_OS_FAMILY === 'Windows') {
                exec('taskkill /IM ffmpeg.exe /F 2>&1');
            } else {
                exec('pkill -9 ffmpeg 2>&1');
            }
            // 更新資料庫狀態
            $DB->execute("UPDATE {videoprogress_compress_queue} SET status = 'pending', timemodified = ? WHERE status = 'processing'", [time()]);
            echo json_encode(['success' => true, 'message' => '已終止壓縮並重設項目']);
            exit;
            
        case 'cancel':
            $queueId = required_param('queue_id', PARAM_INT);
            $DB->delete_records('videoprogress_compress_queue', ['id' => $queueId]);
            echo json_encode(['success' => true, 'message' => '已從佇列中移除']);
            exit;
            
        case 'add_to_queue':
            require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');
            $fileId = required_param('file_id', PARAM_INT);
            $contextId = required_param('context_id', PARAM_INT);
            $filename = required_param('filename', PARAM_TEXT);
            videoprogress_queue_add($contextId, $fileId, $filename);
            echo json_encode(['success' => true, 'message' => '已加入壓縮佇列']);
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
    <?= !$ffmpegOk ? 'FFmpeg 未設定或路徑無效。' : '影片壓縮功能未啟用。' ?>
    <a href="<?= $settingsUrl ?>" class="alert-link">前往設定</a>
</div>
<?php endif; ?>

<?php if ($stats && $stats->total_count > 0): ?>
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h4 class="mb-0"><i class="fa fa-bar-chart"></i> 壓縮統計</h4>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-3">
                <div class="h1 text-primary"><?= $stats->total_count ?></div>
                <div class="text-muted">已壓縮檔案</div>
            </div>
            <div class="col-md-3">
                <div class="h1 text-secondary"><?= $stats->total_original_gb ?> GB</div>
                <div class="text-muted">原始總大小</div>
            </div>
            <div class="col-md-3">
                <div class="h1 text-success"><?= $stats->total_saved_gb ?> GB</div>
                <div class="text-muted">已節省空間</div>
            </div>
            <div class="col-md-3">
                <div class="h1 text-warning">-<?= $stats->avg_percent ?>%</div>
                <div class="text-muted">平均壓縮率</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h4 class="mb-0"><i class="fa fa-list"></i> 待處理佇列</h4>
    </div>
    <div class="card-body">
        <?php if (empty($pendingItems)): ?>
            <div class="alert alert-success mb-0">
                <i class="fa fa-check"></i> 目前沒有待壓縮的影片。
            </div>
        <?php else: ?>
            <p class="text-muted">選擇要壓縮的項目，然後點擊「開始壓縮」。一次最多處理 3 個。</p>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th width="40"><input type="checkbox" id="select-all"></th>
                        <th>檔案名稱</th>
                        <th>課程</th>
                        <th>活動</th>
                        <th>大小</th>
                        <th>狀態</th>
                        <th>時間</th>
                        <th width="80">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $statusMap = [
                        'pending' => ['badge' => '等待處理', 'class' => 'bg-secondary'],
                        'processing' => ['badge' => '處理中', 'class' => 'bg-primary'],
                        'failed' => ['badge' => '失敗', 'class' => 'bg-danger'],
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
                        <td><button class="btn btn-sm btn-outline-danger cancel-item-btn" data-id="<?= $item->id ?>" title="從佇列移除"><i class="fa fa-times"></i></button></td>
                    </tr>
                    <?php if ($item->status === 'failed' && !empty($item->last_error)): ?>
                    <tr class="table-danger">
                        <td colspan="8" class="small text-danger">
                            <i class="fa fa-exclamation-circle"></i> 錯誤: <?= s($item->last_error) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="d-flex gap-2">
                <button id="start-compression-btn" class="btn btn-primary" disabled>
                    <i class="fa fa-play"></i> 開始壓縮 (<span id="selected-count">0</span>/3)
                </button>
                <button id="reset-btn" class="btn btn-outline-secondary">
                    <i class="fa fa-refresh"></i> 重設處理中項目
                </button>
            </div>
            <div id="compression-progress" style="display:none;" class="mt-3">
                <div class="progress" style="height:25px;">
                    <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%;">0%</div>
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
        <h4 class="mb-0"><i class="fa fa-check-circle"></i> 最近完成的壓縮</h4>
    </div>
    <div class="card-body">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>檔案</th>
                    <th>課程</th>
                    <th>活動</th>
                    <th>原始大小</th>
                    <th>壓縮後</th>
                    <th>節省</th>
                    <th>時間</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($completedLogs as $log): 
                    $savedPct = $log->original_size > 0 ? round((($log->original_size - $log->compressed_size) / $log->original_size) * 100, 1) : 0;
                ?>
                <tr>
                    <td><?= s($log->filename) ?></td>
                    <td><?= s($log->course_name ?? '未知') ?></td>
                    <td><a href="<?= $log->activity_url ?? '#' ?>"><?= s($log->activity_name ?? '未知') ?></a></td>
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
        <h4 class="mb-0"><i class="fa fa-film"></i> 可加入壓縮佇列的影片</h4>
    </div>
    <div class="card-body">
        <p class="text-muted">以下影片大於 50MB 且尚未在壓縮佇列中，可以手動加入：</p>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>檔案名稱</th>
                    <th>課程</th>
                    <th>活動</th>
                    <th>大小</th>
                    <th>操作</th>
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
                        <button class="btn btn-sm btn-primary add-to-queue-btn" 
                                data-file-id="<?= $video->fileid ?>" 
                                data-context-id="<?= $video->contextid ?>" 
                                data-filename="<?= s($video->filename) ?>">
                            <i class="fa fa-plus"></i> 加入佇列
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
document.addEventListener('DOMContentLoaded', function() {
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
        var count = document.querySelectorAll('.queue-checkbox:checked:not(:disabled)').length;
        if (selectedCount) selectedCount.textContent = count;
        if (startBtn) startBtn.disabled = count === 0 || count > 3;
    }
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            var checked = 0;
            checkboxes.forEach(function(cb) {
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
    
    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', function() {
            var count = document.querySelectorAll('.queue-checkbox:checked:not(:disabled)').length;
            if (count > 3) {
                this.checked = false;
                alert('最多只能選擇 3 個項目');
            }
            updateSelectedCount();
        });
    });
    
    if (startBtn) {
        startBtn.addEventListener('click', function() {
            var selectedIds = [];
            document.querySelectorAll('.queue-checkbox:checked:not(:disabled)').forEach(function(cb) {
                selectedIds.push(cb.value);
            });
            
            if (selectedIds.length === 0) return;
            
            startBtn.disabled = true;
            progressDiv.style.display = 'block';
            resultDiv.innerHTML = '';
            
            var completed = 0;
            var total = selectedIds.length;
            var results = [];
            
            function processNext() {
                if (completed >= total) {
                    // 全部完成
                    var successCount = results.filter(function(r) { return r.success; }).length;
                    var failCount = results.filter(function(r) { return !r.success; }).length;
                    
                    progressBar.style.width = '100%';
                    progressBar.textContent = '100%';
                    
                    var html = '<div class="alert alert-info">';
                    html += '<strong>處理完成！</strong> 成功: ' + successCount + ', 失敗: ' + failCount;
                    html += '</div>';
                    
                    if (failCount > 0) {
                        html += '<div class="alert alert-danger"><ul class="mb-0">';
                        results.filter(function(r) { return !r.success; }).forEach(function(r) {
                            html += '<li>' + r.error + '</li>';
                        });
                        html += '</ul></div>';
                    }
                    
                    resultDiv.innerHTML = html;
                    
                    setTimeout(function() { location.reload(); }, 3000);
                    return;
                }
                
                var queueId = selectedIds[completed];
                var percent = Math.round((completed / total) * 100);
                progressBar.style.width = percent + '%';
                progressBar.textContent = percent + '%';
                progressText.textContent = '正在壓縮第 ' + (completed + 1) + ' / ' + total + ' 個影片...';
                
                var xhr = new XMLHttpRequest();
                xhr.open('GET', 'manage_compression.php?ajax=compress&queue_id=' + queueId, true);
                xhr.timeout = 600000;
                
                xhr.onload = function() {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        results.push(data);
                    } catch (e) {
                        results.push({success: false, error: '解析回應失敗'});
                    }
                    completed++;
                    processNext();
                };
                
                xhr.onerror = function() {
                    results.push({success: false, error: '網路錯誤'});
                    completed++;
                    processNext();
                };
                
                xhr.ontimeout = function() {
                    results.push({success: false, error: '請求超時'});
                    completed++;
                    processNext();
                };
                
                xhr.send();
            }
            
            processNext();
        });
    }
    
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            if (!confirm('確定要重設所有「處理中」狀態的項目嗎？')) return;
            
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'manage_compression.php?ajax=reset', true);
            xhr.onload = function() {
                try {
                    var data = JSON.parse(xhr.responseText);
                    alert(data.message || data.error);
                    location.reload();
                } catch (e) {
                    alert('操作失敗');
                }
            };
            xhr.send();
        });
    }
    
    // 單獨取消某個項目
    document.querySelectorAll('.cancel-item-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('確定要從佇列中移除這個項目嗎？')) return;
            
            var queueId = this.dataset.id;
            var row = this.closest('tr');
            
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'manage_compression.php?ajax=cancel&queue_id=' + queueId, true);
            xhr.onload = function() {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        row.remove();
                        // 如果有錯誤訊息行也一併移除
                        var nextRow = row.nextElementSibling;
                        if (nextRow && nextRow.classList.contains('table-danger')) {
                            nextRow.remove();
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
    
    // 加入佇列按鈕
    document.querySelectorAll('.add-to-queue-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var fileId = this.dataset.fileId;
            var contextId = this.dataset.contextId;
            var filename = this.dataset.filename;
            var row = this.closest('tr');
            var button = this;
            
            button.disabled = true;
            button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 加入中...';
            
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'manage_compression.php?ajax=add_to_queue&file_id=' + fileId + '&context_id=' + contextId + '&filename=' + encodeURIComponent(filename), true);
            xhr.onload = function() {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        row.remove();
                        location.reload(); // 重整以顯示新加入的項目
                    } else {
                        alert(data.error || '操作失敗');
                        button.disabled = false;
                        button.innerHTML = '<i class="fa fa-plus"></i> 加入佇列';
                    }
                } catch (e) {
                    alert('操作失敗');
                    button.disabled = false;
                    button.innerHTML = '<i class="fa fa-plus"></i> 加入佇列';
                }
            };
            xhr.send();
        });
    });
    
    // 自動刷新：檢查是否有處理中的項目
    function checkForUpdates() {
        var processingItems = document.querySelectorAll('.badge.bg-primary');
        if (processingItems.length > 0) {
            setTimeout(function() {
                location.reload();
            }, 10000);
        }
    }
    
    checkForUpdates();
});

// 備援：如果 DOMContentLoaded 已經過了，再跑一次
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(function() {
        var processingItems = document.querySelectorAll('.badge.bg-primary');
        if (processingItems.length > 0) {
            setTimeout(function() { location.reload(); }, 10000);
        }
    }, 100);
}
</script>
<?php

echo $OUTPUT->footer();
