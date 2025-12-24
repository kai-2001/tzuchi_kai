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
 * Video Progress 內部函式庫
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * 取得使用者進度
 *
 * @param int $videogressid 活動 ID
 * @param int $userid 使用者 ID
 * @return stdClass|false 進度物件或 false
 */
function videoprogress_get_user_progress($videogressid, $userid) {
    global $DB;
    $records = $DB->get_records('videoprogress_progress', array(
        'videoprogress' => $videogressid,
        'userid' => $userid
    ));
    return $records ? reset($records) : false;
}

/**
 * 儲存觀看區段
 *
 * @param int $videogressid 活動 ID
 * @param int $userid 使用者 ID
 * @param int $start 區段開始秒數
 * @param int $end 區段結束秒數
 * @return bool
 */
function videoprogress_save_segment($videogressid, $userid, $start, $end) {
    global $DB;

    // 確保 start < end
    if ($start >= $end) {
        return false;
    }

    $now = time();



    // 簡單策略：只插入，不刪除。讓 update_progress 處理重疊計算。
    // 這樣可以避免 Race Condition 導致資料遺失。
    
    $record = new stdClass();
    $record->videoprogress = $videogressid;
    $record->userid = $userid;
    $record->segmentstart = $start;
    $record->segmentend = $end;
    $record->timemodified = $now;
    
    // 直接插入
    $newid = $DB->insert_record('videoprogress_segments', $record);


    // 更新進度摘要
    videoprogress_update_progress($videogressid, $userid, $end);

    return true;
}

/**
 * 更新使用者進度摘要
 *
 * @param int $videogressid 活動 ID
 * @param int $userid 使用者 ID
 * @param int $lastposition 最後位置
 */
function videoprogress_update_progress($videogressid, $userid, $lastposition = null) {
    global $DB;

    $now = time();



    // 取得活動資訊
    $videoprogress = $DB->get_record('videoprogress', array('id' => $videogressid), '*', MUST_EXIST);

    // 取得所有觀看區段
    $segments = $DB->get_records('videoprogress_segments', array(
        'videoprogress' => $videogressid,
        'userid' => $userid
    ));

    // 根據影片類型使用不同的計算方式
    if ($videoprogress->videotype === 'external') {
        // 外部網址：簡單累加所有區段時間（每次觀看都計算）
        $watchedtime = 0;
        foreach ($segments as $segment) {
            $watchedtime += ($segment->segmentend - $segment->segmentstart);
        }
    } else {
        // YouTube / 上傳影片：使用區間合併算法（避免重複計算同一段落）
        $intervals = array();
        foreach ($segments as $segment) {
            $intervals[] = array($segment->segmentstart, $segment->segmentend);
        }
        
        // 按開始時間排序
        usort($intervals, function($a, $b) {
            return $a[0] - $b[0];
        });
        
        // 合併重疊區間
        $merged = array();
        foreach ($intervals as $interval) {
            if (empty($merged)) {
                $merged[] = $interval;
            } else {
                $last = &$merged[count($merged) - 1];
                if ($interval[0] <= $last[1]) {
                    // 重疊或相鄰，合併
                    $last[1] = max($last[1], $interval[1]);
                } else {
                    // 不重疊，新增
                    $merged[] = $interval;
                }
            }
        }
        
        // 計算合併後的總時間
        $watchedtime = 0;
        foreach ($merged as $interval) {
            $watchedtime += ($interval[1] - $interval[0]);
        }
    }


    // 計算百分比和完成狀態
    $duration = $videoprogress->videoduration;
    
    if ($videoprogress->videotype === 'external') {
        // ...
        $requiredSeconds = isset($videoprogress->externalmintime) ? $videoprogress->externalmintime : 60;
        $percentcomplete = $requiredSeconds > 0 ? min(100, round(($watchedtime / $requiredSeconds) * 100)) : 0;
        $completed = $watchedtime >= $requiredSeconds ? 1 : 0;
    } else {
        // ...
        $percentcomplete = $duration > 0 ? min(100, round(($watchedtime / $duration) * 100)) : 0;
        $completed = $percentcomplete >= $videoprogress->completionpercent ? 1 : 0;
    }





    // 取得或建立進度記錄
    // ...
    // Note: I will just instrument up to here for now.
    $progress_records = $DB->get_records('videoprogress_progress', array(
        'videoprogress' => $videogressid,
        'userid' => $userid
    ));

    $progress = null;
    if ($progress_records) {
        // 如果有多筆記錄，使用第一筆，刪除其他的
        $progress = reset($progress_records);
        array_shift($progress_records); // 移除第一筆
        
        if (!empty($progress_records)) {
            $ids_to_delete = array_keys($progress_records);
            $DB->delete_records_list('videoprogress_progress', 'id', $ids_to_delete);

        }

        $progress->watchedtime = $watchedtime;
        if ($lastposition !== null) {
            $progress->lastposition = $lastposition;
        }
        $progress->percentcomplete = $percentcomplete;
        $progress->completed = $completed;
        $progress->timemodified = $now;
        $DB->update_record('videoprogress_progress', $progress);
    } else {
        $progress = new stdClass();
        $progress->videoprogress = $videogressid;
        $progress->userid = $userid;
        $progress->watchedtime = $watchedtime;
        $progress->lastposition = $lastposition ?? 0;
        $progress->percentcomplete = $percentcomplete;
        $progress->completed = $completed;
        $progress->timecreated = $now;
        $progress->timemodified = $now;
        $DB->insert_record('videoprogress_progress', $progress);
    }

    if ($completed) {
        videoprogress_trigger_completion($videogressid, $userid);
    }
}

