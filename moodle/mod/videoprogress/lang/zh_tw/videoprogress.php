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
 * Video Progress 繁體中文語言檔
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
