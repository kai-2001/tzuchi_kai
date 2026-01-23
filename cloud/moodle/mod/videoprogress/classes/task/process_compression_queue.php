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
 * 排程任務：處理影片壓縮佇列
 *
 * 此任務每次從佇列中處理一部影片的壓縮。
 * 透過 Moodle 的排程任務系統運作（需要 cron）。
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoprogress\task;

defined('MOODLE_INTERNAL') || die();

class process_compression_queue extends \core\task\scheduled_task {

    /**
     * 取得任務名稱
     */
    public function get_name() {
        return get_string('task_process_compression', 'mod_videoprogress');
    }

    /**
     * 執行任務
     */
    public function execute() {
        global $CFG;

        // 檢查是否啟用壓縮
        if (!get_config('mod_videoprogress', 'enablecompression')) {
            mtrace('Video compression is disabled');
            return;
        }

        // 檢查 FFmpeg
        $ffmpegpath = get_config('mod_videoprogress', 'ffmpegpath');
        if (empty($ffmpegpath) || !file_exists($ffmpegpath)) {
            mtrace('FFmpeg not found at: ' . $ffmpegpath);
            return;
        }

        require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');

        // 重設卡住的項目（處理超過 10 分鐘）
        $this->reset_stuck_items();

        // 逐一處理佇列項目
        $processed = 0;
        $maxItems = 5; // 每次 cron 最多處理 5 個項目

        while ($processed < $maxItems) {
            $item = videoprogress_queue_get_next();
            if (!$item) {
                break;
            }

            mtrace("Processing compression for file: {$item->filename} (ID: {$item->fileid})");
            
            // 直接處理此項目（非背景程序）
            $this->process_item($item);
            $processed++;
        }

        if ($processed > 0) {
            mtrace("Processed {$processed} compression tasks");
        } else {
            mtrace("No pending compression tasks");
        }
    }

    /**
     * 重設卡在「處理中」狀態過久的項目
     */
    private function reset_stuck_items() {
        global $DB;

        // 尋找處理中超過 10 分鐘的項目
        $stuckTime = time() - 600; // 10 分鐘
        $stuck = $DB->get_records_select(
            'videoprogress_compress_queue',
            "status = 'processing' AND timemodified < :stucktime",
            ['stucktime' => $stuckTime]
        );

        foreach ($stuck as $item) {
            mtrace("Resetting stuck item: {$item->filename}");
            $item->status = 'failed';
            $item->attempts = $item->attempts + 1;
            $item->last_error = 'Process timed out (stuck for >10 minutes)';
            $item->timemodified = time();

            if ($item->attempts >= 3) {
                $item->status = 'abandoned';
            }

            $DB->update_record('videoprogress_compress_queue', $item);
        }
    }

    /**
     * 處理單一壓縮項目
     */
    private function process_item($item) {
        global $CFG, $DB;

        // 標記為處理中
        videoprogress_queue_processing($item->fileid);

        // 取得檔案
        $fs = get_file_storage();
        $file = $fs->get_file_by_id($item->fileid);

        if (!$file || $file->is_directory()) {
            videoprogress_queue_failed($item->fileid, 'File not found');
            return;
        }

        $filename = $file->get_filename();
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // 取得設定
        $ffmpegpath = get_config('mod_videoprogress', 'ffmpegpath');
        $crf = get_config('mod_videoprogress', 'compressioncrf') ?: '23';

        // 建立暫存目錄
        $tempdir = $CFG->tempdir . '/videoprogress_compress';
        if (!is_dir($tempdir)) {
            mkdir($tempdir, 0777, true);
        }

        $inputpath = $tempdir . '/input_' . $item->fileid . '.' . $ext;
        $outputpath = $tempdir . '/output_' . $item->fileid . '.mp4';

        // 複製檔案至暫存區
        $file->copy_content_to($inputpath);

        $originalSize = filesize($inputpath);
        mtrace("Original size: " . $this->format_bytes($originalSize));

        // 50MB 門檻值
        $minSize = 50 * 1024 * 1024;
        if ($originalSize < $minSize) {
            mtrace("File too small, skipping");
            videoprogress_queue_complete($item->fileid);
            @unlink($inputpath);
            return;
        }

        // 建立 FFmpeg 指令
        $command = escapeshellcmd($ffmpegpath) . ' -i ' . escapeshellarg($inputpath)
            . ' -c:v libx264 -crf ' . escapeshellarg($crf)
            . ' -preset medium -c:a aac -b:a 128k -movflags +faststart -y '
            . escapeshellarg($outputpath) . ' 2>&1';

        mtrace("Executing: " . $command);

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            mtrace('FFmpeg failed with code ' . $returnCode);
            videoprogress_queue_failed($item->fileid, 'FFmpeg failed: ' . implode("\n", array_slice($output, -5)));
            @unlink($inputpath);
            @unlink($outputpath);
            return;
        }

        if (!file_exists($outputpath)) {
            mtrace('Output file not created');
            videoprogress_queue_failed($item->fileid, 'Output file not created');
            @unlink($inputpath);
            return;
        }

        $compressedSize = filesize($outputpath);
        mtrace("Compressed size: " . $this->format_bytes($compressedSize));

        // 只有壓縮後較小才取代
        if ($compressedSize >= $originalSize) {
            mtrace('Compressed file is not smaller, keeping original');
            videoprogress_queue_complete($item->fileid);
            @unlink($inputpath);
            @unlink($outputpath);
            return;
        }

        // 取代原始檔案
        try {
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

            // 記錄壓縮結果
            $this->log_compression($item->contextid, $newfile->get_id(), $filename, $originalSize, $compressedSize, $crf);

            // 標記完成
            videoprogress_queue_complete($item->fileid);

            $saved = $originalSize - $compressedSize;
            $percent = round(($saved / $originalSize) * 100, 1);
            mtrace("Compression complete! Saved " . $this->format_bytes($saved) . " ({$percent}%)");

        } catch (\Exception $e) {
            mtrace('Error: ' . $e->getMessage());
            videoprogress_queue_failed($item->fileid, $e->getMessage());
        }

        // 清理暫存檔
        @unlink($inputpath);
        @unlink($outputpath);
    }

    /**
     * 格式化檔案大小為可讀式字串
     */
    private function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 記錄壓縮結果
     */
    private function log_compression($contextid, $fileid, $filename, $originalSize, $compressedSize, $crf) {
        global $DB;

        require_once(__DIR__ . '/../compression_queue.php');

        // 確保記錄資料表存在
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('videoprogress_compression_log');

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
    }
}
