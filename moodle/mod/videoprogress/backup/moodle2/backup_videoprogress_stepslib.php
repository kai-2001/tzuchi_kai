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
 * mod_videoprogress 備份結構步驟
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * 定義完整的 videoprogress 備份結構，包含檔案與 ID 註解
 */
class backup_videoprogress_activity_structure_step extends backup_activity_structure_step
{

    protected function define_structure()
    {

        // 定義各個元素
        $videoprogress = new backup_nested_element('videoprogress', array('id'), array(
            'name',
            'intro',
            'introformat',
            'videotype',
            'videourl',
            'videoduration',
            'requiredpercentage',
            'timecreated',
            'timemodified'
        ));

        // 建立樹狀結構
        // （活動層級的 videoprogress 沒有子項目，使用者資料將另行新增）

        // 定義資料來源
        $videoprogress->set_source_table('videoprogress', array('id' => backup::VAR_ACTIVITYID));

        // 定義 ID 註解
        // （不需要）

        // 定義檔案註解
        $videoprogress->annotate_files('mod_videoprogress', 'intro', null);
        $videoprogress->annotate_files('mod_videoprogress', 'video', null);
        $videoprogress->annotate_files('mod_videoprogress', 'package', null);

        // 回傳根元素（videoprogress），包裝成標準活動結構
        return $this->prepare_activity_structure($videoprogress);
    }
}
