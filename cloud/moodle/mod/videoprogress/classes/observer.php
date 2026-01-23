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
 * Video Progress 事件觀察者
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * 事件觀察者類別
 */
class mod_videoprogress_observer {

    /**
     * 當課程模組被刪除時觸發
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        global $DB;

        // 檢查是否為 videoprogress 模組
        $data = $event->get_data();
        if (isset($data['other']['modulename']) && $data['other']['modulename'] === 'videoprogress') {
            $instanceid = $data['other']['instanceid'];
            
            // 刪除相關進度記錄
            $DB->delete_records('videoprogress_segments', ['videoprogress' => $instanceid]);
            $DB->delete_records('videoprogress_progress', ['videoprogress' => $instanceid]);
        }
    }
}
