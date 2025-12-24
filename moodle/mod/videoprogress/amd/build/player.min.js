/**
 * Video Progress 播放器模組
 *
 * @module     mod_videoprogress/player
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('mod_videoprogress/player', ['jquery', 'core/ajax', 'core/notification'], function ($, Ajax, Notification) {
    'use strict';

    var config = {
        cmid: 0,
        videoid: 0,
        videotype: 'youtube',
        videourl: '',
        externalurl: '',
        duration: 0,
        lastposition: 0,
        completionpercent: 80,
        requirefocus: false,
        externalmintime: 60
    };

    var player = null;
    var lastSavedTime = 0;
    var segmentStart = 0;
    var saveInterval = null;
    var youtubeReady = false;
    var externalStartTime = null;  // 外部網址開始時間
    var externalTimerStarted = false;  // 外部網址計時器是否已啟動
    var overlayClickCooldown = false;  // 遮罩層點擊冷卻期（防止立即觸發計時）

    /**
     * 初始化模組
     *
     * @param {Object} options 設定選項
     */
    var init = function (options) {
        log('Init called with type: ' + options.videotype);
        config = $.extend(config, options);

        if (config.videotype === 'youtube') {
            initYouTubePlayer();
        } else if (config.videotype === 'external') {
            initExternalTracking();
        } else {
            initHTML5Player();
        }

        // 綁定續播按鈕
        $('#videoprogress-resume-btn').on('click', function () {
            seekTo(config.lastposition);
            $('#videoprogress-resume-prompt').fadeOut();
        });

        // 頁面關閉前儲存進度
        $(window).on('beforeunload', function () {
            saveProgress(true);
        });

        // 專注模式：切換分頁時暫停影片 (僅 YouTube / 上傳影片)
        if (config.requirefocus && (config.videotype === 'youtube' || config.videotype === 'upload')) {
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'hidden') {
                    // 暫停影片
                    if (config.videotype === 'youtube' && player && player.pauseVideo) {
                        player.pauseVideo();
                    } else if (player && player.element) {
                        player.element.pause();
                    }
                }
            });
        }
    };

    /**
     * 初始化 YouTube 播放器
     */
    var initYouTubePlayer = function () {
        // 載入 YouTube IFrame API
        if (!window.YT) {
            var tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            var firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
        }

        // YouTube API 載入完成後初始化播放器
        window.onYouTubeIframeAPIReady = function () {
            youtubeReady = true;
            createYouTubePlayer();
        };

        // 如果 API 已經載入
        if (window.YT && window.YT.Player) {
            youtubeReady = true;
            createYouTubePlayer();
        }
    };

    /**
     * 建立 YouTube 播放器
     */
    var createYouTubePlayer = function () {
        log('createYouTubePlayer called via existing iframe');
        // 如果已經有 iframe，就不需要再建立，直接綁定 API
        // 注意：YT.Player 建構子會自動處理現有的 iframe
        player = new YT.Player('videoprogress-youtube-player', {
            // 不傳入 videoId，讓它使用 iframe src 的影片
            playerVars: {
                'autoplay': 0,
                'controls': 1,
                'rel': 0,
                'modestbranding': 1
            },
            events: {
                'onReady': onYouTubePlayerReady,
                'onStateChange': onYouTubeStateChange
            }
        });
    };

    /**
     * YouTube 播放器準備就緒
     *
     * @param {Object} event 事件
     */
    var onYouTubePlayerReady = function (event) {
        log('YouTube Player Ready!');
        // 嘗試取得影片長度
        var duration = Math.floor(event.target.getDuration());
        log('getDuration() returned: ' + duration);

        if (duration > 0) {
            config.duration = duration;
            log('Duration set to: ' + config.duration);
        } else {
            log('Duration is 0, will try again when playing');
        }

        // 開始追蹤
        startTracking();
    };

    /**
     * YouTube 播放狀態變化
     *
     * @param {Object} event 事件
     */
    var onYouTubeStateChange = function (event) {
        log('YouTube State Change: ' + event.data);
        if (event.data === YT.PlayerState.PLAYING) {
            log('Video Playing');

            // 如果還沒取得長度，在播放時再試一次
            if (config.duration === 0 && player && player.getDuration) {
                var duration = Math.floor(player.getDuration());
                if (duration > 0) {
                    config.duration = duration;
                    log('Duration obtained during playback: ' + config.duration);
                }
            }

            // 開始播放，記錄起始位置
            segmentStart = Math.floor(player.getCurrentTime());
        } else if (event.data === YT.PlayerState.PAUSED) {
            // 暫停時儲存區段
            saveProgress(false);
        } else if (event.data === YT.PlayerState.ENDED) {
            // 影片結束時，強制儲存到影片總長度
            log('Video ENDED - forcing 100% completion');
            saveProgressWithEnd(config.duration);
        }
    };

    /**
     * 初始化 HTML5 播放器
     */
    var initHTML5Player = function () {
        var video = document.getElementById('videoprogress-html5-player');

        log('initHTML5Player called');

        if (!video) {
            log('ERROR: HTML5 video element not found!');
            return;
        }

        log('HTML5 video element found');

        player = {
            element: video,
            getCurrentTime: function () {
                return video.currentTime;
            },
            seekTo: function (seconds) {
                video.currentTime = seconds;
            },
            getDuration: function () {
                return video.duration;
            }
        };

        // 影片載入後取得長度
        video.addEventListener('loadedmetadata', function () {
            var duration = Math.floor(video.duration);
            log('HTML5 loadedmetadata: duration=' + duration + ', current config.duration=' + config.duration);
            // 強制設定 duration（移除條件檢查）
            if (duration > 0) {
                config.duration = duration;
                log('HTML5 duration FORCED to: ' + config.duration);
            }
            startTracking();
        });

        // 如果影片已經載入，直接取得長度
        if (video.readyState >= 1) {
            var duration = Math.floor(video.duration);
            log('HTML5 video already loaded: duration=' + duration);
            if (config.duration === 0 && duration > 0) {
                config.duration = duration;
            }
            startTracking();
        }

        // 播放狀態變化
        video.addEventListener('play', function () {
            log('HTML5 play event, currentTime=' + video.currentTime);
            segmentStart = Math.floor(video.currentTime);

            // 再次檢查長度
            if (config.duration === 0 && video.duration > 0) {
                config.duration = Math.floor(video.duration);
                log('HTML5 duration obtained on play: ' + config.duration);
            }
        });

        video.addEventListener('pause', function () {
            log('HTML5 pause event');
            saveProgress(false);
        });

        video.addEventListener('ended', function () {
            // 影片結束時，強制儲存到影片總長度
            log('HTML5 Video ENDED - forcing 100% completion');
            saveProgressWithEnd(config.duration);
        });

        // 時間更新事件 (每秒觸發多次，用於即時更新進度)
        video.addEventListener('timeupdate', function () {
            // 每 5 秒更新一次 segmentStart
            var currentTime = Math.floor(video.currentTime);
            if (currentTime - segmentStart >= 5) {
                saveProgress(false);
                segmentStart = currentTime;
            }
        });
    };

    /**
     * 初始化外部網址時間追蹤
     */
    var initExternalTracking = function () {
        log('initExternalTracking called, externalurl=' + config.externalurl);

        // 嘗試自動偵測影片長度
        // 如果外部 URL 是直接的影片檔案連結（.mp4, .webm, .ogg 等），可以偵測長度
        if (config.externalurl && config.duration === 0) {
            var url = config.externalurl.toLowerCase();
            if (url.match(/\.(mp4|webm|ogg|ogv|mov|m4v)(\?|$)/i)) {
                log('External URL is a direct video file, trying to detect duration...');

                // 建立隱藏的 video 元素來偵測長度
                var tempVideo = document.createElement('video');
                tempVideo.style.display = 'none';
                tempVideo.preload = 'metadata';

                tempVideo.addEventListener('loadedmetadata', function () {
                    var duration = Math.floor(tempVideo.duration);
                    log('External video duration detected: ' + duration);
                    if (duration > 0) {
                        config.duration = duration;
                        // 儲存一次進度，讓後端也更新 duration
                        saveProgress(false);
                    }
                    // 移除暫時的 video 元素
                    document.body.removeChild(tempVideo);
                });

                tempVideo.addEventListener('error', function () {
                    log('Failed to load external video for duration detection');
                    document.body.removeChild(tempVideo);
                });

                document.body.appendChild(tempVideo);
                tempVideo.src = config.externalurl;
            } else {
                log('External URL is not a direct video file, duration must be set manually');
            }
        }

        // 不自動開始計時，等待使用者點擊 iframe
        // 外部網址：每次只發送這段時間的增量，不累加
        segmentStart = 0;  // 每次都從 0 開始計算這一段的時間
        externalTimerStarted = false;

        // 偵測 iframe 點擊（使用 window.blur + activeElement 檢測）
        var iframe = document.getElementById('videoprogress-external-iframe');

        window.addEventListener('blur', function () {
            // 如果在冷卻期內，不開始計時
            if (overlayClickCooldown) {
                log('Blur ignored due to overlay cooldown');
                return;
            }

            if (document.activeElement === iframe && !externalTimerStarted) {
                // 使用者點擊了 iframe，開始計時
                externalTimerStarted = true;
                externalStartTime = Date.now();
                log('External timer started by iframe click');

                // 更新 UI 提示
                var hint = document.getElementById('videoprogress-timer-hint');
                if (hint) {
                    hint.className = 'alert alert-success mt-2';
                    hint.innerHTML = '<i class="fa fa-check"></i> ' +
                        (M.str && M.str.mod_videoprogress && M.str.mod_videoprogress.timerstarted
                            ? M.str.mod_videoprogress.timerstarted
                            : '計時已開始');
                }
            }
        });

        // 每 5 秒儲存一次進度（只有計時器啟動後才儲存）
        saveInterval = setInterval(function () {
            if (document.visibilityState === 'visible' && externalTimerStarted) {
                saveProgress(false);
                // 重設計時器起點，避免下次儲存時區段重疊
                segmentStart = 0;
                externalStartTime = Date.now();
            }
        }, 5000);

        // 頁面可見性變化
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                // 只有計時器啟動時才處理隱藏事件
                if (!externalTimerStarted) return;

                // 頁面隱藏前，先記錄當前時間
                var currentTime = getCurrentTime();

                // 儲存進度
                saveProgress(false);

                // 暫停計時器，下次重新從 0 開始計算
                segmentStart = 0;
                externalTimerStarted = false;
                externalStartTime = null;

                // 強制暫停：移除 iframe（這會停止所有播放）
                var iframe = document.getElementById('videoprogress-external-iframe');
                if (iframe) {
                    // 儲存 iframe 的 src 以便稍後恢復
                    iframe.setAttribute('data-saved-src', iframe.src);
                    iframe.src = 'about:blank';
                    // 標記需要顯示遮罩層
                    iframe.setAttribute('data-needs-overlay', 'true');
                }

                log('External timer paused due to tab switch, next segmentStart=' + segmentStart);
            } else {
                // 頁面重新可見，檢查是否需要顯示遮罩層
                var iframe = document.getElementById('videoprogress-external-iframe');
                if (iframe && iframe.getAttribute('data-needs-overlay') === 'true') {
                    // 顯示遮罩層
                    var overlay = document.getElementById('videoprogress-overlay');
                    if (overlay) {
                        overlay.style.display = 'flex';
                    }

                    // 更新 UI 提示為暫停狀態
                    var hint = document.getElementById('videoprogress-timer-hint');
                    if (hint) {
                        hint.className = 'alert alert-danger mb-2';
                        hint.innerHTML = '<i class="fa fa-pause"></i> ' +
                            (M.str && M.str.mod_videoprogress && M.str.mod_videoprogress.timerpaused
                                ? M.str.mod_videoprogress.timerpaused
                                : '計時已暫停，請點擊影片繼續');
                    }
                }
            }
        });

        // 遮罩層點擊事件：只移除遮罩，等待使用者點擊影片播放按鈕
        var overlay = document.getElementById('videoprogress-overlay');
        if (overlay) {
            overlay.addEventListener('click', function () {
                // 恢復 iframe 內容
                var iframe = document.getElementById('videoprogress-external-iframe');
                if (iframe) {
                    var savedSrc = iframe.getAttribute('data-saved-src');
                    if (savedSrc && iframe.src !== savedSrc) {
                        iframe.src = savedSrc;
                    }
                    // 清除遮罩層標記
                    iframe.removeAttribute('data-needs-overlay');
                }

                // 隱藏遮罩層
                overlay.style.display = 'none';

                // 設定冷卻期，防止 blur 事件立即觸發計時
                // 因為點擊遮罩後焦點會轉移到 iframe，但這不代表使用者點了播放
                overlayClickCooldown = true;
                setTimeout(function () {
                    overlayClickCooldown = false;
                    log('Overlay cooldown ended');
                }, 500);  // 500ms 冷卻期

                // 不自動開始計時，等待使用者點擊 iframe 後才開始
                // externalTimerStarted 保持為 false
                // window blur 事件會偵測到使用者點擊 iframe 後再開始計時

                // 更新 UI 提示：提醒使用者點擊影片繼續播放
                var hint = document.getElementById('videoprogress-timer-hint');
                if (hint) {
                    hint.className = 'alert alert-warning mb-2';
                    hint.innerHTML = '<i class="fa fa-play"></i> ' +
                        (M.str && M.str.mod_videoprogress && M.str.mod_videoprogress.clickvideoplay
                            ? M.str.mod_videoprogress.clickvideoplay
                            : '請點擊影片播放按鈕繼續');
                }

                log('Overlay removed, waiting for user to click video play button');
            });
        }
    };

    /**
     * 開始追蹤
     */
    var startTracking = function () {
        // 每 5 秒儲存一次進度
        saveInterval = setInterval(function () {
            if (isPlaying()) {
                saveProgress(false);
                segmentStart = Math.floor(getCurrentTime());
            }
        }, 5000);
    };

    /**
     * 檢查是否正在播放
     *
     * @return {Boolean}
     */
    var isPlaying = function () {
        if (config.videotype === 'youtube' && player && player.getPlayerState) {
            return player.getPlayerState() === YT.PlayerState.PLAYING;
        } else if (config.videotype === 'upload' && player && player.element) {
            return !player.element.paused;
        } else if (config.videotype === 'external') {
            // 外部網址：頁面可見且計時器已啟動才算「播放中」
            return document.visibilityState === 'visible' && externalTimerStarted;
        }
        return false;
    };

    /**
     * 取得當前播放時間
     *
     * @return {Number}
     */
    var getCurrentTime = function () {
        if (config.videotype === 'youtube' && player && player.getCurrentTime) {
            return Math.floor(player.getCurrentTime());
        } else if (config.videotype === 'upload' && player) {
            return Math.floor(player.getCurrentTime());
        } else if (config.videotype === 'external' && externalStartTime && externalTimerStarted) {
            // 外部網址：計算已經過的秒數（只有計時器啟動後才計算）
            var elapsedSeconds = Math.floor((Date.now() - externalStartTime) / 1000);
            return segmentStart + elapsedSeconds;
        }
        return 0;
    };

    /**
     * 跳轉到指定時間
     *
     * @param {Number} seconds 秒數
     */
    var seekTo = function (seconds) {
        if (config.videotype === 'youtube' && player && player.seekTo) {
            player.seekTo(seconds, true);
        } else if (config.videotype === 'upload' && player) {
            player.seekTo(seconds);
        }
        // 外部網址不支援跳轉
    };

    /**
     * 儲存進度到伺服器
 *
 * @param {Boolean} sync 是否同步請求
 */
    var saveProgress = function (sync) {
        var currentTime = getCurrentTime();

        // 如果沒有變化，不儲存
        if (currentTime <= segmentStart) {
            return;
        }

        // 確保有正確的影片長度
        var videoDuration = config.duration;
        log('DEBUG: Initial videoDuration=' + videoDuration + ', config.duration=' + config.duration);

        if (videoDuration === 0) {
            // 嘗試從多個來源取得長度

            // 方法1: 直接從 HTML5 video 元素取得
            var videoElement = document.getElementById('videoprogress-html5-player');
            if (videoElement && videoElement.duration && videoElement.duration > 0 && !isNaN(videoElement.duration)) {
                videoDuration = Math.floor(videoElement.duration);
                config.duration = videoDuration;
                log('Duration from HTML5 element: ' + videoDuration);
            }

            // 方法2: 從 player.element 取得
            if (videoDuration === 0 && player && player.element && player.element.duration) {
                var d = player.element.duration;
                if (d > 0 && !isNaN(d)) {
                    videoDuration = Math.floor(d);
                    config.duration = videoDuration;
                    log('Duration from player.element: ' + videoDuration);
                }
            }

            // 方法3: 從 player.getDuration() 取得 (YouTube)
            if (videoDuration === 0 && player && player.getDuration) {
                try {
                    var d = player.getDuration();
                    if (d && d > 0 && !isNaN(d)) {
                        videoDuration = Math.floor(d);
                        config.duration = videoDuration;
                        log('Duration from player.getDuration(): ' + videoDuration);
                    }
                } catch (e) {
                    log('Error getting duration: ' + e);
                }
            }

            log('Final videoDuration=' + videoDuration);
        }

        var data = {
            cmid: config.cmid,
            segmentstart: segmentStart,
            segmentend: currentTime,
            currentposition: currentTime,
            videoduration: videoDuration  // 傳送影片長度給後端
        };

        log('Saving progress: start=' + segmentStart + ', end=' + currentTime + ', duration=' + videoDuration);

        if (sync) {
            // 頁面關閉時使用同步請求
            var xhr = new XMLHttpRequest();
            xhr.open('POST', M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey, false);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify([{
                index: 0,
                methodname: 'mod_videoprogress_save_progress',
                args: data
            }]));
        } else {
            // 正常使用 AJAX
            Ajax.call([{
                methodname: 'mod_videoprogress_save_progress',
                args: data,
                done: function (response) {
                    log('Save success. Response: ' + JSON.stringify(response));
                    if (response.completed) {
                        // 更新 UI 顯示完成狀態
                        updateProgressUI(response.percentcomplete, true);
                    } else {
                        updateProgressUI(response.percentcomplete, false);
                    }
                },
                fail: function (error) {
                    log('Save FAILED: ' + JSON.stringify(error));

                }
            }]);
        }

        lastSavedTime = currentTime;
        log('Progress saved. Time: ' + currentTime);

        // 外部網址：每次儲存後重設計時器，避免區段重疊
        if (config.videotype === 'external' && externalTimerStarted) {
            segmentStart = 0;
            externalStartTime = Date.now();
            log('External timer reset after save');
        }
    };

    /**
     * 儲存進度到伺服器（指定結束時間）
     * 用於影片結束時強制儲存到影片總長度
     *
     * @param {Number} endTime 結束時間（秒）
     */
    var saveProgressWithEnd = function (endTime) {
        var data = {
            cmid: config.cmid,
            segmentstart: segmentStart,
            segmentend: endTime,
            currentposition: endTime,
            videoduration: config.duration
        };

        log('Saving progress with forced end: start=' + segmentStart + ', end=' + endTime);

        Ajax.call([{
            methodname: 'mod_videoprogress_save_progress',
            args: data,
            done: function (response) {
                log('Save success. Percent: ' + response.percentcomplete + '%');
                // 強制顯示 100%
                updateProgressUI(100, true);
            },
            fail: function (error) {
                log('Save FAILED: ' + JSON.stringify(error));
            }
        }]);
    };

    /**
     * 更新進度 UI
     *
     * @param {Number} percent 百分比
     * @param {Boolean} completed 是否完成
     */
    var updateProgressUI = function (percent, completed) {
        var progressBar = $('.videoprogress-status .progress .progress-bar');
        log('updateProgressUI called: percent=' + percent + ', completed=' + completed + ', found=' + progressBar.length);
        progressBar.css('width', percent + '%').text(percent + '%');

        if (completed) {
            progressBar.removeClass('bg-primary').addClass('bg-success');
            // 使用安全的方式取得語言字串
            var completedText = '已完成';
            if (M.str && M.str.mod_videoprogress && M.str.mod_videoprogress.completed) {
                completedText = M.str.mod_videoprogress.completed;
            }
            $('.videoprogress-status .badge')
                .removeClass('badge-secondary bg-secondary')
                .addClass('badge-success bg-success')
                .text(completedText);

            log('Activity marked as completed!');
        }
    };

    /**
     * 除錯日誌（已停用）
     */
    var log = function (msg) {
        // Debug logging disabled in production
    };

    return {
        init: init
    };
});
