/**
 * 超高效能粒子吸引系統
 * 使用 Canvas + RAF + 物理引擎
 * 替換 CSS 背景粒子，實現游標吸引效果
 */

class ParticleAttraction {
    constructor(options = {}) {
        this.config = {
            particleCount: options.particleCount || 60,
            minSize: 1.2,
            maxSize: 2.5,
            attractionRadius: 200,
            attractionForce: 0.12,
            friction: 0.93,
            returnForce: 0.03,
            colors: ['#2563eb', '#06b6d4', '#6366f1', '#3b82f6', '#22d3ee'],
            opacityRange: [0.15, 0.3]
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
            const baseX = Math.random() * window.innerWidth;
            const baseY = Math.random() * window.innerHeight;

            // 浮動屬性
            const floatSpeed = 0.15 + Math.random() * 0.25;
            const floatAngle = Math.random() * Math.PI * 2;
            const floatRange = 15 + Math.random() * 20;

            // 計算初始浮動偏移，讓粒子一開始就在浮動軌跡上
            const initialFloatX = Math.cos(floatAngle) * floatRange;
            const initialFloatY = Math.sin(floatAngle * 0.7) * floatRange * 0.6;

            this.particles.push({
                x: baseX + initialFloatX,  // 初始位置在浮動軌跡上
                y: baseY + initialFloatY,
                baseX: baseX,
                baseY: baseY,
                vx: 0,
                vy: 0,
                size: Math.random() * (maxSize - minSize) + minSize,
                color: colors[Math.floor(Math.random() * colors.length)],
                opacity: Math.random() * (opacityRange[1] - opacityRange[0]) + opacityRange[0],
                floatSpeed: floatSpeed,
                floatAngle: floatAngle,
                floatRange: floatRange
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
        // 持續更新浮動角度（不論是否被吸引）
        particle.floatAngle += particle.floatSpeed * 0.01;
        const floatX = Math.cos(particle.floatAngle) * particle.floatRange;
        const floatY = Math.sin(particle.floatAngle * 0.7) * particle.floatRange * 0.6;
        const targetX = particle.baseX + floatX;
        const targetY = particle.baseY + floatY;

        const dx = this.mouse.x - particle.x;
        const dy = this.mouse.y - particle.y;
        const distance = Math.sqrt(dx * dx + dy * dy);

        if (this.mouse.active && distance < this.config.attractionRadius) {
            // 吸引力（越近越強）
            const force = (1 - distance / this.config.attractionRadius) * this.config.attractionForce;
            const angle = Math.atan2(dy, dx);

            particle.vx += Math.cos(angle) * force;
            particle.vy += Math.sin(angle) * force;
        } else {
            // 柔和回歸：用小力量慢慢拉回，不會彈射
            const returnDx = targetX - particle.x;
            const returnDy = targetY - particle.y;

            particle.vx += returnDx * 0.015;
            particle.vy += returnDy * 0.015;
        }

        // 應用摩擦力（減速，防止過衝彈射）
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

// 自動初始化（desktop 端啟用）
document.addEventListener('DOMContentLoaded', () => {
    // 檢查是否為移動裝置
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

    if (!isMobile) {
        // 延遲啟動確保頁面載入完成
        setTimeout(() => {
            window.particleAttraction = new ParticleAttraction({
                particleCount: 60,
                attractionRadius: 200,
                attractionForce: 0.12,
                friction: 0.93,
                returnForce: 0.03
            });

            // 隱藏原本的 CSS 背景粒子（避免與 Canvas 粒子重疊）
            const style = document.createElement('style');
            style.textContent = '.landing-page::before, body::after { display: none !important; }';
            document.head.appendChild(style);
        }, 200);
    }
});
