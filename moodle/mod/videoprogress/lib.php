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
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * 支援的功能列表
 *
 * @param string $feature 功能常數
 * @return mixed
 */
function videoprogress_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true; // 支援「瀏覽此活動」作為完成條件
        case FEATURE_COMPLETION_HAS_RULES:
            return false; // 移除活動完成條件選項，完全由 80% 門檻控制
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * 新增活動實例
 *
 * @param stdClass $data 表單資料
 * @param mod_videoprogress_mod_form $mform 表單物件
 * @return int 新建的活動 ID
 */
function videoprogress_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = time();

    // 處理 YouTube URL，提取影片 ID
    if ($data->videotype === 'youtube' && !empty($data->videourl)) {
        $data->videourl = videoprogress_extract_youtube_url($data->videourl);
    }

    $data->id = $DB->insert_record('videoprogress', $data);

    // 處理上傳的影片檔案
    if ($data->videotype === 'upload') {
        videoprogress_save_video_file($data, $mform);
    }

    return $data->id;
}

/**
 * 更新活動實例
 *
 * @param stdClass $data 表單資料
 * @param mod_videoprogress_mod_form $mform 表單物件
 * @return bool
 */
function videoprogress_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    // 處理 YouTube URL
    if ($data->videotype === 'youtube' && !empty($data->videourl)) {
        $data->videourl = videoprogress_extract_youtube_url($data->videourl);
    }

    $DB->update_record('videoprogress', $data);

    // 處理上傳的影片檔案
    if ($data->videotype === 'upload') {
        videoprogress_save_video_file($data, $mform);
    }

    return true;
}

/**
 * 刪除活動實例
 *
 * @param int $id 活動 ID
 * @return bool
 */
function videoprogress_delete_instance($id) {
    global $DB;

    if (!$videoprogress = $DB->get_record('videoprogress', array('id' => $id))) {
        return false;
    }

    // 刪除相關記錄
    $DB->delete_records('videoprogress_segments', array('videoprogress' => $id));
    $DB->delete_records('videoprogress_progress', array('videoprogress' => $id));
    $DB->delete_records('videoprogress', array('id' => $id));

    return true;
}

/**
 * 重設課程使用者資料
 *
 * @param stdClass $data 重設資料
 * @return array 狀態陣列
 */
function videoprogress_reset_userdata($data) {
    global $DB;

    $status = array();

    if (!empty($data->reset_videoprogress)) {
        $sql = "DELETE FROM {videoprogress_segments}
                WHERE videoprogress IN (SELECT id FROM {videoprogress} WHERE course = ?)";
        $DB->execute($sql, array($data->courseid));

        $sql = "DELETE FROM {videoprogress_progress}
                WHERE videoprogress IN (SELECT id FROM {videoprogress} WHERE course = ?)";
        $DB->execute($sql, array($data->courseid));

        $status[] = array(
            'component' => get_string('modulenameplural', 'videoprogress'),
            'item' => get_string('resetprogress', 'videoprogress'),
            'error' => false
        );
    }

    return $status;
}

/**
 * 從 YouTube URL 提取標準化 URL
 *
 * @param string $url 原始 URL
 * @return string 標準化 URL
 */
function videoprogress_extract_youtube_url($url) {
    // 支援多種 YouTube URL 格式
    $patterns = array(
        '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/',
    );

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }
    }

    return $url;
}

/**
 * 儲存上傳的影片檔案
 *
 * @param stdClass $data 活動資料
 * @param moodleform $mform 表單物件
 */
function videoprogress_save_video_file($data, $mform) {
    global $DB;

    $cmid = $data->coursemodule;
    $context = context_module::instance($cmid);

    if ($mform) {
        file_save_draft_area_files(
            $data->videofile,
            $context->id,
            'mod_videoprogress',
            'video',
            0,
            array('subdirs' => 0, 'maxfiles' => 1)
        );
    }
}

/**
 * 提供檔案服務
 *
 * @param stdClass $course 課程
 * @param stdClass $cm 課程模組
 * @param context $context 上下文
 * @param string $filearea 檔案區域
 * @param array $args 參數
 * @param bool $forcedownload 強制下載
 * @param array $options 選項
 * @return bool
 */
function videoprogress_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if ($filearea !== 'video') {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_videoprogress', $filearea, $itemid, $filepath, $filename);

    if (!$file) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

/**
 * 取得課程模組資訊
 *
 * @param stdClass $coursemodule 課程模組
 * @return cached_cm_info
 */
function videoprogress_get_coursemodule_info($coursemodule) {
    global $DB;

    if (!$videoprogress = $DB->get_record('videoprogress', array('id' => $coursemodule->instance))) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $videoprogress->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('videoprogress', $videoprogress, $coursemodule->id, false);
    }

    return $info;
}

/**
 * 取得完成狀態
 *
 * @param stdClass $course 課程
 * @param cm_info $cm 課程模組
 * @param int $userid 使用者 ID
 * @param bool $type 類型
 * @return bool
 */
function videoprogress_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    $videoprogress = $DB->get_record('videoprogress', array('id' => $cm->instance), '*', MUST_EXIST);
    
    $records = $DB->get_records('videoprogress_progress', array(
        'videoprogress' => $videoprogress->id,
        'userid' => $userid
    ));
    $progress = $records ? reset($records) : false;

    if (!$progress) {
        return false;
    }

    return $progress->percentcomplete >= $videoprogress->completionpercent;
}

/**
 * 標記活動已檢視並觸發事件
 *
 * @param stdClass $videoprogress 活動物件
 * @param stdClass $course 課程
 * @param stdClass $cm 課程模組
 * @param context $context 上下文
 */
function videoprogress_view($videoprogress, $course, $cm, $context) {
    global $DB;

    // 觸發課程模組檢視事件
    $event = \mod_videoprogress\event\course_module_viewed::create(array(
        'objectid' => $videoprogress->id,
        'context' => $context,
    ));
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('videoprogress', $videoprogress);
    $event->trigger();

    // 標記活動完成（檢視）
    $completion = new completion_info($course);
    
    // 清理重複完成記錄 (防止 Core API 崩潰)
    $dup_records = $DB->get_records('course_modules_completion', array(
        'coursemoduleid' => $cm->id,
        'userid' => $USER->id
    ), 'timemodified DESC');
    if (count($dup_records) > 1) {
        reset($dup_records);
        array_shift($dup_records);
        $DB->delete_records_list('course_modules_completion', 'id', array_keys($dup_records));
    }

    $completion->set_module_viewed($cm);
}
