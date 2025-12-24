<!-- templates/landing.php - 封面首頁 (immiller 風格) -->
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>雲嘉學習網 | 大林慈濟教學部</title>
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
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>

<body class="landing-page">
    <!-- 導覽列 -->
    <nav class="landing-nav" id="landingNav">
        <div class="nav-container">
            <a href="index.php" class="nav-brand">
                <i class="fas fa-cloud"></i>
                <span>雲嘉學習網</span>
            </a>

            <div class="nav-menu">
                <a href="#features" class="nav-link">平台特色</a>
                <a href="#about" class="nav-link">關於我們</a>
                <a href="#contact" class="nav-link">聯絡我們</a>
                <a href="<?php echo $moodle_url; ?>/course/index.php" class="nav-link" target="_blank">課程列表</a>
            </div>

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
    </nav>

    <!-- Hero 區塊 -->
    <section class="landing-hero">
        <div class="hero-bg">
            <div class="hero-gradient"></div>
            <div class="hero-particles"></div>
        </div>

        <div class="hero-content">
            <div class="hero-badge scroll-animate fade-scale">
                <i class="fas fa-star"></i>
                大林慈濟教學部
            </div>

            <h1 class="hero-title scroll-animate slide-up">
                <span class="title-main">雲嘉</span><span class="title-accent">學習網</span>
            </h1>

            <p class="hero-subtitle scroll-animate slide-up delay-1">
                中南部地區醫院同仁的雲端學習平台<br>
                分享專業課程，共創學習成長
            </p>

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
                    <span class="stat-number">1000+</span>
                    <span class="stat-label">專業課程</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-number">5000+</span>
                    <span class="stat-label">學員人數</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="stat-number">98%</span>
                    <span class="stat-label">滿意度</span>
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
                <h2 class="section-title">為什麼選擇雲嘉學習網？</h2>
                <p class="section-desc">專為中南部地區醫院同仁打造的學習與分享平台</p>
            </div>

            <div class="features-grid">
                <div class="feature-card scroll-animate slide-left delay-1">
                    <div class="feature-icon">
                        <i class="fas fa-book-medical"></i>
                    </div>
                    <h3>同仁自製課程</h3>
                    <p>醫院同仁可上傳自己開設的課程，分享專業知識與經驗</p>
                </div>

                <div class="feature-card scroll-animate slide-up delay-2">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>彈性學習</h3>
                    <p>隨時隨地自主學習，依個人進度安排課程時間</p>
                </div>

                <div class="feature-card scroll-animate slide-right delay-3">
                    <div class="feature-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h3>認證時數</h3>
                    <p>完成課程可取得學習認證，符合專業進修時數需求</p>
                </div>

                <div class="feature-card scroll-animate slide-left delay-2">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>跨院交流</h3>
                    <p>中南部地區各院同仁共同學習，促進經驗交流與合作</p>
                </div>

                <div class="feature-card scroll-animate slide-up delay-3">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>進度追蹤</h3>
                    <p>完整的學習紀錄與進度分析，掌握個人成長軌跡</p>
                </div>

                <div class="feature-card scroll-animate slide-right delay-2">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>專人支援</h3>
                    <p>專業客服團隊，即時解答您的學習問題</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 關於我們區塊 -->
    <section class="landing-about" id="about">
        <div class="about-container">
            <div class="about-header scroll-animate slide-up">
                <span class="section-badge">關於我們</span>
                <h2 class="section-title">大林慈濟醫院教學部</h2>
                <p class="section-desc">致力於推動中南部地區醫療教育的創新與發展</p>
            </div>

            <div class="about-content">
                <div class="about-text scroll-animate slide-left">
                    <div class="about-block">
                        <h3><i class="fas fa-bullseye"></i> 我們的使命</h3>
                        <p>雲嘉學習網由大林慈濟醫院教學部建置營運，旨在為中南部地區醫院同仁提供一個便利的雲端學習平台。我們相信，知識的分享能夠提升整體醫療品質，讓每一位同仁都能持續成長。</p>
                    </div>

                    <div class="about-block">
                        <h3><i class="fas fa-eye"></i> 我們的願景</h3>
                        <p>建構一個開放、共享的學習社群，讓醫療專業知識能夠跨越醫院界限，促進同仁間的交流與合作，共同為提升病人照護品質而努力。</p>
                    </div>
                </div>

                <div class="about-values scroll-animate slide-right">
                    <h3>核心價值</h3>
                    <div class="values-list">
                        <div class="value-item">
                            <div class="value-icon"><i class="fas fa-share-alt"></i></div>
                            <div class="value-content">
                                <h4>知識共享</h4>
                                <p>鼓勵同仁分享專業經驗，建構學習型組織</p>
                            </div>
                        </div>
                        <div class="value-item">
                            <div class="value-icon"><i class="fas fa-rocket"></i></div>
                            <div class="value-content">
                                <h4>持續創新</h4>
                                <p>運用數位科技優化學習體驗</p>
                            </div>
                        </div>
                        <div class="value-item">
                            <div class="value-icon"><i class="fas fa-hands-helping"></i></div>
                            <div class="value-content">
                                <h4>跨域合作</h4>
                                <p>促進區域醫院間的教育資源整合</p>
                            </div>
                        </div>
                        <div class="value-item">
                            <div class="value-icon"><i class="fas fa-heart"></i></div>
                            <div class="value-content">
                                <h4>以人為本</h4>
                                <p>關注每位學習者的成長需求</p>
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
                        <span>數千門專業課程</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>個人化學習推薦</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>學習進度同步</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>線上證書下載</span>
                    </div>
                </div>
            </div>

            <div class="login-form-card scroll-animate slide-right">
                <div class="login-header">
                    <i class="fas fa-user-circle"></i>
                    <h3>會員登入</h3>
                </div>
                <div class="login-body">
                    <?php if ($error_msg): ?>
                        <div class="alert alert-danger" style="border-radius: 12px;">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo h($error_msg); ?>
                        </div>
                    <?php endif; ?>
                    <form method="post">
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
                            <a href="<?php echo $moodle_url; ?>/login/forgot_password.php" target="_blank"
                                class="forgot-link">
                                忘記密碼?
                            </a>
                        </div>
                        <button type="submit" class="btn-login">
                            <i class="fas fa-sign-in-alt"></i>
                            登入
                        </button>
                    </form>
                    <div class="register-link">
                        <span>還沒有帳號？</span>
                        <a href="register.php">立即註冊 <i class="fas fa-arrow-right"></i></a>
                    </div>
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
                <i class="fas fa-cloud"></i>
                <span>雲嘉學習網</span>
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

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });
        }
    </script>
</body>

</html>