/**
 * YouTube Player Module
 * YouTube 播放器模組 - 完整版
 *
 * @module     mod_videoprogress/players/youtube
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('mod_videoprogress/players/youtube', [], function () {
    'use strict';

    var player = null;
    var positionInterval = null;
    var saveInterval = null;  // 定期儲存 interval
    var config = null;
    var segmentStart = 0;
    var lastPlaybackPosition = 0;
    var callbacks = {
        onProgress: null
    };

    /**
     * 初始化 YouTube 播放器
     */
    function init(options, onProgress) {
        config = options;
        callbacks.onProgress = onProgress;

        if (config.lastposition > 0) {
            segmentStart = Math.floor(config.lastposition);
            lastPlaybackPosition = segmentStart;
        }

        if (!window.YT) {
            var tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            document.head.appendChild(tag);
            window.onYouTubeIframeAPIReady = createPlayer;
        } else {
            createPlayer();
        }
    }

    /**
     * 建立播放器
     */
    function createPlayer() {
        player = new window.YT.Player('videoprogress-youtube-player', {
            playerVars: {
                'autoplay': 0,
                'controls': 1,
                'rel': 0,
                'modestbranding': 1
            },
            events: {
                onReady: onReady,
                onStateChange: onStateChange
            }
        });
    }

    /**
     * 播放器就緒
     */
    function onReady(event) {
        var duration = Math.floor(event.target.getDuration());
        if (duration > 0) {
            config.duration = duration;
        }

        if (config.lastposition > 0) {
            player.seekTo(config.lastposition, true);
        }
    }

    /**
     * 狀態變化
     */
    function onStateChange(event) {
        if (event.data === window.YT.PlayerState.PLAYING) {
            // 開始播放
            if (config.duration === 0 && player && player.getDuration) {
                var duration = Math.floor(player.getDuration());
                if (duration > 0) {
                    config.duration = duration;
                }
            }

            segmentStart = Math.floor(player.getCurrentTime());
            lastPlaybackPosition = segmentStart;
            startTracking();

        } else if (event.data === window.YT.PlayerState.PAUSED) {
            stopTracking();
            notifyProgress(true);

        } else if (event.data === window.YT.PlayerState.ENDED) {
            stopTracking();
            // 影片結束，強制 100%
            lastPlaybackPosition = config.duration;
            notifyProgress(true);
        }
    }

    /**
     * 開始追蹤
     */
    function startTracking() {
        if (positionInterval) return;

        // 每 500ms 更新位置變數（不觸發 UI 更新，與舊版邏輯一致）
        positionInterval = setInterval(function () {
            if (player) {
                var currentTime = Math.floor(player.getCurrentTime());
                var diff = Math.abs(currentTime - lastPlaybackPosition);

                // 偵測跳轉 (Seek) - 跳轉時重設起點
                if (diff > 2) {
                    segmentStart = currentTime;
                }

                lastPlaybackPosition = currentTime;
                // 移除 notifyProgress(false) - 改為只在 5 秒 interval 時通知
            }
        }, 500);

        if (!saveInterval) {
            saveInterval = setInterval(function () {
                notifyProgress(true);  // forceUpdate = true
                segmentStart = lastPlaybackPosition;
            }, 5000);
        }
    }

    /**
     * 停止追蹤
     */
    function stopTracking() {
        if (positionInterval) {
            clearInterval(positionInterval);
            positionInterval = null;
        }
        if (saveInterval) {
            clearInterval(saveInterval);
            saveInterval = null;
        }
    }

    /**
     * 通知進度
     */
    function notifyProgress(forceUpdate) {
        if (callbacks.onProgress && config.duration > 0) {
            var isPlaying = player && typeof player.getPlayerState === 'function' &&
                player.getPlayerState() === window.YT.PlayerState.PLAYING;

            callbacks.onProgress({
                currentTime: lastPlaybackPosition,
                duration: config.duration,
                segmentStart: segmentStart,
                isPlaying: isPlaying,
                forceUpdate: forceUpdate
            });
        }
    }

    function getCurrentTime() {
        return lastPlaybackPosition;
    }

    function seekTo(seconds) {
        if (player) {
            player.seekTo(seconds, true);
            segmentStart = Math.floor(seconds);
            lastPlaybackPosition = segmentStart;
        }
    }

    function getDuration() {
        return config.duration || (player && typeof player.getDuration === 'function' ? player.getDuration() : 0);
    }

    function isPlaying() {
        return player && typeof player.getPlayerState === 'function' &&
            player.getPlayerState() === window.YT.PlayerState.PLAYING;
    }

    /**
     * 清除資源
     */
    function destroy() {
        stopTracking();
        if (player && typeof player.destroy === 'function') {
            player.destroy();
        }
        player = null;
        config = null;
    }

    return {
        init: init,
        getCurrentTime: getCurrentTime,
        seekTo: seekTo,
        getDuration: getDuration,
        isPlaying: isPlaying,
        destroy: destroy
    };
});
