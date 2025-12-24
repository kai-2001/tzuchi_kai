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
 * Video Progress 觀看頁面
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/videoprogress/lib.php');
require_once($CFG->dirroot.'/mod/videoprogress/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // Course module ID
$v  = optional_param('v', 0, PARAM_INT);  // Video progress instance ID

if ($id) {
    $cm = get_coursemodule_from_id('videoprogress', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $videoprogress = $DB->get_record('videoprogress', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($v) {
    $videoprogress = $DB->get_record('videoprogress', array('id' => $v), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $videoprogress->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('videoprogress', $videoprogress->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('missingidandcmid', 'videoprogress');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videoprogress:view', $context);

// 觸發檢視事件
videoprogress_view($videoprogress, $course, $cm, $context);

// 設定頁面
$PAGE->set_url('/mod/videoprogress/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($videoprogress->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// 取得使用者進度
$userprogress = videoprogress_get_user_progress($videoprogress->id, $USER->id);

// 載入 JavaScript 模組
$jsconfig = array(
    'cmid' => $cm->id,
    'videoid' => $videoprogress->id,
    'videotype' => $videoprogress->videotype,
    'videourl' => $videoprogress->videourl,
    'externalurl' => isset($videoprogress->externalurl) ? $videoprogress->externalurl : '',
    'duration' => $videoprogress->videoduration,
    'lastposition' => $userprogress ? $userprogress->lastposition : 0,
    'completionpercent' => $videoprogress->completionpercent,
    'requirefocus' => isset($videoprogress->requirefocus) ? (bool)$videoprogress->requirefocus : false,
    'externalmintime' => isset($videoprogress->externalmintime) ? $videoprogress->externalmintime : 60
);
$PAGE->requires->js_call_amd('mod_videoprogress/player', 'init', array($jsconfig));


// 開始輸出
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($videoprogress->name));

// 顯示說明
if (!empty($videoprogress->intro)) {
    echo $OUTPUT->box(format_module_intro('videoprogress', $videoprogress, $cm->id), 'generalbox', 'intro');
}

// 進度資訊區塊
$percentcomplete = $userprogress ? $userprogress->percentcomplete : 0;
$watchedtime = $userprogress ? $userprogress->watchedtime : 0;

echo '<div class="videoprogress-status card mb-4">';
echo '<div class="card-body">';
echo '<h5 class="card-title">' . get_string('yourprogress', 'videoprogress') . '</h5>';

if ($videoprogress->videotype === 'external') {
    // 外部網址：顯示「已觀看 X 秒 / 需要 Y 秒」
    $requiredSeconds = isset($videoprogress->externalmintime) ? $videoprogress->externalmintime : 60;
    $iscompleted = $watchedtime >= $requiredSeconds;
    $progressPercent = $requiredSeconds > 0 ? min(100, round(($watchedtime / $requiredSeconds) * 100)) : 0;
    
    // 格式化已觀看時間
    $watchedFormatted = gmdate('i:s', $watchedtime);
    $requiredFormatted = gmdate('i:s', $requiredSeconds);
    
    echo '<div class="progress mb-2" style="height: 25px;">';
    echo '<div class="progress-bar ' . ($iscompleted ? 'bg-success' : 'bg-primary') . '" role="progressbar" ';
    echo 'style="width: ' . $progressPercent . '%;" aria-valuenow="' . $progressPercent . '" aria-valuemin="0" aria-valuemax="100">';
    echo $watchedFormatted . ' / ' . $requiredFormatted;
    echo '</div>';
    echo '</div>';
    echo '<p class="card-text">';
    if ($iscompleted) {
        echo '<span class="badge badge-success bg-success">' . get_string('completed', 'videoprogress') . '</span>';
    } else {
        echo '<span class="badge badge-secondary bg-secondary">' . get_string('notcompleted', 'videoprogress') . '</span>';
        echo ' - ' . get_string('externalrequirement', 'videoprogress', $requiredSeconds);
    }
    echo '</p>';
    // 顯示上次觀看位置提示
    if ($watchedtime > 0) {
        echo '<p class="text-muted small"><i class="fa fa-clock-o"></i> 已累積觀看 ' . $watchedFormatted . '</p>';
    }
} else {
    // YouTube 和上傳影片：顯示百分比
    $iscompleted = $percentcomplete >= $videoprogress->completionpercent;
    
    echo '<div class="progress mb-2" style="height: 25px;">';
    echo '<div class="progress-bar ' . ($iscompleted ? 'bg-success' : 'bg-primary') . '" role="progressbar" ';
    echo 'style="width: ' . $percentcomplete . '%;" aria-valuenow="' . $percentcomplete . '" aria-valuemin="0" aria-valuemax="100">';
    echo $percentcomplete . '%';
    echo '</div>';
    echo '</div>';
    echo '<p class="card-text">';
    if ($iscompleted) {
        echo '<span class="badge badge-success bg-success">' . get_string('completed', 'videoprogress') . '</span>';
    } else {
        echo '<span class="badge badge-secondary bg-secondary">' . get_string('notcompleted', 'videoprogress') . '</span>';
        echo ' - ' . get_string('completiondetail:percent', 'videoprogress', $videoprogress->completionpercent);
    }
    echo '</p>';
}

echo '</div>';
echo '</div>';

// 影片播放器區塊
echo '<div class="videoprogress-player card">';
echo '<div class="card-body">';

if ($videoprogress->videotype === 'youtube' && !empty($videoprogress->videourl)) {
    // 試著從 URL 提取 ID
    $videoid = '';
    if (preg_match('/(?:embed\/|v=|youtu\.be\/|\/v\/|watch\?v=|&v=)([a-zA-Z0-9_-]{11})/', $videoprogress->videourl, $matches)) {
        $videoid = $matches[1];
    }
    
    // YouTube 播放器容器
    echo '<div id="videoprogress-youtube-container" class="ratio ratio-16x9">';
    if ($videoid) {
        // 直接輸出 iframe，確保一定有東西顯示
        // enablejsapi=1 是必須的，讓 JS 可以控制
        $src = 'https://www.youtube.com/embed/' . $videoid . '?enablejsapi=1&rel=0&modestbranding=1';
        echo '<iframe id="videoprogress-youtube-player" src="' . $src . '" allowfullscreen frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>';
    } else {
        // 解析失敗，顯示錯誤與原始網址方便除錯
        echo '<div class="alert alert-danger m-3">';
        echo '<strong>Error:</strong> Cannot extract YouTube ID from URL.<br>';
        echo 'URL: ' . s($videoprogress->videourl);
        echo '</div>';
    }
    echo '</div>';
} else if ($videoprogress->videotype === 'external' && !empty($videoprogress->externalurl)) {
    // 外部網址 iframe - 計時提示放在上方
    echo '<div id="videoprogress-timer-hint" class="alert alert-warning mb-2">';
    echo '<i class="fa fa-hand-pointer-o"></i> ' . get_string('clicktostart', 'videoprogress');
    echo '</div>';
    echo '<div id="videoprogress-external-wrapper" style="position: relative;">';
    echo '<div id="videoprogress-external-container" class="ratio ratio-16x9">';
    echo '<iframe id="videoprogress-external-iframe" src="' . s($videoprogress->externalurl) . '" ';
    echo 'allowfullscreen frameborder="0"></iframe>';
    echo '</div>';
    // 遮罩層：切換分頁回來時顯示
    echo '<div id="videoprogress-overlay" style="display: none; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.9); z-index: 10; cursor: pointer; justify-content: center; align-items: center; transition: opacity 0.3s;">';
    echo '<div style="text-align: center; color: white; padding: 30px; background: rgba(255,255,255,0.1); border-radius: 20px; backdrop-filter: blur(10px);">';
    echo '<i class="fa fa-play-circle" style="font-size: 96px; margin-bottom: 20px; color: rgba(255,255,255,0.9); text-shadow: 0 0 15px rgba(255,255,255,0.3);"></i>';
    echo '<h3 style="margin: 15px 0; font-size: 24px; font-weight: bold;">' . get_string('timerpaused', 'videoprogress') . '</h3>';
    echo '<p style="margin: 10px 0; font-size: 16px; opacity: 0.9;">點擊以繼續觀看</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
} else if ($videoprogress->videotype === 'upload') {
    // HTML5 Video 播放器
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_videoprogress', 'video', 0, 'sortorder', false);
    $videofile = reset($files);
    
    if ($videofile) {
        $videourl = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_videoprogress',
            'video',
            0,
            '/',
            $videofile->get_filename()
        );
        echo '<video id="videoprogress-html5-player" class="w-100" controls>';
        echo '<source src="' . $videourl . '" type="' . $videofile->get_mimetype() . '">';
        echo get_string('error:novideo', 'videoprogress');
        echo '</video>';
    } else {
        echo '<div class="alert alert-warning">' . get_string('error:novideo', 'videoprogress') . '</div>';
    }
} else {
    echo '<div class="alert alert-warning">' . get_string('error:novideo', 'videoprogress') . '</div>';
}

echo '</div>';
echo '</div>';

// 續播提示
if ($userprogress && $userprogress->lastposition > 0) {
    $formattedtime = gmdate('H:i:s', $userprogress->lastposition);
    if ($videoprogress->videotype === 'external') {
        // 外部網址：只顯示資訊，不顯示按鈕（因為無法跳轉）
        echo '<div class="alert alert-info mt-3">';
        echo '<i class="fa fa-info-circle"></i> ';
        echo '上次累積觀看至 ' . $formattedtime;
        echo '</div>';
    } else {
        // YouTube 和上傳影片：顯示可點擊的續播按鈕
        echo '<div id="videoprogress-resume-prompt" class="alert alert-info mt-3">';
        echo '<button type="button" class="btn btn-primary" id="videoprogress-resume-btn">';
        echo get_string('resumefrom', 'videoprogress', $formattedtime);
        echo '</button>';
        echo '</div>';
    }
}

echo $OUTPUT->footer();
