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
 * English strings for videoprogress
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Video Progress';
$string['modulenameplural'] = 'Video Progress';
$string['modulename_help'] = 'The Video Progress module allows teachers to add videos (YouTube or uploaded files) and track student viewing progress. Students must watch a specified percentage of the video to complete the activity.';
$string['pluginname'] = 'Video Progress';
$string['pluginadministration'] = 'Video Progress Administration';

// Form fields
$string['name'] = 'Name';
$string['videotype'] = 'Video source';
$string['videotype_help'] = 'Choose between YouTube video or uploaded file.';
$string['videotype_youtube'] = 'YouTube';
$string['videotype_upload'] = 'Upload file';
$string['videotype_external'] = 'External URL (iframe)';
$string['videourl'] = 'YouTube URL';
$string['videourl_help'] = 'Enter the YouTube video URL. Supports various formats like youtube.com/watch?v=xxx or youtu.be/xxx';
$string['externalurl'] = 'External URL';
$string['externalurl_help'] = 'Enter the external page URL to embed. Progress will be calculated based on time spent on the page.';
$string['externaltimetracking'] = 'Progress is calculated based on your time spent on this page.';
$string['externalmintime'] = 'Minimum time (seconds)';
$string['externalmintime_help'] = 'Minimum seconds the student must spend on this page to complete. Example: 300 = 5 minutes.';
$string['videofile'] = 'Video file';
$string['videofile_help'] = 'Upload a video file (MP4, WebM, etc.)';
$string['videoduration'] = 'Video duration (seconds)';
$string['videoduration_help'] = 'Enter the total video duration in seconds. YouTube and uploaded videos are auto-detected.';
$string['detectduration'] = 'Detect duration';
$string['completionpercent'] = 'Completion threshold (%)';
$string['completionpercent_help'] = 'Percentage of video the student must watch to mark the activity as complete.';
$string['completionusepercent'] = 'Require completion percentage';
$string['completiondetail:percent'] = 'Watch {$a}% of the video';
$string['completiondetail:view'] = 'Open to complete';

// View page
$string['watchvideo'] = 'Watch video';
$string['yourprogress'] = 'Your progress';
$string['percentwatched'] = '{$a}% watched';
$string['completed'] = 'Completed';
$string['notcompleted'] = 'Not completed';
$string['resumefrom'] = 'Resume from {$a}';
$string['watchedsegments'] = 'Watched segments';
$string['seconds'] = 'seconds';
$string['externalrequirement'] = 'Required: {$a} seconds';
$string['requirefocus'] = 'Focus mode (pause when switching tabs)';
$string['requirefocus_help'] = 'When enabled, the video will automatically pause when the student switches to another tab or window, and progress tracking will stop. This ensures students focus on watching the video.';
$string['clicktostart'] = 'Click the video to start playback, timer will start automatically';
$string['timerstarted'] = 'Timer started';
$string['timerpaused'] = 'Timer paused, click video to continue';
$string['clickvideoplay'] = 'Click the video play button to continue';

// Progress report
$string['progressreport'] = 'Progress report';
$string['student'] = 'Student';
$string['progress'] = 'Progress';
$string['lastaccess'] = 'Last access';
$string['status'] = 'Status';
$string['noattempts'] = 'No viewing records yet';

// Capabilities
$string['videoprogress:view'] = 'View video';
$string['videoprogress:addinstance'] = 'Add Video Progress activity';
$string['videoprogress:viewreport'] = 'View progress report';

// Reset
$string['resetprogress'] = 'Reset all video progress data';

// Error messages
$string['error:novideo'] = 'No video has been configured for this activity.';
$string['error:invalidurl'] = 'Invalid YouTube URL.';

// Completion rules
$string['completiondetail:percent'] = 'Watch at least {$a}% of the video';

// ZIP validation
$string['zip_validation_failed'] = 'ZIP validation failed: {$a}';

// FFmpeg compression settings
$string['ffmpeg_settings'] = 'Video Compression Settings';
$string['ffmpeg_settings_desc'] = 'Configure FFmpeg to automatically compress uploaded videos and save disk space.';
$string['enablecompression'] = 'Enable video compression';
$string['enablecompression_desc'] = 'Automatically compress uploaded videos in the background using FFmpeg.';
$string['ffmpegpath'] = 'FFmpeg path';
$string['ffmpegpath_desc'] = 'Full path to the FFmpeg executable. Example: C:\\ffmpeg\\bin\\ffmpeg.exe (Windows) or /usr/bin/ffmpeg (Linux)';
$string['compressioncrf'] = 'Compression quality (CRF)';
$string['compressioncrf_desc'] = 'Constant Rate Factor: lower = better quality but larger file. Recommended: 23';
$string['crf_high'] = 'High quality (CRF 18) - Larger file';
$string['crf_medium'] = 'Medium quality (CRF 23) - Recommended';
$string['crf_low'] = 'Low quality (CRF 28) - Smallest file';

// Compression task
$string['task_compress_video'] = 'Compress video files';
$string['compression_started'] = 'Video compression started';
$string['compression_completed'] = 'Video compression completed';
$string['compression_failed'] = 'Video compression failed';
$string['compression_skipped'] = 'Video compression skipped (FFmpeg not configured)';

// FFmpeg detection status
$string['ffmpeg_detected'] = '✅ <strong>FFmpeg detected</strong>: {$a}<br>You can enable video compression to save disk space.';
$string['ffmpeg_not_detected'] = '⚠️ <strong>FFmpeg not detected</strong><br>This feature is optional. If you don\'t need automatic video compression, you can ignore this section.<br>To use this feature, please install <a href="https://ffmpeg.org/download.html" target="_blank">FFmpeg</a> first, then enter the path below.';

// Scheduled task
$string['task_process_compression'] = 'Process video compression queue';

// Off-peak hours settings
$string['offpeakhours'] = 'Enable off-peak hours';
$string['offpeakhours_desc'] = 'Only run compression during off-peak hours to reduce server load';
$string['offpeakstart'] = 'Off-peak start time';
$string['offpeakstart_desc'] = 'Start time when compression tasks can run';
$string['offpeakend'] = 'Off-peak end time';
$string['offpeakend_desc'] = 'End time when compression tasks can run';

// Compression management page
$string['compression_management'] = 'Video Compression Management';

// Form static notes
$string['upload_zip_note'] = '<i class="fa fa-info-circle"></i> <strong>Evercam ZIP Package Supported</strong><br><small>• For <strong>ZIP files</strong>, must contain: <code>index.html</code> (required), video file, <code>config.js</code> (optional, for chapters)<br>• For <strong>plain video files</strong> (MP4, MOV, etc.), just upload the video file directly</small>';
$string['completionpercent_note'] = 'Set to 0% means open to complete';
$string['external_detection_note'] = '<strong>External URL auto-detection:</strong><br>The system will attempt to detect videos on the page. If successful, "viewing percentage" will be used as completion criteria; otherwise, "minimum time" will be used.';
