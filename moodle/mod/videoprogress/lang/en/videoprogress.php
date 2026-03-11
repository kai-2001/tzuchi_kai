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

// Error messages
$string['error:zip_no_index'] = 'ZIP file must contain an index.html file. Please check your ZIP package structure.';
$string['error:unsafe_url'] = 'Unsafe URL: Cannot access internal or localhost addresses.';
$string['error:queue_not_found'] = 'Queue item not found';

// Player mode descriptions
$string['mode:evercam'] = 'Evercam dual-screen mode - Precise progress tracking enabled';
$string['mode:video_detected'] = 'Video auto-detected';
$string['mode:zip_package'] = 'ZIP package mode - Precise progress tracking enabled';

// Management page link
$string['open_compression_management'] = 'Open Compression Management';

// Compression Management
$string['compression_management'] = 'Video Compression Management';
$string['manage:compress_complete'] = 'Compression complete';
$string['manage:reset_complete'] = 'Process terminated and item reset';
$string['manage:removed_complete'] = 'Removed from queue';
$string['autoqueue'] = 'Auto-process compression queue';
$string['autoqueue_desc'] = 'When enabled, the system will automatically process the compression queue via cron. When disabled, compression must be manually triggered (like Windows mode).';
$string['manage:btn_priority'] = 'Priority';
$string['manage:priority_complete'] = 'Item moved to front of queue';
$string['manage:added_to_queue'] = 'Added to compression queue';
$string['manage:stats_title'] = 'Compression Statistics';
$string['manage:total_compressed'] = 'Files Compressed';
$string['manage:total_original'] = 'Original Size';
$string['manage:total_saved'] = 'Space Saved';
$string['manage:avg_rate'] = 'Avg. Rate';
$string['manage:queue_title'] = 'Processing Queue';
$string['manage:queue_empty'] = 'No videos currently waiting for compression.';
$string['manage:queue_desc'] = 'Select videos to compress and click "Start Compression". Max 3 items at a time.';
$string['manage:table_filename'] = 'Filename';
$string['manage:table_course'] = 'Course';
$string['manage:table_activity'] = 'Activity';
$string['manage:table_size'] = 'Size';
$string['manage:table_status'] = 'Status';
$string['manage:table_time'] = 'Time';
$string['manage:table_action'] = 'Action';
$string['manage:status_pending'] = 'Pending';
$string['manage:status_processing'] = 'Processing';
$string['manage:status_failed'] = 'Failed';
$string['manage:btn_start'] = 'Start Compression';
$string['manage:btn_reset'] = 'Reset Processing Items';
$string['manage:logs_title'] = 'Recently Completed';
$string['manage:log_original'] = 'Original';
$string['manage:log_compressed'] = 'Compressed';
$string['manage:log_saved'] = 'Saved';
$string['manage:videos_title'] = 'Available for Compression';
$string['manage:videos_desc'] = 'The following videos are >50MB and not in queue. You can add them manually:';
$string['manage:btn_add'] = 'Add to Queue';
$string['manage:confirm_reset'] = 'Are you sure you want to reset all "Processing" items?';
$string['manage:confirm_remove'] = 'Are you sure you want to remove this item?';
$string['manage:max_limit'] = 'You can select up to 3 items only';
$string['manage:processing_step'] = 'Compressing video {$a->current} / {$a->total}...';
$string['manage:finish_success'] = 'Finished! Success: {$a->success}, Failed: {$a->fail}';
$string['manage:btn_recover'] = 'Recover Temp Files';
$string['manage:recovering'] = 'Recovering...';
$string['manage:confirm_recover'] = 'Are you sure you want to attempt recovery of completed files from temp? This is usually used for tasks marked as failed due to timeout.';
$string['manage:recover_success'] = 'Recovery successful!';
$string['manage:recover_failed'] = 'Recovery failed: {$a}';


// ZIP Service Errors
$string['zip_extract_failed'] = 'ZIP extraction failed';
$string['zip_no_index'] = 'ZIP package must contain index.html';
$string['zip_invalid'] = 'Invalid or corrupt ZIP file';
$string['zip_too_many_files'] = 'File count limit exceeded ({$a})';
$string['zip_path_traversal'] = 'Path traversal attack detected';
$string['zip_bad_extension'] = 'File type not allowed: .{$a}';
$string['zip_too_large'] = 'Max uncompressed size limit exceeded';

// Renderers & Player
$string['manage:wait_warning'] = 'Compressing large videos may take several minutes. Please do not close this page.';
$string['html5_video_not_supported'] = 'Your browser does not support HTML5 video.';
$string['chapter_list'] = 'Chapters';

// Admin only label
$string['adminonly'] = 'Admin only';

// Privacy API
$string['privacy:metadata:segments'] = 'Information about video segments watched by the user';
$string['privacy:metadata:segments:userid'] = 'The ID of the user who watched the segment';
$string['privacy:metadata:segments:segmentstart'] = 'The start time of the watched segment (in seconds)';
$string['privacy:metadata:segments:segmentend'] = 'The end time of the watched segment (in seconds)';
$string['privacy:metadata:segments:timecreated'] = 'The time when the segment was watched';

$string['privacy:metadata:progress'] = 'Information about user\'s video viewing progress';
$string['privacy:metadata:progress:userid'] = 'The ID of the user';
$string['privacy:metadata:progress:currentposition'] = 'The current playback position (in seconds)';
$string['privacy:metadata:progress:percentcomplete'] = 'The percentage of video completed';
$string['privacy:metadata:progress:completed'] = 'Whether the video has been completed';
$string['privacy:metadata:progress:timemodified'] = 'The time when the progress was last updated';

// Security settings
$string['security_settings'] = 'Security Settings';
$string['security_settings_desc'] = 'These settings involve security risks, please adjust with caution.';
$string['iframe_relaxed_sandbox'] = 'Relax iframe security restrictions';
$string['iframe_relaxed_sandbox_desc'] = '<strong style="color: red;">⚠️ Security Risk! Do not enable unless necessary.</strong><br>When enabled, external URL iframes will allow <code>allow-same-origin</code>. This is required for some sites that use localStorage, but it allows the external site to access browser storage, posing a potential security risk.';

// Recovery strings
$string['manage:reset_success'] = 'Processing items have been reset';
$string['recover:found_items'] = 'Found {$a} items to recover';
$string['recover:no_items'] = 'No processing or failed items found.';
$string['recover:no_output'] = 'No valid output file found (Path: {$a})';
$string['recover:file_writing'] = '⚠️ File appears to be still writing (last update: {$a} seconds ago), please try again later';
$string['recover:file_corrupted'] = '❌ File exists but corrupted/incomplete (last update: {$a} seconds ago, possibly stopped)';
$string['recover:skip_verify'] = 'Skipping verification (no FFmpeg)';
$string['recover:success'] = '✅ Recovery successful!';
$string['recover:failed'] = '❌ Recovery failed: {$a}';
$string['recover:complete'] = 'Recovery complete! Successfully recovered {$a->count} items.\n\nDetailed report:\n{$a->logs}';

