/**
 * Speech Portal - Main JavaScript
 * Optimized for Premium UI & Robust Carousel
 */

// Global login state
window.isLoggedIn = window.isLoggedIn || false;

/**
 * Check authentication before accessing a video
 */
function checkAuth(videoId) {
    if (window.isLoggedIn) {
        window.location.href = 'watch.php?id=' + videoId;
    } else {
        const modal = document.getElementById('loginModal');
        if (modal) modal.style.display = 'flex';
    }
}

/**
 * Close the login modal
 */
function closeModal() {
    const modal = document.getElementById('loginModal');
    if (modal) modal.style.display = 'none';
}

/**
 * Common confirmation dialogs for object management
 */
function confirmDelete(id) {
    if (confirm('確定要刪除這部影片嗎？此操作無法復原。')) {
        window.location.href = 'delete_video.php?id=' + id;
    }
}

function confirmDeleteAnnouncement(id) {
    if (confirm('確定要刪除這個公告嗎？')) {
        window.location.href = 'manage_announcements.php?action=delete&id=' + id;
    }
}

/**
 * Auto-refresh logic (Moved from templates/manage.php)
 * Refreshes the page if there are active background jobs (Pending/Processing)
 */
function initAutoRefresh() {
    const activeBadges = document.querySelectorAll('.badge.bg-warning, .badge.bg-info');
    if (activeBadges.length > 0) {
        console.log("Active jobs detected, starting auto-refresh interval...");
        setInterval(() => {
            // Prevent refresh if user is interacting with an input
            if (document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                window.location.reload();
            } else {
                console.log("User interaction detected, skipping refresh this cycle.");
            }
        }, 12000); // Check every 12 seconds
    }
}

/**
 * Enhanced Form Validation (Inspired by Zhenyang project)
 */
function checkForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;

    const requiredFields = form.querySelectorAll('[required]');
    for (let field of requiredFields) {
        if (!field.value.trim()) {
            const label = form.querySelector(`label[for="${field.id}"]`) || { innerText: field.name || '此欄位' };
            alert(`${label.innerText.replace(':', '')} 為必填項目`);
            field.focus();
            return false;
        }
    }
    return true;
}

/**
 * Centralized API Post Wrapper (JSON)
 */
async function apiPost(url, data) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        const result = await response.json();

        if (result.status === 'error') {
            alert(result.msg || '發生錯誤');
            return null;
        }
        return result;
    } catch (error) {
        console.error('API Error:', error);
        alert('網路連線失敗，請稍後再試。');
        return null;
    }
}

// ============================================
// Initialization Logic
// ============================================
window.addEventListener('load', () => {
    // 啟用 Bootstrap Tooltip
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map((el) => new bootstrap.Tooltip(el));
    }

    // 初始化輪播
    initSwipers();

    // 初始化導覽列
    initHeaderScroll();

    // 初始化滾動動畫
    initScrollAnimations();

    // 初始化自動重新整理
    initAutoRefresh();
});

function initSwipers() {
    if (typeof Swiper === 'undefined') {
        return;
    }

    const heroSwiperSelector = '.hero-swiper';
    if (document.querySelector(heroSwiperSelector)) {
        new Swiper(heroSwiperSelector, {
            loop: true,
            autoplay: {
                delay: 6000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
                dynamicBullets: true
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            }
        });
    }
}

function initHeaderScroll() {
    const header = document.querySelector('header');
    if (!header || header.classList.contains('static-header')) return;

    let ticking = false;

    const onScroll = () => {
        if (window.scrollY > 50) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
        ticking = false;
    };

    const handleScroll = () => {
        if (!ticking) {
            window.requestAnimationFrame(onScroll);
            ticking = true;
        }
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    // Initial check
    onScroll();
}

function initScrollAnimations() {
    const animatedElements = document.querySelectorAll('.scroll-animate');
    if (animatedElements.length === 0) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    animatedElements.forEach((el) => observer.observe(el));
}
