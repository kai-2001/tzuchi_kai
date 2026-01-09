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
 * Video Progress 課程內列表頁
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT); // Course ID

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_login($course);
$PAGE->set_url('/mod/videoprogress/index.php', array('id' => $id));
$PAGE->set_pagelayout('incourse');

$strname = get_string('modulenameplural', 'videoprogress');
$PAGE->set_title($strname);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($strname);

if (!$videoprogresses = get_all_instances_in_course('videoprogress', $course)) {
    notice(get_string('noattempts', 'videoprogress'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_' . $course->format);
    $table->head = array($strsectionname, $strname, get_string('progress', 'videoprogress'));
    $table->align = array('center', 'left', 'center');
} else {
    $table->head = array($strname, get_string('progress', 'videoprogress'));
    $table->align = array('left', 'center');
}

// 預先載入所有資料（修復 N+1 問題）
$videoIds = array_column($videoprogresses, 'id');
$allProgress = $DB->get_records_list('videoprogress_progress', 'videoprogress', $videoIds, '', 'videoprogress, userid, percentcomplete');
$allSections = $DB->get_records('course_sections', array('course' => $id), '', 'id, section, name');

// 建立進度對照表（以 videoprogress ID 為 key）
$progressMap = [];
foreach ($allProgress as $p) {
    if ($p->userid == $USER->id) {
        $progressMap[$p->videoprogress] = $p->percentcomplete;
    }
}

foreach ($videoprogresses as $videoprogress) {
    $cm = get_coursemodule_from_instance('videoprogress', $videoprogress->id);
    
    // 從預載資料取得進度
    $percentcomplete = $progressMap[$videoprogress->id] ?? 0;

    $class = $videoprogress->visible ? '' : 'class="dimmed"';
    $link = '<a ' . $class . ' href="' . new moodle_url('/mod/videoprogress/view.php', array('id' => $cm->id)) . '">';
    $link .= format_string($videoprogress->name) . '</a>';

    $progressbar = '<div class="progress" style="min-width: 100px;">';
    $progressbar .= '<div class="progress-bar" role="progressbar" style="width: ' . $percentcomplete . '%;">';
    $progressbar .= $percentcomplete . '%</div></div>';

    if ($usesections) {
        // 從預載資料取得 section
        $section = $allSections[$cm->section] ?? null;
        $sectionname = $section ? get_section_name($course, $section) : '';
        $table->data[] = array($sectionname, $link, $progressbar);
    } else {
        $table->data[] = array($link, $progressbar);
    }
}

echo html_writer::table($table);
echo $OUTPUT->footer();

