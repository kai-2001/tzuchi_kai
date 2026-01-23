/**
 * External Timer Module
 * 外部網址計時追蹤模組 - 完整版
 *
 * @module     mod_videoprogress/players/external
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('mod_videoprogress/players/external', ['jquery'], function ($) {
    'use strict';

    var config = null;
    var startTime = null;
    var timerStarted = false;
    var isActive = true;
    var overlayClickCooldown = false;
    var callbacks = {
        onProgress: null
    };

    /**
     * 初始化外部追蹤
     */
    function init(options, onProgress) {
        config = options;
        callbacks.onProgress = onProgress;

        // 嘗試偵測影片 URL
        if (config.externalurl) {
            detectVideoUrl(config.externalurl);
        } else {
            initTimerTracking();
        }
    }

    /**
     * 偵測影片 URL
     */
    function detectVideoUrl(url) {
        var hint = document.getElementById('videoprogress-timer-hint');
        var urlLower = url.toLowerCase();

        // 直接影片格式
        if (urlLower.match(/\.(mp4|webm|ogg|ogv|mov|m4v)(\?|$)/i)) {
            detectDurationFromVideoUrl(url);
            return;
        }

        // 呼叫 API
        if (hint) {
            hint.className = 'alert alert-info mb-2';
            hint.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 正在偵測影片資訊...';
        }

        $.ajax({
            url: M.cfg.wwwroot + '/mod/videoprogress/detect_video.php',
            method: 'GET',
            data: { url: url },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.videourl) {
                    if (response.duration && response.duration > 0) {
                        config.duration = response.duration;
                        var minutes = Math.floor(response.duration / 60);
                        var seconds = response.duration % 60;
                        if (hint) {
                            hint.className = 'alert alert-success mb-2';
                            hint.innerHTML = '<i class="fa fa-check"></i> 影片長度已偵測: ' + minutes + ' 分 ' + seconds + ' 秒';
                        }
                    } else {
                        detectDurationFromVideoUrl(response.videourl);
                        return;
                    }
                } else {
                    if (hint) {
                        hint.className = 'alert alert-warning mb-2';
                        hint.innerHTML = '<i class="fa fa-exclamation-triangle"></i> 無法自動偵測影片長度，將使用計時模式';
                    }
                }
                initTimerTracking();
            },
            error: function () {
                if (hint) {
                    hint.className = 'alert alert-warning mb-2';
                    hint.innerHTML = '<i class="fa fa-exclamation-triangle"></i> 無法自動偵測影片長度，將使用計時模式';
                }
                initTimerTracking();
            }
        });
    }

    /**
     * 從影片 URL 偵測時長
     */
    function detectDurationFromVideoUrl(videoUrl) {
        var hint = document.getElementById('videoprogress-timer-hint');
        var tempVideo = document.createElement('video');
        tempVideo.style.display = 'none';
        tempVideo.preload = 'metadata';
        tempVideo.crossOrigin = 'anonymous';

        tempVideo.addEventListener('loadedmetadata', function () {
            var duration = Math.floor(tempVideo.duration);
            if (duration > 0) {
                config.duration = duration;
                var minutes = Math.floor(duration / 60);
                var seconds = duration % 60;
                if (hint) {
                    hint.className = 'alert alert-success mb-2';
                    hint.innerHTML = '<i class="fa fa-check"></i> 影片長度已偵測: ' + minutes + ' 分 ' + seconds + ' 秒';
                }
            }
            document.body.removeChild(tempVideo);
            initTimerTracking();
        });

        tempVideo.addEventListener('error', function () {
            if (hint) {
                hint.className = 'alert alert-warning mb-2';
                hint.innerHTML = '<i class="fa fa-exclamation-triangle"></i> 無法載入影片偵測長度，將使用計時模式';
            }
            if (tempVideo.parentNode) {
                document.body.removeChild(tempVideo);
            }
            initTimerTracking();
        });

        document.body.appendChild(tempVideo);
        tempVideo.src = videoUrl;
    }

    /**
     * 初始化計時追蹤
     */
    function initTimerTracking() {
        timerStarted = false;
        var iframe = document.getElementById('videoprogress-external-iframe');

        // 同源偵測
        var bindIframeEvents = function () {
            try {
                var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                if (iframeDoc) {
                    $(iframeDoc).on('click keydown scroll mousemove touchstart', function () {
                        if (!timerStarted) startTimer();
                    });
                    return true;
                }
            } catch (e) {
                return false;
            }
        };

        if (iframe) {
            iframe.onload = bindIframeEvents;
            bindIframeEvents();
        }

        // 跨域偵測
        window.addEventListener('blur', function () {
            if (overlayClickCooldown) return;
            if (document.activeElement === iframe && !timerStarted) {
                startTimer();
            }
        });

        // 可見性追蹤
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                isActive = false;
                showOverlay();
            }
        });

        // 遮罩點擊
        var overlay = document.getElementById('videoprogress-overlay');
        if (overlay) {
            overlay.addEventListener('click', function () {
                overlay.style.display = 'none';
                isActive = true;
                overlayClickCooldown = true;
                setTimeout(function () { overlayClickCooldown = false; }, 1000);
            });
        }
    }

    /**
     * 開始計時
     */
    var lastSaveElapsed = 0;  // 上次儲存時的累計秒數
    function startTimer() {
        timerStarted = true;
        startTime = Date.now();

        var hint = document.getElementById('videoprogress-timer-hint');
        if (hint) {
            hint.className = 'alert alert-success mb-2';
            hint.innerHTML = '<i class="fa fa-check"></i> 計時已開始';
        }

        // 每秒更新 UI 顯示，但只每 5 秒儲存一次（與 4.5 一致）
        setInterval(function () {
            if (!isActive) return;

            var elapsed = Math.floor((Date.now() - startTime) / 1000);
            var requiredTime = config.externalmintime || 60;

            // 只有每 5 秒才呼叫 onProgress 儲存（避免過多 API 呼叫）
            if (elapsed - lastSaveElapsed >= 5) {
                if (callbacks.onProgress) {
                    callbacks.onProgress({
                        watchedTime: elapsed,
                        requiredTime: requiredTime,
                        isActive: isActive
                    });
                }
                lastSaveElapsed = elapsed;
            }

            // 每秒更新顯示
            var display = document.getElementById('videoprogress-external-display');
            if (display) {
                var minutes = Math.floor(elapsed / 60);
                var seconds = elapsed % 60;
                var timeStr = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                var percent = Math.min(100, Math.round((elapsed / requiredTime) * 100));
                display.innerHTML = '已觀看: ' + timeStr + ' (' + percent + '%)';
            }
        }, 1000);
    }

    /**
     * 顯示遮罩
     */
    function showOverlay() {
        var overlay = document.getElementById('videoprogress-overlay');
        if (overlay) {
            overlay.style.display = 'flex';
        }
    }

    function getWatchedTime() {
        if (!startTime) return 0;
        return Math.floor((Date.now() - startTime) / 1000);
    }

    function isPlaying() {
        return timerStarted && isActive;
    }

    return {
        init: init,
        getWatchedTime: getWatchedTime,
        isPlaying: isPlaying
    };
});
