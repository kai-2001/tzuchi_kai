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
    var lastPlaybackPosition = 0;  // 追蹤正常播放時的位置（用於跳轉時儲存正確的區段）
    var hasPlayedOnce = false;  // 標記是否已經真正播放過（用於防止初始 seeking 時儲存空區段）

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
        } else if (config.videotype === 'evercam') {
            initEvercamPlayer();
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

        // 專注模式：切換分頁時暫停影片 (YouTube / 上傳影片 / Evercam)
        if (config.requirefocus && (config.videotype === 'youtube' || config.videotype === 'upload' || config.videotype === 'evercam')) {
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'hidden') {
                    // 暫停影片
                    if (config.videotype === 'youtube' && player && player.pauseVideo) {
                        player.pauseVideo();
                    } else if (config.videotype === 'evercam' && player && player.pause) {
                        player.pause();
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
     * 初始化 Evercam 播放器 (使用 Player.js 協議)
     */
    var evercamPositionInterval = null;
    var initEvercamPlayer = function () {
        log('Initializing Evercam player with Player.js protocol');

        var iframe = document.getElementById('videoprogress-external-iframe');
        if (!iframe) {
            log('ERROR: Evercam iframe not found');
            return;
        }

        // 等待 iframe 載入完成
        var initPlayerJs = function () {
            // 使用 playerjs 協議與 iframe 通訊
            // Player.js 使用 postMessage 實現跨 iframe 通訊

            var evercamPlayer = {
                element: iframe,
                isReady: false,
                duration: 0,
                currentTime: 0,
                paused: true,
                listeners: {},

                // 發送訊息到 iframe
                send: function (method, value, listener) {
                    var msg = {
                        context: 'player.js',
                        version: '0.0.10',
                        method: method
                    };
                    if (value !== undefined) {
                        msg.value = value;
                    }
                    if (listener !== undefined) {
                        msg.listener = listener;
                    }
                    try {
                        iframe.contentWindow.postMessage(JSON.stringify(msg), '*');
                    } catch (e) {
                        log('Evercam postMessage error: ' + e.message);
                    }
                },

                // 播放
                play: function () {
                    this.send('play');
                },

                // 暫停
                pause: function () {
                    this.send('pause');
                },

                // 取得時長
                getDuration: function (callback) {
                    var listenerId = 'getDuration_' + Date.now();
                    this.listeners[listenerId] = callback;
                    this.send('getDuration', undefined, listenerId);
                },

                // 取得當前時間
                getCurrentTime: function (callback) {
                    var listenerId = 'getCurrentTime_' + Date.now();
                    this.listeners[listenerId] = callback;
                    this.send('getCurrentTime', undefined, listenerId);
                },

                // 設定當前時間 (跳轉)
                setCurrentTime: function (time) {
                    this.send('setCurrentTime', time);
                },

                // 訂閱事件
                on: function (event, callback) {
                    var listenerId = event + '_' + Date.now();
                    this.listeners[listenerId] = callback;
                    this.send('addEventListener', event, listenerId);
                }
            };

            // 監聽來自 iframe 的訊息
            window.addEventListener('message', function (e) {
                var data;
                try {
                    data = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
                } catch (err) {
                    return; // 不是我們的訊息
                }

                if (!data || data.context !== 'player.js') {
                    return;
                }

                log('Evercam message received: ' + JSON.stringify(data));

                // 處理 ready 事件
                if (data.event === 'ready') {
                    log('Evercam player ready');
                    evercamPlayer.isReady = true;

                    // 取得影片長度
                    evercamPlayer.getDuration(function (duration) {
                        log('Evercam duration: ' + duration);
                        evercamPlayer.duration = duration;
                        if (duration > 0 && config.duration === 0) {
                            config.duration = Math.floor(duration);
                            // 儲存一次進度更新 duration
                            saveProgress(false);
                        }
                    });

                    // 訂閱 timeupdate 事件
                    evercamPlayer.on('timeupdate', function () { });

                    // 訂閱 play 事件
                    evercamPlayer.on('play', function () {
                        log('Evercam play event');
                        evercamPlayer.paused = false;
                        // 開始新區段
                        segmentStart = Math.floor(evercamPlayer.currentTime);
                        lastPlaybackPosition = segmentStart;
                    });

                    // 訂閱 pause 事件
                    evercamPlayer.on('pause', function () {
                        log('Evercam pause event');
                        evercamPlayer.paused = true;
                        // 儲存區段
                        saveProgress(false);
                    });

                    // 訂閱 ended 事件
                    evercamPlayer.on('ended', function () {
                        log('Evercam ended event');
                        saveProgress(true);
                    });
                }

                // 處理 timeupdate 事件
                if (data.event === 'timeupdate' && data.value) {
                    var currentTime = data.value.seconds || 0;
                    var duration = data.value.duration || evercamPlayer.duration;

                    if (duration > 0 && config.duration === 0) {
                        config.duration = Math.floor(duration);
                    }

                    // 跳轉偵測
                    var diff = Math.abs(currentTime - lastPlaybackPosition);
                    if (diff > 2 && lastPlaybackPosition > 0) {
                        // 偵測到跳轉
                        log('Evercam seek detected: from ' + lastPlaybackPosition + ' to ' + currentTime);
                        // 儲存跳轉前的區段
                        saveProgress(false, segmentStart, Math.floor(lastPlaybackPosition));
                        // 重設新區段起點
                        segmentStart = Math.floor(currentTime);
                    }

                    evercamPlayer.currentTime = currentTime;
                    lastPlaybackPosition = currentTime;

                    // 更新 UI 進度條
                    if (config.duration > 0) {
                        var percent = Math.floor((currentTime / config.duration) * 100);
                        updateProgressUI(percent);
                    }
                }

                // 處理 callback 回應
                if (data.listener && evercamPlayer.listeners[data.listener]) {
                    evercamPlayer.listeners[data.listener](data.value);
                    delete evercamPlayer.listeners[data.listener];
                }

                // 處理事件回應
                if (data.event && data.event !== 'ready') {
                    for (var key in evercamPlayer.listeners) {
                        if (key.indexOf(data.event + '_') === 0) {
                            evercamPlayer.listeners[key](data.value);
                        }
                    }
                }
            });

            // 設定全域 player 變數
            player = evercamPlayer;

            // 發送 ready 訂閱
            evercamPlayer.on('ready', function () { });
        };

        // 等 iframe 載入後初始化
        if (iframe.contentWindow) {
            initPlayerJs();
        } else {
            iframe.onload = initPlayerJs;
        }

        // 定期儲存進度 (每 5 秒)
        saveInterval = setInterval(function () {
            if (player && !player.paused && document.visibilityState === 'visible') {
                saveProgress(false);
            }
        }, 5000);

        // 顯示 UI 提示
        var hint = document.getElementById('videoprogress-timer-hint');
        if (hint) {
            hint.className = 'alert alert-info mb-2';
            hint.innerHTML = '<i class="fa fa-info-circle"></i> Evercam 播放器 - 精確進度追蹤已啟用';
        }
    };

    /**
     * YouTube 播放狀態變化
     *
     * @param {Object} event 事件
     */
    var youtubePositionInterval = null;  // YouTube 位置追蹤 interval
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
            lastPlaybackPosition = segmentStart;  // 初始化 lastPlaybackPosition
            log('YouTube Playing, segmentStart=' + segmentStart + ', lastPlaybackPosition=' + lastPlaybackPosition);

            // 啟動快速位置追蹤（每 500ms 更新一次 lastPlaybackPosition）
            if (youtubePositionInterval) {
                clearInterval(youtubePositionInterval);
            }
            youtubePositionInterval = setInterval(function () {
                if (player && player.getCurrentTime) {
                    lastPlaybackPosition = Math.floor(player.getCurrentTime());
                }
            }, 500);
        } else if (event.data === YT.PlayerState.PAUSED) {
            // 停止快速位置追蹤
            if (youtubePositionInterval) {
                clearInterval(youtubePositionInterval);
                youtubePositionInterval = null;
            }
            // 暫停時儲存區段（使用 lastPlaybackPosition 避免儲存跳轉後的位置）
            log('YouTube Paused, lastPlaybackPosition=' + lastPlaybackPosition);
            saveProgress(false, true);
        } else if (event.data === YT.PlayerState.ENDED) {
            // 停止快速位置追蹤
            if (youtubePositionInterval) {
                clearInterval(youtubePositionInterval);
                youtubePositionInterval = null;
            }
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
            hasPlayedOnce = true;  // 標記已經開始播放
            segmentStart = Math.floor(video.currentTime);
            lastPlaybackPosition = segmentStart;  // 初始化 lastPlaybackPosition

            // 再次檢查長度
            if (config.duration === 0 && video.duration > 0) {
                config.duration = Math.floor(video.duration);
                log('HTML5 duration obtained on play: ' + config.duration);
            }
        });

        video.addEventListener('pause', function () {
            log('HTML5 pause event, isSeeking=' + isSeeking + ', justSeeked=' + justSeeked + ', lastPlaybackPosition=' + lastPlaybackPosition + ', beforeSeekPosition=' + beforeSeekPosition);
            // 如果正在 seeking 或剛完成 seeking，不要儲存（會由 seeking 事件處理）
            // justSeeked 用於處理 seeked 比 pause 先觸發的情況
            if (!isSeeking && !justSeeked) {
                // 使用 beforeSeekPosition 作為結束時間（這是真正觀看到的位置）
                // 如果 beforeSeekPosition 有效，直接用它；否則用 lastPlaybackPosition
                var endPos = (beforeSeekPosition > 0 && beforeSeekPosition >= segmentStart) ? beforeSeekPosition : lastPlaybackPosition;
                // 確保儲存的區段有意義（至少 1 秒）
                if (endPos > segmentStart) {
                    log('HTML5 pause: saving progress end=' + endPos);
                    saveProgressWithEnd(endPos, false);
                }
            } else {
                log('HTML5 pause: skipped due to seeking/justSeeked');
            }
        });

        video.addEventListener('ended', function () {
            // 影片結束時，強制儲存到影片總長度
            log('HTML5 Video ENDED - forcing 100% completion');
            saveProgressWithEnd(config.duration);
        });

        // 跳轉追蹤用變數
        var seekingFromPosition = 0;
        var isSeeking = false;
        var beforeSeekPosition = 0;  // 跳轉前的真實觀看位置（timeupdate 更新）
        var justSeeked = false;  // 標記剛完成跳轉（用於 pause 判斷）

        // 跳轉開始事件 - 儲存跳轉前的區段
        video.addEventListener('seeking', function () {
            isSeeking = true;  // 標記正在 seeking
            justSeeked = true;  // 標記正在跳轉中

            // 使用 beforeSeekPosition 作為跳轉前的真實位置（而非可能已被污染的 lastPlaybackPosition）
            var actualEndPosition = beforeSeekPosition > 0 ? beforeSeekPosition : lastPlaybackPosition;

            log('HTML5 seeking event: from=' + seekingFromPosition + ', segmentStart=' + segmentStart + ', beforeSeekPosition=' + beforeSeekPosition + ', lastPlaybackPosition=' + lastPlaybackPosition + ', hasPlayedOnce=' + hasPlayedOnce);

            // 儲存跳轉前的區段（如果有內容的話，且影片已經開始播放過）
            // 條件：hasPlayedOnce 且 至少播放了 2 秒以上
            var minWatched = actualEndPosition - segmentStart;
            if (hasPlayedOnce && minWatched >= 2) {
                log('Saving segment before seek: ' + segmentStart + ' to ' + actualEndPosition);
                // 使用 saveProgressWithEnd 帶入正確的結束時間，不強制顯示 100%
                saveProgressWithEnd(actualEndPosition, false);
            } else {
                log('Skipping segment save: minWatched=' + minWatched + ' < 2');
            }
        });

        // 跳轉完成事件 - 重設 segmentStart 到新位置
        video.addEventListener('seeked', function () {
            var newPosition = Math.floor(video.currentTime);
            log('HTML5 seeked event: new position=' + newPosition);
            segmentStart = newPosition;
            seekingFromPosition = newPosition;
            lastPlaybackPosition = newPosition;  // 重設 lastPlaybackPosition
            beforeSeekPosition = newPosition;  // 同步重設 beforeSeekPosition
            isSeeking = false;  // 標記 seeking 結束
            // 延遲清除 justSeeked 標記，讓 pause 事件有機會檢測到剛跳轉
            setTimeout(function () {
                justSeeked = false;
            }, 100);
            log('segmentStart reset to: ' + segmentStart);
        });

        // 時間更新事件 (每秒觸發多次，用於即時更新進度)
        video.addEventListener('timeupdate', function () {
            var currentTime = Math.floor(video.currentTime);

            // 跳轉檢測：如果時間差超過 2 秒，視為跳轉而非正常播放
            // 修正：也要檢測從 0 開始的跳轉（hasPlayedOnce 但 segmentStart 仍為 0 且跳很遠）
            var timeDiff = Math.abs(currentTime - lastPlaybackPosition);
            var fromStartSeek = (segmentStart === 0 && currentTime > 10 && hasPlayedOnce);
            if (timeDiff > 2 || fromStartSeek) {
                // 跳轉中，不更新 lastPlaybackPosition，等待 seeked 事件處理
                log('timeupdate: Seek detected (diff=' + timeDiff + ', fromStartSeek=' + fromStartSeek + '), skipping update');
                return;
            }

            // 如果正在 seeking，不處理 timeupdate（避免用跳轉後的位置錯誤計算）
            if (isSeeking) {
                log('timeupdate: isSeeking=true, skipping');
                return;
            }

            // 正常播放：追蹤當前位置
            seekingFromPosition = currentTime;
            beforeSeekPosition = currentTime;  // 記錄跳轉前的真實觀看位置
            lastPlaybackPosition = currentTime;  // 更新 lastPlaybackPosition

            // 每 5 秒更新一次
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
        log('Current config.duration=' + config.duration);

        // 嘗試自動偵測影片長度（外部網址模式下始終嘗試偵測）
        if (config.externalurl) {
            detectVideoUrl(config.externalurl);
        } else {
            // 沒有 externalurl，直接初始化計時追蹤
            initExternalTimerTracking();
        }
    };

    /**
     * 偵測影片 URL 並取得時長
     * 如果 URL 是直接影片格式，直接偵測時長
     * 否則呼叫 detect_video.php API 解析網頁
     *
     * @param {String} url 外部網址
     */
    var detectVideoUrl = function (url) {
        var urlLower = url.toLowerCase();

        // 檢查是否是直接影片格式
        if (urlLower.match(/\.(mp4|webm|ogg|ogv|mov|m4v)(\?|$)/i)) {
            log('External URL is a direct video file, detecting duration...');
            detectDurationFromVideoUrl(url);
        } else {
            // 不是直接影片格式，呼叫 API 解析網頁
            log('External URL is not a direct video file, calling detect_video API...');

            // 更新 UI 提示
            var hint = document.getElementById('videoprogress-timer-hint');
            if (hint) {
                hint.className = 'alert alert-info mb-2';
                hint.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 正在偵測影片資訊...';
            }

            // 呼叫 detect_video.php API
            $.ajax({
                url: M.cfg.wwwroot + '/mod/videoprogress/detect_video.php',
                method: 'GET',
                data: { url: url },
                dataType: 'json',
                success: function (response) {
                    log('detect_video API response: ' + JSON.stringify(response));

                    if (response.success && response.videourl) {
                        log('Video URL detected: ' + response.videourl + ' (method: ' + response.method + ')');

                        // 優先使用 API 回傳的 duration（已在後端取得，繞過 CORS）
                        if (response.duration && response.duration > 0) {
                            log('Duration from API: ' + response.duration + ' seconds');
                            config.duration = response.duration;

                            // 更新 UI 提示
                            var minutes = Math.floor(response.duration / 60);
                            var seconds = response.duration % 60;
                            if (hint) {
                                hint.className = 'alert alert-success mb-2';
                                hint.innerHTML = '<i class="fa fa-check"></i> 影片長度已偵測: ' +
                                    minutes + ' 分 ' + seconds + ' 秒';
                            }

                            // 儲存一次進度，讓後端也更新 duration
                            saveProgress(false);
                            // 繼續初始化計時追蹤
                            initExternalTimerTracking();
                        } else {
                            // API 沒有提供 duration，嘗試用瀏覽器載入
                            log('No duration from API, trying browser detection...');
                            detectDurationFromVideoUrl(response.videourl);
                        }
                    } else {
                        log('Failed to detect video URL: ' + (response.error || 'Unknown error'));
                        // 顯示錯誤提示
                        if (hint) {
                            hint.className = 'alert alert-warning mb-2';
                            hint.innerHTML = '<i class="fa fa-exclamation-triangle"></i> 無法自動偵測影片長度，將使用計時模式';
                        }
                        // 繼續初始化計時追蹤
                        initExternalTimerTracking();
                    }
                },
                error: function (xhr, status, error) {
                    log('detect_video API error: ' + error);
                    // 顯示錯誤提示
                    if (hint) {
                        hint.className = 'alert alert-warning mb-2';
                        hint.innerHTML = '<i class="fa fa-exclamation-triangle"></i> 無法自動偵測影片長度，將使用計時模式';
                    }
                    // 繼續初始化計時追蹤
                    initExternalTimerTracking();
                }
            });
        }
    };

    /**
     * 從影片 URL 偵測時長
     *
     * @param {String} videoUrl 影片 URL
     */
    var detectDurationFromVideoUrl = function (videoUrl) {
        var tempVideo = document.createElement('video');
        tempVideo.style.display = 'none';
        tempVideo.preload = 'metadata';
        tempVideo.crossOrigin = 'anonymous';

        tempVideo.addEventListener('loadedmetadata', function () {
            var duration = Math.floor(tempVideo.duration);
            log('External video duration detected: ' + duration + ' seconds');

            if (duration > 0) {
                config.duration = duration;

                // 更新 UI 提示
                var hint = document.getElementById('videoprogress-timer-hint');
                if (hint) {
                    var minutes = Math.floor(duration / 60);
                    var seconds = duration % 60;
                    hint.className = 'alert alert-success mb-2';
                    hint.innerHTML = '<i class="fa fa-check"></i> 影片長度已偵測: ' +
                        minutes + ' 分 ' + seconds + ' 秒';
                }

                // 儲存一次進度，讓後端也更新 duration
                saveProgress(false);
            }

            // 移除暫時的 video 元素
            document.body.removeChild(tempVideo);
            // 繼續初始化計時追蹤
            initExternalTimerTracking();
        });

        tempVideo.addEventListener('error', function (e) {
            log('Failed to load video for duration detection: ' + (e.message || 'Unknown error'));

            // 顯示提示
            var hint = document.getElementById('videoprogress-timer-hint');
            if (hint) {
                hint.className = 'alert alert-warning mb-2';
                hint.innerHTML = '<i class="fa fa-exclamation-triangle"></i> 無法載入影片偵測長度，將使用計時模式';
            }

            // 移除暫時的 video 元素
            if (tempVideo.parentNode) {
                document.body.removeChild(tempVideo);
            }
            // 繼續初始化計時追蹤
            initExternalTimerTracking();
        });

        document.body.appendChild(tempVideo);
        tempVideo.src = videoUrl;
    };

    /**
     * 初始化外部網址計時追蹤（iframe 點擊偵測、進度儲存等）
     */
    var initExternalTimerTracking = function () {

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
                // 更新 lastPlaybackPosition（用於跳轉時儲存正確的區段）
                lastPlaybackPosition = Math.floor(getCurrentTime());
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
 * @param {Boolean} useLastPlaybackPosition 是否使用 lastPlaybackPosition 而非 getCurrentTime()
 */
    var saveProgress = function (sync, useLastPlaybackPosition) {
        // 決定使用哪個時間作為結束點
        var currentTime;
        if (useLastPlaybackPosition) {
            // 使用 lastPlaybackPosition（跳轉前的位置）
            // 即使是 0 也使用，後面的 currentTime <= segmentStart 會過濾掉無效區段
            currentTime = lastPlaybackPosition;
            log('saveProgress using lastPlaybackPosition: ' + currentTime);
        } else {
            currentTime = getCurrentTime();
        }

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
     * 用於影片結束時強制儲存到影片總長度，或 seeking 時儲存正確的區段
     *
     * @param {Number} endTime 結束時間（秒）
     * @param {Boolean} forceComplete 是否強制顯示為 100% 完成（預設 true）
     */
    var saveProgressWithEnd = function (endTime, forceComplete) {
        // 預設為強制完成
        if (forceComplete === undefined) {
            forceComplete = true;
        }

        var data = {
            cmid: config.cmid,
            segmentstart: segmentStart,
            segmentend: endTime,
            currentposition: endTime,
            videoduration: config.duration
        };

        log('Saving progress with forced end: start=' + segmentStart + ', end=' + endTime + ', forceComplete=' + forceComplete);

        Ajax.call([{
            methodname: 'mod_videoprogress_save_progress',
            args: data,
            done: function (response) {
                log('Save success. Percent: ' + response.percentcomplete + '%');
                if (forceComplete) {
                    // 影片結束時強制顯示 100%
                    updateProgressUI(100, true);
                } else {
                    // seeking 時使用實際百分比
                    updateProgressUI(response.percentcomplete, response.completed);
                }
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
     * 除錯日誌（生產環境已停用）
     */
    var log = function (msg) {
        // Production: disabled
    };

    return {
        init: init
    };
});
