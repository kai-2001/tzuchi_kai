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

// 如果完成門檻設為 0%，點開即完成
if ($videoprogress->completionpercent == 0) {
    videoprogress_trigger_completion($videoprogress->id, $USER->id);
}

// 設定頁面
$PAGE->set_url('/mod/videoprogress/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($videoprogress->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// 取得使用者進度
$userprogress = videoprogress_get_user_progress($videoprogress->id, $USER->id);

// 外部網址模式：嘗試偵測實際影片 URL
$detectedVideoUrl = null;
$detectedDuration = null;
$useHtml5Video = false;
$isEvercam = false;  // Evercam 使用 Player.js 協議

if ($videoprogress->videotype === 'external' && !empty($videoprogress->externalurl)) {
    $externalUrl = $videoprogress->externalurl;
    
    // 計算基礎 URL
    $baseUrl = $externalUrl;
    if (preg_match('/index\.html$/i', $externalUrl)) {
        $baseUrl = preg_replace('/index\.html$/i', '', $externalUrl);
    }
    if (!str_ends_with($baseUrl, '/')) {
        $baseUrl .= '/';
    }
    
    $actualMp4Url = null;
    
    // 優先嘗試 config.js（可取得實際影片檔名和章節資訊）
    $configUrl = $baseUrl . 'config.js';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $configUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    $configContent = curl_exec($ch);
    $configHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($configHttpCode === 200 && $configContent) {
        // 解析 config.js 取得影片檔名
        if (preg_match('/var\s+config\s*=\s*(\{.+\})/s', $configContent, $matches)) {
            $configJson = json_decode($matches[1], true);
            if ($configJson && isset($configJson['src'][0]['src'])) {
                $videoFilename = $configJson['src'][0]['src'];
                $actualMp4Url = $baseUrl . $videoFilename;
                $isEvercam = true;
            }
        }
    }
    
    // 如果 config.js 不存在，嘗試標準 media.mp4
    if (!$isEvercam) {
        $guessedMp4 = $baseUrl . 'media.mp4';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $guessedMp4,
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 206) {
            $isEvercam = true;
            $actualMp4Url = $guessedMp4;
        }
    }
    
    // 如果確認是 Evercam，嘗試取得影片時長
    if ($isEvercam && $actualMp4Url) {
        if (empty($videoprogress->videoduration)) {
            $detection = videoprogress_detect_external_video($actualMp4Url);
            if ($detection && !empty($detection['duration'])) {
                $detectedDuration = $detection['duration'];
                global $DB;
                $DB->set_field('videoprogress', 'videoduration', $detectedDuration, ['id' => $videoprogress->id]);
                $videoprogress->videoduration = $detectedDuration;
            }
        } else {
            $detectedDuration = $videoprogress->videoduration;
        }
    }
    
    // 非 Evercam：嘗試偵測並轉換為 HTML5 Video
    if (!$isEvercam) {
        
        // 如果資料庫已有 duration（之前成功偵測過），用簡單的 URL 替換來快速取得影片 URL
        if (!empty($videoprogress->videoduration)) {
            // 嘗試常見的替換模式
            if (preg_match('/(.+\/)index\.html$/i', $externalUrl, $matches)) {
                // index.html -> media.mp4
                $guessedUrl = $matches[1] . 'media.mp4';
                // 快速檢查 URL 是否有效（HEAD 請求）
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $guessedUrl,
                    CURLOPT_NOBODY => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($code === 200 || $code === 206) {
                    $detectedVideoUrl = $guessedUrl;
                    $detectedDuration = $videoprogress->videoduration;
                    $useHtml5Video = true;
                }
            }
        }
        
        // 如果快速替換失敗，嘗試完整偵測
        if (!$useHtml5Video) {
            $detection = videoprogress_detect_external_video($externalUrl);
            if ($detection && !empty($detection['videourl'])) {
                $detectedVideoUrl = $detection['videourl'];
                $detectedDuration = $detection['duration'];
                $useHtml5Video = true;
                
                // 如果偵測到時長且資料庫中沒有，更新資料庫
                if ($detectedDuration && empty($videoprogress->videoduration)) {
                    global $DB;
                    $DB->set_field('videoprogress', 'videoduration', $detectedDuration, ['id' => $videoprogress->id]);
                    $videoprogress->videoduration = $detectedDuration;
                }
            }
        }
    }
}

// 決定 videotype
$effectiveVideoType = $videoprogress->videotype;
if ($isEvercam) {
    $effectiveVideoType = 'upload';  // Evercam 使用雙畫面：HTML5 Video + 章節目錄
} else if ($useHtml5Video) {
    $effectiveVideoType = 'upload';   // 轉換為 HTML5 Video
}

