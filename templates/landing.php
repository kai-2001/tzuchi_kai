<!-- templates/landing.php - 封面首頁 (immiller 風格) -->
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>雲嘉學習網 | 大林慈濟教學部</title>
    <link rel="icon" href="logo/small_logo.svg" type="image/svg+xml">
    <meta name="description" content="雲嘉學習網 - 中南部地區醫院同仁雲端學習平台，提供專業醫療課程與學習資源。">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Serif+TC:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/landing.css?v=<?php echo time(); ?>">

    <!-- Particle Attraction Effect -->
    <script src="assets/js/particle-attraction.js?v=<?php echo time(); ?>" defer></script>
</head>

<body class="landing-page">
    <!-- 導覽列 -->
    <nav class="landing-nav" id="landingNav">
        <div class="nav-container">
            <a href="index.php" class="nav-brand">
                <img src="logo/small_logo.svg" alt="雲嘉e學院" style="height: 55px; width: auto;">
            </a>

            <div class="nav-menu">
                <a href="#features" class="nav-link">平台特色</a>
                <a href="#about" class="nav-link">關於我們</a>
                <a href="#contact" class="nav-link">聯絡我們</a>
            </div>

            <div class="nav-right-group">
                <div class="nav-actions">
                    <a href="#login-section" class="btn-nav-login">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>登入</span>
                    </a>
                </div>

                <!-- 手機版漢堡選單 -->
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Hero 區塊 -->
    <section class="landing-hero">
        <div class="hero-bg">
            <div class="hero-gradient"></div>
            <div class="hero-particles"></div>
        </div>

        <div class="hero-content">
            <div class="hero-brand-wrapper" style="text-align: center; margin-bottom: 0px;">
                <img src="logo/big_logo.svg" alt="雲嘉e學院" class="scroll-animate fade-scale"
                    style="height: 300px; width: auto; padding-left: 285px;">
            </div>

            <div class="hero-buttons scroll-animate slide-up delay-2">
                <a href="#login-section" class="btn-hero btn-hero-primary">
                    <i class="fas fa-rocket"></i>
                    立即開始
                </a>
                <a href="#features" class="btn-hero btn-hero-outline">
                    <i class="fas fa-info-circle"></i>
                    了解更多
                </a>
            </div>

            <div class="hero-stats scroll-animate fade-scale delay-3">
                <div class="stat-item">
                    <span class="stat-number">多院共建</span>
                    <span class="stat-label">核心教材</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-number">100%</span>
                    <span class="stat-label">CBME 接軌</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-number">智慧化</span>
                    <span class="stat-label">歷程追蹤</span>
                </div>
            </div>
        </div>

        <div class="hero-scroll-indicator">
            <div class="scroll-mouse">
                <div class="scroll-wheel"></div>
            </div>
            <span>向下滾動</span>
        </div>
    </section>

    <!-- 特色區塊 -->
    <section class="landing-features" id="features">
        <div class="features-container">
            <div class="section-header scroll-animate slide-up">
                <span class="section-badge">平台特色</span>
                <h2 class="section-title">全方位能力導向醫療學習網絡</h2>
                <p class="section-desc">透過「三雲架構」解決資源孤島，打造區域醫療教育生態圈</p>
            </div>

            <div class="features-grid">
                <div class="feature-card scroll-animate slide-left delay-1">
                    <div class="feature-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <h3>統一教材資源庫</h3>
                    <p>集中化管理優質教學內容，實現「一次開發，多院受益」的共享資源效應</p>
                </div>

                <div class="feature-card scroll-animate slide-up delay-2">
                    <div class="feature-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>CBME 能力導向評估</h3>
                    <p>接軌國際標準，導入 EPA 評估框架，將學習紀錄與臨床能力指標無縫銜接</p>
                </div>

                <div class="feature-card scroll-animate slide-right delay-3">
                    <div class="feature-icon">
                        <i class="fas fa-network-wired"></i>
                    </div>
                    <h3>跨院合作與共享</h3>
                    <p>打破醫院圍牆限制，支援跨院團隊協作編輯教材，促進區域知識交流與發展</p>
                </div>

                <div class="feature-card scroll-animate slide-left delay-2">
                    <div class="feature-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <h3>智慧推薦與個人化學習</h3>
                    <p>系統根據歷程數據與學習需求，主動推薦最適合的補強教材與個人化學習路徑</p>
                </div>

                <div class="feature-card scroll-animate slide-up delay-3">
                    <div class="feature-icon">
                        <i class="fas fa-link"></i>
                    </div>
                    <h3>職涯歷程不斷鏈</h3>
                    <p>支援學習紀錄跨平台延續，無論跨院輪訓或進修，您的學習履歷從此隨身帶著走</p>
                </div>

                <div class="feature-card scroll-animate slide-right delay-2">
                    <div class="feature-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3>階層化師資培育</h3>
                    <p>提供從新進到資深的完整培育階梯，建立區域教師社群與教學品質監控機制</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 關於我們區塊 -->
    <section class="landing-about" id="about">
        <div class="about-container">
            <div class="about-header scroll-animate slide-up">
                <span class="section-badge">關於我們</span>
                <h2 class="section-title">雲嘉醫療數位學習聯盟</h2>
                <p class="section-desc">由大林慈濟醫院發起，結合 20 年數位學習經驗，推動區域醫療創新</p>
            </div>

            <div class="about-content">
                <div class="about-text scroll-animate slide-left">
                    <div class="about-block">
                        <h3><i class="fas fa-bullseye"></i> 我們的使命</h3>
                        <p>為了解決區域醫院在發展數位學習時面臨的「平台依賴、資源孤島、歷程斷裂」等痛點，我們發起成立雲嘉醫療數位學習聯盟，致力於整合區域優質教學資源，建立一套標準化且具延續性的醫學教育生態系統。
                        </p>
                    </div>

                    <div class="about-block">
                        <h3><i class="fas fa-eye"></i> 我們的願景</h3>
                        <p>我們期盼建構一個「全方位能力導向醫療學習網絡」，結合學習雲、歷程雲與師培雲的三雲架構，讓醫療專業知識能跨越醫院界限，實現資源最大化與人才培育的無縫接軌。</p>
                    </div>
                </div>

                <div class="about-values scroll-animate slide-right">
                    <h3>核心價值</h3>
                    <div class="values-list">
                        <div class="value-item">
                            <div class="value-icon"><i class="fas fa-building"></i></div>
                            <div class="value-content">
                                <h4>共建資源</h4>
                                <p>匯聚各院專業，共同開發高品質臨床教材</p>
                            </div>
                        </div>
                        <div class="value-item">
                            <div class="value-icon"><i class="fas fa-share-alt"></i></div>
                            <div class="value-content">
                                <h4>共享知識</h4>
                                <p>打破地理限制，促進區域內的經驗流通交流</p>
                            </div>
                        </div>
                        <div class="value-item">
                            <div class="value-icon"><i class="fas fa-award"></i></div>
                            <div class="value-content">
                                <h4>共榮發展</h4>
                                <p>接軌 CBME 國際標準，提升整體教學與照護品質</p>
                            </div>
                        </div>
                        <div class="value-item">
                            <div class="value-icon"><i class="fas fa-user-md"></i></div>
                            <div class="value-content">
                                <h4>能力導向</h4>
                                <p>以 EPA 為核心框架，精準評估與培育專業人才</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 登入區塊 -->
    <section class="landing-login" id="login-section">
        <div class="login-wrapper">
            <div class="login-info scroll-animate slide-left">
                <h2>開始您的學習旅程</h2>
                <p>登入即可存取完整的課程資源與個人學習紀錄</p>

                <div class="login-benefits">
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>區域優質核心教材庫</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>EPA 能力導向評估追蹤</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>專屬個人化學習藍圖</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>職涯學習履歷無縫銜接</span>
                    </div>
                </div>
            </div>

            <div class="login-form-card scroll-animate slide-right">
                <div class="login-header">
                    <i class="fas fa-user-circle"></i>
                    <h3>會員登入</h3>
                </div>
                <div class="login-body">
                    <!-- 錯誤訊息容器 --預設隱藏 -->
                    <div id="login-error-container" class="alert alert-danger"
                        style="border-radius: 12px; display: none;">
                        <i class="fas fa-exclamation-circle me-2"></i><span id="login-error-msg"></span>
                    </div>

                    <form id="login-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label class="form-label">帳號</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" name="username" class="form-control" placeholder="請輸入帳號" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">密碼</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" class="form-control" placeholder="請輸入密碼"
                                    required>
                            </div>
                        </div>
                        <div class="form-options">
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="remember">
                                <span class="checkmark"></span>
                                保持登入
                            </label>
                            <a href="forgot_password.php" class="forgot-link">
                                忘記密碼?
                            </a>
                        </div>
                        <button type="submit" id="btn-login" class="btn-login">
                            <i class="fas fa-sign-in-alt"></i>
                            <span class="btn-text">登入</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- 聯絡我們區塊 -->
    <section class="landing-contact" id="contact">
        <div class="contact-container">
            <div class="contact-header scroll-animate slide-up">
                <span class="section-badge">聯絡我們</span>
                <h2 class="section-title">有任何問題嗎？</h2>
                <p class="section-desc">歡迎與我們聯繫，我們很樂意協助您</p>
            </div>

            <div class="contact-grid">
                <div class="contact-card-item scroll-animate slide-up delay-1">
                    <div class="contact-icon-large">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h4>地址</h4>
                    <p>622 嘉義縣大林鎮民生路2號<br>大林慈濟醫院教學部</p>
                </div>

                <div class="contact-card-item scroll-animate slide-up delay-2">
                    <div class="contact-icon-large">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <h4>電話</h4>
                    <p>(05) 264-8000</p>
                </div>

                <div class="contact-card-item scroll-animate slide-up delay-3">
                    <div class="contact-icon-large">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h4>電子郵件</h4>
                    <p>teaching@tzuchi.com.tw</p>
                </div>

                <div class="contact-card-item scroll-animate slide-up delay-4">
                    <div class="contact-icon-large">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h4>服務時間</h4>
                    <p>週一至週五<br>08:00 - 17:00</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 頁尾 -->
    <footer class="landing-footer">
        <div class="footer-container">
            <div class="footer-brand">
                <img src="logo/small_logo.svg" alt="雲嘉學習網" class="footer-logo" style="height: 32px;">
            </div>
            <p class="footer-copyright">
                &copy; <?php echo date('Y'); ?> 大林慈濟醫院教學部. All rights reserved.
            </p>
        </div>
    </footer>

    <!-- 滾動動畫腳本 -->
    <script>
        // 導覽列滾動效果
        const nav = document.getElementById('landingNav');
        let lastScroll = 0;

        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;

            if (currentScroll > 50) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }

            lastScroll = currentScroll;
        });

        // 滾動動畫
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.scroll-animate').forEach(el => {
            observer.observe(el);
        });

        // 平滑滾動
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // 手機選單
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navMenu = document.querySelector('.nav-menu');

        if (mobileMenuBtn && navMenu) {
            mobileMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                navMenu.classList.toggle('active');
            });

            // 點擊空白處關閉選單
            document.addEventListener('click', (e) => {
                if (navMenu.classList.contains('active')) {
                    if (!navMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                        navMenu.classList.remove('active');
                    }
                }
            });
        }

        // --- 新增：AJAX 登入處理 ---
        document.addEventListener('DOMContentLoaded', function () {
            const loginForm = document.getElementById('login-form');
            const errorContainer = document.getElementById('login-error-container');
            const errorMsg = document.getElementById('login-error-msg');
            const btnLogin = document.getElementById('btn-login');

            if (loginForm) {
                loginForm.addEventListener('submit', function (e) {
                    e.preventDefault(); // 阻止表單傳統送出行為

                    // 1. UI 狀態切換成載入中
                    const btnIcon = btnLogin.querySelector('i');
                    const btnText = btnLogin.querySelector('.btn-text');
                    const originalIconClass = btnIcon.className;
                    const originalText = btnText.innerText;

                    btnIcon.className = 'fas fa-spinner fa-spin';
                    btnText.innerText = '登入中...';
                    btnLogin.disabled = true;

                    // 隱藏先前的錯誤訊息
                    errorContainer.style.display = 'none';

                    // 2. 收集表單資料
                    const formData = new FormData(loginForm);
                    formData.append('ajax', '1'); // 告訴後端這是 AJAX 請求

                    // 3. 發送 Fetch 請求
                    fetch('index.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // 登入成功，導向首頁 (index.php) 會自動跳轉儀表板
                                window.location.href = 'index.php';
                            } else {
                                // 登入失敗，顯示錯誤訊息
                                errorMsg.innerText = data.message || '登入失敗，請稍後再試。';
                                errorContainer.style.display = 'block';

                                // 恢復按鈕狀態
                                btnIcon.className = originalIconClass;
                                btnText.innerText = originalText;
                                btnLogin.disabled = false;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            errorMsg.innerText = '系統連線發生錯誤，請檢查網路狀態。';
                            errorContainer.style.display = 'block';

                            // 恢復按鈕狀態
                            btnIcon.className = originalIconClass;
                            btnText.innerText = originalText;
                            btnLogin.disabled = false;
                        });
                });
            }

            // (移除原本登入錯誤時強制滾動的腳本，因為現在不會重新載入網頁了)
        });

    </script>
    <!-- 已移除游標跟隨光暈效果以提升效能 -->
</body>

</html>