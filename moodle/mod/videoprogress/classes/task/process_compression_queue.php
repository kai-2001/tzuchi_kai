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
 * 此任務負責：
 * 1. 檢查並重設卡住的壓縮項目
 * 2. 為待處理的項目建立 Ad-hoc 任務（由 compress_video.php 執行實際壓縮）
 * 
 * 注意：此任務不直接執行壓縮，而是派發 Ad-hoc 任務，避免阻塞 Cron。
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoprogress\task;

defined('MOODLE_INTERNAL') || die();

class process_compression_queue extends \core\task\scheduled_task
{

    /**
     * 取得任務名稱
     */
    public function get_name()
    {
        return get_string('task_process_compression', 'mod_videoprogress');
    }

    /**
     * 執行任務
     */
    public function execute()
    {
        global $CFG, $DB;

        // 檢查是否啟用壓縮
        if (!get_config('mod_videoprogress', 'enablecompression')) {
            mtrace('Video compression is disabled');
            return;
        }

        // 檢查自動佇列處理是否啟用（僅 Linux）
        if (PHP_OS_FAMILY !== 'Windows') {
            $autoqueue = get_config('mod_videoprogress', 'autoqueue');

            // 如果沒有設定過，預設為開啟
            if ($autoqueue === false) {
                set_config('autoqueue', 1, 'mod_videoprogress');
                $autoqueue = 1;
            }

            // 如果明確設為 0，則跳過
            if ($autoqueue == 0) {
                mtrace('Auto-queue processing is disabled (manual mode)');
                return;
            }
        }

        // 檢查 FFmpeg
        $ffmpegpath = get_config('mod_videoprogress', 'ffmpegpath');
        if (empty($ffmpegpath) || !file_exists($ffmpegpath)) {
            mtrace('FFmpeg not found at: ' . $ffmpegpath);
            return;
        }

        require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');

        // Step 1: 重設卡住的項目（處理超過 10 分鐘）
        $this->reset_stuck_items();

        // Step 2: 檢查是否有待處理的項目需要建立 Ad-hoc 任務
        $this->dispatch_pending_items();

        mtrace('Compression queue processing completed');
    }

    /**
     * 重設卡在「處理中」狀態過久的項目
     */
    private function reset_stuck_items()
    {
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
            $item->status = 'pending';
            $item->attempts = $item->attempts + 1;
            $item->last_error = 'Process timed out (stuck for >10 minutes), will retry';
            $item->timemodified = time();
            // 移到佇列最後，避免一直卡住
            $item->timecreated = time();

            if ($item->attempts >= 3) {
                $item->status = 'abandoned';
                $item->last_error = 'Abandoned after 3 failed attempts';
            }

            $DB->update_record('videoprogress_compress_queue', $item);
        }
    }

    /**
     * 為待處理的項目建立 Ad-hoc 任務
     * 
     * 這個函式會檢查佇列中的 pending 項目，
     * 如果該項目還沒有對應的 Ad-hoc 任務，就建立一個。
     */
    private function dispatch_pending_items()
    {
        global $DB;

        // 取得所有待處理的項目
        $pendingItems = $DB->get_records('videoprogress_compress_queue', ['status' => 'pending'], 'priority DESC, timecreated ASC', '*', 0, 10);

        if (empty($pendingItems)) {
            mtrace('No pending items in queue');
            return;
        }

        // 取得現有的 Ad-hoc 任務
            $existingTasks = $DB->get_records('task_adhoc', ['classname' => '\\mod_videoprogress\\task\\compress_video']);
        $existingFileIds = [];

        foreach ($existingTasks as $task) {
            $customData = json_decode($task->customdata);
            if (!empty($customData->fileid)) {
                $existingFileIds[$customData->fileid] = true;
            }
        }

        $dispatched = 0;
        foreach ($pendingItems as $item) {
            // 如果這個檔案已經有 Ad-hoc 任務了，就跳過
            if (isset($existingFileIds[$item->fileid])) {
                mtrace("Ad-hoc task already exists for: {$item->filename}");
                continue;
            }

            // 建立新的 Ad-hoc 任務
            $task = new \mod_videoprogress\task\compress_video();
            $task->set_custom_data([
                'contextid' => $item->contextid,
                'fileid' => $item->fileid,
                'filename' => $item->filename
            ]);
            \core\task\manager::queue_adhoc_task($task, true);

            mtrace("Dispatched Ad-hoc task for: {$item->filename}");
            $dispatched++;
        }

        if ($dispatched > 0) {
            mtrace("Dispatched {$dispatched} new compression tasks");
        }
    }
}
