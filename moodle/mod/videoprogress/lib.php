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
 * Video Progress - 核心函式庫
 * 
 * 此檔案只包含 Moodle 必要的回調函數。
 * 業務邏輯已移至 classes/service/ 目錄。
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

use mod_videoprogress\service\file_service;
use mod_videoprogress\service\zip_service;
use mod_videoprogress\service\compression_service;

// =========================================================================
// Moodle 模組回調（必須）
// =========================================================================

/**
 * 支援的功能列表
 */
function videoprogress_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * 告訴 Moodle 此模組有自己的品牌圖示
 */
function mod_videoprogress_is_branded(): bool {
    return true;
}

/**
 * 新增活動實例
 */
function videoprogress_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = time();

    if ($data->videotype === 'youtube' && !empty($data->videourl)) {
        $data->videourl = videoprogress_extract_youtube_url($data->videourl);
    }

    $data->id = $DB->insert_record('videoprogress', $data);

    if ($data->videotype === 'upload') {
        videoprogress_save_video_file($data, $mform);
    }

    if (isset($data->coursemodule)) {
        $DB->set_field('course_modules', 'completion', 2, ['id' => $data->coursemodule]);
    }

    return $data->id;
}

/**
 * 更新活動實例
 */
function videoprogress_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    if ($data->videotype === 'youtube' && !empty($data->videourl)) {
        $data->videourl = videoprogress_extract_youtube_url($data->videourl);
    }

    $DB->update_record('videoprogress', $data);

    if ($data->videotype === 'upload') {
        videoprogress_save_video_file($data, $mform);
    }

    return true;
}

/**
 * 刪除活動實例
 */
function videoprogress_delete_instance($id) {
    global $DB;

    if (!$DB->get_record('videoprogress', ['id' => $id])) {
        return false;
    }

    // 刪除所有相關記錄（包含 segments 表）
    $DB->delete_records('videoprogress_segments', ['videoprogress' => $id]);
    $DB->delete_records('videoprogress_progress', ['videoprogress' => $id]);
    $DB->delete_records('videoprogress', ['id' => $id]);

    return true;
}

/**
 * 重設課程使用者資料
 */
function videoprogress_reset_userdata($data) {
    global $DB;

    $status = [];

    if (!empty($data->reset_videoprogress)) {
        $sql = "DELETE FROM {videoprogress_segments}
                WHERE videoprogress IN (SELECT id FROM {videoprogress} WHERE course = ?)";
        $DB->execute($sql, [$data->courseid]);

        $sql = "DELETE FROM {videoprogress_progress}
                WHERE videoprogress IN (SELECT id FROM {videoprogress} WHERE course = ?)";
        $DB->execute($sql, [$data->courseid]);

        $status[] = [
            'component' => get_string('modulenameplural', 'videoprogress'),
            'item' => get_string('resetprogress', 'videoprogress'),
            'error' => false
        ];
    }

    return $status;
}

// =========================================================================
// 輔助函數
// =========================================================================

/**
 * 從 YouTube URL 提取標準化 URL
 */
function videoprogress_extract_youtube_url($url) {
    // 支援多種 YouTube URL 格式，統一轉為 embed 格式
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }
    return $url;
}

/**
 * 儲存上傳的影片檔案
 */
