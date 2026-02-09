/**
 * 超高效能粒子吸引系統
 * 使用 Canvas + RAF + 物理引擎
 * 替換 CSS 背景粒子，實現游標吸引效果
 */

class ParticleAttraction {
    constructor(options = {}) {
        this.config = {
            particleCount: options.particleCount || 60,
            minSize: 1.5,
            maxSize: 3,
            attractionRadius: 200,
            attractionForce: 0.12,
            friction: 0.93,
            returnForce: 0.03,
            colors: ['#2563eb', '#06b6d4', '#6366f1', '#3b82f6', '#22d3ee'],
            opacityRange: [0.25, 0.45]
        };

        this.canvas = null;
        this.ctx = null;
        this.particles = [];
        this.mouse = { x: -1000, y: -1000, active: false };
        this.animationId = null;
        this.lastTime = 0;
        this.fps = 60;
        this.frameInterval = 1000 / this.fps;

        this.init();
    }

    init() {
        this.createCanvas();
        this.createParticles();
        this.bindEvents();
        this.animate(0);
    }

    createCanvas() {
        this.canvas = document.createElement('canvas');
        this.canvas.id = 'particle-attraction-canvas';
        this.canvas.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        `;
        document.body.appendChild(this.canvas);

        this.ctx = this.canvas.getContext('2d', {
            alpha: true,
            desynchronized: true // 啟用低延遲渲染
        });

        this.resize();
    }

    resize() {
        const dpr = Math.min(window.devicePixelRatio || 1, 2); // 限制最大 DPR
        const width = window.innerWidth;
        const height = window.innerHeight;

        this.canvas.width = width * dpr;
        this.canvas.height = height * dpr;
        this.canvas.style.width = width + 'px';
        this.canvas.style.height = height + 'px';
        this.ctx.scale(dpr, dpr);
    }

    createParticles() {
        this.particles = [];
        const { particleCount, minSize, maxSize, colors, opacityRange } = this.config;

        for (let i = 0; i < particleCount; i++) {
            const x = Math.random() * window.innerWidth;
            const y = Math.random() * window.innerHeight;

            this.particles.push({
                x: x,
                y: y,
                baseX: x,
                baseY: y,
                vx: 0,
                vy: 0,
                size: Math.random() * (maxSize - minSize) + minSize,
                color: colors[Math.floor(Math.random() * colors.length)],
                opacity: Math.random() * (opacityRange[1] - opacityRange[0]) + opacityRange[0]
            });
        }
    }

    bindEvents() {
        // 使用 passive 事件監聽提升性能
        let ticking = false;

        const handleMouseMove = (e) => {
            if (!ticking) {
                this.mouse.x = e.clientX;
                this.mouse.y = e.clientY;
                this.mouse.active = true;
                ticking = true;

                requestAnimationFrame(() => {
                    ticking = false;
                });
            }
        };

        window.addEventListener('mousemove', handleMouseMove, { passive: true });

        window.addEventListener('mouseleave', () => {
            this.mouse.active = false;
        });

        window.addEventListener('resize', () => {
            this.resize();
            // 不重新創建粒子，只調整畫布尺寸
        });
    }

    updateParticle(particle) {
        const dx = this.mouse.x - particle.x;
        const dy = this.mouse.y - particle.y;
        const distance = Math.sqrt(dx * dx + dy * dy);

        // 只對範圍內的粒子應用吸引力
        if (this.mouse.active && distance < this.config.attractionRadius) {
            const force = (1 - distance / this.config.attractionRadius) * this.config.attractionForce;
            const angle = Math.atan2(dy, dx);

            particle.vx += Math.cos(angle) * force;
            particle.vy += Math.sin(angle) * force;
        } else {
            // 回到原始位置
            const returnDx = particle.baseX - particle.x;
            const returnDy = particle.baseY - particle.y;

            particle.vx += returnDx * this.config.returnForce;
            particle.vy += returnDy * this.config.returnForce;
        }

        // 應用摩擦力
        particle.vx *= this.config.friction;
        particle.vy *= this.config.friction;

        // 更新位置
        particle.x += particle.vx;
        particle.y += particle.vy;
    }

    drawParticle(particle) {
        this.ctx.beginPath();
        this.ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
        this.ctx.fillStyle = particle.color;
        this.ctx.globalAlpha = particle.opacity;
        this.ctx.fill();
    }

    animate(currentTime) {
        this.animationId = requestAnimationFrame((time) => this.animate(time));

        // FPS 控制（確保穩定 60 FPS）
        const elapsed = currentTime - this.lastTime;
        if (elapsed < this.frameInterval) return;

        this.lastTime = currentTime - (elapsed % this.frameInterval);

        // 清空畫布
        this.ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);

        // 批次更新和繪製所有粒子
        for (let i = 0; i < this.particles.length; i++) {
            this.updateParticle(this.particles[i]);
            this.drawParticle(this.particles[i]);
        }
    }

    destroy() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }
        if (this.canvas && this.canvas.parentNode) {
            this.canvas.parentNode.removeChild(this.canvas);
        }
    }
}

// 自動初始化（僅在 desktop 且 landing page）
document.addEventListener('DOMContentLoaded', () => {
    // 檢查是否為移動裝置
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    const isLandingPage = document.body.classList.contains('landing-page');

    if (!isMobile && isLandingPage) {
        // 延遲啟動確保頁面載入完成
        setTimeout(() => {
            window.particleAttraction = new ParticleAttraction({
                particleCount: 60,
                attractionRadius: 200,
                attractionForce: 0.12,
                friction: 0.93,
                returnForce: 0.03
            });

            // 隱藏原本的 CSS 背景粒子
            const style = document.createElement('style');
            style.textContent = '.landing-page::before { display: none !important; }';
            document.head.appendChild(style);
        }, 200);
    }
});
