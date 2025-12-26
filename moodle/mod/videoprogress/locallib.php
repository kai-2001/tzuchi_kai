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
    // 外部網址如果有 duration（使用 HTML5 Video 模式），跟上傳影片一樣用區間合併
    $usePercentMode = ($videoprogress->videotype !== 'external') || 
                      ($videoprogress->videotype === 'external' && $videoprogress->videoduration > 0);
    
    if ($usePercentMode) {
        // YouTube / 上傳影片 / 外部網址(HTML5)：使用區間合併算法（避免重複計算同一段落）
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
    } else {
        // 外部網址（iframe 計時模式）：簡單累加所有區段時間
        $watchedtime = 0;
        foreach ($segments as $segment) {
            $watchedtime += ($segment->segmentend - $segment->segmentstart);
        }
    }

    // 計算百分比和完成狀態
    $duration = $videoprogress->videoduration;
    
    if ($videoprogress->videotype === 'external' && $duration <= 0) {
        // 外部網址（iframe 計時模式）：用累計時間
        $requiredSeconds = isset($videoprogress->externalmintime) ? $videoprogress->externalmintime : 60;
        $percentcomplete = $requiredSeconds > 0 ? min(100, round(($watchedtime / $requiredSeconds) * 100)) : 0;
        $completed = $watchedtime >= $requiredSeconds ? 1 : 0;
    } else {
        // YouTube / 上傳影片 / 外部網址(HTML5)：用百分比
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
 * 偵測外部網址的實際影片 URL 和時長
 * 
 * @param string $externalurl 外部網址
 * @return array|null ['videourl' => string, 'duration' => int] 或 null
 */
function videoprogress_detect_external_video($externalurl) {
    global $CFG;
    
    if (empty($externalurl)) {
        return null;
    }
    
    // 如果已經是直接影片格式，直接回傳
    if (preg_match('/\.(mp4|webm|ogg|ogv|mov|m4v)(\?|$)/i', $externalurl)) {
        return [
            'videourl' => $externalurl,
            'duration' => null,
            'is_direct_video' => true
        ];
    }
    
    // 呼叫 detect_video.php 的邏輯（內部重複使用）
    require_once(__DIR__ . '/detect_video.php');
    
    // 由於 detect_video.php 是為 AJAX 設計的，我們需要直接呼叫其中的函數
    // 先檢查函數是否存在
    if (!function_exists('getVideoDuration')) {
        return null;
    }
    
    // 嘗試解析網頁找出影片 URL
    $videoUrl = null;
    $method = '';
    
    // 使用 cURL 取得外部網頁內容
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $externalurl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    if ($html === false || $httpCode !== 200) {
        return null;
    }
    
    // 取得基底 URL
    $parsedUrl = parse_url($finalUrl);
    $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
    if (isset($parsedUrl['port'])) {
        $baseUrl .= ':' . $parsedUrl['port'];
    }
    $basePath = isset($parsedUrl['path']) ? dirname($parsedUrl['path']) : '';
    if ($basePath === '\\' || $basePath === '/') {
        $basePath = '';
    }
    
    // 輔助函數：轉換為絕對 URL
    $makeAbsoluteUrl = function($relativeUrl) use ($baseUrl, $basePath) {
        if (preg_match('/^https?:\/\//i', $relativeUrl)) {
            return $relativeUrl;
        }
        if (strpos($relativeUrl, '//') === 0) {
            return 'https:' . $relativeUrl;
        }
        if (strpos($relativeUrl, '/') === 0) {
            return $baseUrl . $relativeUrl;
        }
        return $baseUrl . $basePath . '/' . $relativeUrl;
    };
    
    // 嘗試各種方法找出影片 URL
    $patterns = [
        '/<video[^>]+src=["\']([^"\']+\.(mp4|webm|ogg|ogv|mov|m4v)[^"\']*)["\']/' => 'video_src',
        '/<source[^>]+src=["\']([^"\']+\.(mp4|webm|ogg|ogv|mov|m4v)[^"\']*)["\']/' => 'source_src',
        '/file:\s*["\']([^"\']+\.(mp4|webm|ogg|ogv|mov|m4v)[^"\']*)["\']/' => 'jwplayer',
        '/["\']([^"\']*\.(mp4|webm)[^"\']*)["\']/' => 'generic',
    ];
    
    foreach ($patterns as $pattern => $methodName) {
        if (preg_match($pattern, $html, $matches)) {
            $videoUrl = $makeAbsoluteUrl($matches[1]);
            $method = $methodName;
            break;
        }
    }
    
    // 如果還沒找到，嘗試常見檔名
    if (!$videoUrl) {
        $commonNames = ['media.mp4', 'video.mp4', 'main.mp4', 'content.mp4'];
        foreach ($commonNames as $name) {
            $testUrl = $baseUrl . $basePath . '/' . $name;
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $testUrl,
                CURLOPT_NOBODY => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($code === 200) {
                $videoUrl = $testUrl;
                $method = 'common_name';
                break;
            }
        }
    }
    
    if (!$videoUrl) {
        return null;
    }
    
    // 取得影片時長
    $debug = [];
    $duration = getVideoDuration($videoUrl, $debug);
    
    return [
        'videourl' => $videoUrl,
        'duration' => $duration,
        'method' => $method,
        'is_direct_video' => false
    ];
}
