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
 * Player Renderer - 播放器渲染器
 * 
 * 負責各種播放器的 HTML 輸出
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoprogress\output;

defined('MOODLE_INTERNAL') || die();

/**
 * 播放器渲染器類別
 */
class player_renderer {

    /**
     * 渲染 YouTube 播放器
     */
    public static function youtube(string $videoUrl): string {
        $videoid = '';
        if (preg_match('/(?:embed\/|v=|youtu\.be\/|\/v\/|watch\?v=|&v=)([a-zA-Z0-9_-]{11})/', $videoUrl, $matches)) {
            $videoid = $matches[1];
        }

        if (!$videoid) {
            return '<div class="alert alert-danger">無法解析 YouTube 影片 ID</div>';
        }

        $src = "https://www.youtube.com/embed/{$videoid}?enablejsapi=1&rel=0&modestbranding=1";
        return <<<HTML
<div id="videoprogress-youtube-container" class="ratio ratio-16x9">
    <iframe id="videoprogress-youtube-player" src="{$src}" allowfullscreen frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
</div>
HTML;
    }

    /**
     * 渲染 HTML5 播放器
     */
    public static function html5(string $videoUrl, array $chapters = [], ?int $duration = null, string $infoText = ''): string {
        $html = '';

        if ($infoText) {
            $durationText = $duration ? ' (' . floor($duration / 60) . ' 分 ' . ($duration % 60) . ' 秒)' : '';
            // 4.5 版本一致性：自動偵測顯示綠色 (Success)，Evercam/ZIP 顯示藍色 (Info)
            if ($infoText === '影片已自動偵測') {
                $html .= "<div class=\"alert alert-success mb-2\"><i class=\"fa fa-check\"></i> {$infoText}{$durationText}</div>";
            } else {
                $html .= "<div class=\"alert alert-info mb-2\"><i class=\"fa fa-info-circle\"></i> {$infoText}{$durationText}</div>";
            }
        }

        if (!empty($chapters)) {
            return $html . self::dual_panel($videoUrl, $chapters);
        }

        return $html . <<<HTML
<video id="videoprogress-html5-player" class="w-100" controls style="border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
    <source src="{$videoUrl}" type="video/mp4">
    您的瀏覽器不支援 HTML5 影片播放。
</video>
HTML;
    }

