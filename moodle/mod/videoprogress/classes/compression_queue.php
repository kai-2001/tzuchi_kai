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
 * 壓縮佇列管理
 *
 * 這個檔案負責處理影片壓縮的排隊機制，
 * 包含失敗重試、狀態追蹤等功能。
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * 確保壓縮佇列的資料表存在，沒有的話就建一個
 */
function videoprogress_ensure_queue_table() {
    global $DB;
    
    $dbman = $DB->get_manager();
    $table = new xmldb_table('videoprogress_compress_queue');
    
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('last_error', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('fileid_idx', XMLDB_INDEX_UNIQUE, ['fileid']);
        
        $dbman->create_table($table);
    }
}

/**
 * 把檔案丟進壓縮佇列裡排隊
 */
function videoprogress_queue_add($contextid, $fileid, $filename) {
    global $DB;
    
    videoprogress_ensure_queue_table();
    
    // 已經在佇列裡了就不用再加
    if ($DB->record_exists('videoprogress_compress_queue', ['fileid' => $fileid])) {
        return;
    }
    
    $record = new stdClass();
    $record->contextid = $contextid;
    $record->fileid = $fileid;
    $record->filename = $filename;
    $record->status = 'pending';
    $record->attempts = 0;
    $record->timecreated = time();
    $record->timemodified = time();
    
    $DB->insert_record('videoprogress_compress_queue', $record);
}

/**
 * 標記這個項目正在處理中
 */
function videoprogress_queue_processing($fileid) {
    global $DB;
    
    $DB->set_field('videoprogress_compress_queue', 'status', 'processing', ['fileid' => $fileid]);
    $DB->set_field('videoprogress_compress_queue', 'timemodified', time(), ['fileid' => $fileid]);
}

/**
 * 壓縮完成，從佇列中移除
 */
function videoprogress_queue_complete($fileid) {
    global $DB;
    
    $DB->delete_records('videoprogress_compress_queue', ['fileid' => $fileid]);
}

/**
 * 標記為失敗，等一下會自動重試
 */
function videoprogress_queue_failed($fileid, $error) {
    global $DB;
    
    $record = $DB->get_record('videoprogress_compress_queue', ['fileid' => $fileid]);
    if ($record) {
        $record->status = 'failed';
        $record->attempts = $record->attempts + 1;
        $record->last_error = $error;
        $record->timemodified = time();
        
        // 試了 3 次還失敗就放棄吧
        if ($record->attempts >= 3) {
            $record->status = 'abandoned';
        }
        
        $DB->update_record('videoprogress_compress_queue', $record);
    }
}

/**
 * 撈出下一個要處理的項目
 */
function videoprogress_queue_get_next() {
    global $DB;
    
    videoprogress_ensure_queue_table();
    
    // 找等待中的，或是失敗但已經過了 5 分鐘可以重試的
    $sql = "SELECT * FROM {videoprogress_compress_queue} 
            WHERE (status = 'pending' OR (status = 'failed' AND timemodified < :retrytime))
            AND attempts < 3
            ORDER BY timecreated ASC
            LIMIT 1";
    
    return $DB->get_record_sql($sql, ['retrytime' => time() - 300]);
}

/**
 * 處理一個待處理的壓縮項目（從 view.php 或 cron 呼叫）
 * 使用同步處理以避免背景程序中斷
 * 如果有處理到任務則回傳 true
 * 
 * CPU 保護措施（參考 YouTube/Bilibili）：
 * - 一次只處理一個任務
 * - 使用 nice/low priority 執行
 * - 限制 FFmpeg 線程數
 */
function videoprogress_process_queue_item() {
    global $CFG, $DB;
    
    // 先確認壓縮功能有開
    if (!get_config('mod_videoprogress', 'enablecompression')) {
        return false;
    }
    
    // CPU 保護：檢查是否已有任務在處理中（防止多個 cron 同時執行）
    $processingCount = $DB->count_records('videoprogress_compress_queue', ['status' => 'processing']);
    if ($processingCount > 0) {
        // 已有任務在處理，等待
        return false;
    }
    
    // 先把卡住超過 10 分鐘的項目重設
    videoprogress_reset_stuck_items();
    
    $item = videoprogress_queue_get_next();
    if (!$item) {
        return false;
    }
    
    // 標記為處理中
    videoprogress_queue_processing($item->fileid);
    
    // 同步處理，不用背景執行
    try {
        videoprogress_do_compression($item);
        return true;
    } catch (Exception $e) {
        videoprogress_queue_failed($item->fileid, $e->getMessage());
        return false;
    }
}

/**
 * 重設那些卡住太久的項目
 */
function videoprogress_reset_stuck_items() {
    global $DB;
    
    videoprogress_ensure_queue_table();
    
    // 找那些「處理中」但超過 10 分鐘的，可能是壞掉了
    $stuckTime = time() - 600;
    $stuck = $DB->get_records_select(
        'videoprogress_compress_queue',
        "status = 'processing' AND timemodified < :stucktime",
        ['stucktime' => $stuckTime]
    );
    
    foreach ($stuck as $item) {
        $item->status = 'failed';
        $item->attempts = $item->attempts + 1;
        $item->last_error = 'Process timed out';
        $item->timemodified = time();
        
        if ($item->attempts >= 3) {
            $item->status = 'abandoned';
        }
        
        $DB->update_record('videoprogress_compress_queue', $item);
    }
}

/**
 * 真正開始壓縮影片的地方
 */
