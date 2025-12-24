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
 * Video Progress 英文語言檔
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['modulename'] = 'Video Progress';
$string['modulenameplural'] = 'Video Progress';
$string['modulename_help'] = 'The Video Progress module allows teachers to add videos (YouTube or uploaded files) with progress tracking. Students must watch a specified percentage of the video to complete the activity.';
$string['pluginname'] = 'Video Progress';
$string['pluginadministration'] = 'Video Progress administration';

// 表單欄位
$string['name'] = 'Name';
$string['videotype'] = 'Video source';
$string['videotype_help'] = 'Choose whether to use a YouTube video or upload a video file.';
$string['videotype_youtube'] = 'YouTube';
$string['videotype_upload'] = 'Upload file';
$string['videotype_external'] = 'External URL (iframe)';
$string['videourl'] = 'YouTube URL';
$string['videourl_help'] = 'Enter the YouTube video URL. Supports various formats like youtube.com/watch?v=xxx or youtu.be/xxx';
$string['externalurl'] = 'External URL';
$string['externalurl_help'] = 'Enter the URL of the external page to embed. Progress will be tracked based on time spent on the page.';
$string['externaltimetracking'] = 'Progress is tracked based on time spent viewing this page.';
$string['externalmintime'] = 'Minimum viewing time (seconds)';
$string['externalmintime_help'] = 'Minimum time in seconds that students must stay on this page to complete. For example: 300 = 5 minutes.';
$string['videofile'] = 'Video file';
$string['videofile_help'] = 'Upload a video file (MP4, WebM, etc.)';
$string['videoduration'] = 'Video duration (seconds)';
$string['videoduration_help'] = 'Enter the total duration of the video in seconds. YouTube and uploaded videos are detected automatically.';
$string['detectduration'] = 'Detect Duration';
$string['completionpercent'] = 'Completion threshold (%)';
$string['completionpercent_help'] = 'Students must reach this percentage of the video to be marked as complete.';
$string['completionusepercent'] = 'Require completion score';

// 觀看頁面
$string['watchvideo'] = 'Watch video';
$string['yourprogress'] = 'Your progress';
$string['percentwatched'] = '{$a}% watched';
$string['completed'] = 'Completed';
$string['notcompleted'] = 'Not completed';
$string['resumefrom'] = 'Resume from {$a}';
$string['watchedsegments'] = 'Watched segments';
$string['seconds'] = 'seconds';
$string['externalrequirement'] = 'Required: {$a} seconds';
$string['requirefocus'] = 'Focus Mode (pause when switching tabs)';
$string['requirefocus_help'] = 'When enabled, the video will automatically pause and stop counting progress when the student switches to another tab or window. This ensures students stay focused on the video.';
$string['clicktostart'] = 'Click the video to start playing, timer will start automatically';
$string['timerstarted'] = 'Timer started';
$string['timerpaused'] = 'Timer paused, click the video to continue';

// 進度報告
$string['progressreport'] = 'Progress report';
$string['student'] = 'Student';
$string['progress'] = 'Progress';
$string['lastaccess'] = 'Last access';
$string['status'] = 'Status';
$string['noattempts'] = 'No attempts yet';

// 權限
$string['videoprogress:view'] = 'View video';
$string['videoprogress:addinstance'] = 'Add a new Video Progress activity';
$string['videoprogress:viewreport'] = 'View progress report';

// 重設
$string['resetprogress'] = 'Reset all video progress data';

// 錯誤訊息
$string['error:novideo'] = 'No video configured for this activity.';
$string['error:invalidurl'] = 'Invalid YouTube URL.';

// 完成條件
$string['completiondetail:percent'] = 'Watch at least {$a}% of the video';
