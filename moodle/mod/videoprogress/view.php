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

use mod_videoprogress\service\video_detector;
use mod_videoprogress\service\zip_service;
use mod_videoprogress\output\player_renderer;
use mod_videoprogress\output\progress_renderer;
use mod_videoprogress\output\compression_renderer;

// =========================================================================
// AJAX 壓縮處理
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'compress') {
    require_login();
    require_capability('moodle/site:config', context_system::instance());
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');
        $item = $DB->get_record('videoprogress_compress_queue', ['id' => required_param('queue_id', PARAM_INT)]);
        if (!$item) {
            die(json_encode(['success' => false, 'error' => '找不到佇列項目']));
        }
        set_time_limit(600);
        videoprogress_queue_processing($item->fileid);
        videoprogress_do_compression($item);
        echo json_encode(['success' => true, 'message' => '壓縮完成']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// =========================================================================
// 載入活動
// =========================================================================
$id = optional_param('id', 0, PARAM_INT);
$v  = optional_param('v', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('videoprogress', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $videoprogress = $DB->get_record('videoprogress', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($v) {
    $videoprogress = $DB->get_record('videoprogress', ['id' => $v], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $videoprogress->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('videoprogress', $videoprogress->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('missingidandcmid', 'videoprogress');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videoprogress:view', $context);

videoprogress_view($videoprogress, $course, $cm, $context);

if ($videoprogress->completionpercent == 0) {
    videoprogress_trigger_completion($videoprogress->id, $USER->id);
}

// =========================================================================
// 準備資料
// =========================================================================
$userprogress = videoprogress_get_user_progress($videoprogress->id, $USER->id);
$videoData = ['type' => $videoprogress->videotype, 'url' => null, 'duration' => $videoprogress->videoduration ?? 0, 'chapters' => [], 'is_time_mode' => false];

// 影片偵測
if ($videoprogress->videotype === 'external' && !empty($videoprogress->externalurl)) {
    $detector = new video_detector($videoprogress->externalurl);
    $result = $detector->detect();
    $videoData['url'] = $result['video_url'];
    $videoData['chapters'] = $result['chapters'] ?? [];
    $videoData['is_evercam'] = $result['is_evercam'];
    $videoData['use_html5'] = $result['use_html5'] || $result['is_evercam'];
    $videoData['is_time_mode'] = !$videoData['use_html5'];
    if (!empty($result['duration']) && empty($videoprogress->videoduration)) {
        $DB->set_field('videoprogress', 'videoduration', $result['duration'], ['id' => $videoprogress->id]);
        $videoData['duration'] = $result['duration'];
    }
} else if ($videoprogress->videotype === 'upload' && zip_service::is_package($context->id)) {
    $config = zip_service::get_config($context->id);
    $videoData['url'] = zip_service::get_base_url($context->id) . ($config['src'][0]['src'] ?? 'media.mp4');
    $videoData['chapters'] = $config['index'] ?? [];
    $videoData['duration'] = $config['duration'] ?? 0;
    $videoData['is_zip'] = true;
}

$effectiveType = ($videoData['is_evercam'] ?? false) || ($videoData['use_html5'] ?? false) ? 'upload' : $videoprogress->videotype;

// =========================================================================
// JavaScript
// =========================================================================
$PAGE->requires->js_call_amd('mod_videoprogress/player', 'init', [[
    'cmid' => $cm->id,
    'videoid' => $videoprogress->id,
    'videotype' => $effectiveType,
    'videourl' => $videoprogress->videourl ?? '',
    'externalurl' => $videoprogress->externalurl ?? '',
    'detectedVideoUrl' => $videoData['url'],
    'duration' => $videoData['duration'],
    'lastposition' => $userprogress ? $userprogress->lastposition : 0,
    'completionpercent' => $videoprogress->completionpercent,
    'currentProgress' => $userprogress ? $userprogress->progress : 0,  // 傳遞目前百分比，防止刷新後縮回
    'requirefocus' => !empty($videoprogress->requirefocus),
    'externalmintime' => $videoprogress->externalmintime ?? 60,
]]);

// =========================================================================
// 頁面設定
// =========================================================================
$PAGE->set_url('/mod/videoprogress/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($videoprogress->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// =========================================================================
// 渲染
// =========================================================================
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($videoprogress->name));

if (!empty($videoprogress->intro)) {
    echo $OUTPUT->box(format_module_intro('videoprogress', $videoprogress, $cm->id), 'generalbox', 'intro');
}

echo compression_renderer::panel($context);
echo progress_renderer::render($userprogress, $videoprogress, $videoData['is_time_mode']);

echo '<div class="videoprogress-player card"><div class="card-body">';

switch ($videoprogress->videotype) {
    case 'youtube':
        echo player_renderer::youtube($videoprogress->videourl);
        break;
    case 'external':
        if ($videoData['use_html5'] ?? false) {
            $infoText = ($videoData['is_evercam'] ?? false) ? 'Evercam 雙畫面模式 - 精確進度追蹤已啟用' : '影片已自動偵測';
            echo player_renderer::html5($videoData['url'], $videoData['chapters'], $videoData['duration'], $infoText);
        } else {
            echo player_renderer::iframe($videoprogress->externalurl);
        }
        break;
    case 'upload':
        if ($videoData['is_zip'] ?? false) {
            echo player_renderer::html5($videoData['url'], $videoData['chapters'], $videoData['duration'], 'ZIP 套件模式 - 精確進度追蹤已啟用');
        } else {
            echo player_renderer::upload($context);
        }
        break;
    default:
        echo '<div class="alert alert-warning">' . get_string('error:novideo', 'videoprogress') . '</div>';
}

echo '</div></div>';

if ($userprogress && $userprogress->lastposition > 0) {
    // 外部 iframe 模式無法跳轉，只顯示資訊；其他模式顯示可點擊按鈕
    if ($videoprogress->videotype === 'external' && !($videoData['use_html5'] ?? false)) {
        echo player_renderer::resume_info($userprogress->lastposition);
    } else {
        echo player_renderer::resume_button($userprogress->lastposition);
    }
}

echo $OUTPUT->footer();
