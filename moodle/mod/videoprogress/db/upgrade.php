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
 * Video Progress 升級腳本
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * 執行升級
 *
 * @param int $oldversion 舊版本號
 * @return bool
 */
function xmldb_videoprogress_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024121501) {

        // 定義要新增的欄位 externalurl
        $table = new xmldb_table('videoprogress');
        $field = new xmldb_field('externalurl', XMLDB_TYPE_TEXT, null, null, null, null, null, 'videourl');

        // 如果欄位不存在，則新增
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // 保存升級點
        upgrade_mod_savepoint(true, 2024121501, 'videoprogress');
    }

    if ($oldversion < 2024121602) {
        // 新增 externalmintime 欄位
        $table = new xmldb_table('videoprogress');
        $field = new xmldb_field('externalmintime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '60', 'completionpercent');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2024121602, 'videoprogress');
    }

    if ($oldversion < 2024121603) {
        // 新增 requirefocus 欄位（專注模式）
        $table = new xmldb_table('videoprogress');
        $field = new xmldb_field('requirefocus', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'externalmintime');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2024121603, 'videoprogress');
    }

    return true;
}
