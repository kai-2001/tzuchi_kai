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

namespace mod_videoprogress\task;

/**
 * 清理孤兒進度記錄的排程任務
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_orphan_records extends \core\task\scheduled_task {

    /**
     * 取得任務名稱
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup_orphan', 'mod_videoprogress');
    }

    /**
     * 執行清理任務
     */
    public function execute() {
        global $DB;

        // 刪除沒有對應主記錄的 segments
        $sql = "DELETE FROM {videoprogress_segments} 
                WHERE videoprogress NOT IN (SELECT id FROM {videoprogress})";
        $DB->execute($sql);

        // 刪除沒有對應主記錄的 progress
        $sql = "DELETE FROM {videoprogress_progress} 
                WHERE videoprogress NOT IN (SELECT id FROM {videoprogress})";
        $DB->execute($sql);

        mtrace('VideoProgress: Orphan records cleanup completed.');
    }
}
