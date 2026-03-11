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
 * mod_videoprogress 備份設定
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/videoprogress/backup/moodle2/backup_videoprogress_stepslib.php');

/**
 * 定義 videoprogress 的備份設定
 */
class backup_videoprogress_activity_task extends backup_activity_task
{

    /**
     * 此活動沒有特定設定
     */
    protected function define_my_settings()
    {
    }

    /**
     * 定義備份步驟，將實例資料儲存至 videoprogress.xml 檔案
     */
    protected function define_my_steps()
    {
        $this->add_step(new backup_videoprogress_activity_structure_step('videoprogress_structure', 'videoprogress.xml'));
    }

    /**
     * 編碼活動中的連結，使其可傳輸（編碼後的連結）
     *
     * @param string $content 內容
     * @return string 編碼後的內容
     */
    static public function encode_content_links($content)
    {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        // 連結至 videoprogress 活動列表
        $search = "/(" . $base . "\/mod\/videoprogress\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@VIDEOPROGRESSINDEX*$2@$', $content);

        // 連結至 videoprogress 單一活動檢視頁面
        $search = "/(" . $base . "\/mod\/videoprogress\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@VIDEOPROGRESSVIEWBYID*$2@$', $content);

        return $content;
    }
}
