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
 * Compression Renderer - 壓縮狀態渲染器
 * 
 * 負責壓縮管理區塊的 HTML 輸出
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoprogress\output;

defined('MOODLE_INTERNAL') || die();

use mod_videoprogress\service\compression_service;

/**
 * 壓縮狀態渲染器類別
 */
class compression_renderer {

    /**
     * 渲染壓縮管理面板（管理員專用）
     */
    public static function panel(\context_module $context): string {
        if (!has_capability('moodle/site:config', \context_system::instance())) {
            return '';
        }

        $items = compression_service::get_pending_items($context->id);
        if (empty($items)) {
            return '';
        }

        $html = '<div class="card mb-4 border-warning">';
        $html .= '<div class="card-header bg-warning text-dark">';
        $html .= '<i class="fa fa-compress"></i> <strong>影片壓縮管理</strong> <small class="text-muted">(管理員專用)</small></div>';
        $html .= '<div class="card-body">';

        foreach ($items as $item) {
            $html .= self::item($item);
        }

        $html .= self::progress_bar();
        $html .= '<div id="compression-result" style="display: none;"></div>';
        $html .= '</div></div>';
        $html .= self::script();

        return $html;
    }

    /**
     * 渲染單個項目
     */
    private static function item(object $item): string {
        $statusMap = [
            'pending' => ['badge' => '等待處理', 'class' => 'bg-secondary', 'btn' => 'btn-primary', 'icon' => 'fa-play', 'text' => '執行壓縮'],
            'processing' => ['badge' => '正在壓縮', 'class' => 'bg-primary', 'btn' => 'btn-secondary', 'icon' => 'fa-spinner fa-spin', 'text' => '處理中...', 'disabled' => true],
            'failed' => ['badge' => '失敗', 'class' => 'bg-danger', 'btn' => 'btn-warning', 'icon' => 'fa-refresh', 'text' => '重試'],
        ];
        $s = $statusMap[$item->status] ?? $statusMap['pending'];
        $disabled = isset($s['disabled']) ? 'disabled' : '';

        $error = ($item->status === 'failed' && !empty($item->last_error))
            ? '<p class="text-danger small mb-1">錯誤: ' . s($item->last_error) . '</p>' : '';

        return <<<HTML
{$error}
<div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
    <span><i class="fa fa-file-video-o"></i> {$item->filename} <span class="badge {$s['class']}">{$s['badge']}</span></span>
    <button class="btn {$s['btn']} btn-sm compress-btn" data-queue-id="{$item->id}" {$disabled}>
        <i class="fa {$s['icon']}"></i> {$s['text']}
    </button>
</div>
HTML;
    }

    /**
     * 渲染進度條
     */
    private static function progress_bar(): string {
        return <<<HTML
<div id="compression-progress" style="display: none;" class="mt-3">
    <div class="progress" style="height: 25px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" style="width: 100%;">壓縮中，請稍候...</div>
    </div>
    <p class="text-muted small mt-2"><i class="fa fa-info-circle"></i> 壓縮大型影片可能需要數分鐘，請勿關閉此頁面。</p>
</div>
HTML;
    }

    /**
     * 渲染 JavaScript
     */
    private static function script(): string {
        return <<<'JS'
<script>
document.querySelectorAll(".compress-btn").forEach(function(btn) {
    btn.addEventListener("click", function() {
        var queueId = this.getAttribute("data-queue-id");
        var btn = this;
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 處理中...';
        document.getElementById("compression-progress").style.display = "block";
        document.getElementById("compression-result").style.display = "none";
        
        fetch(window.location.href + "&ajax=compress&queue_id=" + queueId)
            .then(r => r.json())
            .then(data => {
                document.getElementById("compression-progress").style.display = "none";
                var result = document.getElementById("compression-result");
                if (data.success) {
                    result.innerHTML = '<div class="alert alert-success"><i class="fa fa-check"></i> ' + data.message + '</div>';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    result.innerHTML = '<div class="alert alert-danger"><i class="fa fa-times"></i> ' + data.error + '</div>';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-refresh"></i> 重試';
                }
                result.style.display = "block";
            })
            .catch(e => {
                document.getElementById("compression-progress").style.display = "none";
                document.getElementById("compression-result").innerHTML = '<div class="alert alert-danger">網路錯誤</div>';
                document.getElementById("compression-result").style.display = "block";
                btn.disabled = false;
            });
    });
});
</script>
JS;
    }
}
