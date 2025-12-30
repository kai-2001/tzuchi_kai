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
 * Confirm delete action
 */
function confirmDelete(id, type) {
    type = type || 'video';
    const message = '確定要刪除這部影片嗎？此動作無法復原。';
    if (confirm(message)) {
        window.location.href = 'delete_video.php?id=' + id;
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
});

function initSwipers() {
    if (typeof Swiper === 'undefined') {
        console.warn("Swiper not loaded yet, retrying...");
        setTimeout(initSwipers, 500);
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
            },
            on: {
                init: function () {
                    console.log('Swiper initialized');
                }
            }
        });
    }
}

function initHeaderScroll() {
    const header = document.querySelector('header');
    if (!header || header.classList.contains('static-header')) return;

    const handleScroll = () => {
        if (window.scrollY > 50) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    };

    window.addEventListener('scroll', handleScroll);
    handleScroll();
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
