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

namespace mod_videoprogress\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Ad-hoc 任務：使用 FFmpeg 壓縮影片檔案
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compress_video extends \core\task\adhoc_task
{

    /**
     * 取得任務名稱
     *
     * @return string
     */
    public function get_name()
    {
        return get_string('task_compress_video', 'mod_videoprogress');
    }

    /**
     * 執行壓縮任務
     */
    public function execute()
    {
        global $CFG, $DB;

        $data = $this->get_custom_data();
        $fileid = $data->fileid ?? null;

        // 在 try 外面的基本驗證，因為這些不需要回滾
        if (empty($data->contextid) || empty($fileid)) {
            mtrace('compress_video: Missing contextid or fileid');
            return;
        }

        // 檢查是否啟用壓縮
        $enabled = get_config('mod_videoprogress', 'enablecompression');
        if (!$enabled) {
            mtrace('compress_video: Compression is disabled');
            return;
        }

        // =========================================================================
        // 離峰時段檢查
        // =========================================================================
        $offpeakEnabled = get_config('mod_videoprogress', 'offpeakhours');
        if ($offpeakEnabled) {
            $currentHour = (int) date('G'); // 0-23
            $startHour = (int) get_config('mod_videoprogress', 'offpeakstart');
            $endHour = (int) get_config('mod_videoprogress', 'offpeakend');

            // 處理跨午夜的情況（例如 22:00 - 06:00）
            $inOffpeak = false;
            if ($startHour <= $endHour) {
                // 例如 02:00 - 06:00
                $inOffpeak = ($currentHour >= $startHour && $currentHour < $endHour);
            } else {
                // 例如 22:00 - 06:00（跨午夜）
                $inOffpeak = ($currentHour >= $startHour || $currentHour < $endHour);
            }

            if (!$inOffpeak) {
                mtrace('compress_video: Outside off-peak hours (' . sprintf('%02d:00-%02d:00', $startHour, $endHour) . '), current: ' . $currentHour . ':00');
                return;
            }
        }

        // 取得 FFmpeg 路徑
        $ffmpegpath = get_config('mod_videoprogress', 'ffmpegpath');
        if (empty($ffmpegpath) || !file_exists($ffmpegpath)) {
            mtrace('compress_video: FFmpeg not found at: ' . $ffmpegpath);
            return;
        }

        // 取得 CRF 值
        $crf = get_config('mod_videoprogress', 'compressioncrf');
        if (empty($crf)) {
            $crf = '23'; // 預設中品質
        }

        // 取得檔案
        $fs = get_file_storage();
        $file = $fs->get_file_by_id($data->fileid);

        if (!$file || $file->is_directory()) {
            mtrace('compress_video: File not found or is directory');
            return;
        }

        // 只處理影片格式，統一壓縮成 mp4
        $filename = $file->get_filename();
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $videoExtensions = ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv', 'wmv', 'flv', '3gp', 'ts', 'mts', 'm2ts', 'ogv', 'rm', 'rmvb'];

        if (!in_array($ext, $videoExtensions)) {
            mtrace('compress_video: Not a video file: ' . $filename);
            return;
        }

        mtrace('compress_video: Starting compression for ' . $filename);

        // 更新佇列狀態為「處理中」
        require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');

        // =========================================================================
        // 並發限制：最多同時 2 個壓縮任務
        // =========================================================================
        $maxConcurrent = 2;
        $processingCount = $DB->count_records('videoprogress_compress_queue', ['status' => 'processing']);
        if ($processingCount >= $maxConcurrent) {
            mtrace('compress_video: Too many concurrent compressions (' . $processingCount . '/' . $maxConcurrent . '), will retry later');
            return; // 下次 cron 再處理
        }

        videoprogress_queue_processing($data->fileid);

        // 建立暫存目錄
        $tempdir = $CFG->tempdir . '/videoprogress_compress';
        if (!is_dir($tempdir)) {
            mkdir($tempdir, 0777, true);
        }

        // =========================================================================
        // 磁碟空間檢查：需要檔案大小的 2 倍空間（輸入 + 輸出）
        // =========================================================================
        $filesize = $file->get_filesize();
        $requiredSpace = $filesize * 2;
        $freeSpace = disk_free_space($tempdir);

        if ($freeSpace !== false && $freeSpace < $requiredSpace) {
            mtrace('compress_video: Not enough disk space. Required: ' . round($requiredSpace / 1024 / 1024) . ' MB, Available: ' . round($freeSpace / 1024 / 1024) . ' MB');
            videoprogress_queue_failed($data->fileid, 'Disk space insufficient');
            return;
        }

        // 複製檔案到暫存目錄
        $inputpath = $tempdir . '/input_' . $data->fileid . '.' . $ext;
        $outputpath = $tempdir . '/output_' . $data->fileid . '.mp4';

        $file->copy_content_to($inputpath);

        // 記錄原始大小
        $originalSize = filesize($inputpath);
        mtrace('compress_video: Original size: ' . $this->format_bytes($originalSize));

        // 檔案大小門檻：小於 50MB 的檔案不壓縮（不值得）
        $minSize = 50 * 1024 * 1024; // 50MB
        if ($originalSize < $minSize) {
            mtrace('compress_video: File too small (' . $this->format_bytes($originalSize) . ' < 50MB), skipping');
            @unlink($inputpath);
            return;
        }

        // 組合 FFmpeg 壓縮指令
        // 使用 H.264 編碼，指定 CRF 值，音訊轉 AAC，moov atom 移到開頭
        // CPU 保護：限制使用 2 個 CPU 核心
        $ffmpegCmd = escapeshellcmd($ffmpegpath) . ' -i ' . escapeshellarg($inputpath)
            . ' -threads 2'  // 限制最多使用 2 個 CPU 核心
            . ' -c:v libx264 -crf ' . escapeshellarg($crf)
            . ' -preset medium -c:a aac -b:a 128k -movflags +faststart -y '
            . escapeshellarg($outputpath) . ' 2>&1';

        // 根據作業系統使用不同的優先級控制
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: 為了能正確取得 PID，我們直接執行 ffmpeg，不使用 start /B 封裝
            // (Windows 的 proc_open PID 指向 cmd.exe 或 ffmpeg.exe)
            $command = $ffmpegCmd;
        } else {
            // Linux/Unix：使用 nice 設定低優先級
            // nice 會執行 exec，所以 PID 應該是 ffmpeg 進程
            $command = 'nice -n 15 ' . $ffmpegCmd;
        }

        // Linux 系統負載檢查
        if (PHP_OS_FAMILY !== 'Windows') {
            $loadAvg = sys_getloadavg();
            $cpuCount = (int) shell_exec('nproc') ?: 4;
            $loadThreshold = $cpuCount * 0.8;

            $waitCount = 0;
            while ($loadAvg[0] > $loadThreshold && $waitCount < 10) {
                mtrace('compress_video: System load high (' . round($loadAvg[0], 2) . ' > ' . $loadThreshold . '), waiting 30s...');
                sleep(30);
                $loadAvg = sys_getloadavg();
                $waitCount++;
            }
        } else {
            // [New] Windows CPU 使用率檢查
            $waitCount = 0;
            $output = @shell_exec('wmic cpu get loadpercentage /value');
            if ($output && preg_match('/LoadPercentage=(\d+)/', $output, $matches)) {
                $cpuLoad = (int)$matches[1];
                while ($cpuLoad > 80 && $waitCount < 10) {
                    mtrace('compress_video: CPU load high (' . $cpuLoad . '%), waiting 30s...');
                    sleep(30);
                    $output = @shell_exec('wmic cpu get loadpercentage /value');
                    if ($output && preg_match('/LoadPercentage=(\d+)/', $output, $matches)) {
                        $cpuLoad = (int)$matches[1];
                    }
                    $waitCount++;
                }
            }
        }

        mtrace('compress_video: Executing with proc_open to capture PID');
        mtrace('compress_video: Command: ' . $command);

        // 使用 proc_open 取代 exec
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w']  // stderr (FFmpeg 輸出通常在這裡)
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (is_resource($process)) {
            // 取得 PID 並更新資料庫
            $status = proc_get_status($process);
            $pid = $status['pid'];

            mtrace('compress_video: Started process with PID: ' . $pid);

            // 更新 PID 到資料庫
            // 需先找到對應的 queue 紀錄 id
            $queueRecord = $DB->get_record('videoprogress_compress_queue', ['fileid' => $data->fileid, 'status' => 'processing']);
            if ($queueRecord) {
                $DB->set_field('videoprogress_compress_queue', 'pid', $pid, ['id' => $queueRecord->id]);
            }

            // 讀取輸出 (會阻塞直到結束)
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            // 等待進程結束並取得回傳碼
            $returnCode = proc_close($process);

            // 合併輸出供除錯
            $output = explode("\n", $stdout . "\n" . $stderr);
        } else {
            mtrace('compress_video: Failed to open process');
            $returnCode = -1;
            $output = ['Failed to open process'];
        }

        if ($returnCode !== 0) {
            mtrace('compress_video: FFmpeg failed with code ' . $returnCode);
            mtrace('compress_video: Output: ' . implode("\n", array_slice($output, -10))); // 取最後 10 行
            // 清理暫存檔
            @unlink($inputpath);
            @unlink($outputpath);
            return;
        }

        // 檢查輸出檔案
        if (!file_exists($outputpath)) {
            mtrace('compress_video: Output file not created');
            videoprogress_queue_failed($data->fileid, 'Output file not created');
            @unlink($inputpath);
            return;
        }

        $compressedSize = filesize($outputpath);
        mtrace('compress_video: Compressed size: ' . $this->format_bytes($compressedSize));

        // 只有在壓縮後變小時才替換
        if ($compressedSize >= $originalSize) {
            mtrace('compress_video: Compressed file is not smaller, keeping original');
            @unlink($inputpath);
            @unlink($outputpath);
            // 標記為完成（雖然沒有壓縮，但不再需要處理）
            videoprogress_queue_complete($data->fileid);
            return;
        }

        // 計算節省的空間
        $saved = $originalSize - $compressedSize;
        $percent = round(($saved / $originalSize) * 100, 1);
        mtrace('compress_video: Saved ' . $this->format_bytes($saved) . ' (' . $percent . '%)');

        // 替換原始檔案
        $newfile = null;
        try {
            // 使用臨時檔名建立壓縮後的檔案（避免覆蓋原始檔案）
            $tempFilename = pathinfo($filename, PATHINFO_FILENAME) . '_tmp_' . time() . '.mp4';
            $filerecord = [
                'contextid' => $file->get_contextid(),
                'component' => $file->get_component(),
                'filearea' => $file->get_filearea(),
                'itemid' => $file->get_itemid(),
                'filepath' => $file->get_filepath(),
                'filename' => $tempFilename,
            ];

            // 先建立臨時檔案
            $tempfile = $fs->create_file_from_pathname($filerecord, $outputpath);

            if (!$tempfile) {
                throw new \Exception('Failed to create compressed file in Moodle storage');
            }

            // 刪除原始檔案
            $file->delete();

            // 用原始檔名重新建立檔案
            $finalFilename = pathinfo($filename, PATHINFO_FILENAME) . '.mp4';
            $finalFilerecord = [
                'contextid' => $tempfile->get_contextid(),
                'component' => $tempfile->get_component(),
                'filearea' => $tempfile->get_filearea(),
                'itemid' => $tempfile->get_itemid(),
                'filepath' => $tempfile->get_filepath(),
                'filename' => $finalFilename,
            ];

            // 從臨時檔案複製內容到最終檔案
            $newfile = $fs->create_file_from_storedfile($finalFilerecord, $tempfile);

            // 刪除臨時檔案
            $tempfile->delete();

            // 記錄壓縮歷史
            $this->log_compression($data->contextid, $newfile->get_id(), $filename, $originalSize, $compressedSize, $crf);

            // 標記佇列為完成
            videoprogress_queue_complete($data->fileid);

            mtrace('compress_video: Successfully replaced with compressed version');

        } catch (\Exception $e) {
            mtrace('compress_video: Error replacing file: ' . $e->getMessage());

            // 如果新檔案已經建立但後續步驟失敗，刪除新檔案避免佔用空間
            if ($newfile && $newfile instanceof \stored_file) {
                try {
                    $newfile->delete();
                    mtrace('compress_video: Cleaned up partial compressed file');
                } catch (\Exception $cleanupE) {
                    mtrace('compress_video: Failed to cleanup: ' . $cleanupE->getMessage());
                }
            }

            // 標記佇列為失敗
            videoprogress_queue_failed($data->fileid, $e->getMessage());
        }

        // 清理暫存檔
        @unlink($inputpath);
        @unlink($outputpath);

        mtrace('compress_video: Task completed');
    }

    /**
     * 格式化檔案大小
     *
     * @param int $bytes 位元組數
     * @return string 格式化後的字串
     */
    private function format_bytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 處理任務執行中的錯誤
     * 將項目狀態回滾到 pending，讓下次 cron 可以重試
     *
     * @param int|null $fileid 檔案 ID
     * @param string $error 錯誤訊息
     */
    private function handle_execution_error(?int $fileid, string $error): void
    {
        global $DB, $CFG;

        mtrace('compress_video: Error occurred - ' . $error);

        if ($fileid) {
            require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');

            // 嘗試將狀態改回 pending，讓下次 cron 重試
            $item = $DB->get_record('videoprogress_compress_queue', ['fileid' => $fileid]);
            if ($item && $item->status === 'processing') {
                $item->status = 'pending';
                $item->timemodified = time();
                $item->attempts = ($item->attempts ?? 0) + 1;
                $item->last_error = $error;

                // 超過 3 次就標記失敗
                if ($item->attempts >= 3) {
                    $item->status = 'failed';
                    mtrace('compress_video: Item marked as failed after 3 attempts');
                }

                $DB->update_record('videoprogress_compress_queue', $item);
                mtrace('compress_video: Item status rolled back to ' . $item->status);
            }
        }
    }

    /**
     * 記錄壓縮歷史到資料庫
     *
     * @param int $contextid Context ID
     * @param int $fileid 壓縮後的檔案 ID
     * @param string $filename 檔案名稱
     * @param int $originalSize 原始大小
     * @param int $compressedSize 壓縮後大小
     * @param int $crf CRF 值
     */
    private function log_compression($contextid, $fileid, $filename, $originalSize, $compressedSize, $crf)
    {
        global $DB;

        // 確保資料表存在
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('videoprogress_compression_log');

        if (!$dbman->table_exists($table)) {
            // 建立資料表
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
            $table->add_index('contextid_idx', XMLDB_INDEX_NOTUNIQUE, ['contextid']);

            $dbman->create_table($table);
            mtrace('compress_video: Created compression_log table');
        }

        // 計算節省的空間
        $savedSize = $originalSize - $compressedSize;
        $savedPercent = round(($savedSize / $originalSize) * 100, 2);

        // 插入記錄
        $record = new \stdClass();
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
        mtrace('compress_video: Logged compression result');
    }
}

