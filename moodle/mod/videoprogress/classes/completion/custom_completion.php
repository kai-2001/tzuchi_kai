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

declare(strict_types=1);

namespace mod_videoprogress\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for the videoprogress activity.
 *
 * Defines mod_videoprogress's custom completion rules and fetches the completion statuses
 * of the custom completion rules for a given videoprogress instance and a user.
 *
 * @package mod_videoprogress
 * @copyright 2024 Tzu Chi Medical Foundation
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $userid = $this->userid;
        $videoprogress = $DB->get_record('videoprogress', ['id' => $this->cm->instance], '*', MUST_EXIST);

        if ($rule == 'completionenabled') {
            // 取得使用者觀看進度
            $progress = $DB->get_record('videoprogress_progress', [
                'videoprogress' => $videoprogress->id,
                'userid' => $userid
            ]);
            
            // 取得完成門檻
            $threshold = $videoprogress->completionpercent ?? 0;
            
            // 如果門檻是 0，只要檢視過就算完成
            if ($threshold == 0) {
                // 點開即完成
                return COMPLETION_COMPLETE;
            }
            
            // 檢查觀看百分比是否達到門檻
            $percentcomplete = $progress ? $progress->percentcomplete : 0;
            $status = $percentcomplete >= $threshold;
            
            return $status ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        return COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completionenabled',
        ];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;
        
        // 從資料庫取得 completionpercent
        $videoprogress = $DB->get_record('videoprogress', ['id' => $this->cm->instance]);
        $completionpercent = $videoprogress ? ($videoprogress->completionpercent ?? 0) : 0;

        if ($completionpercent == 0) {
            $description = get_string('completiondetail:view', 'videoprogress');
        } else {
            $description = get_string('completiondetail:percent', 'videoprogress', $completionpercent);
        }

        return [
            'completionenabled' => $description,
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionenabled',
            'completionusegrade',
            'completionpassgrade',
        ];
    }
}
