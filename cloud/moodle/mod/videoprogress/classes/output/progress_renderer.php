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
 * Progress Renderer - 進度渲染器
 * 
 * 負責進度區塊的 HTML 輸出
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoprogress\output;

defined('MOODLE_INTERNAL') || die();

/**
 * 進度渲染器類別
 */
class progress_renderer {

    /**
     * 渲染進度區塊
     */
    public static function render($userprogress, object $videoprogress, bool $isTimeMode = false): string {
        $html = '<div class="videoprogress-status card mb-4"><div class="card-body">';
        $html .= '<h5 class="card-title">' . get_string('yourprogress', 'videoprogress') . '</h5>';

        if ($isTimeMode) {
            $html .= self::time_progress($userprogress, $videoprogress);
        } else {
            $html .= self::percent_progress($userprogress, $videoprogress);
        }

        $html .= '</div></div>';
        return $html;
    }

    /**
     * 渲染時間進度
     */
    private static function time_progress($userprogress, object $videoprogress): string {
        $watchedtime = $userprogress ? $userprogress->watchedtime : 0;
        $required = $videoprogress->externalmintime ?? 60;
        $completed = $watchedtime >= $required;
        $percent = $required > 0 ? min(100, round(($watchedtime / $required) * 100)) : 0;

        $watchedFmt = gmdate('i:s', $watchedtime);
        $requiredFmt = gmdate('i:s', $required);
        $barClass = $completed ? 'bg-success' : 'bg-primary';
        $badge = $completed 
            ? '<span class="badge bg-success">' . get_string('completed', 'videoprogress') . '</span>'
            : '<span class="badge bg-secondary">' . get_string('notcompleted', 'videoprogress') . '</span>';

        $html = '<div class="progress mb-2" style="height: 25px;">';
        $html .= "<div class=\"progress-bar {$barClass}\" style=\"width: {$percent}%;\" aria-valuenow=\"{$percent}\" aria-valuemin=\"0\" aria-valuemax=\"100\">";
        $html .= "{$watchedFmt} / {$requiredFmt}";
        $html .= "</div></div>";
        
        $html .= "<p class=\"card-text\">{$badge}</p>";

        if ($watchedtime > 0) {
            $html .= '<p class="text-muted small"><i class="fa fa-clock-o"></i> 已累積觀看 ' . $watchedFmt . '</p>';
        }

        return $html;
    }

    /**
     * 渲染百分比進度
     */
    private static function percent_progress($userprogress, object $videoprogress): string {
        $percent = $userprogress ? $userprogress->percentcomplete : 0;
        $threshold = $videoprogress->completionpercent;

        if ($threshold == 0) {
            $completed = true;
            $displayPercent = 100;
        } else {
            $completed = $percent >= $threshold;
            $displayPercent = $percent;
        }

        $barClass = $completed ? 'bg-success' : 'bg-primary';
        $badge = $completed 
            ? '<span class="badge bg-success">' . get_string('completed', 'videoprogress') . '</span>'
            : '<span class="badge bg-secondary">' . get_string('notcompleted', 'videoprogress') . '</span> - '
              . get_string('completiondetail:percent', 'videoprogress', $threshold);

        return <<<HTML
<div class="progress mb-2" style="height: 25px;">
    <div class="progress-bar {$barClass}" style="width: {$displayPercent}%;" aria-valuenow="{$displayPercent}" aria-valuemin="0" aria-valuemax="100" id="videoprogress-progressbar">
        {$displayPercent}%
    </div>
</div>
<p class="card-text">{$badge}</p>
HTML;
    }
}
