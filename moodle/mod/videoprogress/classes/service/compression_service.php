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
 * Compression Service - 影片壓縮服務
 * 
 * 負責影片壓縮的業務邏輯
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoprogress\service;

defined('MOODLE_INTERNAL') || die();

/**
 * 壓縮服務類別
 */
class compression_service {

    /**
     * 取得待處理的壓縮項目
     * 
     * @param int|null $contextid 上下文 ID（可選）
     * @return array 壓縮佇列項目
     */
    public static function get_pending_items(?int $contextid = null): array {
        global $DB;
        
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('videoprogress_compress_queue');
        
        if (!$dbman->table_exists($table)) {
            return [];
        }

        $params = [];
        // 使用 JOIN 一次取得所有需要的資料，避免 N+1 問題
        $sql = "SELECT q.*, 
                       ctx.instanceid as cmid,
                       c.fullname as course_name,
                       vp.name as activity_name,
                       f.filesize
                FROM {videoprogress_compress_queue} q
                LEFT JOIN {context} ctx ON ctx.id = q.contextid
                LEFT JOIN {course_modules} cm ON cm.id = ctx.instanceid
                LEFT JOIN {course} c ON c.id = cm.course
                LEFT JOIN {videoprogress} vp ON vp.id = cm.instance
                LEFT JOIN {files} f ON f.id = q.fileid AND f.component = 'mod_videoprogress'
                WHERE q.status IN ('pending', 'processing', 'failed')";
        
        if ($contextid) {
            $sql .= " AND q.contextid = ?";
            $params[] = $contextid;
        }
        
        $sql .= " ORDER BY q.timecreated ASC";
        
        $items = $DB->get_records_sql($sql, $params);

        // 補充 activity_url（無法在 SQL 中生成）
        foreach ($items as &$item) {
            $item->course_name = $item->course_name ?? '未知';
            $item->activity_name = $item->activity_name ?? '未知';
            $item->filesize = $item->filesize ?? 0;
            $item->activity_url = $item->cmid 
                ? new \moodle_url('/mod/videoprogress/view.php', ['id' => $item->cmid])
                : '#';
        }

        return $items;
    }

    /**
     * 豐富項目資訊（課程名、活動名、檔案大小）
     * 
     * @param object $item 佇列項目
     */
    private static function enrich_item_info(object &$item): void {
        global $DB;

        try {
            $cm = get_coursemodule_from_id('videoprogress', $item->cmid);
            if ($cm) {
                $course = $DB->get_record('course', ['id' => $cm->course]);
                $vp = $DB->get_record('videoprogress', ['id' => $cm->instance]);
                $item->course_name = $course ? $course->fullname : '未知';
                $item->activity_name = $vp ? $vp->name : '未知';
                $item->activity_url = new \moodle_url('/mod/videoprogress/view.php', ['id' => $cm->id]);

                // 取得檔案大小
                $fs = get_file_storage();
                $file = $fs->get_file_by_id($item->fileid);
                $item->filesize = $file ? $file->get_filesize() : 0;
            } else {
                self::set_item_defaults($item);
            }
        } catch (\Exception $e) {
            self::set_item_defaults($item);
        }
    }

    /**
     * 設定項目預設值
     * 
     * @param object $item 佇列項目
     */
    private static function set_item_defaults(object &$item): void {
        $item->course_name = '錯誤';
        $item->activity_name = '錯誤';
        $item->activity_url = '#';
        $item->filesize = 0;
    }

    /**
     * 取得壓縮統計資料
     * 
     * @return object|null 統計資料
     */
    public static function get_statistics(): ?object {
        global $DB;
        
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('videoprogress_compression_log');
        
        if (!$dbman->table_exists($table)) {
            return null;
        }

        $stats = $DB->get_record_sql(
            "SELECT 
                COUNT(*) as total_count,
                COALESCE(SUM(original_size), 0) as total_original,
                COALESCE(SUM(compressed_size), 0) as total_compressed,
                COALESCE(SUM(saved_size), 0) as total_saved
             FROM {videoprogress_compression_log}"
        );

        if ($stats && $stats->total_count > 0) {
            $stats->avg_percent = round((($stats->total_original - $stats->total_compressed) / $stats->total_original) * 100, 1);
            $stats->total_original_gb = round($stats->total_original / 1024 / 1024 / 1024, 2);
            $stats->total_saved_gb = round($stats->total_saved / 1024 / 1024 / 1024, 2);
        }

        return $stats;
    }

