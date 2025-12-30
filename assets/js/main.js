/**
 * 跳轉到 Moodle 並自動登入（使用 SSO）
 * 如果是選課連結，會先清除快取
 */
// 全域過渡動畫旗標
let isRedirecting = false;

function goToMoodle(targetUrl) {
    if (isRedirecting) return;

    // 顯示全域讀取動畫
    showGlobalLoading('正在前往課程...');

    // 如果是選課頁面，先清除快取
    if (targetUrl.includes('/enrol/') || targetUrl.includes('/course/view.php')) {
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
                <img src="assets/img/tenor.gif" alt="Loading..." style="width: 120px; height: auto; margin-bottom: 20px;">
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
 * 透過 SSO 跳轉到 Moodle
 */
function redirectWithSSO(targetUrl) {
    console.log('SSO: Fetching URL for', targetUrl);
    fetch('get_sso_url.php?url=' + encodeURIComponent(targetUrl))
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

/**
 * 顯示首頁
 */
function showHome() {
    // 隱藏所有 page-section
    document.querySelectorAll('.page-section').forEach(function (section) {
        section.classList.remove('active');
    });

    var home = document.getElementById('section-home');
    var feats = document.getElementById('section-features');
    if (home) home.classList.add('active');
    if (feats) feats.classList.remove('active');

    // 儲存目前狀態為首頁
    sessionStorage.setItem('activeTab', 'showHome');

    // 更新導覽列高亮
    updateNavHighlight('showHome');
}

/**
 * 顯示指定的 Tab
 */
function showTab(tabId) {
    // 隱藏所有 page-section
    document.querySelectorAll('.page-section').forEach(function (section) {
        section.classList.remove('active');
    });

    // 嘗試顯示對應的 section (教師介面)
    var targetSection = document.getElementById('section-' + tabId);
    if (targetSection) {
        targetSection.classList.add('active');
    }

    // 學生介面：處理 tab-pane
    var home = document.getElementById('section-home');
    var feats = document.getElementById('section-features');

    // 如果不是教師專用 section，則使用學生介面邏輯
    if (!targetSection && feats) {
        if (home) home.classList.remove('active');
        feats.classList.add('active');

        document.querySelectorAll('.tab-pane').forEach(function (tab) {
            tab.classList.remove('show', 'active');
        });
        var target = document.getElementById(tabId);
        if (target) target.classList.add('show', 'active');
    }

    // 儲存目前頁籤到 Session，以便重新整理時預設開啟
    sessionStorage.setItem('activeTab', tabId);

    // 更新導覽列高亮
    updateNavHighlight(tabId);
}

/**
 * 更新導覽列高亮狀態
 */
function updateNavHighlight(targetId) {
    // 移除所有高亮
    document.querySelectorAll('.pg-link').forEach(function (link) {
        link.classList.remove('pg-link-active');
    });

    // 根據目標 ID 查找對應連結，但排除 .pg-brand (Logo)
    var selector = '';
    if (targetId === 'showHome') {
        // 專門找「個人主頁」的連結 (具有 .pg-link 且 onclick 包含 showHome)
        selector = '.pg-link[onclick*="showHome"]';
    } else {
        selector = '.pg-link[onclick*="' + targetId + '"]';
    }

    var activeLink = document.querySelector(selector);
    if (activeLink) {
        activeLink.classList.add('pg-link-active');
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
 * 頁面載入初始化
 */
window.addEventListener('load', function () {
    // 1. 處理 URL 參數中的 tab (優先級最高，從 Moodle 跳過來時使用)
    const urlParams = new URLSearchParams(window.location.search);
    const urlTab = urlParams.get('tab');

    // 2. 處理 Session 中的 tab (重新整理時使用)
    const sessionTab = sessionStorage.getItem('activeTab');

    if (urlTab) {
        if (urlTab === 'showHome') {
            showHome();
        } else {
            showTab(urlTab);
        }
        // 優化：處理完 URL 參數後立刻將其從網址列抹除，避免重新整理時重複觸發
        const newUrl = window.location.pathname + window.location.hash;
        window.history.replaceState({}, document.title, newUrl);
    } else if (sessionTab) {
        if (sessionTab === 'showHome') {
            showHome();
        } else {
            showTab(sessionTab);
        }
    } else {
        // 預設顯示首頁
        showHome();
    }

    // 啟用 Bootstrap Tooltip
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

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
                // 動畫完成後取消觀察（可選）
                // observer.unobserve(entry.target);
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
