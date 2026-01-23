/**
 * Cloud Sea Background - 雲海動態背景
 * 自動建立雲海HTML結構並添加動態效果
 */

class CloudSeaBackground {
    constructor(options = {}) {
        this.options = {
            container: options.container || document.body,
            autoInit: options.autoInit !== false,
            enableParticles: options.enableParticles !== false,
            particleCount: options.particleCount || 30,
            enableMouseInteraction: options.enableMouseInteraction !== false
        };

        if (this.options.autoInit) {
            this.init();
        }
    }

    init() {
        this.createStructure();

        if (this.options.enableParticles) {
            this.createParticles();
        }

        if (this.options.enableMouseInteraction) {
            this.setupMouseInteraction();
        }
    }

    createStructure() {
        // 建立主容器
        const cloudSeaContainer = document.createElement('div');
        cloudSeaContainer.className = 'cloud-sea-container';
        cloudSeaContainer.id = 'cloud-sea-bg';

        // 太陽光暈
        const sunGlow = document.createElement('div');
        sunGlow.className = 'sun-glow';
        cloudSeaContainer.appendChild(sunGlow);

        // 浮動雲朵
        for (let i = 0; i < 3; i++) {
            const floatingCloud = document.createElement('div');
            floatingCloud.className = 'floating-cloud';
            cloudSeaContainer.appendChild(floatingCloud);
        }

        // 霧氣過渡層
        const mistGradient = document.createElement('div');
        mistGradient.className = 'mist-gradient';
        cloudSeaContainer.appendChild(mistGradient);

        // 雲海包裝器
        const cloudSeaWrapper = document.createElement('div');
        cloudSeaWrapper.className = 'cloud-sea-wrapper';

        // 建立多層雲
        for (let i = 1; i <= 4; i++) {
            const cloudLayer = document.createElement('div');
            cloudLayer.className = `cloud-layer cloud-layer-${i}`;
            cloudSeaWrapper.appendChild(cloudLayer);
        }

        // 波浪覆蓋層
        const waveOverlay = document.createElement('div');
        waveOverlay.className = 'wave-overlay';
        cloudSeaWrapper.appendChild(waveOverlay);

        cloudSeaContainer.appendChild(cloudSeaWrapper);

        // 粒子容器
        if (this.options.enableParticles) {
            const particleContainer = document.createElement('div');
            particleContainer.className = 'cloud-particles';
            particleContainer.id = 'cloud-particles';
            cloudSeaContainer.appendChild(particleContainer);
        }

        // 插入到頁面
        if (this.options.container === document.body) {
            document.body.insertBefore(cloudSeaContainer, document.body.firstChild);
        } else {
            this.options.container.insertBefore(cloudSeaContainer, this.options.container.firstChild);
        }

        this.container = cloudSeaContainer;
        this.wrapper = cloudSeaWrapper;
    }

    createParticles() {
        const particleContainer = document.getElementById('cloud-particles');
        if (!particleContainer) return;

        // 添加粒子樣式
        const style = document.createElement('style');
        style.textContent = `
            .cloud-particles {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
                z-index: 8;
            }

            .cloud-particle {
                position: absolute;
                background: radial-gradient(circle, 
                    rgba(255, 255, 255, 0.8) 0%, 
                    rgba(255, 255, 255, 0.4) 50%, 
                    transparent 100%
                );
                border-radius: 50%;
                animation: particleFloat linear infinite;
            }

            @keyframes particleFloat {
                0% {
                    transform: translateY(100vh) translateX(0) scale(0);
                    opacity: 0;
                }
                10% {
                    opacity: 1;
                    transform: translateY(90vh) translateX(10px) scale(1);
                }
                90% {
                    opacity: 0.8;
                }
                100% {
                    transform: translateY(-20vh) translateX(-10px) scale(0.5);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // 建立粒子
        for (let i = 0; i < this.options.particleCount; i++) {
            this.createParticle(particleContainer, i);
        }
    }

    createParticle(container, index) {
        const particle = document.createElement('div');
        particle.className = 'cloud-particle';

        const size = Math.random() * 15 + 5;
        const left = Math.random() * 100;
        const duration = Math.random() * 15 + 20;
        const delay = Math.random() * duration;

        particle.style.cssText = `
            width: ${size}px;
            height: ${size}px;
            left: ${left}%;
            animation-duration: ${duration}s;
            animation-delay: -${delay}s;
        `;

        container.appendChild(particle);
    }

    setupMouseInteraction() {
        let lastX = 0;
        let lastY = 0;

        document.addEventListener('mousemove', (e) => {
            const deltaX = (e.clientX - window.innerWidth / 2) / window.innerWidth;
            const deltaY = (e.clientY - window.innerHeight / 2) / window.innerHeight;

            if (this.wrapper) {
                this.wrapper.style.transform = `
                    rotateX(${45 + deltaY * 5}deg) 
                    rotateZ(${deltaX * 2}deg)
                `;
            }
        });

        // 重置效果
        document.addEventListener('mouseleave', () => {
            if (this.wrapper) {
                this.wrapper.style.transform = 'rotateX(45deg)';
            }
        });
    }

    // 動態調整天空顏色（可選功能）
    setTimeOfDay(time) {
        const colors = {
            dawn: {
                gradient: 'linear-gradient(180deg, #1a1a3e 0%, #4a3f6b 25%, #8b6b8a 50%, #d4a5a5 75%, #f5d5c8 100%)'
            },
            day: {
                gradient: 'linear-gradient(180deg, #0a4a8c 0%, #1a7fd4 25%, #4da6e8 50%, #87ceeb 75%, #b8e0f7 100%)'
            },
            sunset: {
                gradient: 'linear-gradient(180deg, #1a1a3e 0%, #6b3a5b 25%, #c76b4a 50%, #f4a460 75%, #ffd89b 100%)'
            },
            night: {
                gradient: 'linear-gradient(180deg, #0a0a1a 0%, #1a1a3e 25%, #2a2a5e 50%, #3a3a7e 75%, #4a4a9e 100%)'
            }
        };

        if (colors[time] && this.container) {
            this.container.style.background = colors[time].gradient;
        }
    }

    // 銷毀實例
    destroy() {
        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }
    }
}

// 自動初始化（在DOM載入後）
document.addEventListener('DOMContentLoaded', function () {
    // 檢查是否需要自動初始化
    if (document.querySelector('[data-cloud-sea-auto]')) {
        window.cloudSeaBg = new CloudSeaBackground();
    }
});

// 導出給模組使用
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CloudSeaBackground;
}
