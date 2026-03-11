class StudentNav extends HTMLElement {
    connectedCallback() {
        const activePage = this.getAttribute('active') || '';

        // Retrieve global config injected by index.php
        const config = window.PortalConfig || { webRoot: '.', user: { fullname: '學生' } };
        const webRoot = config.webRoot;
        const userName = config.user.fullname || config.user.username || '學生';
        const userInitial = userName ? String(userName).charAt(0) : '學';

        this.innerHTML = `
        <nav class="nav-v2">
            <div style="display: flex; align-items: center;">
                <a href="${webRoot}/index.php?page=student_dashboard" class="nav-v2__brand">
                    <img src="${webRoot}/logo/small_logo.svg" alt="雲嘉學習網" style="height: 48px;">
                </a>
                <div class="nav-v2__menu" id="navMenu">
                    <a href="${webRoot}/index.php?page=student_dashboard" class="nav-v2__link ${activePage === 'dashboard' ? 'nav-v2__link--active' : ''}">
                        <i class="fas fa-home"></i>
                        個人主頁
                    </a>
                    <a href="${webRoot}/index.php?page=student_courses" class="nav-v2__link ${activePage === 'courses' ? 'nav-v2__link--active' : ''}">
                        <i class="fas fa-book"></i>
                        我的課程
                    </a>
                    <a href="${webRoot}/index.php?page=student_degree_audit" class="nav-v2__link ${activePage === 'progress' ? 'nav-v2__link--active' : ''}">
                        <i class="fas fa-chart-line"></i>
                        修課進度
                    </a>
                    <a href="${webRoot}/index.php?page=student_course_catalog" class="nav-v2__link ${activePage === 'catalog' ? 'nav-v2__link--active' : ''}">
                        <i class="fas fa-search"></i>
                        選課中心
                    </a>
                </div>
            </div>
            <div class="nav-v2__right">
                
                <style>
                    /* Override flex gap for the right container */
                    .nav-v2__right {
                        gap: 16px;
                    }

                    /* --- Replicated from Hospital Admin Nav --- */
                    .ha-nav-user {
                        position: relative;
                        display: flex;
                        align-items: center;
                    }
                    .ha-nav-user__trigger {
                        display: flex;
                        align-items: center;
                        gap: var(--space-3);
                        padding: var(--space-2) var(--space-3);
                        border-radius: 8px; /* Changed from --radius-full to be rectangular */
                        cursor: pointer;
                        transition: all var(--duration-fast);
                        background: transparent;
                        border: 1px solid transparent;
                    }
                    .ha-nav-user:hover .ha-nav-user__trigger {
                        background: rgba(241, 245, 249, 0.8);
                        border-color: var(--border-default);
                        border-radius: 8px;
                    }
                    .ha-nav-user__name {
                        font-size: 14px;
                        font-weight: 500;
                        color: var(--text-primary);
                        display: none;
                    }
                    @media (min-width: 768px) {
                        .ha-nav-user__name {
                            display: block;
                        }
                    }
                    .nav-v2__avatar {
                        width: 40px;
                        height: 40px;
                        border-radius: 50%;
                        background: var(--gradient-primary);
                        color: white;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: 600;
                        font-size: 16px;
                        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
                        transition: transform var(--duration-fast);
                    }
                    .ha-nav-user:hover .nav-v2__avatar {
                        transform: scale(1.05);
                    }
                    .ha-nav-user__dropdown {
                        position: absolute;
                        top: 100%;
                        right: 0;
                        margin-top: 0;
                        padding-top: 8px; /* Adjusted padding-top to eliminate the gap */
                        width: 240px;
                        opacity: 0;
                        visibility: hidden;
                        transform: translateY(-10px);
                        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                        z-index: 1000;
                    }
                    /* Add an invisible bridging element inside the dropdown to catch the mouse during the animation gap */
                    .ha-nav-user__dropdown::before {
                        content: '';
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        height: 12px; /* Ensure bridge fully covers the gap */
                        background: transparent;
                    }
                    .ha-nav-user__dropdown-inner {
                        background: rgba(255, 255, 255, 0.98);
                        backdrop-filter: blur(20px);
                        -webkit-backdrop-filter: blur(20px);
                        border: 1px solid rgba(37, 99, 235, 0.1);
                        border-radius: 8px;
                        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
                        padding: var(--space-2);
                    }
                    .ha-nav-user:hover .ha-nav-user__dropdown {
                        opacity: 1;
                        visibility: visible;
                        transform: translateY(0);
                    }
                    .ha-nav-user__info {
                        padding: var(--space-3) var(--space-3) var(--space-2) var(--space-3); /* Removed background completely, adjusted padding */
                        margin-bottom: 0;
                    }
                    .ha-nav-user__info-name {
                        font-size: 14px;
                        font-weight: 600;
                        color: var(--text-primary);
                        margin-bottom: 2px;
                    }
                    .ha-nav-user__info-role {
                        font-size: 12px;
                        color: var(--text-secondary);
                    }
                    .ha-nav-user__divider {
                        height: 1px;
                        background: var(--border-default);
                        margin: var(--space-2) 0;
                    }
                    .ha-nav-user__item {
                        display: flex;
                        align-items: center;
                        gap: var(--space-3);
                        padding: var(--space-3);
                        color: var(--text-secondary);
                        text-decoration: none;
                        font-size: 14px;
                        font-weight: 500;
                        border-radius: 6px;
                        transition: all var(--duration-fast);
                    }
                    .ha-nav-user__item:hover {
                        background: var(--bg-muted);
                        color: var(--text-primary);
                    }
                    .ha-nav-user__item--danger:hover {
                        background: rgba(239, 68, 68, 0.1);
                        color: var(--error);
                    }
                    /* ----------------------------------------------------------- */
                </style>

                <!-- Replicated Hospital Admin Structure -->
                <div class="ha-nav-user" id="haUserMenu">
                    <div class="ha-nav-user__trigger" id="userDropdownToggle">
                        <span class="ha-nav-user__name">
                            ${userName}
                        </span>
                        <div class="nav-v2__avatar">
                            ${userInitial}
                        </div>
                    </div>
                    <div class="ha-nav-user__dropdown" id="haUserDropdown">
                        <div class="ha-nav-user__dropdown-inner">
                            <div class="ha-nav-user__info">
                                <div class="ha-nav-user__info-name">
                                    ${userName}
                                </div>
                                <div class="ha-nav-user__info-role">
                                    學生
                                </div>
                            </div>
                            <div class="ha-nav-user__divider"></div>
                            <a href="${webRoot}/change_password.php" class="ha-nav-user__item">
                                <i class="fas fa-key"></i> 修改密碼
                            </a>
                            <a href="${webRoot}/logout.php" class="ha-nav-user__item ha-nav-user__item--danger">
                                <i class="fas fa-sign-out-alt"></i> 登出系統
                            </a>
                        </div>
                    </div>
                </div>

                <button class="nav-v2__toggle" id="navToggle" aria-label="選單">
                    <span class="nav-v2__toggle-bar"></span>
                    <span class="nav-v2__toggle-bar"></span>
                    <span class="nav-v2__toggle-bar"></span>
                </button>
            </div>
        </nav>
        `;

        // Removed JavaScript click toggles for the user avatar since we added CSS :hover interactions instead

        // Hamburger toggle logic
        const toggle = this.querySelector('#navToggle');
        const menu = this.querySelector('#navMenu');
        if (toggle && menu) {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = menu.classList.toggle('nav-v2__menu--open');
                toggle.classList.toggle('nav-v2__toggle--active', isOpen);
                toggle.setAttribute('aria-expanded', isOpen);
            });
        }
    }
}

customElements.define('student-nav', StudentNav);
