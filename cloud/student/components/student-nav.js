class StudentNav extends HTMLElement {
    connectedCallback() {
        const activePage = this.getAttribute('active') || '';

        this.innerHTML = `
        <nav class="nav-v2">
            <div style="display: flex; align-items: center;">
                <a href="student-dashboard.html" class="nav-v2__brand">
                    <img src="../docs/style-guide/small_logo.svg" alt="雲嘉 e 學院" style="height: 48px;">
                </a>
                <div class="nav-v2__menu" id="navMenu">
                    <a href="student-dashboard.html" class="nav-v2__link ${activePage === 'dashboard' ? 'nav-v2__link--active' : ''}">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                    <a href="student-courses.html" class="nav-v2__link ${activePage === 'courses' ? 'nav-v2__link--active' : ''}">
                        <i class="fas fa-book"></i>
                        我的課程
                    </a>
                    <a href="student-degree-audit.html" class="nav-v2__link ${activePage === 'progress' ? 'nav-v2__link--active' : ''}">
                        <i class="fas fa-chart-line"></i>
                        修課進度
                    </a>
                    <a href="student-course-catalog.html" class="nav-v2__link ${activePage === 'catalog' ? 'nav-v2__link--active' : ''}">
                        <i class="fas fa-search"></i>
                        選課中心
                    </a>
                </div>
            </div>
            <div class="nav-v2__right">
                <button class="btn-v2 btn-v2--ghost btn-v2--icon" title="通知">
                    <i class="fas fa-bell"></i>
                </button>
                <div class="nav-v2__avatar">王小明</div>
                <button class="nav-v2__toggle" id="navToggle" aria-label="選單">
                    <span class="nav-v2__toggle-bar"></span>
                    <span class="nav-v2__toggle-bar"></span>
                    <span class="nav-v2__toggle-bar"></span>
                </button>
            </div>
        </nav>
        `;

        // Hamburger toggle logic
        const toggle = this.querySelector('#navToggle');
        const menu = this.querySelector('#navMenu');
        if (toggle && menu) {
            toggle.addEventListener('click', () => {
                const isOpen = menu.classList.toggle('nav-v2__menu--open');
                toggle.classList.toggle('nav-v2__toggle--active', isOpen);
                toggle.setAttribute('aria-expanded', isOpen);
            });
        }
    }
}

customElements.define('student-nav', StudentNav);