// 載入 JavaScript 模組
$jsconfig = array(
    'cmid' => $cm->id,
    'videoid' => $videoprogress->id,
    'videotype' => $effectiveVideoType,
    'videourl' => $videoprogress->videourl,
    'externalurl' => isset($videoprogress->externalurl) ? $videoprogress->externalurl : '',
    'detectedVideoUrl' => $detectedVideoUrl, // 偵測到的影片 URL
    'duration' => $detectedDuration ?? $videoprogress->videoduration,
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

if ($videoprogress->videotype === 'external' && !$useHtml5Video && !$isEvercam) {
    // 外部網址（iframe 計時模式）：顯示「已觀看 X 秒 / 需要 Y 秒」
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
    // YouTube、上傳影片、Evercam、以及外部網址（HTML5 Video 模式）：顯示百分比
    
    // 特殊處理：如果完成門檻為 0%（點開即完成），直接顯示 100% 完成
    if ($videoprogress->completionpercent == 0) {
        $iscompleted = true;
        $displayPercent = 100;
    } else {
        $iscompleted = $percentcomplete >= $videoprogress->completionpercent;
        $displayPercent = $percentcomplete;
    }
    
    echo '<div class="progress mb-2" style="height: 25px;">';
    echo '<div class="progress-bar ' . ($iscompleted ? 'bg-success' : 'bg-primary') . '" role="progressbar" ';
    echo 'style="width: ' . $displayPercent . '%;" aria-valuenow="' . $displayPercent . '" aria-valuemin="0" aria-valuemax="100">';
    echo $displayPercent . '%';
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
    if ($isEvercam) {
        // Evercam 雙畫面模式：左邊 HTML5 Video + 右邊章節目錄
        // 使用 $actualMp4Url (可能是 media.mp4 或從 config.js 解析的檔名)
        $mp4Url = $actualMp4Url;
        $configUrl = $baseUrl . 'config.js';
        
        // 抓取 config.js 解析章節資料
        $chapters = [];
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $configUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        $configContent = curl_exec($ch);
        curl_close($ch);
        
        // 解析 config.js (格式: var config = {...})
        if ($configContent && preg_match('/var\s+config\s*=\s*(\{.+\})/s', $configContent, $matches)) {
            $configJson = json_decode($matches[1], true);
            if ($configJson && isset($configJson['index'])) {
                $chapters = $configJson['index'];
                // 如果沒有 duration，從 config 取得
                if (empty($detectedDuration) && !empty($configJson['duration'])) {
                    $detectedDuration = intval($configJson['duration']);
                }
            }
        }
        
        echo '<div class="alert alert-info mb-2">';
        echo '<i class="fa fa-info-circle"></i> Evercam 雙畫面模式 - 精確進度追蹤已啟用';
        if ($detectedDuration) {
            $minutes = floor($detectedDuration / 60);
            $seconds = $detectedDuration % 60;
            echo ' (' . $minutes . ' 分 ' . $seconds . ' 秒)';
        }
        echo '</div>';
        
        echo '<div class="row">';
        // 左邊：HTML5 Video 播放器 (用於播放和追蹤)
        echo '<div class="col-md-8">';
        echo '<video id="videoprogress-html5-player" class="w-100" controls style="border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">';
        echo '<source src="' . s($mp4Url) . '" type="video/mp4">';
        echo get_string('error:novideo', 'videoprogress');
        echo '</video>';
        echo '</div>';
        
        // 右邊：章節目錄 (原生渲染)
        echo '<div class="col-md-4">';
        echo '<div class="card h-100">';
        echo '<div class="card-header bg-primary text-white"><i class="fa fa-list"></i> 章節目錄</div>';
        echo '<div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">';
        
        if (!empty($chapters)) {
            echo '<ul class="list-group list-group-flush" id="videoprogress-chapters">';
            $sn = 0;
            $subSn = 0;
            foreach ($chapters as $chapter) {
                $timeMs = intval($chapter['time']);
                $timeSec = $timeMs / 1000;
                $indent = isset($chapter['indent']) && $chapter['indent'] == '1';
                
                // 計算序號
                if ($indent) {
                    $subSn++;
                    $snStr = $sn . '.' . $subSn;
                } else {
                    $sn++;
                    $subSn = 0;
                    $snStr = $sn . '.';
                }
                
                // 格式化時間
                $mins = floor($timeSec / 60);
                $secs = floor($timeSec % 60);
                $timeStr = sprintf('%02d:%02d', $mins, $secs);
                
                $paddingLeft = $indent ? 'padding-left: 30px;' : '';
                
                echo '<li class="list-group-item d-flex justify-content-between align-items-center" ';
                echo 'style="cursor: pointer; ' . $paddingLeft . '" ';
                echo 'data-time="' . $timeSec . '" onclick="document.getElementById(\'videoprogress-html5-player\').currentTime=' . $timeSec . '; document.getElementById(\'videoprogress-html5-player\').play();">';
                echo '<span><strong class="text-muted">' . $snStr . '</strong> ' . s($chapter['title']) . '</span>';
                echo '<span class="badge bg-secondary">' . $timeStr . '</span>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="p-3 text-muted text-center">無法載入章節目錄</div>';
        }
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // 傳遞給 JS 使用 upload 類型的追蹤邏輯
        $effectiveVideoType = 'upload';
        
    } else if ($useHtml5Video && $detectedVideoUrl) {
        // 偵測到 .mp4，使用 HTML5 Video 標籤直接播放
        echo '<div class="alert alert-success mb-2">';
        echo '<i class="fa fa-check"></i> ';
        if ($detectedDuration) {
            $minutes = floor($detectedDuration / 60);
            $seconds = $detectedDuration % 60;
            echo '影片已自動偵測 (' . $minutes . ' 分 ' . $seconds . ' 秒)';
        } else {
            echo '影片已自動偵測';
        }
        echo '</div>';
        echo '<video id="videoprogress-html5-player" class="w-100" controls>';
        echo '<source src="' . s($detectedVideoUrl) . '" type="video/mp4">';
        echo get_string('error:novideo', 'videoprogress');
        echo '</video>';
    } else {
        // 無法偵測到 .mp4，使用原本的 iframe 模式 + 計時
        echo '<div id="videoprogress-timer-hint" class="alert alert-warning mb-2">';
        echo '<i class="fa fa-hand-pointer-o"></i> ' . get_string('clicktostart', 'videoprogress');
        echo '</div>';
        echo '<div id="videoprogress-external-wrapper" style="position: relative;">';
        echo '<div id="videoprogress-external-container" class="ratio ratio-16x9">';
        echo '<iframe id="videoprogress-external-iframe" src="' . s($videoprogress->externalurl) . '" ';
        echo 'sandbox="allow-scripts" referrerpolicy="no-referrer" allowfullscreen frameborder="0"></iframe>';
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
    }
} else if ($videoprogress->videotype === 'upload') {
    // 檢查是否為 ZIP 套件
    if (videoprogress_is_zip_package($context->id)) {
        // ZIP 套件模式：Evercam 風格雙畫面
        $packageBaseUrl = videoprogress_get_package_base_url($context->id);
        
        // 預設影片檔名
        $videoFilename = 'media.mp4';
        $chapters = [];
        $zipDuration = null;
        
        // 嘗試從 config.js 取得章節資料和實際影片檔名
        $fs = get_file_storage();
        $configFile = $fs->get_file($context->id, 'mod_videoprogress', 'package', 0, '/', 'config.js');
        if ($configFile) {
            $configContent = $configFile->get_content();
            if (preg_match('/var\s+config\s*=\s*(\{.+\})/s', $configContent, $matches)) {
                $configJson = json_decode($matches[1], true);
                if ($configJson) {
                    // 取得影片檔名
                    if (isset($configJson['src'][0]['src'])) {
                        $videoFilename = $configJson['src'][0]['src'];
                    }
                    // 取得章節
                    if (isset($configJson['index'])) {
                        $chapters = $configJson['index'];
                    }
                    // 取得時長
                    if (isset($configJson['duration'])) {
                        $zipDuration = intval($configJson['duration']);
                    }
                }
            }
        }
        
        $mp4Url = $packageBaseUrl . $videoFilename;
        
        echo '<div class="alert alert-info mb-2">';
        echo '<i class="fa fa-info-circle"></i> ZIP 套件模式 - 精確進度追蹤已啟用';
        if ($zipDuration) {
            $minutes = floor($zipDuration / 60);
            $seconds = $zipDuration % 60;
            echo ' (' . $minutes . ' 分 ' . $seconds . ' 秒)';
        }
        echo '</div>';
        
        if (!empty($chapters)) {
            // 雙畫面模式
            echo '<div class="row">';
            echo '<div class="col-md-8">';
            echo '<video id="videoprogress-html5-player" class="w-100" controls style="border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">';
            echo '<source src="' . s($mp4Url) . '" type="video/mp4">';
            echo get_string('error:novideo', 'videoprogress');
            echo '</video>';
            echo '</div>';
            
            // 章節目錄
            echo '<div class="col-md-4">';
            echo '<div class="card h-100">';
            echo '<div class="card-header bg-primary text-white"><i class="fa fa-list"></i> 章節目錄</div>';
            echo '<div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">';
            echo '<ul class="list-group list-group-flush" id="videoprogress-chapters">';
            $sn = 0;
            $subSn = 0;
            foreach ($chapters as $chapter) {
                $timeMs = intval($chapter['time']);
                $timeSec = $timeMs / 1000;
                $indent = isset($chapter['indent']) && $chapter['indent'] == '1';
                
                if ($indent) {
                    $subSn++;
                    $snStr = $sn . '.' . $subSn;
                } else {
                    $sn++;
                    $subSn = 0;
                    $snStr = $sn . '.';
                }
                
                $mins = floor($timeSec / 60);
                $secs = floor($timeSec % 60);
                $timeStr = sprintf('%02d:%02d', $mins, $secs);
                
                $paddingLeft = $indent ? 'padding-left: 30px;' : '';
                
                echo '<li class="list-group-item d-flex justify-content-between align-items-center" ';
                echo 'style="cursor: pointer; ' . $paddingLeft . '" ';
                echo 'data-time="' . $timeSec . '" onclick="document.getElementById(\'videoprogress-html5-player\').currentTime=' . $timeSec . '; document.getElementById(\'videoprogress-html5-player\').play();">';
                echo '<span><strong class="text-muted">' . $snStr . '</strong> ' . s($chapter['title']) . '</span>';
                echo '<span class="badge bg-secondary">' . $timeStr . '</span>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        } else {
            // 無章節：單一播放器
            echo '<video id="videoprogress-html5-player" class="w-100" controls>';
            echo '<source src="' . s($mp4Url) . '" type="video/mp4">';
            echo get_string('error:novideo', 'videoprogress');
            echo '</video>';
        }
    } else {
        // 一般上傳影片（非 ZIP 套件）
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_videoprogress', 'video', 0, 'sortorder', false);
        $videofile = reset($files);
        
        if ($videofile) {
            // 檢查是否為 ZIP 檔案（但沒有 index.html）
            $filename = $videofile->get_filename();
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'zip') {
                // ZIP 檔案但沒有 index.html
                echo '<div class="alert alert-danger">';
                echo '<i class="fa fa-exclamation-triangle"></i> ';
                echo '<strong>錯誤：</strong>上傳的 ZIP 檔案中找不到 <code>index.html</code>。';
                echo '<br><br>';
                echo '請確認 ZIP 套件包含以下檔案：';
                echo '<ul class="mb-0 mt-2">';
                echo '<li><code>index.html</code> (必要)</li>';
                echo '<li>影片檔案（如 <code>media.mp4</code>）</li>';
                echo '<li><code>config.js</code> (可選，用於章節目錄)</li>';
                echo '</ul>';
                echo '</div>';
            } else {
                // 一般影片檔案
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
            }
        } else {
            echo '<div class="alert alert-warning">' . get_string('error:novideo', 'videoprogress') . '</div>';
        }
    }
} else {
    echo '<div class="alert alert-warning">' . get_string('error:novideo', 'videoprogress') . '</div>';
}

echo '</div>';
echo '</div>';

// 續播提示
if ($userprogress && $userprogress->lastposition > 0) {
    $formattedtime = gmdate('H:i:s', $userprogress->lastposition);
    // external (純 iframe 模式) 只顯示資訊；其他模式 (youtube, upload, evercam) 顯示跳轉按鈕
    if ($effectiveVideoType === 'external') {
        // 外部網址（純 iframe 模式）：只顯示資訊，不顯示按鈕（因為無法跳轉）
        echo '<div class="alert alert-info mt-3">';
        echo '<i class="fa fa-info-circle"></i> ';
        echo '上次累積觀看至 ' . $formattedtime;
        echo '</div>';
    } else {
        // YouTube、上傳影片、Evercam：顯示可點擊的續播按鈕
        echo '<div id="videoprogress-resume-prompt" class="alert alert-info mt-3">';
        echo '<button type="button" class="btn btn-primary" id="videoprogress-resume-btn">';
        echo get_string('resumefrom', 'videoprogress', $formattedtime);
        echo '</button>';
        echo '</div>';
    }
}

echo $OUTPUT->footer();
