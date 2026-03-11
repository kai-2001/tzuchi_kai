/**
 * student-main.js 
 * Contains generic JavaScript utilities used by the new student interface.
 * Excludes all old dashboard tab-switching and URL rewriting logic.
 */

// 全域過渡動畫旗標
let isRedirecting = false;

function goToMoodle(targetUrl) {
    if (isRedirecting) return;

    // 顯示全域讀取動畫
    showGlobalLoading('正在前往課程...');

    // 設定 Dirty Flag Cookie，告訴入口網：使用者去了 Moodle，回來時必須強制重新整理快取
    document.cookie = "moodle_dirty=1; path=/; max-age=3600";

    // 如果是課程頁面，同步確保課程 visible=1（不等待，與 SSO 跳轉並行）
    var courseMatch = targetUrl.match(/course\/view\.php\?id=(\d+)/);
    if (courseMatch) {
        // Fire-and-forget: 利用 SSO 跳轉的時間差讓 API 完成修正
        fetch('api/student/enter_course.php?id=' + courseMatch[1], { credentials: 'same-origin' }).catch(function () { });
    }

    // 正常流程：清快取 → SSO 跳轉
    if (targetUrl.includes('/enrol/') || courseMatch) {
        fetch('index.php?clear_cache=1', { method: 'GET' })
            .finally(function () {
                redirectWithSSO(targetUrl);
            });
    } else {
        redirectWithSSO(targetUrl);
    }
}

/**
 * 顯示全域讀取動畫 (SSO 跳轉用)
 */
function showGlobalLoading(text) {
    let loader = document.getElementById('global-nav-loader');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'global-nav-loader';
        loader.className = 'global-nav-loader-overlay';
        loader.innerHTML = `
            <div class="loader-content">
                <img src="assets/img/Image_1768032378449.gif" alt="Loading..." style="width: 120px; height: auto; margin-bottom: 20px;">
                <div class="loader-text">${text || '正在處理中...'}</div>
            </div>
        `;
        document.body.appendChild(loader);

        // 動態加入樣式
        const style = document.createElement('style');
        style.textContent = `
            .global-nav-loader-overlay {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(15px);
                z-index: 9999;
                display: flex;
                justify-content: center;
                align-items: center;
                opacity: 0;
                transition: opacity 0.4s ease;
                pointer-events: all;
            }
            .global-nav-loader-overlay.show { opacity: 1; }
            .loader-content { text-align: center; }
            .loader-text { 
                font-weight: 600; 
                color: var(--primary); 
                font-size: 18px;
                letter-spacing: 1px;
            }
        `;
        document.head.appendChild(style);
    }

    setTimeout(() => loader.classList.add('show'), 10);
    isRedirecting = true;
}

/**
 * 隱藏全域讀取動畫
 */
function hideGlobalLoading() {
    isRedirecting = false;
    let loader = document.getElementById('global-nav-loader');
    if (loader) {
        loader.classList.remove('show');
        setTimeout(() => {
            if (loader.parentNode) loader.parentNode.removeChild(loader);
        }, 400);
    }
}

/**
 * 學生直接報名課程
 */
function directEnrolCourse(courseId) {
    if (isRedirecting) return;
    
    showGlobalLoading('正在加選課程...');

    const formData = new FormData();
    formData.append('course_id', courseId);

    fetch('api/student/enrol_course.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 報名成功，跳轉到課程頁面
            const moodleUrl = window.PortalConfig ? window.PortalConfig.moodleUrl : '';
            hideGlobalLoading(); // hide first to reset state before goToMoodle
            
            // 跳轉前可以存一個 cookie/sessionStorage 讓目的地顯示成功訊息 (Moodle 預設加入成功)
            // 由於直接跳轉，我們利用 URL 參數 `?enrolled=1` 或者信賴 Moodle 自己
            goToMoodle(moodleUrl + '/course/view.php?id=' + courseId + '&enrolled=1');
        } else {
            hideGlobalLoading();
            showToast(data.error || data.message || '報名失敗', 'error');
        }
    })
    .catch(error => {
        hideGlobalLoading();
        showToast('無法連線至伺服器', 'error');
        console.error('Enrol course error:', error);
    });
}

/**
 * 透過 SSO 跳轉到 Moodle
 */
