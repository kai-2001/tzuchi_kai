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

namespace mod_videoprogress\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/videoprogress/locallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_module;

/**
 * 儲存影片觀看進度 External API
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_progress extends external_api {

    /**
     * 定義參數
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(array(
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'segmentstart' => new external_value(PARAM_INT, 'Segment start time in seconds'),
            'segmentend' => new external_value(PARAM_INT, 'Segment end time in seconds'),
            'currentposition' => new external_value(PARAM_INT, 'Current playback position in seconds'),
            'videoduration' => new external_value(PARAM_INT, 'Video duration in seconds (optional)', VALUE_DEFAULT, 0),
        ));
    }

    /**
     * 執行儲存進度
     *
     * @param int $cmid 課程模組 ID
     * @param int $segmentstart 區段開始秒數
     * @param int $segmentend 區段結束秒數
     * @param int $currentposition 當前播放位置
     * @param int $videoduration 影片總長度（秒）
     * @return array 結果
     */
    public static function execute($cmid, $segmentstart, $segmentend, $currentposition, $videoduration = 0) {
        global $DB, $USER;

        try {
            // 驗證參數
            $params = self::validate_parameters(self::execute_parameters(), array(
                'cmid' => $cmid,
                'segmentstart' => $segmentstart,
                'segmentend' => $segmentend,
                'currentposition' => $currentposition,
                'videoduration' => $videoduration,
            ));

            // 取得課程模組
            $cm = get_coursemodule_from_id('videoprogress', $params['cmid'], 0, false, MUST_EXIST);
            $context = context_module::instance($cm->id);

            // 驗證權限
            self::validate_context($context);
            require_capability('mod/videoprogress:view', $context);

            // 取得活動
            $videoprogress = $DB->get_record('videoprogress', array('id' => $cm->instance), '*', MUST_EXIST);

            // 如果資料庫中的 videoduration 為 0，但前端傳來了有效的影片長度，自動更新
            if ($videoprogress->videoduration == 0 && $params['videoduration'] > 0) {
                $videoprogress->videoduration = $params['videoduration'];
                $DB->update_record('videoprogress', $videoprogress);
            }

            // 儲存區段
            if ($params['segmentstart'] < $params['segmentend']) {
                $segmentlength = $params['segmentend'] - $params['segmentstart'];
                
                // 合理性驗證：單次儲存的區段不應超過 30 秒
                // 正常播放時 timeupdate 每 5 秒儲存一次
                // 如果超過 30 秒，很可能是異常情況
                if ($segmentlength > 30) {
                    // 記錄異常情況（可選）
                    // debugging("Suspicious segment detected: start={$params['segmentstart']}, end={$params['segmentend']}, length={$segmentlength}", DEBUG_DEVELOPER);
                    
                    // 限制區段長度為 10 秒（從 start 開始）
                    $params['segmentend'] = $params['segmentstart'] + 10;
                }
                
                videoprogress_save_segment(
                    $videoprogress->id,
                    $USER->id,
                    $params['segmentstart'],
                    $params['segmentend']
                );
            }

            // 更新最後位置
            videoprogress_update_progress($videoprogress->id, $USER->id, $params['currentposition']);

            // 取得更新後的進度
            $progress = videoprogress_get_user_progress($videoprogress->id, $USER->id);

            return array(
                'success' => true,
                'percentcomplete' => $progress ? $progress->percentcomplete : 0,
                'completed' => $progress ? (bool)$progress->completed : false,
                'message' => 'Success'
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'percentcomplete' => 0,
                'completed' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * 定義回傳值
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure(array(
            'success' => new external_value(PARAM_BOOL, 'Whether the save was successful'),
            'percentcomplete' => new external_value(PARAM_INT, 'Current completion percentage'),
            'completed' => new external_value(PARAM_BOOL, 'Whether the activity is now completed'),
            'message' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL)
        ));
    }
}