function videoprogress_do_compression($item) {
    global $CFG, $DB;
    
    $fs = get_file_storage();
    $file = $fs->get_file_by_id($item->fileid);
    
    if (!$file || $file->is_directory()) {
        videoprogress_queue_failed($item->fileid, 'File not found');
        return;
    }
    
    $filename = $file->get_filename();
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // 讀取設定
    $ffmpegpath = get_config('mod_videoprogress', 'ffmpegpath');
    $crf = get_config('mod_videoprogress', 'compressioncrf') ?: '23';
    
    if (empty($ffmpegpath) || !file_exists($ffmpegpath)) {
        videoprogress_queue_failed($item->fileid, 'FFmpeg not found');
        return;
    }
    
    // 開一個暫存目錄來工作
    $tempdir = $CFG->tempdir . '/videoprogress_compress';
    if (!is_dir($tempdir)) {
        mkdir($tempdir, 0777, true);
    }
    
    $inputpath = $tempdir . '/input_' . $item->fileid . '.' . $ext;
    $outputpath = $tempdir . '/output_' . $item->fileid . '.mp4';
    
    // 把檔案複製到暫存區
    $file->copy_content_to($inputpath);
    
    $originalSize = filesize($inputpath);
    
    // 小於 50MB 的就不壓了，沒什麼意義
    $minSize = 50 * 1024 * 1024;
    if ($originalSize < $minSize) {
        videoprogress_queue_complete($item->fileid);
        @unlink($inputpath);
        return;
    }
    
    // 建立含 CPU 保護的 FFmpeg 指令
    // 參考 YouTube/Bilibili 等大平台的做法
    $ffmpegCmd = escapeshellcmd($ffmpegpath) . ' -i ' . escapeshellarg($inputpath)
        . ' -threads 2'  // 限制最多使用 2 個 CPU 核心
        . ' -c:v libx264 -crf ' . escapeshellarg($crf)
        . ' -preset medium -c:a aac -b:a 128k -movflags +faststart -y '
        . escapeshellarg($outputpath) . ' 2>&1';
    
    // 根據作業系統使用不同的優先級控制
    if (PHP_OS_FAMILY === 'Windows') {
        // Windows: 以低優先級執行
        $command = 'start /B /LOW /WAIT ' . $ffmpegCmd;
    } else {
        // Linux/Unix: 使用 nice 設定低優先級
        $command = 'nice -n 15 ' . $ffmpegCmd;
    }
    
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        videoprogress_queue_failed($item->fileid, 'FFmpeg error: ' . implode("\n", array_slice($output, -5)));
        @unlink($inputpath);
        @unlink($outputpath);
        return;
    }
    
    if (!file_exists($outputpath)) {
        videoprogress_queue_failed($item->fileid, 'Output file not created');
        @unlink($inputpath);
        return;
    }
    
    $compressedSize = filesize($outputpath);
    
    // 壓完反而變大的話就不要了
    if ($compressedSize >= $originalSize) {
        videoprogress_queue_complete($item->fileid);
        @unlink($inputpath);
        @unlink($outputpath);
        return;
    }
    
    // 用壓縮後的檔案取代原本的
    $filerecord = [
        'contextid' => $file->get_contextid(),
        'component' => $file->get_component(),
        'filearea' => $file->get_filearea(),
        'itemid' => $file->get_itemid(),
        'filepath' => $file->get_filepath(),
        'filename' => pathinfo($filename, PATHINFO_FILENAME) . '.mp4',
    ];
    
    $file->delete();
    $newfile = $fs->create_file_from_pathname($filerecord, $outputpath);
    
    // 記錄一下壓縮結果
    videoprogress_log_compression($item->contextid, $newfile->get_id(), $filename, $originalSize, $compressedSize, $crf);
    
    // 標記完成
    videoprogress_queue_complete($item->fileid);
    
    // 清理暫存檔
    @unlink($inputpath);
    @unlink($outputpath);
}

/**
 * 把壓縮結果寫進資料庫留存
 */
function videoprogress_log_compression($contextid, $fileid, $filename, $originalSize, $compressedSize, $crf) {
    global $DB;
    
    // 確保記錄用的資料表存在
    $dbman = $DB->get_manager();
    $table = new xmldb_table('videoprogress_compression_log');
    
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('original_size', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('compressed_size', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('saved_size', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('saved_percent', XMLDB_TYPE_NUMBER, '5', null, XMLDB_NOTNULL, null, null, 2);
        $table->add_field('crf', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($table);
    }
    
    $savedSize = $originalSize - $compressedSize;
    $savedPercent = round(($savedSize / $originalSize) * 100, 2);
    
    $record = new stdClass();
    $record->contextid = $contextid;
    $record->fileid = $fileid;
    $record->filename = $filename;
    $record->original_size = $originalSize;
    $record->compressed_size = $compressedSize;
    $record->saved_size = $savedSize;
    $record->saved_percent = $savedPercent;
    $record->crf = $crf;
    $record->timecreated = time();
    
    $DB->insert_record('videoprogress_compression_log', $record);
}

/**
 * 統計佇列狀態，給管理頁面用的
 */
function videoprogress_queue_stats() {
    global $DB;
    
    videoprogress_ensure_queue_table();
    
    return [
        'pending' => $DB->count_records('videoprogress_compress_queue', ['status' => 'pending']),
        'processing' => $DB->count_records('videoprogress_compress_queue', ['status' => 'processing']),
        'failed' => $DB->count_records('videoprogress_compress_queue', ['status' => 'failed']),
        'abandoned' => $DB->count_records('videoprogress_compress_queue', ['status' => 'abandoned']),
    ];
}
