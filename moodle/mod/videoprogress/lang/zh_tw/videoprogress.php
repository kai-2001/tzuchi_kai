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
 * 繁體中文語言字串
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = '影片進度追蹤';
$string['modulenameplural'] = '影片進度追蹤';
$string['modulename_help'] = '影片進度追蹤模組允許教師新增影片（YouTube 或上傳檔案），並追蹤學生的觀看進度。學生必須觀看指定百分比的影片才能完成活動。';
$string['pluginname'] = '影片進度追蹤';
$string['pluginadministration'] = '影片進度追蹤管理';

// 表單欄位
$string['name'] = '名稱';
$string['videotype'] = '影片來源';
$string['videotype_help'] = '選擇使用 YouTube 影片或上傳影片檔案。';
$string['videotype_youtube'] = 'YouTube';
$string['videotype_upload'] = '上傳檔案';
$string['videotype_external'] = '外部網址（iframe）';
$string['videourl'] = 'YouTube 網址';
$string['videourl_help'] = '輸入 YouTube 影片網址。支援多種格式，如 youtube.com/watch?v=xxx 或 youtu.be/xxx';
$string['externalurl'] = '外部網址';
$string['externalurl_help'] = '輸入要嵌入的外部頁面網址。進度將根據停留在頁面的時間計算。';
$string['externaltimetracking'] = '進度根據您在此頁面停留的時間計算。';
$string['externalmintime'] = '最少停留秒數';
$string['externalmintime_help'] = '學生必須在此頁面停留的最少秒數才算完成。例如：300 = 5分鐘。';
$string['videofile'] = '影片檔案';
$string['videofile_help'] = '上傳影片檔案（MP4、WebM 等）';
$string['videoduration'] = '影片長度（秒）';
$string['videoduration_help'] = '輸入影片的總長度（秒）。YouTube 和上傳影片會自動偵測。';
$string['detectduration'] = '偵測長度';
$string['completionpercent'] = '完成門檻（%）';
$string['completionpercent_help'] = '學生必須觀看的影片百分比，達到後才會標記活動完成。';
$string['completionusepercent'] = '要求達到完成分數';
$string['completiondetail:percent'] = '觀看 {$a}% 以上的影片';
$string['completiondetail:view'] = '點開即完成';

// 觀看頁面
$string['watchvideo'] = '觀看影片';
$string['yourprogress'] = '您的進度';
$string['percentwatched'] = '已觀看 {$a}%';
$string['completed'] = '已完成';
$string['notcompleted'] = '未完成';
$string['resumefrom'] = '從 {$a} 繼續觀看';
$string['watchedsegments'] = '已觀看區段';
$string['seconds'] = '秒';
$string['externalrequirement'] = '需停留 {$a} 秒';
$string['requirefocus'] = '專注模式（切換分頁時暫停）';
$string['requirefocus_help'] = '啟用後，當學生切換到其他分頁或視窗時，影片會自動暫停且停止計算進度。這可以確保學生專心觀看影片。';
$string['clicktostart'] = '請點擊影片開始播放，計時將自動開始';
$string['timerstarted'] = '計時已開始';
$string['timerpaused'] = '計時已暫停，請點擊影片繼續';
$string['clickvideoplay'] = '請點擊影片播放按鈕繼續';

// 進度報告
$string['progressreport'] = '進度報告';
$string['student'] = '學生';
$string['progress'] = '進度';
$string['lastaccess'] = '最後存取';
$string['status'] = '狀態';
$string['noattempts'] = '尚未有觀看紀錄';

// 權限
$string['videoprogress:view'] = '檢視影片';
$string['videoprogress:addinstance'] = '新增影片進度追蹤活動';
$string['videoprogress:viewreport'] = '檢視進度報告';

// 重設
$string['resetprogress'] = '重設所有影片進度資料';

// 錯誤訊息
$string['error:novideo'] = '此活動尚未設定影片。';
$string['error:invalidurl'] = '無效的 YouTube 網址。';

// 完成條件
$string['completiondetail:percent'] = '觀看至少 {$a}% 的影片';

// ZIP 安全驗證
$string['zip_validation_failed'] = 'ZIP 驗證失敗：{$a}';

// FFmpeg 壓縮設定
$string['ffmpeg_settings'] = '影片壓縮設定';
$string['ffmpeg_settings_desc'] = '設定 FFmpeg 自動壓縮上傳的影片，節省硬碟空間。';
$string['enablecompression'] = '啟用影片壓縮';
$string['enablecompression_desc'] = '上傳影片後自動在背景使用 FFmpeg 壓縮。';
$string['ffmpegpath'] = 'FFmpeg 路徑';
$string['ffmpegpath_desc'] = 'FFmpeg 執行檔的完整路徑。例如：C:\\ffmpeg\\bin\\ffmpeg.exe (Windows) 或 /usr/bin/ffmpeg (Linux)';
$string['compressioncrf'] = '壓縮品質 (CRF)';
$string['compressioncrf_desc'] = '固定速率因子：數值越低品質越好但檔案越大。建議值：23';
$string['crf_high'] = '高品質 (CRF 18) - 檔案較大';
$string['crf_medium'] = '中品質 (CRF 23) - 建議';
$string['crf_low'] = '低品質 (CRF 28) - 檔案最小';

// 壓縮任務
$string['task_compress_video'] = '壓縮影片檔案';
$string['compression_started'] = '影片壓縮已開始';
$string['compression_completed'] = '影片壓縮已完成';
$string['compression_failed'] = '影片壓縮失敗';
$string['compression_skipped'] = '影片壓縮已跳過（FFmpeg 未設定）';

// FFmpeg 偵測狀態
$string['ffmpeg_detected'] = '✅ <strong>已偵測到 FFmpeg</strong>：{$a}<br>您可以啟用影片壓縮功能來節省硬碟空間。';
$string['ffmpeg_not_detected'] = '⚠️ <strong>未偵測到 FFmpeg</strong><br>此功能為選用功能。如果您不需要自動壓縮影片，可以忽略此區塊。<br>如需使用，請先安裝 <a href="https://ffmpeg.org/download.html" target="_blank">FFmpeg</a>，然後在下方填入路徑。';

// 定時任務
$string['task_process_compression'] = '處理影片壓縮佇列';

// 離峰時段設定
$string['offpeakhours'] = '啟用離峰時段';
$string['offpeakhours_desc'] = '只在離峰時段執行壓縮，避免影響系統效能';
$string['offpeakstart'] = '離峰時段開始';
$string['offpeakstart_desc'] = '壓縮任務可執行的起始時間';
$string['offpeakend'] = '離峰時段結束';
$string['offpeakend_desc'] = '壓縮任務可執行的結束時間';

// 壓縮管理頁面
$string['compression_management'] = '影片壓縮管理';

// 表單靜態說明
$string['upload_zip_note'] = '<i class="fa fa-info-circle"></i> <strong>支援 Evercam ZIP 套件</strong><br><small>• 如上傳 <strong>ZIP 檔案</strong>，需包含：<code>index.html</code>（必要）、影片檔案、<code>config.js</code>（可選，用於章節目錄）<br>• 如為<strong>單純影片檔</strong>（MP4、MOV 等），請直接上傳影片檔案即可</small>';
$string['completionpercent_note'] = '設為 0% 表示點開即完成';
$string['external_detection_note'] = '<strong>外部網址自動偵測：</strong><br>系統會嘗試自動偵測網頁中的影片。如果偵測成功，將使用「觀看百分比」作為完成條件；如果無法偵測，則使用「最少停留秒數」作為完成條件。';
