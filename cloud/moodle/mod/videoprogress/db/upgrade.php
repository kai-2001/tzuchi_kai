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

    if ($oldversion < 2024122302) {
        // 新增 completionenabled 欄位
        $table = new xmldb_table('videoprogress');
        $field = new xmldb_field('completionenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'requirefocus');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // 將所有現有活動設為 1
        $DB->execute("UPDATE {videoprogress} SET completionenabled = 1");

        upgrade_mod_savepoint(true, 2024122302, 'videoprogress');
    }

    // 新增壓縮佇列資料表
    if ($oldversion < 2026010502) {
        // 建立 videoprogress_compress_queue 表
        $table = new xmldb_table('videoprogress_compress_queue');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('fileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('attempts', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('last_error', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('fileid_idx', XMLDB_INDEX_UNIQUE, ['fileid']);
            
            $dbman->create_table($table);
        }

        // 建立 videoprogress_compression_log 表
        $logtable = new xmldb_table('videoprogress_compression_log');
        
        if (!$dbman->table_exists($logtable)) {
            $logtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $logtable->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $logtable->add_field('fileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $logtable->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $logtable->add_field('original_size', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
            $logtable->add_field('compressed_size', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
            $logtable->add_field('saved_size', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
            $logtable->add_field('saved_percent', XMLDB_TYPE_NUMBER, '5', 2, XMLDB_NOTNULL, null, null);
            $logtable->add_field('crf', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
            $logtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $logtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            
            $dbman->create_table($logtable);
        }

        upgrade_mod_savepoint(true, 2026010502, 'videoprogress');
    }

    return true;
}