    /**
     * 渲染雙畫面播放器
     */
    private static function dual_panel(string $videoUrl, array $chapters): string {
        $chaptersHtml = self::chapter_list($chapters);

        return <<<HTML
<div class="row">
    <div class="col-md-8">
        <video id="videoprogress-html5-player" class="w-100" controls style="border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            <source src="{$videoUrl}" type="video/mp4">
        </video>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white"><i class="fa fa-list"></i> 章節目錄</div>
            <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                <ul class="list-group list-group-flush" id="videoprogress-chapters">{$chaptersHtml}</ul>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * 渲染章節列表
     */
    private static function chapter_list(array $chapters): string {
        $html = '';
        $sn = 0;
        $subSn = 0;

        foreach ($chapters as $chapter) {
            $isTitle = ($chapter['level'] ?? 0) == 0;
            // Evercam 格式的 time 是毫秒，轉換為秒
            $timeMs = intval($chapter['time']);
            $timeSec = floor($timeMs / 1000);
            $timeDisplay = sprintf('%02d:%02d', floor($timeSec / 60), $timeSec % 60);

            if ($isTitle) {
                // 主章節
                $sn++;
                $subSn = 0;
                $snStr = $sn . '.';
                
                // 使用 list-group-item-action 恢復 hover 效果
                // 使用 flex-grow-1 和 text-truncate 避免文字與徽章重疊
                $html .= "<li class=\"list-group-item list-group-item-action d-flex justify-content-between align-items-center\" ";
                $html .= "style=\"cursor: pointer;\" ";
                $html .= "data-time=\"{$timeSec}\" onclick=\"document.getElementById('videoprogress-html5-player').currentTime={$timeSec}; document.getElementById('videoprogress-html5-player').play();\">";
                $html .= "<span class=\"text-truncate flex-grow-1 me-2\" title=\"" . s($chapter['title']) . "\"><strong class=\"text-muted\">{$snStr}</strong> " . s($chapter['title']) . "</span>";
                $html .= "<span class=\"badge bg-secondary flex-shrink-0\">{$timeDisplay}</span>"; 
                $html .= "</li>";
            } else {
                // 子章節
                $subSn++;
                $snStr = $sn . '.' . $subSn;

                $html .= "<li class=\"list-group-item list-group-item-action d-flex justify-content-between align-items-center\" ";
                $html .= "style=\"cursor: pointer; padding-left: 30px;\" "; 
                $html .= "data-time=\"{$timeSec}\" onclick=\"document.getElementById('videoprogress-html5-player').currentTime={$timeSec}; document.getElementById('videoprogress-html5-player').play();\">";
                $html .= "<span class=\"text-truncate flex-grow-1 me-2\" title=\"" . s($chapter['title']) . "\"><strong class=\"text-muted\">{$snStr}</strong> " . s($chapter['title']) . "</span>";
                $html .= "<span class=\"badge bg-secondary flex-shrink-0\">{$timeDisplay}</span>";
                $html .= "</li>";
            }
        }

        return $html;
    }

    /**
     * 渲染 iframe 播放器（計時模式）
     */
    public static function iframe(string $url): string {
        return <<<HTML
<div id="videoprogress-timer-hint" class="alert alert-warning mb-2">
    <i class="fa fa-hand-pointer-o"></i> 點擊影片開始播放，計時器將自動啟動
</div>
<div id="videoprogress-external-wrapper" style="position: relative;">
    <div id="videoprogress-external-container" class="ratio ratio-16x9">
        <iframe id="videoprogress-external-iframe" src="{$url}" 
                sandbox="allow-scripts" referrerpolicy="no-referrer" allowfullscreen frameborder="0"></iframe>
    </div>
    <div id="videoprogress-overlay" style="display: none; position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
         background: rgba(0,0,0,0.9); z-index: 10; cursor: pointer; justify-content: center; align-items: center;">
        <div style="text-align: center; color: white; padding: 30px; background: rgba(255,255,255,0.1); border-radius: 20px;">
            <i class="fa fa-play-circle" style="font-size: 96px; margin-bottom: 20px;"></i>
            <h3>計時器已暫停</h3>
            <p>點擊以繼續觀看</p>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * 渲染上傳影片播放器
     */
    public static function upload(\context_module $context): string {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_videoprogress', 'video', false, 'filename', false);

        if (empty($files)) {
            return '<div class="alert alert-warning">No video found</div>';
        }

        $file = reset($files);
        $url = \moodle_url::make_pluginfile_url($context->id, 'mod_videoprogress', 'video', 0, $file->get_filepath(), $file->get_filename());

        return self::html5($url->out());
    }

    /**
     * 渲染繼續觀看按鈕
     */
    public static function resume_button(float $lastPosition): string {
        // MVC 優化：由 PHP 輸出基本結構（預設隱藏），JS 負責填入數據和顯示
        // 這樣可以避免 JS 需要動態創建 DOM，同時維持 HTML 結構在 View 層
        return <<<HTML
<div id="videoprogress-resume-prompt" class="alert alert-info mt-3" style="display: none;">
    <button type="button" class="btn btn-primary" id="videoprogress-resume-btn" data-position="0">
        <!-- JS will update text here -->
    </button>
</div>
HTML;
    }

    /**
     * 渲染繼續觀看提示（純資訊模式，用於外部 iframe 無法跳轉的情況）
     */
    public static function resume_info(float $lastPosition): string {
        if ($lastPosition <= 0) return '';

        $time = gmdate('H:i:s', $lastPosition);

        return <<<HTML
<div class="alert alert-info mt-3">
    <i class="fa fa-info-circle"></i> 上次累積觀看至 {$time}
</div>
HTML;
    }
}
