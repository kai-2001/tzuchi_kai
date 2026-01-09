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
 * View Controller - 主頁面控制器
 * 
 * 負責處理 view.php 的所有邏輯
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoprogress\controller;

defined('MOODLE_INTERNAL') || die();

use mod_videoprogress\service\video_detector;
use mod_videoprogress\service\compression_service;
use mod_videoprogress\service\progress_service;

/**
 * 主頁面控制器
 */
class view_controller {

    /** @var object 課程模組 */
    private $cm;

    /** @var object 課程 */
    private $course;

    /** @var object 活動實例 */
    private $videoprogress;

    /** @var \context_module 模組上下文 */
    private $context;

    /** @var object 當前使用者 */
    private $user;

    /** @var video_detector 影片偵測器 */
    private $detector;

    /** @var array 視圖資料 */
    private $viewData = [];

    /**
     * 建構函數
     * 
     * @param int $id 課程模組 ID
     * @param int $v 活動實例 ID
     */
    public function __construct(int $id = 0, int $v = 0) {
        global $DB, $USER;

        $this->user = $USER;
        $this->load_activity($id, $v);
        $this->setup_page();
    }

    /**
     * 載入活動資料
     * 
     * @param int $id 課程模組 ID
     * @param int $v 活動實例 ID
     */
    private function load_activity(int $id, int $v): void {
        global $DB;

        if ($id) {
            $this->cm = get_coursemodule_from_id('videoprogress', $id, 0, false, MUST_EXIST);
            $this->course = $DB->get_record('course', ['id' => $this->cm->course], '*', MUST_EXIST);
            $this->videoprogress = $DB->get_record('videoprogress', ['id' => $this->cm->instance], '*', MUST_EXIST);
        } else if ($v) {
            $this->videoprogress = $DB->get_record('videoprogress', ['id' => $v], '*', MUST_EXIST);
            $this->course = $DB->get_record('course', ['id' => $this->videoprogress->course], '*', MUST_EXIST);
            $this->cm = get_coursemodule_from_instance('videoprogress', $this->videoprogress->id, $this->course->id, false, MUST_EXIST);
        } else {
            throw new \moodle_exception('missingidandcmid', 'videoprogress');
        }

        require_login($this->course, true, $this->cm);
        $this->context = \context_module::instance($this->cm->id);
        require_capability('mod/videoprogress:view', $this->context);
    }

    /**
     * 設定頁面
     */
    private function setup_page(): void {
        global $PAGE;

        $PAGE->set_url('/mod/videoprogress/view.php', ['id' => $this->cm->id]);
        $PAGE->set_title(format_string($this->videoprogress->name));
        $PAGE->set_heading(format_string($this->course->fullname));
        $PAGE->set_context($this->context);
    }

    /**
     * 處理 AJAX 壓縮請求
     * 
     * @return bool 是否處理了 AJAX 請求
     */
    public function handle_ajax_compress(): bool {
        global $CFG;

        if (!isset($_GET['ajax']) || $_GET['ajax'] !== 'compress') {
            return false;
        }

        require_capability('moodle/site:config', \context_system::instance());
        header('Content-Type: application/json; charset=utf-8');

        $queueId = required_param('queue_id', PARAM_INT);

        try {
            require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');
            $pendingItems = compression_service::get_pending_items($this->context->id);
            
            $item = array_filter($pendingItems, fn($i) => $i->id == $queueId);
            $item = reset($item);
            
            if (!$item) {
                echo json_encode(['success' => false, 'error' => '找不到佇列項目']);
                exit;
            }

            set_time_limit(600);
            compression_service::execute_compression($item);
            
            echo json_encode(['success' => true, 'message' => '壓縮完成']);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        exit;
    }

    /**
     * 執行主邏輯
     */
    public function execute(): void {
        // 觸發檢視事件
        videoprogress_view($this->videoprogress, $this->course, $this->cm, $this->context);

        // 0% 門檻時點開即完成
        if ($this->videoprogress->completionpercent == 0) {
            videoprogress_trigger_completion($this->videoprogress->id, $this->user->id);
        }

        // 取得進度
        $this->viewData['progress'] = progress_service::get_user_progress($this->videoprogress->id, $this->user->id);

        // 偵測影片類型
        $this->detect_video_type();

        // 取得壓縮狀態（管理員專用）
        $this->viewData['compression'] = $this->get_compression_data();
    }

    /**
     * 偵測影片類型
     */
    private function detect_video_type(): void {
        $this->viewData['video_type'] = $this->videoprogress->videotype;
        $this->viewData['is_evercam'] = false;
        $this->viewData['use_html5'] = false;
        $this->viewData['video_url'] = null;
        $this->viewData['chapters'] = null;

        if ($this->videoprogress->videotype === 'external' && !empty($this->videoprogress->externalurl)) {
            $this->detector = new video_detector($this->videoprogress->externalurl);
            $result = $this->detector->detect();

            $this->viewData['is_evercam'] = $result['is_evercam'];
            $this->viewData['use_html5'] = $result['use_html5'] || $result['is_evercam'];
            $this->viewData['video_url'] = $result['video_url'];
            $this->viewData['chapters'] = $result['chapters'];
            $this->viewData['base_url'] = $this->detector->get_base_url();

            // 更新影片類型
            if ($result['is_evercam'] || $result['use_html5']) {
                $this->viewData['effective_type'] = 'upload';
            }

            // 更新時長
            if ($result['duration'] && empty($this->videoprogress->videoduration)) {
                global $DB;
                $DB->set_field('videoprogress', 'videoduration', $result['duration'], ['id' => $this->videoprogress->id]);
                $this->videoprogress->videoduration = $result['duration'];
            }
        }

        $this->viewData['duration'] = $this->videoprogress->videoduration ?? 0;
    }

    /**
     * 取得壓縮資料
     * 
     * @return array|null 壓縮資料
     */
    private function get_compression_data(): ?array {
        if (!has_capability('moodle/site:config', \context_system::instance())) {
            return null;
        }

        $items = compression_service::get_pending_items($this->context->id);
        
        if (empty($items)) {
            return null;
        }

        return [
            'has_pending' => true,
            'items' => array_map(function($item) {
                $statusMap = [
                    'pending' => ['badge' => '等待處理', 'class' => 'bg-secondary'],
                    'processing' => ['badge' => '處理中', 'class' => 'bg-primary'],
                    'failed' => ['badge' => '失敗', 'class' => 'bg-danger'],
                ];
                $status = $statusMap[$item->status] ?? ['badge' => $item->status, 'class' => 'bg-secondary'];
                
                return [
                    'id' => $item->id,
                    'filename' => $item->filename,
                    'status' => $item->status,
                    'status_badge' => $status['badge'],
                    'status_class' => $status['class'],
                    'is_processing' => $item->status === 'processing',
                ];
            }, $items),
            'view_mode' => true,
        ];
    }

    /**
     * 取得視圖資料
     * 
     * @return array 視圖資料
     */
    public function get_view_data(): array {
        return array_merge($this->viewData, [
            'cm' => $this->cm,
            'course' => $this->course,
            'videoprogress' => $this->videoprogress,
            'context' => $this->context,
            'user' => $this->user,
        ]);
    }

    /**
     * 取得課程模組
     * 
     * @return object
     */
    public function get_cm(): object {
        return $this->cm;
    }

    /**
     * 取得活動實例
     * 
     * @return object
     */
    public function get_videoprogress(): object {
        return $this->videoprogress;
    }

    /**
     * 取得上下文
     * 
     * @return \context_module
     */
    public function get_context(): \context_module {
        return $this->context;
    }
}