function videoprogress_save_video_file($data, $mform) {
    $context = context_module::instance($data->coursemodule);
    $options = ['subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 1, 'accepted_types' => ['video/*', '.zip']];
    
    file_save_draft_area_files($data->videofile, $context->id, 'mod_videoprogress', 'video', 0, $options);

    // 檢查 ZIP
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_videoprogress', 'video', 0, 'filename', false);
    
    foreach ($files as $file) {
        if (strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) === 'zip') {
            $result = zip_service::process_package($context->id, $file);
            if ($result !== true) {
                \core\notification::error($result);
            } else {
                // 刪除原始 ZIP 檔案
                $fs->delete_area_files($context->id, 'mod_videoprogress', 'video');
            }
            // 排程壓縮
            videoprogress_queue_compression($context->id, 'package');
            return;
        }
    }

    // 排程壓縮
    if (!empty($files)) {
        videoprogress_queue_compression($context->id, 'video');
    }
}

/**
 * 排程 FFmpeg 壓縮任務
 */
function videoprogress_queue_compression($contextid, $filearea) {
    global $DB, $CFG;
    
    if (!get_config('mod_videoprogress', 'enablecompression')) {
        return;
    }

    $fs = get_file_storage();
    $files = $fs->get_area_files($contextid, 'mod_videoprogress', $filearea, 0, 'filename', false);

    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4', 'webm', 'mov', 'avi', 'mkv'])) {
            continue;
        }

        // 檢查是否已在佇列
        $dbman = $DB->get_manager();
        $queueTable = new xmldb_table('videoprogress_compress_queue');
        if ($dbman->table_exists($queueTable) && $DB->record_exists('videoprogress_compress_queue', ['fileid' => $file->get_id()])) {
            continue;
        }

        // 加入佇列
        require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');
        videoprogress_queue_add($contextid, $file->get_id(), $file->get_filename());

        // 建立 ad-hoc 任務（true = 避免重複）
        $task = new \mod_videoprogress\task\compress_video();
        $task->set_custom_data(['contextid' => $contextid, 'fileid' => $file->get_id(), 'filename' => $file->get_filename()]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}

/**
 * 處理壓縮佇列（在頁面載入時觸發）
 * Windows 環境：同步處理（因為沒有 Cron）
 * Linux 環境：Scheduled Task 透過 Cron 處理
 */
function videoprogress_retry_failed_compressions() {
    global $CFG;
    
    if (!get_config('mod_videoprogress', 'enablecompression')) {
        return;
    }
    
    // 只在 Windows 環境觸發（Linux 依靠 Cron Scheduled Task）
    if (PHP_OS_FAMILY !== 'Windows') {
        return;
    }
    
    require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');
    
    // Windows 環境：每次訪問處理一個佇列項目
    videoprogress_process_queue_item();
}

/**
 * 提供檔案服務
 */
function videoprogress_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    return file_service::serve_file($course, $cm, $context, $filearea, $args, $forcedownload, $options);
}

/**
 * 取得課程模組資訊
 */
function videoprogress_get_coursemodule_info($coursemodule) {
    global $DB;

    $info = new cached_cm_info();
    $videoprogress = $DB->get_record('videoprogress', ['id' => $coursemodule->instance]);

    if ($videoprogress) {
        $info->name = $videoprogress->name;
        if ($coursemodule->showdescription) {
            $info->content = format_module_intro('videoprogress', $videoprogress, $coursemodule->id, false);
        }

    // 自訂完成規則：加入 completionenabled 到 customdata（值永遠是 1）
        $info->customdata = (object)[
            'customcompletionrules' => [
                'completionenabled' => 1
            ],
            'completionpercent' => $videoprogress->completionpercent
        ];
    }

    return $info;
}

/**
 * 取得完成狀態
 */
function videoprogress_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    $videoprogress = $DB->get_record('videoprogress', ['id' => $cm->instance], '*', MUST_EXIST);
    $progress = $DB->get_record('videoprogress_progress', ['videoprogressid' => $videoprogress->id, 'userid' => $userid]);

    if (!$progress) {
        return false;
    }

    return $progress->percentcomplete >= $videoprogress->completionpercent;
}

/**
 * 返回活動完成規則的描述
 */
function mod_videoprogress_get_completion_active_rule_descriptions($cm) {
    if (empty($cm->customdata->customcompletionrules) || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    // 檢查是否有啟用完成規則
    if (!empty($cm->customdata->customcompletionrules['completionenabled'])) {
        // 從 customdata 讀取百分比 (在 get_coursemodule_info 中存入)
        $percent = isset($cm->customdata->completionpercent) ? $cm->customdata->completionpercent : 0;
        
        if ($percent == 0) {
            $descriptions[] = get_string('completiondetail:view', 'videoprogress');
        } else {
            $descriptions[] = get_string('completiondetail:percent', 'videoprogress', $percent);
        }
    }
    
    return $descriptions;
}

/**
 * 標記活動已檢視並觸發事件
 */
function videoprogress_view($videoprogress, $course, $cm, $context) {
    $event = \mod_videoprogress\event\course_module_viewed::create([
        'objectid' => $videoprogress->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('videoprogress', $videoprogress);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}



// =========================================================================
// 輔助查詢函數
// =========================================================================



/**
 * 檢查是否為 ZIP 套件
 */
function videoprogress_is_zip_package($contextid) {
    return zip_service::is_package($contextid);
}

/**
 * 取得 ZIP 套件 index URL
 */
function videoprogress_get_package_index_url($contextid) {
    return zip_service::get_index_url($contextid);
}

/**
 * 取得 ZIP 套件基礎 URL
 */
function videoprogress_get_package_base_url($contextid) {
    return zip_service::get_base_url($contextid);
}
