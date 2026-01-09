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
 * Compression Controller - 壓縮管理控制器
 * 
 * 負責處理 manage_compression.php 的所有邏輯
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoprogress\controller;

defined('MOODLE_INTERNAL') || die();

use mod_videoprogress\service\compression_service;

/**
 * 壓縮管理控制器
 */
class compression_controller {

    /** @var \context_system 系統上下文 */
    private $context;

    /**
     * 建構函數
     */
    public function __construct() {
        global $PAGE;

        require_login();
        $this->context = \context_system::instance();
        require_capability('moodle/site:config', $this->context);

        $PAGE->set_url('/mod/videoprogress/manage_compression.php');
        $PAGE->set_context($this->context);
        $PAGE->set_title(get_string('compression_management', 'mod_videoprogress'));
        $PAGE->set_heading(get_string('compression_management', 'mod_videoprogress'));
        $PAGE->set_pagelayout('admin');
    }

    /**
     * 處理 AJAX 壓縮請求
     * 
     * @return bool 是否處理了 AJAX 請求
     */
    public function handle_ajax(): bool {
        global $CFG;

        if (!isset($_GET['ajax'])) {
            return false;
        }

        header('Content-Type: application/json; charset=utf-8');

        switch ($_GET['ajax']) {
            case 'compress':
                $this->handle_compress_request();
                break;
            case 'reset':
                $this->handle_reset_request();
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
        }

        exit;
    }

    /**
     * 處理壓縮請求
     */
    private function handle_compress_request(): void {
        global $CFG, $DB;

        $queueId = required_param('queue_id', PARAM_INT);

        try {
            require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');

            $item = $DB->get_record('videoprogress_compress_queue', ['id' => $queueId]);
            if (!$item) {
                echo json_encode(['success' => false, 'error' => '找不到佇列項目']);
                return;
            }

            set_time_limit(600);
            ini_set('max_execution_time', 600);

            compression_service::execute_compression($item);

            echo json_encode(['success' => true, 'message' => '壓縮完成']);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 處理重置請求
     */
    private function handle_reset_request(): void {
        global $DB;

        try {
            $updated = $DB->execute(
                "UPDATE {videoprogress_compress_queue} SET status = 'pending' WHERE status = 'processing'"
            );
            echo json_encode(['success' => true, 'message' => '已重設處理中的項目']);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 取得視圖資料
     * 
     * @return array 視圖資料
     */
    public function get_view_data(): array {
        $ffmpegpath = get_config('mod_videoprogress', 'ffmpegpath');
        $compressionEnabled = get_config('mod_videoprogress', 'enablecompression');

        return [
            'ffmpeg_configured' => !empty($ffmpegpath) && file_exists($ffmpegpath),
            'compression_enabled' => (bool) $compressionEnabled,
            'ffmpegpath' => $ffmpegpath,
            'settings_url' => new \moodle_url('/admin/settings.php', ['section' => 'modsettingvideoprogress']),
            'pending_items' => compression_service::get_pending_items(),
            'statistics' => compression_service::get_statistics(),
            'completed_logs' => compression_service::get_completed_logs(20),
        ];
    }

    /**
     * 準備統計儀表板資料
     * 
     * @return array 模板資料
     */
    public function get_statistics_data(): array {
        $stats = compression_service::get_statistics();
        
        if (!$stats || $stats->total_count == 0) {
            return ['has_stats' => false];
        }

        return [
            'has_stats' => true,
            'total_count' => $stats->total_count,
            'total_original_gb' => $stats->total_original_gb,
            'total_saved_gb' => $stats->total_saved_gb,
            'avg_percent' => $stats->avg_percent,
        ];
    }

    /**
     * 準備待處理佇列資料
     * 
     * @return array 佇列資料
     */
    public function get_queue_data(): array {
        $items = compression_service::get_pending_items();

        return array_map(function($item) {
            $statusMap = [
                'pending' => ['badge' => '等待處理', 'class' => 'badge-secondary bg-secondary'],
                'processing' => ['badge' => '處理中', 'class' => 'badge-primary bg-primary'],
                'failed' => ['badge' => '失敗', 'class' => 'badge-danger bg-danger'],
            ];
            $status = $statusMap[$item->status] ?? ['badge' => $item->status, 'class' => 'badge-secondary bg-secondary'];

            return [
                'id' => $item->id,
                'filename' => $item->filename,
                'filesize' => compression_service::format_filesize($item->filesize ?? 0),
                'course_name' => $item->course_name ?? '未知',
                'activity_name' => $item->activity_name ?? '未知',
                'activity_url' => $item->activity_url ?? '#',
                'status' => $item->status,
                'status_badge' => $status['badge'],
                'status_class' => $status['class'],
                'is_pending' => $item->status === 'pending',
                'is_processing' => $item->status === 'processing',
                'is_failed' => $item->status === 'failed',
                'last_error' => $item->last_error ?? '',
                'timecreated' => userdate($item->timecreated, '%Y-%m-%d %H:%M'),
            ];
        }, $items);
    }

    /**
     * 準備已完成記錄資料
     * 
     * @return array 記錄資料
     */
    public function get_logs_data(): array {
        $logs = compression_service::get_completed_logs(20);

        return array_map(function($log) {
            $savedPercent = $log->original_size > 0 
                ? round((($log->original_size - $log->compressed_size) / $log->original_size) * 100, 1)
                : 0;

            return [
                'filename' => $log->filename,
                'course_name' => $log->course_name ?? '未知',
                'activity_name' => $log->activity_name ?? '未知',
                'activity_url' => $log->activity_url ?? '#',
                'original_size' => compression_service::format_filesize($log->original_size),
                'compressed_size' => compression_service::format_filesize($log->compressed_size),
                'saved_size' => compression_service::format_filesize($log->saved_size ?? 0),
                'saved_percent' => $savedPercent,
                'crf' => $log->crf ?? 23,
                'timecreated' => userdate($log->timecreated, '%Y-%m-%d %H:%M'),
            ];
        }, $logs);
    }

    /**
     * 檢查是否有處理中的項目（用於自動刷新）
     * 
     * @return bool
     */
    public function has_processing_items(): bool {
        $items = compression_service::get_pending_items();
        foreach ($items as $item) {
            if ($item->status === 'processing') {
                return true;
            }
        }
        return false;
    }
}
