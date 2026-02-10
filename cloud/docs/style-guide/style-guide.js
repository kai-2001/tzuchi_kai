/**
 * Style Guide 互動功能
 */

// Tab 切換
function switchDemoTab(btn, tabId) {
    // 清除所有 active
    btn.parentElement.querySelectorAll('.tab-nav-v2__item').forEach(t => t.classList.remove('is-active'));
    document.querySelectorAll('.tab-content-v2').forEach(c => c.classList.remove('is-active'));

    // 設定 active
    btn.classList.add('is-active');
    document.getElementById(tabId).classList.add('is-active');
}

// Toast 示範
function showDemoToast(message, type = 'success') {
    const existing = document.querySelector('.toast-v2');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast-v2 toast-v2--${type}`;

    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle'
    };

    toast.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i> ${message}`;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideInRight 0.3s reverse forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// 平滑捲動 anchor links
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        e.preventDefault();
        const target = document.querySelector(a.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});


/**
 * 容器版粒子吸引系統（展示用）
 */
(function initParticleDemo() {
    const container = document.getElementById('particle-demo-container');
    const canvas = document.getElementById('particle-demo-canvas');
    if (!container || !canvas) return;

    const ctx = canvas.getContext('2d', { alpha: true });
    const config = {
        particleCount: 50,
        minSize: 1.2,
        maxSize: 2.5,
        attractionRadius: 160,
        attractionForce: 0.12,
        friction: 0.93,
        colors: ['#2563eb', '#06b6d4', '#6366f1', '#3b82f6', '#22d3ee'],
        opacityRange: [0.25, 0.5]
    };

    let particles = [];
    let mouse = { x: -1000, y: -1000, active: false };
    let animId = null;
    let lastTime = 0;
    const frameInterval = 1000 / 60;

    function resize() {
        const rect = container.getBoundingClientRect();
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        canvas.style.width = rect.width + 'px';
        canvas.style.height = rect.height + 'px';
        ctx.scale(dpr, dpr);
    }

    function createParticles() {
        particles = [];
        const rect = container.getBoundingClientRect();
        for (let i = 0; i < config.particleCount; i++) {
            const baseX = Math.random() * rect.width;
            const baseY = Math.random() * rect.height;
            const floatSpeed = 0.15 + Math.random() * 0.25;
            const floatAngle = Math.random() * Math.PI * 2;
            const floatRange = 12 + Math.random() * 18;
            const fx = Math.cos(floatAngle) * floatRange;
            const fy = Math.sin(floatAngle * 0.7) * floatRange * 0.6;

            particles.push({
                x: baseX + fx, y: baseY + fy,
                baseX, baseY, vx: 0, vy: 0,
                size: Math.random() * (config.maxSize - config.minSize) + config.minSize,
                color: config.colors[Math.floor(Math.random() * config.colors.length)],
                opacity: Math.random() * (config.opacityRange[1] - config.opacityRange[0]) + config.opacityRange[0],
                floatSpeed, floatAngle, floatRange
            });
        }
    }

    function update(p) {
        p.floatAngle += p.floatSpeed * 0.01;
        const tx = p.baseX + Math.cos(p.floatAngle) * p.floatRange;
        const ty = p.baseY + Math.sin(p.floatAngle * 0.7) * p.floatRange * 0.6;

        const dx = mouse.x - p.x;
        const dy = mouse.y - p.y;
        const dist = Math.sqrt(dx * dx + dy * dy);

        if (mouse.active && dist < config.attractionRadius) {
            const force = (1 - dist / config.attractionRadius) * config.attractionForce;
            const angle = Math.atan2(dy, dx);
            p.vx += Math.cos(angle) * force;
            p.vy += Math.sin(angle) * force;
        } else {
            p.vx += (tx - p.x) * 0.015;
            p.vy += (ty - p.y) * 0.015;
        }

        p.vx *= config.friction;
        p.vy *= config.friction;
        p.x += p.vx;
        p.y += p.vy;
    }

    function animate(t) {
        animId = requestAnimationFrame(animate);
        const elapsed = t - lastTime;
        if (elapsed < frameInterval) return;
        lastTime = t - (elapsed % frameInterval);

        const rect = container.getBoundingClientRect();
        ctx.clearRect(0, 0, rect.width, rect.height);

        for (const p of particles) {
            update(p);
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
            ctx.fillStyle = p.color;
            ctx.globalAlpha = p.opacity;
            ctx.fill();
        }
    }

    container.addEventListener('mousemove', e => {
        const rect = container.getBoundingClientRect();
        mouse.x = e.clientX - rect.left;
        mouse.y = e.clientY - rect.top;
        mouse.active = true;
    });
    container.addEventListener('mouseleave', () => { mouse.active = false; });
    window.addEventListener('resize', () => { resize(); });

    resize();
    createParticles();
    requestAnimationFrame(animate);
})();
