/**
 * Progress Service Module
 * 進度儲存服務模組 - 完整版
 *
 * @module     mod_videoprogress/services/progress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('mod_videoprogress/services/progress', ['jquery', 'core/ajax'], function ($, Ajax) {
    'use strict';

    var config = null;
    var watchedSegments = [];
    var saveTimer = null;
    var lastSaveTime = 0;
    var lastPosition = 0;
    var segmentStart = 0;
    var storageKey = null;  // 模組級變數，所有函數都能用
    var initialPercent = 0; // 伺服器傳來的初始百分比

    /**
     * 儲存當前位置到 localStorage（即時保存）
     */
    function saveToLocalStorage(position) {
        // 確保 storageKey 有值
        if (!storageKey) {
            var match = window.location.search.match(/[?&]id=(\d+)/);
            var cmid = match ? match[1] : '0';
            storageKey = 'videoprogress_pos_' + cmid;
        }
        try {
            localStorage.setItem(storageKey, position.toString());
        } catch (e) { }
    }

    /**
     * 初始化
     */
    function init(options) {
        config = options;

        // localStorage key（從 URL 取得 cmid）
        var match = window.location.search.match(/[?&]id=(\d+)/);
        var cmid = match ? match[1] : '0';
        storageKey = 'videoprogress_pos_' + cmid;  // 設定模組級變數

        // 初始化百分比（防止進度條縮回）
        if (config.currentProgress > 0) {
            initialPercent = config.currentProgress;
            updateProgressUI(initialPercent, initialPercent >= (config.completionpercent || 80));
        }

        // 取得 localStorage 暫存位置（最後停留位置）
        var cachedPos = 0;
        try {
            var cached = localStorage.getItem(storageKey);
            if (cached) {
                cachedPos = parseFloat(cached) || 0;
            }
        } catch (e) { }

        // 優先使用 localStorage（最後停留位置），若無則用伺服器位置
        var serverPos = config.lastposition || 0;
        var bestPos = cachedPos > 0 ? cachedPos : serverPos;

        if (bestPos > 0) {
            segmentStart = Math.floor(bestPos);
            lastPosition = segmentStart;
            config.lastposition = bestPos;  // 更新 config 讓其他模組也能用

            // 顯示「繼續觀看」按鈕（無論來源是 localStorage 還是 Server）
            updateResumeButton(bestPos);
        }

        // 綁定繼續觀看按鈕事件（MVC 優化：按鈕可能已由 PHP 預渲染）
        var resumeBtn = document.getElementById('videoprogress-resume-btn');
        if (resumeBtn) {
            resumeBtn.addEventListener('click', function () {
                var pos = parseFloat(this.getAttribute('data-position')) || 0;

                // 嘗試不同播放器的跳轉
                var video = document.getElementById('videoprogress-html5-player');
                if (video) {
                    video.currentTime = pos;
                    video.play();
                }

                // YouTube 和其他播放器的處理通常由各自模組負責，或者這裡可以發送全域事件
                // 但目前架構上，HTML5 是最直接受控的。

                // 隱藏提示
                var prompt = document.getElementById('videoprogress-resume-prompt');
                if (prompt) prompt.style.display = 'none';
            });
        }

        // 頁面離開時儲存到伺服器
        window.addEventListener('beforeunload', function () {
            saveNow(true);
        });

        // pagehide 事件（iOS Safari 更可靠）
        window.addEventListener('pagehide', function () {
            saveNow(true);
        });
    }

    /**
     * 更新進度
     */
    function update(data) {
        if (!data) return;

        // 外部計時模式
        if (data.watchedTime !== undefined) {
            updateExternalProgress(data);
            return;
        }

        // 影片進度模式
        if (!data.duration || data.duration <= 0) return;

        // 更新 segmentStart
        if (data.segmentStart !== undefined) {
            segmentStart = data.segmentStart;
        }

        // 更新已觀看區段
        if (data.currentTime !== undefined) {
            lastPosition = data.currentTime;
            saveToLocalStorage(lastPosition);  // 即時保存到 localStorage（用於繼續觀看按鈕）

            if (segmentStart !== undefined && data.currentTime > segmentStart) {
                addSegment(segmentStart, data.currentTime);
            } else {
                addWatchedTime(data.currentTime);
            }

            // 移除即時 UI 更新 - 改為只在 AJAX 回應後更新（與舊版邏輯一致）
            // var clientPercent = calculatePercent(data.duration);
            // updateProgressUI(clientPercent, clientPercent >= (config.completionpercent || 80));
        }

        // 強制更新立即儲存
        if (data.forceUpdate) {
            saveNow(false);
            return;
        }

        // 移除多餘的節流儲存檢查，因為 player.js 已經控制了頻率 (5秒)
        // 額外的節流會導致與 player.js 計算的 segmentStart 不同步，造成進度遺失
        // var now = Date.now();
        // if (now - lastSaveTime < 5000) return;

        // lastSaveTime = now;
        scheduleSave();
    }

    /**
     * 更新外部計時進度
     */
    /**
     * 更新外部計時進度
     */
    function updateExternalProgress(data) {
        var requiredTime = data.requiredTime || config.externalmintime || 60;

        // 外部計時模式：我們需要發送增量區段給後端，後端會累加這些區段來計算總觀看時間
        // 使用 lastPosition 作為上次儲存的時間點
        var currentWatchedTime = data.watchedTime;
        var previousWatchedTime = lastPosition;

        // 如果沒有新增進度，則不發送（除非為了維持 session，但這由 Moodle 處理）
        if (currentWatchedTime <= previousWatchedTime) {
            return;
        }

        var now = Date.now();
        if (now - lastSaveTime < 5000 && !data.forceUpdate) return;
        lastSaveTime = now;

        var payload = {
            cmid: config.cmid,
            segmentstart: previousWatchedTime,
            segmentend: currentWatchedTime,
            currentposition: currentWatchedTime,
            videoduration: requiredTime // 這裡傳遞要求時間作為參考，雖然 save_progress.php 可能不使用它來計算進度
        };

        Ajax.call([{
            methodname: 'mod_videoprogress_save_progress',
            args: payload,
            done: function (response) {
                if (response.percentcomplete !== undefined) {
                    updateProgressUI(response.percentcomplete, response.completed);
                }
                // 更新最後位置
                lastPosition = currentWatchedTime;
            },
            fail: function (error) {
                console.error('External save failed:', error);
            }
        }]);
    }


    /**
     * 添加區段
     */
    function addSegment(start, end) {
        start = Math.floor(start);
        end = Math.floor(end);
        if (end <= start) return;

        var merged = false;
        for (var i = 0; i < watchedSegments.length; i++) {
            var seg = watchedSegments[i];
            if (start <= seg[1] + 1 && end >= seg[0] - 1) {
                seg[0] = Math.min(seg[0], start);
                seg[1] = Math.max(seg[1], end);
                merged = true;
                break;
            }
        }

        if (!merged) {
            watchedSegments.push([start, end]);
        }

        watchedSegments.sort(function (a, b) {
            return a[0] - b[0];
        });
    }

    /**
     * 添加已觀看時間點（簡單模式）
     */
    function addWatchedTime(currentTime) {
        var size = 5;
        var start = Math.floor(currentTime / size) * size;
        var end = start + size;

        var exists = watchedSegments.some(function (seg) {
            return seg[0] === start;
        });

        if (!exists) {
            watchedSegments.push([start, end]);
        }
    }

    /**
     * 排程儲存
     */
    function scheduleSave() {
        if (saveTimer) return;
        saveTimer = setTimeout(function () {
            saveTimer = null;
            saveNow(false);
        }, 1000);
    }

    /**
     * 立即儲存
     * @param {boolean} sync 是否使用同步請求（用於 beforeunload）
     */
    function saveNow(sync) {
        if (!config) return;

        var currentEnd = lastPosition;
        var currentStart = segmentStart;

        // 頁面關閉時，直接從影片元素取得最新位置
        if (sync) {
            var videoElement = document.getElementById('videoprogress-html5-player');
            if (videoElement && videoElement.currentTime > 0) {
                currentEnd = Math.floor(videoElement.currentTime);
            }
        }

        // 如果沒有有效進展，不儲存
        if (currentEnd <= currentStart) {
            return;
        }

        // Duration 多重來源偵測（與 4.5 一致）
        var videoDuration = config.duration || 0;
        if (videoDuration === 0) {
            // 方法1: 從 HTML5 video 元素
            var videoElement = document.getElementById('videoprogress-html5-player');
            if (videoElement && videoElement.duration && videoElement.duration > 0 && !isNaN(videoElement.duration)) {
                videoDuration = Math.floor(videoElement.duration);
                config.duration = videoDuration;
            }
        }

        var data = {
            cmid: config.cmid,
            segmentstart: currentStart,
            segmentend: currentEnd,
            currentposition: currentEnd,
            videoduration: videoDuration
        };

        if (sync) {
            // 頁面關閉時優先使用 sendBeacon（更可靠），降級為同步 XHR
            var payload = JSON.stringify([{
                index: 0,
                methodname: 'mod_videoprogress_save_progress',
                args: data
            }]);
            var url = M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey;

            if (navigator.sendBeacon) {
                // sendBeacon 會在背景完成請求，即使頁面已關閉
                var blob = new Blob([payload], { type: 'application/json' });
                navigator.sendBeacon(url, blob);
            } else {
                // 降級為同步 XHR
                try {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', url, false);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    xhr.send(payload);
                } catch (e) {
                    // 忽略錯誤（頁面正在關閉）
                }
            }
        } else {
            // 正常使用 AJAX
            Ajax.call([{
                methodname: 'mod_videoprogress_save_progress',
                args: data,
                done: function (response) {
                    if (response.percentcomplete !== undefined) {
                        updateProgressUI(response.percentcomplete, response.completed);
                    }
                    segmentStart = currentEnd;
                },
                fail: function (error) {
                    console.error('Save failed:', error);
                }
            }]);
        }
    }

    /**
     * 儲存進度（指定結束時間）- 用於影片結束時強制 100%
     */
    function saveWithEnd(endTime, forceComplete) {
        if (!config) return;

        if (forceComplete === undefined) {
            forceComplete = true;
        }

        // 添加最後區段
        addSegment(segmentStart, endTime);

        var data = {
            cmid: config.cmid,
            segmentstart: segmentStart,
            segmentend: endTime,
            currentposition: endTime,
            videoduration: config.duration
        };

        Ajax.call([{
            methodname: 'mod_videoprogress_save_progress',
            args: data,
            done: function (response) {
                if (forceComplete) {
                    updateProgressUI(100, true);
                } else {
                    updateProgressUI(response.percentcomplete, response.completed);
                }
            },
            fail: function (error) {
                console.error('Save failed:', error);
            }
        }]);
    }

    /**
     * 更新進度 UI（完整版）
     */
    function updateProgressUI(percent, completed) {
        // 方法 1: 使用 ID
        var bar = document.getElementById('videoprogress-progressbar');
        if (bar) {
            bar.style.width = percent + '%';
            bar.textContent = percent + '%';
            bar.setAttribute('aria-valuenow', percent);
        }

        // 方法 2: 使用 jQuery 選擇器（兼容原版）
        var $progressBar = $('.videoprogress-status .progress .progress-bar');
        if ($progressBar.length) {
            $progressBar.css('width', percent + '%').text(percent + '%');
        }

        // 處理完成狀態
        var isComplete = completed || (percent >= (config.completionpercent || 80));

        if (isComplete) {
            // 進度條變綠
            if (bar) {
                bar.className = bar.className.replace('bg-primary', 'bg-success');
            }
            $progressBar.removeClass('bg-primary').addClass('bg-success');

            // Badge 更新
            var completedText = '已完成';
            if (M.str && M.str.mod_videoprogress && M.str.mod_videoprogress.completed) {
                completedText = M.str.mod_videoprogress.completed;
            }

            // 方法 1: ID
            var badge = document.getElementById('videoprogress-complete-badge');
            if (badge) {
                badge.style.display = 'inline';
                badge.textContent = completedText;
            }

            // 方法 2: jQuery（兼容原版）
            $('.videoprogress-status .badge')
                .removeClass('badge-secondary bg-secondary')
                .addClass('badge-success bg-success')
                .text(completedText);
        }
    }

    /**
     * 設定最後位置
     */
    function setLastPosition(position) {
        lastPosition = position;
    }

    /**
     * 設定區段起點
     */
    function setSegmentStart(start) {
        segmentStart = start;
    }

    /**
     * 動態更新或創建「繼續觀看」按鈕
     */
    function updateResumeButton(position) {
        var btn = document.getElementById('videoprogress-resume-btn');
        var prompt = document.getElementById('videoprogress-resume-prompt');

        // 格式化時間 (HH:MM:SS)
        var hours = Math.floor(position / 3600);
        var mins = Math.floor((position % 3600) / 60);
        var secs = Math.floor(position % 60);

        var timeStr = '';
        if (hours > 0) {
            timeStr += (hours < 10 ? '0' : '') + hours + ':';
        }
        timeStr += (mins < 10 ? '0' : '') + mins + ':';
        timeStr += (secs < 10 ? '0' : '') + secs;

        if (btn) {
            if (position > 5) {
                // 更新按鈕數據與顯示
                btn.setAttribute('data-position', position);
                btn.innerHTML = '從 ' + timeStr + ' 繼續觀看';
                if (prompt) prompt.style.display = 'block';
            } else {
                // 進度太短，不顯示
                if (prompt) prompt.style.display = 'none';
            }
        } else if (position > 5) {
            // Fallback: 如果 DOM 中找不到按鈕（理論上不應發生），才動態創建
            var player = document.querySelector('.videoprogress-player');
            if (player) {
                var newPrompt = document.createElement('div');
                newPrompt.id = 'videoprogress-resume-prompt';
                newPrompt.className = 'alert alert-info mt-3';
                newPrompt.innerHTML = '<button id="videoprogress-resume-btn" class="btn btn-primary" data-position="' + position + '">' +
                    '從 ' + timeStr + ' 繼續觀看</button>';
                player.parentNode.insertBefore(newPrompt, player.nextSibling);

                // 綁定事件
                document.getElementById('videoprogress-resume-btn').addEventListener('click', function () {
                    var pos = parseFloat(this.getAttribute('data-position')) || 0;
                    var video = document.getElementById('videoprogress-html5-player');
                    if (video) {
                        video.currentTime = pos;
                        video.play();
                    }
                    newPrompt.style.display = 'none';
                });
            }
        }
    }

    return {
        init: init,
        update: update,
        saveNow: saveNow,
        saveWithEnd: saveWithEnd,
        setLastPosition: setLastPosition,
        setSegmentStart: setSegmentStart
    };
});