function redirectWithSSO(targetUrl) {
    console.log('SSO: Fetching URL for', targetUrl);
    // Uses PortalConfig which is injected by index.php
    const webRoot = window.PortalConfig ? window.PortalConfig.webRoot : '.';
    fetch(webRoot + '/get_sso_url.php?url=' + encodeURIComponent(targetUrl))
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (data.success && data.sso_url) {
                window.location.href = data.sso_url;
            } else {
                window.location.href = targetUrl;
            }
        })
        .catch(function (error) {
            window.location.href = targetUrl;
        });
}

/**
 * 平滑滾動到指定區塊
 */
function scrollToSection(sectionId) {
    var target = document.getElementById(sectionId);
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// 搜尋過濾功能
var currentFilterType = 'all';

/**
 * 過濾課程列表
 */
function filterCourses(type, btnElement) {
    if (type) {
        currentFilterType = type;
        // 如果沒傳入按鈕元素，嘗試根據 type 在 DOM 中尋找
        if (!btnElement) {
            btnElement = document.querySelector(`.filter-btn[data-type="${type}"]`);
        }

        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        if (btnElement) btnElement.classList.add('active');
    }
    var searchEl = document.getElementById('courseSearchInput');
    var searchInput = searchEl ? searchEl.value.toLowerCase() : '';
    var items = document.querySelectorAll('.course-item');
    var visibleCount = 0;
    items.forEach(function (item) {
        var itemType = item.getAttribute('data-type');
        var itemName = item.getAttribute('data-name');
        var typeMatch = (currentFilterType === 'all') || (itemType === currentFilterType);
        var nameMatch = itemName.includes(searchInput);
        if (typeMatch && nameMatch) {
            item.style.display = 'block';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });
    var noResult = document.getElementById('no-result-msg');
    if (noResult) {
        noResult.style.display = (visibleCount === 0) ? 'block' : 'none';
    }
}

/**
 * 過濾課程（由動態按鈕使用）
 * 這是 filterCourses 的別名，用於支援動態生成的分類篩選器
 */
function filterCoursesByType(type, btn) {
    filterCourses(type, btn);
}

/**
 * 顯示 Toast 提示訊息
 */
function showToast(message, type = 'info', duration = 3000) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    // 設定顏色和圖示
    let bg = '#333';
    let icon = '';

    switch (type) {
        case 'success': bg = '#10b981'; icon = '<i class="fas fa-check-circle"></i>'; break;
        case 'error': bg = '#ef4444'; icon = '<i class="fas fa-exclamation-circle"></i>'; break;
        case 'warning': bg = '#f59e0b'; icon = '<i class="fas fa-exclamation-triangle"></i>'; break;
        default: bg = '#3b82f6'; icon = '<i class="fas fa-info-circle"></i>';
    }

    toast.className = 'toast-message';
    toast.style.cssText = `
        background: ${bg};
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 15px;
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.3s ease;
        min-width: 200px;
        max-width: 400px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    `;

    toast.innerHTML = `${icon} <span style="flex:1;">${message}</span>`;
    container.appendChild(toast);

    // Animate In
    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
    }, 10);

    // Auto Remove
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        setTimeout(() => {
            if (toast.parentNode) container.removeChild(toast);
        }, 300);
    }, duration);
}

/**
 * 頁面載入初始化
 */
window.addEventListener('load', function () {
    // 啟用 Bootstrap Tooltip (有載入 Bootstrap 才執行)
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // 滾動觸發動畫 - Intersection Observer
    initScrollAnimations();
});

/**
 * 初始化滾動觸發動畫
 */
function initScrollAnimations() {
    const animatedElements = document.querySelectorAll('.scroll-animate');

    if (animatedElements.length === 0) return;

    const observerOptions = {
        root: null,
        rootMargin: '0px 0px -50px 0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
            }
        });
    }, observerOptions);

    animatedElements.forEach(function (el) {
        observer.observe(el);
    });
}

/**
 * 為元素添加滾動動畫類別 (可由 PHP 呼叫)
 */
function addScrollAnimation(selector, animationType, delay) {
    const elements = document.querySelectorAll(selector);
    elements.forEach(function (el, index) {
        el.classList.add('scroll-animate', animationType);
        if (delay) {
            el.classList.add('delay-' + Math.min(index + 1, 4));
        }
    });
}
