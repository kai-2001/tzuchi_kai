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
 * 確保壓縮佇列的資料表欄位完整
 * 
 * [Note] 資料表應該由 install.xml 或 upgrade.php 建立
 *        這裡只處理舊版本升級時可能缺少的欄位
 */
function videoprogress_ensure_queue_table()
{
    global $DB;

    $dbman = $DB->get_manager();
    $table = new xmldb_table('videoprogress_compress_queue');

    // 如果表不存在，表示安裝不完整，記錄錯誤但不動態建立
    if (!$dbman->table_exists($table)) {
        debugging('videoprogress: compress_queue table does not exist. Please run Moodle upgrade.', DEBUG_NORMAL);
        return false;
    }

    // [Backwards Compat] 舊版本可能缺少 pid 欄位，自動新增
    $pidField = new xmldb_field('pid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'status');
    if (!$dbman->field_exists($table, $pidField)) {
        $dbman->add_field($table, $pidField);
    }

    return true;
}

/**
 * 把檔案丟進壓縮佇列裡排隊
 */
function videoprogress_queue_add($contextid, $fileid, $filename)
{
    global $DB;

    videoprogress_ensure_queue_table();

    // 已經在佇列裡了，檢查狀態
    if ($record = $DB->get_record('videoprogress_compress_queue', ['fileid' => $fileid])) {
        // 如果是 abandoned 或 failed，重設為 pending 讓它重跑
        // 如果是 processing/pending 其實也可以重設（相當於強制重新排隊）
        $record->status = 'pending';
        $record->attempts = 0;
        $record->timemodified = time();
        $record->last_error = null; // 清除錯誤訊息
        $DB->update_record('videoprogress_compress_queue', $record);
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
function videoprogress_queue_processing($fileid)
{
    global $DB;

    $DB->set_field('videoprogress_compress_queue', 'status', 'processing', ['fileid' => $fileid]);
    $DB->set_field('videoprogress_compress_queue', 'timemodified', time(), ['fileid' => $fileid]);
}

/**
 * 壓縮完成，從佇列中移除
 */
function videoprogress_queue_complete($fileid)
{
    global $DB;

    $DB->delete_records('videoprogress_compress_queue', ['fileid' => $fileid]);
}

/**
 * 標記為失敗，等一下會自動重試
 */
function videoprogress_queue_failed($fileid, $error)
{
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
function videoprogress_queue_get_next()
{
    global $DB;

    videoprogress_ensure_queue_table();

    // 找等待中的，或是失敗但已經過了 5 分鐘可以重試的
    $sql = "SELECT * FROM {videoprogress_compress_queue} 
            WHERE (status = 'pending' OR (status = 'failed' AND timemodified < :retrytime))
            AND attempts < 3
            ORDER BY priority DESC, timecreated ASC
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
function videoprogress_process_queue_item()
{
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
function videoprogress_reset_stuck_items()
{
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
function videoprogress_do_compression($item)
{
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

    // [Recovery] 檢查是否有上次殘留的壓縮成功檔案 (殭屍復活機制)
    if (file_exists($outputpath) && filesize($outputpath) > 1024) {
        // 發現壓縮好的檔案，且大小合理，直接跳過 FFmpeg
        // 這裡可以加個 Log 或標記，但為了簡單直接繼續往下跑
    } else {
        // 只有在沒有現成檔案時，才執行 FFmpeg
        // 建立含 CPU 保護的 FFmpeg 指令
        // 參考 YouTube/Bilibili 等大平台的做法
        $ffmpegCmd = escapeshellcmd($ffmpegpath) . ' -i ' . escapeshellarg($inputpath)
            . ' -threads 2'  // 限制最多使用 2 個 CPU 核心
            . ' -c:v libx264 -crf ' . escapeshellarg($crf)
            . ' -preset medium -c:a aac -b:a 128k -movflags +faststart -y '
            . escapeshellarg($outputpath);

        // 根據作業系統使用不同的優先級控制
        if (PHP_OS_FAMILY === 'Windows') {
            // [Windows Option A] 混合模式：shell_exec 讓 FFmpeg 可見 + tasklist 查詢 PID
            // 這是最穩定、相容性最高的方案

            // 移除共用變數中的 2>&1 (Windows 不需要，且會導致 start 指令解析錯誤)
            $cmdWindows = str_replace(' 2>&1', '', $ffmpegCmd);

            // Step 1: 使用 shell_exec('start /B') 啟動 FFmpeg，使其在工作管理員可見
            // 不使用 /WAIT，讓它在背景執行。加上空白標題 "" 避免 Windows 解析錯誤
            $command = 'start "" /B /LOW ' . $cmdWindows;
            shell_exec($command); // 使用 shell_exec 避免 stdin 管道導致 FFmpeg 卡住

            // Step 2: 等待一下讓 FFmpeg 啟動
            sleep(1);

            // Step 3: 使用 tasklist 查詢 ffmpeg.exe 的 PID (比 WMIC 更可靠)
            $tasklistOutput = [];
            exec('tasklist /FI "IMAGENAME eq ffmpeg.exe" /FO CSV /NH 2>&1', $tasklistOutput);
            $pid = null;
            foreach ($tasklistOutput as $line) {
                // CSV 格式: "ffmpeg.exe","12345","Console","1","516,632 K"
                if (preg_match('/"ffmpeg\.exe","(\d+)"/', $line, $matches)) {
                    $pid = intval($matches[1]);
                    break;
                }
            }

            // 儲存 PID (抓到最好，抓不到也沒關係，因為 Reset 會用霰彈槍)
            if ($pid) {
                $DB->set_field('videoprogress_compress_queue', 'pid', $pid, ['fileid' => $item->fileid]);
            }

            // Step 4: 等待 FFmpeg 完成 (輪詢檢查進程是否還在執行)
            $returnCode = 0;
            $output = [];
            $maxWaitTime = 3600; // 最多等待 1 小時
            $startTime = time();

            while (true) {
                // 檢查 FFmpeg 是否還在執行
                $checkOutput = [];
                exec('tasklist /FI "IMAGENAME eq ffmpeg.exe" 2>&1', $checkOutput);
                $isRunning = false;
                foreach ($checkOutput as $line) {
                    if (stripos($line, 'ffmpeg.exe') !== false) {
                        $isRunning = true;
                        break;
                    }
                }

                if (!$isRunning) {
                    // FFmpeg 已結束
                    break;
                }

                // 檢查是否被 Reset (狀態改為 pending)
                $currentItem = $DB->get_record('videoprogress_compress_queue', ['id' => $item->id]);
                if ($currentItem && $currentItem->status === 'pending') {
                    // 被 Reset 了，需要終止 FFmpeg
                    if ($pid) {
                        @exec('taskkill /PID ' . $pid . ' /F /T 2>&1');
                    }
                    @unlink($inputpath);
                    @unlink($outputpath);
                    return;
                }

                // 超時檢查
                if ((time() - $startTime) > $maxWaitTime) {
                    $returnCode = 1;
                    $output = ['FFmpeg timeout after 1 hour'];
                    break;
                }

                // 等待 2 秒後再檢查
                sleep(2);
            }

            // 清除 PID
            $DB->set_field('videoprogress_compress_queue', 'pid', null, ['fileid' => $item->fileid]);

            // [Fix] 生死簿檢查：再次檢查資料庫狀態
            // 如果狀態已經變成 pending，代表這是被 Reset 強制終止的，不應該繼續處理
            $currentStatus = $DB->get_field('videoprogress_compress_queue', 'status', ['fileid' => $item->fileid]);
            if ($currentStatus === 'pending') {
                return; // 直接結束，不要寫入 log 或更新成 success
            }

            // 檢查輸出檔案是否存在來判斷成功與否
            if (!file_exists($outputpath) || filesize($outputpath) < 1024) {
                $returnCode = 1;
                $output = ['FFmpeg output file not created or too small'];
            }

        } else {
            // Linux/Unix: 使用 proc_open 取得 PID，以便精確終止
            // [Fix] 使用 exec 讓 FFmpeg 取代 shell 進程，確保 PID 正確 (修正語法錯誤)
            $command = 'exec nice -n 15 ' . $ffmpegCmd . ' 2>&1';

            $descriptorspec = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w'],  // stderr
            ];

            $process = proc_open($command, $descriptorspec, $pipes);

            if (is_resource($process)) {
                // 取得 PID 並儲存到資料庫
                $status = proc_get_status($process);
                $pid = $status['pid'];

                // [Fix] 儲存 PID 到佇列，供重設時使用
                $DB->set_field('videoprogress_compress_queue', 'pid', $pid, ['fileid' => $item->fileid]);

                // 關閉輸入管道
                fclose($pipes[0]);

                // 讀取輸出（避免緩衝區滿導致阻塞）
                $output = stream_get_contents($pipes[1]);
                $errorOutput = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);

                // 等待進程結束
                $returnCode = proc_close($process);

                // 清除 PID
                $DB->set_field('videoprogress_compress_queue', 'pid', null, ['fileid' => $item->fileid]);

                $output = explode("\n", $output . "\n" . $errorOutput);
            } else {
                $returnCode = 1;
                $output = ['Failed to start FFmpeg process'];
            }
        }

        // [Fix] Windows Edge Case: start /WAIT 可能在被 taskkill 時回傳 0 (視為成功)。
        // 因此必需再次確認 DB 狀態是否被外部重設為 pending。
        // 如果是 pending，代表這是被手動停止的，不應視為壓縮成功。
        $checkStatus = $DB->get_record('videoprogress_compress_queue', ['id' => $item->id]);
        if ($checkStatus && $checkStatus->status === 'pending') {
            @unlink($inputpath);
            @unlink($outputpath);
            return;
        }

        if ($returnCode !== 0) {
            // [Fix] 檢查是否被外部 Reset 改回 Pending 了，如果是就不標記為失敗
            $currentItem = $DB->get_record('videoprogress_compress_queue', ['id' => $item->id]);
            if ($currentItem && $currentItem->status === 'pending') {
                @unlink($inputpath);
                @unlink($outputpath);
                return;
            }

            videoprogress_queue_failed($item->fileid, 'FFmpeg error: ' . implode("\n", array_slice($output, -5)));
            @unlink($inputpath);
            @unlink($outputpath);
            return;
        }
    } // End of else (Recovery check)

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

    // [Safe Replacement] 安全的檔案替換：先建新檔，成功後才刪舊檔
    // Step 1: 用暫時檔名建立壓縮後的新檔案
    $tempFilename = '_compressed_tmp_' . time() . '_' . pathinfo($filename, PATHINFO_FILENAME) . '.mp4';
    $tempFilerecord = [
        'contextid' => $file->get_contextid(),
        'component' => $file->get_component(),
        'filearea' => $file->get_filearea(),
        'itemid' => $file->get_itemid(),
        'filepath' => $file->get_filepath(),
        'filename' => $tempFilename,
    ];

    try {
        $newfile = $fs->create_file_from_pathname($tempFilerecord, $outputpath);

        if (!$newfile) {
            throw new Exception('Failed to create compressed file in Moodle file storage');
        }

        // Step 2: 新檔案成功建立後，才刪除原始檔
        $originalFileid = $file->get_id();
        $file->delete();

        // Step 3: 把暫時檔名改成正式檔名
        $finalFilename = pathinfo($filename, PATHINFO_FILENAME) . '.mp4';
        $newfile->rename($file->get_filepath(), $finalFilename);

        // Step 4: 記錄壓縮結果
        videoprogress_log_compression($item->contextid, $newfile->get_id(), $filename, $originalSize, $compressedSize, $crf);

        // Step 5: 標記完成
        videoprogress_queue_complete($item->fileid);

    } catch (Exception $e) {
        // 建立新檔案失敗，保留原始檔，清理輸出檔，標記失敗
        videoprogress_queue_failed($item->fileid, 'File replacement failed: ' . $e->getMessage());
        @unlink($outputpath);
    }

    // 清理暫存檔
    @unlink($inputpath);
    @unlink($outputpath);
}

/**
 * 把壓縮結果寫進資料庫留存
 */
function videoprogress_log_compression($contextid, $fileid, $filename, $originalSize, $compressedSize, $crf)
{
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
function videoprogress_queue_stats()
{
    global $DB;

    videoprogress_ensure_queue_table();

    return [
        'pending' => $DB->count_records('videoprogress_compress_queue', ['status' => 'pending']),
        'processing' => $DB->count_records('videoprogress_compress_queue', ['status' => 'processing']),
        'failed' => $DB->count_records('videoprogress_compress_queue', ['status' => 'failed']),
        'abandoned' => $DB->count_records('videoprogress_compress_queue', ['status' => 'abandoned']),
    ];
}
