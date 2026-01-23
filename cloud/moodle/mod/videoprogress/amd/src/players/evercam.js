/**
 * Evercam Player Module
 * Evercam 播放器模組 - Player.js 協議
 *
 * @module     mod_videoprogress/players/evercam
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('mod_videoprogress/players/evercam', [], function () {
    'use strict';

    var player = null;
    var config = null;
    var segmentStart = 0;
    var lastPlaybackPosition = 0;
    var saveInterval = null;
    var callbacks = {
        onProgress: null
    };

    /**
     * 初始化 Evercam 播放器
     */
    function init(options, onProgress) {
        config = options;
        callbacks.onProgress = onProgress;

        if (config.lastposition > 0) {
            segmentStart = Math.floor(config.lastposition);
            lastPlaybackPosition = segmentStart;
        }

        var iframe = document.getElementById('videoprogress-external-iframe');
        if (!iframe) return;

        initPlayerJs(iframe);
    }

    /**
     * 初始化 Player.js 協議
     */
    function initPlayerJs(iframe) {
        var evercamPlayer = {
            element: iframe,
            isReady: false,
            duration: 0,
            currentTime: 0,
            paused: true,
            listeners: {},

            send: function (method, value, listener) {
                var msg = {
                    context: 'player.js',
                    version: '0.0.10',
                    method: method
                };
                if (value !== undefined) msg.value = value;
                if (listener !== undefined) msg.listener = listener;
                try {
                    iframe.contentWindow.postMessage(JSON.stringify(msg), '*');
                } catch (e) { }
            },

            play: function () { this.send('play'); },
            pause: function () { this.send('pause'); },

            getDuration: function (callback) {
                var listenerId = 'getDuration_' + Date.now();
                this.listeners[listenerId] = callback;
                this.send('getDuration', undefined, listenerId);
            },

            getCurrentTime: function (callback) {
                var listenerId = 'getCurrentTime_' + Date.now();
                this.listeners[listenerId] = callback;
                this.send('getCurrentTime', undefined, listenerId);
            },

            setCurrentTime: function (time) {
                this.send('setCurrentTime', time);
            },

            on: function (event, callback) {
                var listenerId = event + '_' + Date.now();
                this.listeners[listenerId] = callback;
                this.send('addEventListener', event, listenerId);
            }
        };

        // 監聽訊息
        window.addEventListener('message', function (e) {
            var data;
            try {
                data = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
            } catch (err) { return; }

            if (!data || data.context !== 'player.js') return;

            // Ready 事件
            if (data.event === 'ready') {
                evercamPlayer.isReady = true;
                evercamPlayer.getDuration(function (duration) {
                    evercamPlayer.duration = duration;
                    if (duration > 0 && config.duration === 0) {
                        config.duration = Math.floor(duration);
                    }
                });
                evercamPlayer.on('timeupdate', function () { });
                evercamPlayer.on('play', function () {
                    evercamPlayer.paused = false;
                    segmentStart = Math.floor(evercamPlayer.currentTime);
                    lastPlaybackPosition = segmentStart;
                });
                evercamPlayer.on('pause', function () {
                    evercamPlayer.paused = true;
                    notifyProgress();
                });
                evercamPlayer.on('ended', function () {
                    notifyProgress();
                });
            }

            // Timeupdate 事件
            if (data.event === 'timeupdate' && data.value) {
                var currentTime = data.value.seconds || 0;
                var duration = data.value.duration || evercamPlayer.duration;

                if (duration > 0 && config.duration === 0) {
                    config.duration = Math.floor(duration);
                }

                // 跳轉偵測 - 跳轉時保存舊區段
                var diff = Math.abs(currentTime - lastPlaybackPosition);
                if (diff > 2 && lastPlaybackPosition > 0) {
                    notifyProgress();  // 保存跳轉前的區段
                    segmentStart = Math.floor(currentTime);
                }

                evercamPlayer.currentTime = currentTime;
                lastPlaybackPosition = currentTime;

                // 即時更新 UI 進度條（與舊版一致）
                if (config.duration > 0) {
                    var percent = Math.floor((currentTime / config.duration) * 100);
                    var bar = document.getElementById('videoprogress-progressbar');
                    if (bar) {
                        bar.style.width = percent + '%';
                        bar.textContent = percent + '%';
                    }
                }
            }

            // Callback 回應
            if (data.listener && evercamPlayer.listeners[data.listener]) {
                evercamPlayer.listeners[data.listener](data.value);
                delete evercamPlayer.listeners[data.listener];
            }

            // 事件回應
            if (data.event && data.event !== 'ready') {
                for (var key in evercamPlayer.listeners) {
                    if (key.indexOf(data.event + '_') === 0) {
                        evercamPlayer.listeners[key](data.value);
                    }
                }
            }
        });

        player = evercamPlayer;
        evercamPlayer.on('ready', function () { });

        // 定期儲存
        saveInterval = setInterval(function () {
            if (player && !player.paused && document.visibilityState === 'visible') {
                notifyProgress();
            }
        }, 5000);

        // UI 提示
        var hint = document.getElementById('videoprogress-timer-hint');
        if (hint) {
            hint.className = 'alert alert-info mb-2';
            hint.innerHTML = '<i class="fa fa-info-circle"></i> Evercam 播放器 - 精確進度追蹤已啟用';
        }
    }

    /**
     * 通知進度
     */
    function notifyProgress() {
        if (callbacks.onProgress && config.duration > 0) {
            callbacks.onProgress({
                currentTime: lastPlaybackPosition,
                duration: config.duration,
                segmentStart: segmentStart,
                isPlaying: player && !player.paused
            });
        }
    }

    function getCurrentTime() { return lastPlaybackPosition; }
    function seekTo(seconds) {
        if (player) {
            player.setCurrentTime(seconds);
            segmentStart = Math.floor(seconds);
            lastPlaybackPosition = seconds;
        }
    }
    function getDuration() { return config.duration || 0; }
    function isPlaying() { return player && !player.paused; }

    /**
     * 清除資源
     */
    function destroy() {
        if (saveInterval) {
            clearInterval(saveInterval);
            saveInterval = null;
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