    /**
     * 取得已完成的壓縮記錄
     * 
     * @param int $limit 限制數量
     * @return array 壓縮記錄
     */
    public static function get_completed_logs(int $limit = 20): array {
        global $DB;
        
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('videoprogress_compression_log');
        
        if (!$dbman->table_exists($table)) {
            return [];
        }

        // 使用 JOIN 一次取得所有需要的資料，避免 N+1 問題
        $sql = "SELECT log.*, 
                       ctx.instanceid as cmid,
                       cs.fullname as course_name,
                       vp.name as activity_name
                FROM {videoprogress_compression_log} log
                LEFT JOIN {context} ctx ON ctx.id = log.contextid
                LEFT JOIN {course_modules} cm ON cm.id = ctx.instanceid
                LEFT JOIN {course} cs ON cs.id = cm.course
                LEFT JOIN {videoprogress} vp ON vp.id = cm.instance
                ORDER BY log.timecreated DESC";

        $logs = $DB->get_records_sql($sql, [], 0, $limit);

        // 補充 activity_url（無法在 SQL 中生成）
        foreach ($logs as &$log) {
            $log->course_name = $log->course_name ?? '未知';
            $log->activity_name = $log->activity_name ?? '未知';
            $log->activity_url = $log->cmid 
                ? new \moodle_url('/mod/videoprogress/view.php', ['id' => $log->cmid])
                : '#';
        }

        return $logs;
    }

    /**
     * 豐富記錄資訊
     * 
     * @param object $log 記錄
     */
    private static function enrich_log_info(object &$log): void {
        global $DB;

        try {
            $context = \context::instance_by_id($log->contextid, IGNORE_MISSING);
            if ($context && $context->contextlevel == CONTEXT_MODULE) {
                $cm = get_coursemodule_from_id('videoprogress', $context->instanceid);
                if ($cm) {
                    $course = $DB->get_record('course', ['id' => $cm->course]);
                    $vp = $DB->get_record('videoprogress', ['id' => $cm->instance]);
                    $log->course_name = $course ? $course->fullname : '未知';
                    $log->activity_name = $vp ? $vp->name : '未知';
                    $log->activity_url = new \moodle_url('/mod/videoprogress/view.php', ['id' => $cm->id]);
                } else {
                    $log->course_name = '未知';
                    $log->activity_name = '未知';
                    $log->activity_url = '#';
                }
            } else {
                $log->course_name = '未知';
                $log->activity_name = '未知';
                $log->activity_url = '#';
            }
        } catch (\Exception $e) {
            $log->course_name = '錯誤';
            $log->activity_name = '錯誤';
            $log->activity_url = '#';
        }
    }

    /**
     * 執行壓縮
     * 
     * @param object $item 佇列項目
     * @return bool 是否成功
     */
    public static function execute_compression(object $item): bool {
        global $CFG;
        
        require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');
        
        // 標記為處理中
        videoprogress_queue_processing($item->fileid);
        
        // 執行壓縮
        videoprogress_do_compression($item);
        
        return true;
    }

    /**
     * 檢查是否可以執行壓縮（並發限制、時段限制等）
     * 
     * @return array ['can_run' => bool, 'reason' => string]
     */
    public static function can_execute(): array {
        global $DB;
        
        // 檢查並發限制
        $maxConcurrent = 2;
        $processingCount = $DB->count_records('videoprogress_compress_queue', ['status' => 'processing']);
        if ($processingCount >= $maxConcurrent) {
            return [
                'can_run' => false,
                'reason' => "Too many concurrent compressions ($processingCount/$maxConcurrent)"
            ];
        }

        // 檢查離峰時段
        $offpeakEnabled = get_config('mod_videoprogress', 'offpeakhours');
        if ($offpeakEnabled) {
            $currentHour = (int) date('G');
            $startHour = (int) get_config('mod_videoprogress', 'offpeakstart');
            $endHour = (int) get_config('mod_videoprogress', 'offpeakend');
            
            $inOffpeak = false;
            if ($startHour <= $endHour) {
                $inOffpeak = ($currentHour >= $startHour && $currentHour < $endHour);
            } else {
                $inOffpeak = ($currentHour >= $startHour || $currentHour < $endHour);
            }
            
            if (!$inOffpeak) {
                return [
                    'can_run' => false,
                    'reason' => sprintf('Outside off-peak hours (%02d:00-%02d:00)', $startHour, $endHour)
                ];
            }
        }

        return ['can_run' => true, 'reason' => ''];
    }

    /**
     * 格式化檔案大小
     * 
     * @param int $bytes 位元組數
     * @return string 格式化字串
     */
    public static function format_filesize(int $bytes): string {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } else if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } else if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
