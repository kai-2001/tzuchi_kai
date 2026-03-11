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
 * mod_videoprogress 還原任務
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/videoprogress/backup/moodle2/restore_videoprogress_stepslib.php');

/**
 * 還原任務，提供所有還原所需的設定與步驟
 */
class restore_videoprogress_activity_task extends restore_activity_task
{

    /**
     * 定義此活動的特定設定
     */
    protected function define_my_settings()
    {
        // 此活動沒有特定設定
    }

    /**
     * 定義此活動的特定步驟
     */
    protected function define_my_steps()
    {
        // 我們只有一個結構步驟
        $this->add_step(new restore_videoprogress_activity_structure_step('videoprogress_structure', 'videoprogress.xml'));
    }

    /**
     * 定義活動中需要由連結解碼器處理的內容
     */
    static public function define_decode_contents()
    {
        $contents = array();

        $contents[] = new restore_decode_content('videoprogress', array('intro'), 'videoprogress');

        return $contents;
    }

    /**
     * 定義連結解碼規則，供連結解碼器執行
     */
    static public function define_decode_rules()
    {
        $rules = array();

        $rules[] = new restore_decode_rule('VIDEOPROGRESSVIEWBYID', '/mod/videoprogress/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('VIDEOPROGRESSINDEX', '/mod/videoprogress/index.php?id=$1', 'course');

        return $rules;

    }

    /**
     * 定義還原日誌規則，供 restore_logs_processor 還原 videoprogress 日誌時使用
     * 必須回傳 restore_log_rule 元素的陣列
     */
    static public function define_restore_log_rules()
    {
        $rules = array();

        $rules[] = new restore_log_rule('videoprogress', 'add', 'view.php?id={course_module}', '{videoprogress}');
        $rules[] = new restore_log_rule('videoprogress', 'update', 'view.php?id={course_module}', '{videoprogress}');
        $rules[] = new restore_log_rule('videoprogress', 'view', 'view.php?id={course_module}', '{videoprogress}');

        return $rules;
    }

    /**
     * 定義課程日誌還原規則，供 restore_logs_processor 還原課程日誌時使用
     * 必須回傳 restore_log_rule 元素的陣列
     */
    static public function define_restore_log_rules_for_course()
    {
        $rules = array();

        $rules[] = new restore_log_rule('videoprogress', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
