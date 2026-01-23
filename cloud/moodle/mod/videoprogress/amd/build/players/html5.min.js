/**
 * HTML5 Player Module
 * HTML5 影片播放器模組 - 完整版
 *
 * @module     mod_videoprogress/players/html5
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('mod_videoprogress/players/html5', [], function () {
    'use strict';

    var video = null;
    var config = null;
    var segmentStart = 0;
    var lastPlaybackPosition = 0;
    var hasPlayedOnce = false;
    var isSeeking = false;
    var justSeeked = false;
    var continuousPlaySeconds = 0;
    var storageKey = null;  // localStorage key
    var saveInterval = null;  // 5 秒儲存 interval
    var callbacks = {
        onProgress: null
    };

    /**
     * 取得 localStorage key（從 URL 的 id 參數）
     */
    function getStorageKey() {
        var match = window.location.search.match(/[?&]id=(\d+)/);
        var cmid = match ? match[1] : '0';
        return 'videoprogress_pos_' + cmid;
    }

    /**
     * 儲存當前位置到 localStorage
     */
    function savePosition() {
        if (!video) return;
        // 確保 storageKey 有值
        if (!storageKey) {
            storageKey = getStorageKey();
        }
        try {
            localStorage.setItem(storageKey, video.currentTime.toString());
        } catch (e) { }
    }

    /**
     * 初始化 HTML5 播放器
     */
    function init(options, onProgress) {
        config = options;
        callbacks.onProgress = onProgress;
        storageKey = getStorageKey();  // 初始化 localStorage key

        if (config.lastposition > 0) {
            segmentStart = Math.floor(config.lastposition);
            lastPlaybackPosition = segmentStart;
        }

        video = document.getElementById('videoprogress-html5-player');
        if (!video) return;

        bindEvents();
        initChapters();

        // 恢復上次位置
        if (config.lastposition > 0) {
            video.currentTime = config.lastposition;
        }
    }

    /**
     * 綁定事件
     */
    function bindEvents() {
        // 載入 metadata
        video.addEventListener('loadedmetadata', function () {
            var duration = Math.ceil(video.duration);
            if (duration > 0) {
                config.duration = duration;
            }
        });

        // 如果已經載入
        if (video.readyState >= 1 && video.duration > 0) {
            config.duration = Math.ceil(video.duration);
        }

        // 播放
        video.addEventListener('play', function () {
            hasPlayedOnce = true;
            segmentStart = Math.floor(video.currentTime);
            lastPlaybackPosition = segmentStart;

            if (config.duration === 0 && video.duration > 0) {
                config.duration = Math.ceil(video.duration);
            }

            // 啟動 5 秒儲存 interval（與 YouTube/Evercam 一致）
            if (!saveInterval) {
                saveInterval = setInterval(function () {
                    if (!video.paused) {
                        notifyProgress(true);
                        segmentStart = Math.floor(video.currentTime);
                    }
                }, 5000);
            }
        });

        // 暫停
        video.addEventListener('pause', function () {
            // 清除 interval
            if (saveInterval) {
                clearInterval(saveInterval);
                saveInterval = null;
            }

            if (!isSeeking && !justSeeked && continuousPlaySeconds >= 2) {
                var endPos = lastPlaybackPosition;
                if (endPos > segmentStart) {
                    notifyProgress();
                }
            }
        });

        // 結束
        video.addEventListener('ended', function () {
            // 清除 interval
            if (saveInterval) {
                clearInterval(saveInterval);
                saveInterval = null;
            }

            lastPlaybackPosition = config.duration;
            notifyProgress();
        });

        // 跳轉開始
        video.addEventListener('seeking', function () {
            isSeeking = true;
            justSeeked = true;
            continuousPlaySeconds = 0;
        });

        // 跳轉完成（合併兩個監聽器）
        video.addEventListener('seeked', function () {
            var newPos = Math.floor(video.currentTime);
            segmentStart = newPos;
            lastPlaybackPosition = newPos;
            isSeeking = false;
            savePosition();  // 合併自原本的第二個監聽器
        });

        // 時間更新
        video.addEventListener('timeupdate', function () {
            var currentTime = Math.floor(video.currentTime);

            // 即時暫存到 localStorage（用於繼續觀看按鈕）
            savePosition();

            // 跳轉偵測
            var timeDiff = Math.abs(currentTime - lastPlaybackPosition);
            if (timeDiff > 2) {
                continuousPlaySeconds = 0;
                segmentStart = currentTime;  // 跳轉時重設起點
                return;
            }

            if (isSeeking) return;

            lastPlaybackPosition = currentTime;

            // 累加連續播放秒數
            if (currentTime > segmentStart) {
                continuousPlaySeconds = currentTime - segmentStart;
            }

            // 連續播放 2 秒後清除 justSeeked
            if (justSeeked && continuousPlaySeconds >= 2) {
                justSeeked = false;
            }
        });
    }

    /**
     * 初始化章節點擊
     */
    function initChapters() {
        var chapters = document.querySelectorAll('#videoprogress-chapters .chapter-item');
        chapters.forEach(function (item) {
            item.addEventListener('click', function () {
                var time = parseFloat(this.getAttribute('data-time')) || 0;
                seekTo(time);
                video.play();
            });
        });
    }

    /**
     * 通知進度
     * @param {boolean} forceUpdate 是否強制立即更新 UI
     */
    function notifyProgress(forceUpdate) {
        // 優先使用 config.duration，否則用影片元素的 duration
        var duration = config.duration || (video ? Math.ceil(video.duration) : 0);
        if (callbacks.onProgress && duration > 0) {
            callbacks.onProgress({
                currentTime: lastPlaybackPosition,
                duration: duration,
                segmentStart: segmentStart,
                isPlaying: video && !video.paused,
                forceUpdate: forceUpdate || false
            });
        }
    }

    function getCurrentTime() { return lastPlaybackPosition; }
    function seekTo(seconds) {
        if (video) {
            video.currentTime = seconds;
            segmentStart = Math.floor(seconds);
            lastPlaybackPosition = seconds;
        }
    }
    function getDuration() { return config.duration || (video ? video.duration : 0); }
    function isPlaying() { return video && !video.paused; }

    /**
     * 清除資源（用於頁面卸載時避免記憶體洩漏）
     */
    function destroy() {
        if (saveInterval) {
            clearInterval(saveInterval);
            saveInterval = null;
        }
        video = null;
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