/**
 * 觸發活動完成
 * 直接操作資料庫標記完成，不依賴 Moodle 的完成追蹤設定
 *
 * @param int $videogressid 活動 ID
 * @param int $userid 使用者 ID
 */
function videoprogress_trigger_completion($videogressid, $userid) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/completionlib.php');

    $videoprogress = $DB->get_record('videoprogress', array('id' => $videogressid), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $videoprogress->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('videoprogress', $videogressid, $course->id, false, MUST_EXIST);

    // 步驟1: 確保課程已啟用完成追蹤
    if (empty($course->enablecompletion)) {
        $DB->set_field('course', 'enablecompletion', 1, array('id' => $course->id));

        // 重新取得課程資料
        $course = $DB->get_record('course', array('id' => $videoprogress->course), '*', MUST_EXIST);
    }

    // 步驟2: 確保活動已啟用完成追蹤
    if ($cm->completion == 0) {
        $DB->set_field('course_modules', 'completion', COMPLETION_TRACKING_AUTOMATIC, array('id' => $cm->id));

        // 重新取得 cm 資料 (使用 rebuild 確保快取更新)
        rebuild_course_cache($course->id, true);
        $cm = get_coursemodule_from_instance('videoprogress', $videogressid, $course->id, false, MUST_EXIST);
    }

    // 步驟3: 使用 Moodle 完成 API
    $completion = new completion_info($course);
    
    if ($completion->is_enabled($cm)) {
        // 先清理重複記錄，防止 Core API 崩潰
        videoprogress_fix_completion_records($cm->id, $userid);
        // 使用 update_state 標記完成
        $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
    } else {
        // 如果還是沒有啟用，直接設定完成資料
        $data = new stdClass();
        $data->coursemoduleid = $cm->id;
        $data->userid = $userid;
        $data->completionstate = COMPLETION_COMPLETE;
        $data->viewed = 1;
        $data->overrideby = null;
        $data->timemodified = time();
        
        $records = $DB->get_records('course_modules_completion', array(
            'coursemoduleid' => $cm->id,
            'userid' => $userid
        ));
        
        $existing = null;
        if ($records) {
            $existing = reset($records);
            // 如果有多筆，清理掉
            array_shift($records);
            if (!empty($records)) {
                $DB->delete_records_list('course_modules_completion', 'id', array_keys($records));
            }
        }
        
        if ($existing) {
            $data->id = $existing->id;
            $DB->update_record('course_modules_completion', $data);
        } else {
            $DB->insert_record('course_modules_completion', $data);
        }
    }
    
    rebuild_course_cache($course->id, true);
}

/**
 * 取得影片的上傳檔案 URL
 *
 * @param int $contextid 上下文 ID
 * @return string|null 檔案 URL 或 null
 */
function videoprogress_get_video_url($contextid) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($contextid, 'mod_videoprogress', 'video', 0, 'sortorder', false);
    $file = reset($files);

    if ($file) {
        return moodle_url::make_pluginfile_url(
            $contextid,
            'mod_videoprogress',
            'video',
            0,
            '/',
            $file->get_filename()
        );
    }

    return null;
}

/**
 * 修復重複的完成記錄
 * (防止 Moodle Core API 崩潰)
 */
function videoprogress_fix_completion_records($cmid, $userid) {
    global $DB;
    $records = $DB->get_records('course_modules_completion', array(
        'coursemoduleid' => $cmid,
        'userid' => $userid
    ), 'timemodified DESC');
    
    if (count($records) > 1) {
        // 保留最新的一筆，刪除其他
        $keep = reset($records);
        array_shift($records);
        $DB->delete_records_list('course_modules_completion', 'id', array_keys($records));
    }
}

/**
 * 除錯日誌 helper（已停用）
 */
function videoprogress_log($msg) {
    // Debug logging disabled in production
}
