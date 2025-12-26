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
            return false; // 不使用 Moodle 內建的「瀏覽即完成」
        case FEATURE_COMPLETION_HAS_RULES:
            return true; // 啟用自訂完成規則（觀看百分比）
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * 告訴 Moodle 此模組有自己的品牌圖示，不要套用顏色濾鏡
 *
 * @return bool
 */
function mod_videoprogress_is_branded(): bool {
    return true;
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

    // 自動啟用完成追蹤（確保活動有完成條件）
    if (isset($data->coursemodule)) {
        $DB->set_field('course_modules', 'completion', 2, 
            array('id' => $data->coursemodule));
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
        
        // 檢查是否為 ZIP 檔案
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_videoprogress', 'video', 0, 'sortorder', false);
        $file = reset($files);
        
        if ($file && strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) === 'zip') {
            // 處理 ZIP 套件（包含安全驗證）
            $result = videoprogress_process_zip_package($context->id, $file);
            if ($result !== true) {
                // 驗證失敗，刪除已上傳的 ZIP 並拋出錯誤
                $fs->delete_area_files($context->id, 'mod_videoprogress', 'video');
                throw new moodle_exception('zip_validation_failed', 'videoprogress', '', $result);
            }
        }
    }
}

/**
 * 提供檔案服務（包含安全 Headers）
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

    // 支援 video 和 package 兩個 filearea
    if ($filearea !== 'video' && $filearea !== 'package') {
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

    // 對 package 區域的檔案加入安全檢查
    if ($filearea === 'package') {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // 禁止直接訪問 HTML 檔案（防止執行惡意 JS）
        if (in_array($ext, ['html', 'htm'])) {
            return false;  // 拒絕存取
        }
        
        // 對 JS 檔案加入 CSP（config.js 需要被讀取）
        if ($ext === 'js') {
            header("Content-Security-Policy: default-src 'none';");
        }
        
        // 所有檔案都加入這些安全 Headers
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("Referrer-Policy: no-referrer");
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

    // 自訂完成規則：加入 completionenabled 到 customdata（值永遠是 1）
    $info->customdata = (object)[
        'customcompletionrules' => [
            'completionenabled' => 1  // 永遠是 1，讓 Moodle 知道有設定完成條件
        ]
    ];

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
 * 返回活動完成規則的描述
 *
 * @param cm_info|stdClass $cm 課程模組資訊
 * @return array 完成規則描述陣列
 */
function mod_videoprogress_get_completion_active_rule_descriptions($cm) {
    if (empty($cm->customdata['customcompletionrules']) || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        if ($key === 'completionpercent') {
            if ($val == 0) {
                $descriptions[] = '點開即完成';
            } else {
                $descriptions[] = '觀看 ' . $val . '% 以上的影片';
            }
        }
    }
    return $descriptions;
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

/**
 * 處理 ZIP 套件：安全驗證後解壓縮到 package filearea
 *
 * @param int $contextid 上下文 ID
 * @param stored_file $zipfile ZIP 檔案
 * @return bool|string true 成功，失敗則返回錯誤訊息
 */
function videoprogress_process_zip_package($contextid, $zipfile) {
    // 安全設定
    $allowed_extensions = [
        'html', 'htm', 'css', 'js', 'json', 'xml', 'txt',
        'mp4', 'webm', 'ogg', 'm4v', 'mp3', 'wav',
        'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico',
        'woff', 'woff2', 'ttf', 'eot', 'otf'
    ];
    $blocked_extensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 
                           'exe', 'bat', 'sh', 'cmd', 'com', 'dll',
                           'htaccess', 'htpasswd'];
    $max_uncompressed_size = 1024 * 1024 * 1024; // 1GB
    $max_file_count = 1000;
    
    // 取得 ZIP 內容列表（不解壓）
    $packer = get_file_packer('application/zip');
    $files = $zipfile->list_files($packer);
    
    if (!$files) {
        return 'ZIP 檔案無效或損壞';
    }
    
    // 安全驗證
    $total_size = 0;
    $file_count = 0;
    $has_index = false;
    
    foreach ($files as $file) {
        $filename = $file->pathname;
        $file_count++;
        
        // 檢查檔案數量
        if ($file_count > $max_file_count) {
            return "ZIP 包含太多檔案（上限 {$max_file_count} 個）";
        }
        
        // 檢查路徑穿越攻擊
        if (strpos($filename, '..') !== false) {
            return "ZIP 包含非法路徑：{$filename}";
        }
        
        // 跳過目錄
        if (substr($filename, -1) === '/') {
            continue;
        }
        
        // 取得副檔名
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // 檢查禁止的副檔名
        if (in_array($ext, $blocked_extensions)) {
            return "ZIP 包含禁止的檔案類型：{$filename}";
        }
        
        // 檢查是否在白名單中
        if (!in_array($ext, $allowed_extensions)) {
            return "ZIP 包含不允許的檔案類型：{$filename}（.{$ext}）";
        }
        
        // 累計解壓後大小
        $total_size += $file->size;
        if ($total_size > $max_uncompressed_size) {
            return "ZIP 解壓後大小超過限制（上限 1GB）";
        }
        
        // 檢查是否有 index.html
        if (strtolower(basename($filename)) === 'index.html') {
            $has_index = true;
        }
    }
    
    // 必須有 index.html
    if (!$has_index) {
        return 'ZIP 必須包含 index.html';
    }
    
    // 驗證通過，開始解壓
    $fs = get_file_storage();
    
    // 先刪除舊的 package 檔案
    $fs->delete_area_files($contextid, 'mod_videoprogress', 'package');
    
    // 解壓縮 ZIP 到 package filearea
    $result = $zipfile->extract_to_storage(
        $packer,
        $contextid,
        'mod_videoprogress',
        'package',
        0,
        '/'
    );
    
    if (!$result) {
        return 'ZIP 解壓縮失敗';
    }
    
    // 再次驗證 index.html 存在
    $indexfile = $fs->get_file($contextid, 'mod_videoprogress', 'package', 0, '/', 'index.html');
    if (!$indexfile) {
        return '解壓後找不到 index.html';
    }
    
    return true;
}

/**
 * 檢查活動是否為 ZIP 套件模式
 *
 * @param int $contextid 上下文 ID
 * @return bool
 */
function videoprogress_is_zip_package($contextid) {
    $fs = get_file_storage();
    $indexfile = $fs->get_file($contextid, 'mod_videoprogress', 'package', 0, '/', 'index.html');
    return $indexfile && !$indexfile->is_directory();
}

/**
 * 取得 ZIP 套件的 index.html URL
 *
 * @param int $contextid 上下文 ID
 * @return string|null URL 或 null
 */
function videoprogress_get_package_index_url($contextid) {
    $fs = get_file_storage();
    $indexfile = $fs->get_file($contextid, 'mod_videoprogress', 'package', 0, '/', 'index.html');
    
    if (!$indexfile) {
        return null;
    }
    
    return moodle_url::make_pluginfile_url(
        $contextid,
        'mod_videoprogress',
        'package',
        0,
        '/',
        'index.html'
    )->out(false);
}

/**
 * 取得 ZIP 套件的基礎 URL（用於載入相對資源）
 *
 * @param int $contextid 上下文 ID
 * @return string|null URL 或 null
 */
function videoprogress_get_package_base_url($contextid) {
    return moodle_url::make_pluginfile_url(
        $contextid,
        'mod_videoprogress',
        'package',
        0,
        '/',
        ''
    )->out(false);
}
