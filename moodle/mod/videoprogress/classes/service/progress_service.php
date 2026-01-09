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
 * Progress Service - 進度追蹤服務
 * 
 * 負責影片觀看進度的業務邏輯
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoprogress\service;

defined('MOODLE_INTERNAL') || die();

/**
 * 進度追蹤服務類別
 */
class progress_service {

    /**
     * 取得使用者進度
     * 
     * @param int $videoprogressid 活動 ID
     * @param int $userid 使用者 ID
     * @return object|null 進度資料
     */
    public static function get_user_progress(int $videoprogressid, int $userid): ?object {
        global $DB;

        $progress = $DB->get_record('videoprogress_progress', [
            'videoprogressid' => $videoprogressid,
            'userid' => $userid
        ]);

        if ($progress) {
            // 解碼已觀看的區段
            if (!empty($progress->watchedsegments)) {
                $progress->segments = json_decode($progress->watchedsegments, true) ?? [];
            } else {
                $progress->segments = [];
            }

            // 計算人類可讀的時間
            $progress->lastposition_formatted = self::format_time($progress->lastposition ?? 0);
        }

        return $progress ?: null;
    }

    /**
     * 儲存使用者進度
     * 
     * @param int $videoprogressid 活動 ID
     * @param int $userid 使用者 ID
     * @param float $percentcomplete 完成百分比
     * @param array $segments 已觀看區段
     * @param float|null $lastposition 最後位置
     * @return bool 是否成功
     */
    public static function save_progress(int $videoprogressid, int $userid, float $percentcomplete, array $segments = [], ?float $lastposition = null): bool {
        global $DB;

        $now = time();
        $existingProgress = $DB->get_record('videoprogress_progress', [
            'videoprogressid' => $videoprogressid,
            'userid' => $userid
        ]);

        if ($existingProgress) {
            // 更新現有記錄（只允許進度增加，不允許倒退）
            if ($percentcomplete > $existingProgress->percentcomplete) {
                $existingProgress->percentcomplete = $percentcomplete;
            }
            
            // 合併區段
            $existingSegments = json_decode($existingProgress->watchedsegments, true) ?? [];
            $mergedSegments = self::merge_segments($existingSegments, $segments);
            $existingProgress->watchedsegments = json_encode($mergedSegments);
            
            if ($lastposition !== null) {
                $existingProgress->lastposition = $lastposition;
            }
            
            $existingProgress->timemodified = $now;
            
            return $DB->update_record('videoprogress_progress', $existingProgress);
        } else {
            // 建立新記錄
            $newProgress = new \stdClass();
            $newProgress->videoprogressid = $videoprogressid;
            $newProgress->userid = $userid;
            $newProgress->percentcomplete = $percentcomplete;
            $newProgress->watchedsegments = json_encode($segments);
            $newProgress->lastposition = $lastposition ?? 0;
            $newProgress->timecreated = $now;
            $newProgress->timemodified = $now;
            
            return (bool) $DB->insert_record('videoprogress_progress', $newProgress);
        }
    }

    /**
     * 合併觀看區段
     * 
     * @param array $existing 現有區段
     * @param array $new 新區段
     * @return array 合併後的區段
     */
    private static function merge_segments(array $existing, array $new): array {
        // 合併兩個區段陣列
        $all = array_merge($existing, $new);
        
        if (empty($all)) {
            return [];
        }

        // 按開始時間排序
        usort($all, function($a, $b) {
            $startA = is_array($a) ? $a[0] : $a->start;
            $startB = is_array($b) ? $b[0] : $b->start;
            return $startA <=> $startB;
        });

        // 合併重疊的區段
        $merged = [];
        $current = $all[0];

        for ($i = 1; $i < count($all); $i++) {
            $next = $all[$i];
            
            $currentEnd = is_array($current) ? $current[1] : $current->end;
            $nextStart = is_array($next) ? $next[0] : $next->start;
            $nextEnd = is_array($next) ? $next[1] : $next->end;
            
            if ($nextStart <= $currentEnd + 1) {
                // 區段重疊或相鄰，合併
                if (is_array($current)) {
                    $current[1] = max($currentEnd, $nextEnd);
                } else {
                    $current->end = max($currentEnd, $nextEnd);
                }
            } else {
                // 區段不重疊，加入結果並移動到下一個
                $merged[] = $current;
                $current = $next;
            }
        }
        
        $merged[] = $current;
        
        return $merged;
    }

    /**
     * 計算完成百分比
     * 
     * @param array $segments 已觀看區段
     * @param float $totalDuration 總時長
     * @return float 百分比 (0-100)
     */
    public static function calculate_percent(array $segments, float $totalDuration): float {
        if ($totalDuration <= 0 || empty($segments)) {
            return 0;
        }

        $watchedSeconds = 0;
        foreach ($segments as $segment) {
            $start = is_array($segment) ? $segment[0] : $segment->start;
            $end = is_array($segment) ? $segment[1] : $segment->end;
            $watchedSeconds += ($end - $start);
        }

        $percent = round(($watchedSeconds / $totalDuration) * 100, 1);

        // [Fix] 容錯處理：如果進度超過 95%，直接視為 100%
        if ($percent >= 95) {
            return 100;
        }

        return min(100, $percent);
    }

    /**
     * 檢查是否達成完成條件
     * 
     * @param int $videoprogressid 活動 ID
     * @param int $userid 使用者 ID
     * @param float $requiredPercent 要求百分比
     * @return bool 是否完成
     */
    public static function is_completed(int $videoprogressid, int $userid, float $requiredPercent): bool {
        $progress = self::get_user_progress($videoprogressid, $userid);
        
        if (!$progress) {
            return false;
        }

        return $progress->percentcomplete >= $requiredPercent;
    }

    /**
     * 格式化時間
     * 
     * @param float $seconds 秒數
     * @return string 格式化字串 (HH:MM:SS 或 MM:SS)
     */
    public static function format_time(float $seconds): string {
        $seconds = (int) $seconds;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%02d:%02d', $minutes, $secs);
    }

    /**
     * 取得繼續觀看位置
     * 
     * @param int $videoprogressid 活動 ID
     * @param int $userid 使用者 ID
     * @return float 秒數
     */
    public static function get_resume_position(int $videoprogressid, int $userid): float {
        $progress = self::get_user_progress($videoprogressid, $userid);
        
        if (!$progress || empty($progress->lastposition)) {
            return 0;
        }

        return (float) $progress->lastposition;
    }

    /**
     * 重置使用者進度
     * 
     * @param int $videoprogressid 活動 ID
     * @param int|null $userid 使用者 ID（null 表示所有使用者）
     * @return bool 是否成功
     */
    public static function reset_progress(int $videoprogressid, ?int $userid = null): bool {
        global $DB;

        $params = ['videoprogressid' => $videoprogressid];
        
        if ($userid !== null) {
            $params['userid'] = $userid;
        }

        return $DB->delete_records('videoprogress_progress', $params);
    }
}
