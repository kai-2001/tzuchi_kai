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
 * mod_videoprogress 還原結構步驟
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * 還原單一 videoprogress 活動的結構步驟
 */
class restore_videoprogress_activity_structure_step extends restore_activity_structure_step
{

    protected function define_structure()
    {

        $paths = array();
        $paths[] = new restore_path_element('videoprogress', '/activity/videoprogress');

        // 回傳包裝成標準活動結構的路徑
        return $this->prepare_activity_structure($paths);
    }

    protected function process_videoprogress($data)
    {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // 新增 videoprogress 記錄
        $newitemid = $DB->insert_record('videoprogress', $data);
        // 新增「活動」記錄後立即呼叫此方法
        $this->apply_activity_instance($newitemid);
    }

    protected function after_execute()
    {
        // 新增 videoprogress 相關檔案，不需要依 itemname 比對（只需內部處理的 context）
        $this->add_related_files('mod_videoprogress', 'intro', null);
        $this->add_related_files('mod_videoprogress', 'video', null);
        $this->add_related_files('mod_videoprogress', 'package', null);
    }
}
