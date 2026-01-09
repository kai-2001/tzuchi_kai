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
 * Video Progress 模組管理設定
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    
    // 嘗試自動偵測 FFmpeg
    $ffmpegDetected = false;
    $detectedPath = '';
    
    // 常見的 FFmpeg 路徑
    $commonPaths = [];
    if (PHP_OS_FAMILY === 'Windows') {
        $commonPaths = [
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\Program Files (x86)\\ffmpeg\\bin\\ffmpeg.exe',
        ];
    } else {
        $commonPaths = [
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/opt/homebrew/bin/ffmpeg',
        ];
    }
    
    // 檢查已設定的路徑
    $savedPath = get_config('mod_videoprogress', 'ffmpegpath');
    if (!empty($savedPath) && file_exists($savedPath)) {
        $ffmpegDetected = true;
        $detectedPath = $savedPath;
    } else {
        // 嘗試常見路徑
        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                $ffmpegDetected = true;
                $detectedPath = $path;
                break;
            }
        }
    }
    
    // 根據是否偵測到 FFmpeg 顯示不同訊息
    if ($ffmpegDetected) {
        $statusMessage = get_string('ffmpeg_detected', 'mod_videoprogress', $detectedPath);
    } else {
        $statusMessage = get_string('ffmpeg_not_detected', 'mod_videoprogress');
    }
    
    // FFmpeg 壓縮設定區塊（進階選項）
    $manageUrl = new moodle_url('/mod/videoprogress/manage_compression.php');
    $manageLink = '<a href="' . $manageUrl . '" class="btn btn-primary btn-sm" style="color: #fff !important;"><i class="fa fa-cogs"></i> 開啟壓縮管理頁面</a>';
    
    $settings->add(new admin_setting_heading(
        'mod_videoprogress/ffmpeg_header',
        get_string('ffmpeg_settings', 'mod_videoprogress'),
        $statusMessage . '<br><br>' . $manageLink . '<br><br>' . get_string('ffmpeg_settings_desc', 'mod_videoprogress')
    ));

    // 啟用/停用壓縮
    $settings->add(new admin_setting_configcheckbox(
        'mod_videoprogress/enablecompression',
        get_string('enablecompression', 'mod_videoprogress'),
        get_string('enablecompression_desc', 'mod_videoprogress'),
        0  // 預設關閉
    ));

    // FFmpeg 執行檔路徑（只有在未偵測到時才需要手動填寫）
    $defaultPath = $ffmpegDetected ? $detectedPath : (PHP_OS_FAMILY === 'Windows' ? 'C:\\ffmpeg\\bin\\ffmpeg.exe' : '/usr/bin/ffmpeg');
    $settings->add(new admin_setting_configtext(
        'mod_videoprogress/ffmpegpath',
        get_string('ffmpegpath', 'mod_videoprogress'),
        get_string('ffmpegpath_desc', 'mod_videoprogress'),
        $defaultPath,
        PARAM_RAW
    ));

    // 壓縮品質 (CRF 值)
    $crfoptions = [
        '18' => get_string('crf_high', 'mod_videoprogress'),
        '23' => get_string('crf_medium', 'mod_videoprogress'),
        '28' => get_string('crf_low', 'mod_videoprogress'),
    ];
    $settings->add(new admin_setting_configselect(
        'mod_videoprogress/compressioncrf',
        get_string('compressioncrf', 'mod_videoprogress'),
        get_string('compressioncrf_desc', 'mod_videoprogress'),
        '23',
        $crfoptions
    ));

    // 離峰時段設定（只在 Linux 環境顯示，Windows 沒有 cron 無法使用此功能）
    if (PHP_OS_FAMILY !== 'Windows') {
        $settings->add(new admin_setting_configcheckbox(
            'mod_videoprogress/offpeakhours',
            get_string('offpeakhours', 'mod_videoprogress'),
            get_string('offpeakhours_desc', 'mod_videoprogress'),
            0  // 預設關閉（不限制時段）
        ));
        
        // 離峰時段起始時間
        $houroptions = [];
        for ($i = 0; $i < 24; $i++) {
            $houroptions[$i] = sprintf('%02d:00', $i);
        }
        $settings->add(new admin_setting_configselect(
            'mod_videoprogress/offpeakstart',
            get_string('offpeakstart', 'mod_videoprogress'),
            get_string('offpeakstart_desc', 'mod_videoprogress'),
            '2',  // 預設凌晨 2 點
            $houroptions
        ));
        
        // 離峰時段結束時間
        $settings->add(new admin_setting_configselect(
            'mod_videoprogress/offpeakend',
            get_string('offpeakend', 'mod_videoprogress'),
            get_string('offpeakend_desc', 'mod_videoprogress'),
            '6',  // 預設凌晨 6 點
            $houroptions
        ));
    }
}
