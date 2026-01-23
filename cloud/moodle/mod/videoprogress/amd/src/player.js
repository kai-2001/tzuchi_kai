/**
 * Video Progress Player Module
 * 影片進度追蹤播放器 - 主協調器
 *
 * @module     mod_videoprogress/player
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('mod_videoprogress/player', [
    'jquery',
    'mod_videoprogress/players/youtube',
    'mod_videoprogress/players/html5',
    'mod_videoprogress/players/external',
    'mod_videoprogress/players/evercam',
    'mod_videoprogress/services/progress'
], function ($, YouTubePlayer, HTML5Player, ExternalPlayer, EvercamPlayer, ProgressService) {
    'use strict';

    var config = null;
    var activePlayer = null;

    /**
     * 初始化
     */
    function init(options) {
        config = options;

        // 初始化進度服務
        ProgressService.init(config);

        // 根據影片類型初始化對應播放器
        switch (config.videotype) {
            case 'youtube':
                initYouTube();
                break;
            case 'evercam':
                initEvercam();
                break;
            case 'upload':
                initHTML5();
                break;
            case 'external':
                initExternal();
                break;
            default:
                initHTML5();
        }

        // 繼續觀看按鈕
        initResumeButton();

        // 焦點追蹤
        if (config.requirefocus) {
            initFocusTracking();
        }
    }

    /**
     * 初始化 YouTube
     */
    function initYouTube() {
        activePlayer = YouTubePlayer;
        YouTubePlayer.init(config, onProgress);
    }

    /**
     * 初始化 Evercam
     */
    function initEvercam() {
        activePlayer = EvercamPlayer;
        EvercamPlayer.init(config, onProgress);
    }

    /**
     * 初始化 HTML5
     */
    function initHTML5() {
        activePlayer = HTML5Player;
        HTML5Player.init(config, onProgress);
    }

    /**
     * 初始化外部追蹤
     */
    function initExternal() {
        // 檢查是否有偵測到的影片 URL（轉為 HTML5）
        if (config.detectedVideoUrl) {
            activePlayer = HTML5Player;
            HTML5Player.init(config, onProgress);
        } else {
            activePlayer = ExternalPlayer;
            ExternalPlayer.init(config, onExternalProgress);
        }
    }

    /**
     * 影片進度回呼
     */
    function onProgress(data) {
        ProgressService.setLastPosition(data.currentTime);
        ProgressService.update({
            currentTime: data.currentTime,
            duration: data.duration,
            segmentStart: data.segmentStart,
            forceUpdate: data.forceUpdate
        });
    }

    /**
     * 外部計時進度回呼
     */
    function onExternalProgress(data) {
        ProgressService.update({
            watchedTime: data.watchedTime,
            requiredTime: data.requiredTime
        });
    }

    /**
     * 初始化繼續觀看按鈕
     */
    function initResumeButton() {
        var btn = document.getElementById('videoprogress-resume-btn');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var position = parseFloat(this.getAttribute('data-position')) || 0;
            if (activePlayer && activePlayer.seekTo) {
                activePlayer.seekTo(position);
            }
            var prompt = document.getElementById('videoprogress-resume-prompt');
            if (prompt) prompt.style.display = 'none';
        });
    }

    /**
     * 初始化焦點追蹤（專注模式）
     * 與 4.5 一致：切換分頁時暫停影片
     */
    function initFocusTracking() {
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                // 儲存當前進度
                if (activePlayer && activePlayer.isPlaying && activePlayer.isPlaying()) {
                    ProgressService.saveNow(false);
                }

                // 暫停影片（與 4.5 一致）
                if (config.videotype === 'youtube') {
                    // YouTube: 透過 player 模組的內部 player 物件
                    var ytPlayer = document.getElementById('videoprogress-youtube-player');
                    if (ytPlayer && ytPlayer.contentWindow) {
                        ytPlayer.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
                    }
                } else if (config.videotype === 'upload') {
                    // HTML5: 直接暫停 video 元素
                    var video = document.getElementById('videoprogress-html5-player');
                    if (video && !video.paused) {
                        video.pause();
                    }
                } else if (config.videotype === 'evercam') {
                    // Evercam: 透過 Player.js postMessage
                    var iframe = document.getElementById('videoprogress-external-iframe');
                    if (iframe && iframe.contentWindow) {
                        iframe.contentWindow.postMessage(JSON.stringify({
                            context: 'player.js',
                            version: '0.0.10',
                            method: 'pause'
                        }), '*');
                    }
                }
            }
        });
    }

    return {
        init: init
    };
});
