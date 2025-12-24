/**
 * 跳轉到 Moodle 並自動登入（使用 SSO）
 * 如果是選課連結，會先清除快取
 */
function goToMoodle(targetUrl) {
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
 * 透過 SSO 跳轉到 Moodle
 */
function redirectWithSSO(targetUrl) {
    console.log('SSO: Fetching URL for', targetUrl);
    fetch('get_sso_url.php?url=' + encodeURIComponent(targetUrl))
        .then(function (response) {
            console.log('SSO: Response status', response.status);
            return response.json();
        })
        .then(function (data) {
            console.log('SSO: Data received', data);
            if (data.success && data.sso_url) {
                window.location.href = data.sso_url;
            } else {
                console.error('SSO Failed:', data);
                window.location.href = targetUrl;
            }
        })
        .catch(function (error) {
            console.error('SSO Error:', error);
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
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        if (btnElement) btnElement.classList.add('active');
    }
    var searchInput = document.getElementById('courseSearchInput').value.toLowerCase();
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
 * 頁面載入初始化
 */
window.addEventListener('load', function () {
    // 處理 URL 參數中的 tab
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab) {
        showTab(tab);
    } else {
        // 預設顯示首頁，並高亮對應標籤
        updateNavHighlight('showHome');
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
