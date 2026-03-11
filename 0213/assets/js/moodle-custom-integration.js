/* 
    ============================================
    雲嘉學習網 - Moodle 完整自訂導覽列整合腳本
    整合了 body-top.html (HTML) 與 body-footer.html (JS)
    功能包含：
    1. 自動注入導覽列與 Font Awesome
    2. 自動抓取 Moodle 用戶資訊填入導覽列
    3. 管理員/教師權限判斷與選單切換
    4. 登出連結重定向至 /logout.php
    5. 強制移除 Sticky Footer
    6. 角色切換功能
    7. 自動將 /my/ 頁面重定向回入口網
    ============================================
*/

(function () {

    // 若 PortalConfig 未定義（在 Moodle 頁面中），自動偵測 webRoot
    if (typeof window.PortalConfig === 'undefined') {
        var scriptEl = document.querySelector('script[src*="moodle-custom-integration"]');
        var detectedRoot = '';
        if (scriptEl) {
            var m = scriptEl.src.match(/^.*?(\/[^/]+)\/assets\//);
            if (m) detectedRoot = m[1];
        }
        window.PortalConfig = { webRoot: detectedRoot };
    }

    // ========================================
    // 自動將 /my/ (Moodle 原生儀表板) 跳轉回入口網儀表板
    // ========================================
    if (window.location.pathname.match(/\/my(\/|\/index\.php)?$/)) {
        var _isHA = (function (n) { var v = "; " + document.cookie; var p = v.split("; " + n + "="); return p.length === 2 ? p.pop().split(";").shift() : null; })('portal_is_hospital_admin') === '1';
        var _isCC = (function (n) { var v = "; " + document.cookie; var p = v.split("; " + n + "="); return p.length === 2 ? p.pop().split(";").shift() : null; })('portal_is_coursecreator') === '1';
        if (_isHA) {
            window.location.replace(window.PortalConfig.webRoot + '/index.php');
        } else if (_isCC) {
            window.location.replace(window.PortalConfig.webRoot + '/index.php');
        } else {
            window.location.replace(window.PortalConfig.webRoot + '/index.php?page=student_dashboard');
        }
        return; // 終止腳本
    }

    function getCookie(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length === 2) return parts.pop().split(";").shift();
        return null;
    }

    function getNavHtml() {
        var isAdmin = getCookie('portal_is_admin') === '1';
        var isHospitalAdmin = getCookie('portal_is_hospital_admin') === '1';
        var isCourseCreator = getCookie('portal_is_coursecreator') === '1';

        // 判斷是否為學生 (沒有任何管理權限)
        var isStudent = !isAdmin && !isHospitalAdmin && !isCourseCreator;

        if (isStudent) {
            // === 學生專用導覽列 (完全仿造 student-nav.js 的 nav-v2 樣式) ===
            return `
            <nav class="nav-v2">
                <div style="display: flex; align-items: center;">
                    <a href="${PortalConfig.webRoot}/index.php?page=student_dashboard" class="nav-v2__brand">
                        <img src="${PortalConfig.webRoot}/logo/small_logo.svg" alt="雲嘉學習網" style="height: 48px;">
                    </a>
                    <div class="nav-v2__menu" id="navMenu">
                        <a href="${PortalConfig.webRoot}/index.php?page=student_dashboard" class="nav-v2__link" id="student-nav-link-dashboard">
                            <i class="fas fa-home"></i> 個人主頁
                        </a>
                        <a href="${PortalConfig.webRoot}/index.php?page=student_courses" class="nav-v2__link" id="student-nav-link-courses">
                            <i class="fas fa-book"></i> 我的課程
                        </a>
                        <a href="${PortalConfig.webRoot}/index.php?page=student_degree_audit" class="nav-v2__link" id="student-nav-link-progress">
                            <i class="fas fa-chart-line"></i> 修課進度
                        </a>
                        <a href="${PortalConfig.webRoot}/index.php?page=student_course_catalog" class="nav-v2__link" id="student-nav-link-catalog">
                            <i class="fas fa-search"></i> 選課中心
                        </a>
                    </div>
                </div>
                <div class="nav-v2__right">
                    <div class="ha-nav-user" id="haUserMenu">
                        <div class="ha-nav-user__trigger" id="userDropdownToggle">
                            <span class="ha-nav-user__name" id="custom-user-name">User</span>
                            <div class="nav-v2__avatar" id="custom-user-avatar">U</div>
                        </div>
                        <div class="ha-nav-user__dropdown" id="haUserDropdown">
                            <div class="ha-nav-user__dropdown-inner">
                                <div class="ha-nav-user__info">
                                    <div class="ha-nav-user__info-name" id="custom-dropdown-name">User</div>
                                    <div class="ha-nav-user__info-role">學生</div>
                                </div>
                                <div class="ha-nav-user__divider"></div>
                                <a href="${PortalConfig.webRoot}/change_password.php" class="ha-nav-user__item">
                                    <i class="fas fa-key"></i> 修改密碼
                                </a>
                                <a href="${PortalConfig.webRoot}/logout.php" class="ha-nav-user__item ha-nav-user__item--danger">
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
        } else if (isHospitalAdmin) {
            // === 院區管理員導覽列 (nav-v2 樣式，與入口網一致) ===
            return `
            <nav class="nav-v2">
                <div style="display: flex; align-items: center;">
                    <a href="${PortalConfig.webRoot}/index.php" class="nav-v2__brand">
                        <img src="${PortalConfig.webRoot}/logo/small_logo.svg" alt="雲嘉學習網" style="height: 48px;">
                    </a>
                    <div class="nav-v2__menu" id="navMenu">
                        <a href="${PortalConfig.webRoot}/index.php" class="nav-v2__link">
                            <i class="fas fa-home"></i> 首頁
                        </a>
                        <a href="${PortalConfig.webRoot}/index.php?page=users" class="nav-v2__link">
                            <i class="fas fa-users-cog"></i> 成員管理
                        </a>
                        <a href="${PortalConfig.webRoot}/index.php?page=management" class="nav-v2__link">
                            <i class="fas fa-chalkboard"></i> 課程管理
                        </a>
                        <a href="${PortalConfig.webRoot}/index.php?page=cohorts" class="nav-v2__link">
                            <i class="fas fa-layer-group"></i> 群組管理
                        </a>
                        <a href="${PortalConfig.webRoot}/index.php?page=tags" class="nav-v2__link">
                            <i class="fas fa-tags"></i> 標籤管理
                        </a>
                    </div>
                </div>
                <div class="nav-v2__right">
                    <a href="#" id="switch-role-link" class="nav-v2__link" style="display:none; margin-right:8px; font-size:13px;">
                        <i class="fas fa-user-graduate"></i> <span id="switch-role-text">切換為學生檢視</span>
                    </a>
                    <div id="custom-edit-mode-container" style="margin-right: 8px;"></div>
                    <div class="ha-nav-user" id="haUserMenu">
                        <div class="ha-nav-user__trigger" id="userDropdownToggle">
                            <span class="ha-nav-user__name" id="custom-user-name">User</span>
                            <div class="nav-v2__avatar" id="custom-user-avatar">U</div>
                        </div>
                        <div class="ha-nav-user__dropdown" id="haUserDropdown">
                            <div class="ha-nav-user__dropdown-inner">
                                <div class="ha-nav-user__info">
                                    <div class="ha-nav-user__info-name" id="custom-dropdown-name">User</div>
                                    <div class="ha-nav-user__info-role">院區管理員</div>
                                </div>
                                <div class="ha-nav-user__divider"></div>
                                <a href="${PortalConfig.webRoot}/change_password.php" class="ha-nav-user__item">
                                    <i class="fas fa-key"></i> 修改密碼
                                </a>
                                <a href="${PortalConfig.webRoot}/logout.php" class="ha-nav-user__item ha-nav-user__item--danger">
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
        } else if (isCourseCreator) {
            // === 開課教師導覽列 (nav-v2 樣式，與入口網一致) ===
            return `
            <nav class="nav-v2">
                <div style="display: flex; align-items: center;">
                    <a href="${PortalConfig.webRoot}/index.php" class="nav-v2__brand">
                        <img src="${PortalConfig.webRoot}/logo/small_logo.svg" alt="雲嘉學習網" style="height: 48px;">
                    </a>
                    <div class="nav-v2__menu" id="navMenu">
                        <a href="${PortalConfig.webRoot}/index.php" class="nav-v2__link">
                            <i class="fas fa-home"></i> 個人主頁
                        </a>
                        <a href="${PortalConfig.webRoot}/index.php?page=management" class="nav-v2__link">
                            <i class="fas fa-chalkboard"></i> 課程管理
                        </a>
                    </div>
                </div>
                <div class="nav-v2__right">
                    <a href="#" id="switch-role-link" class="nav-v2__link" style="display:none; margin-right:8px; font-size:13px;">
                        <i class="fas fa-user-graduate"></i> <span id="switch-role-text">切換為學生檢視</span>
                    </a>
                    <div id="custom-edit-mode-container" style="margin-right: 8px;"></div>
                    <div class="ha-nav-user" id="haUserMenu">
                        <div class="ha-nav-user__trigger" id="userDropdownToggle">
                            <span class="ha-nav-user__name" id="custom-user-name">User</span>
                            <div class="nav-v2__avatar" id="custom-user-avatar">U</div>
                        </div>
                        <div class="ha-nav-user__dropdown" id="haUserDropdown">
                            <div class="ha-nav-user__dropdown-inner">
                                <div class="ha-nav-user__info">
                                    <div class="ha-nav-user__info-name" id="custom-dropdown-name">User</div>
                                    <div class="ha-nav-user__info-role">開課教師</div>
                                </div>
                                <div class="ha-nav-user__divider"></div>
                                <a href="${PortalConfig.webRoot}/change_password.php" class="ha-nav-user__item">
                                    <i class="fas fa-key"></i> 修改密碼
                                </a>
                                <a href="${PortalConfig.webRoot}/logout.php" class="ha-nav-user__item ha-nav-user__item--danger">
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
        } else {
            // === 系統管理員 (root admin) 導覽列 ===
            return `
            <nav id="portal-global-nav">
                <div style="display:flex; align-items:center;">
                    <a href="${PortalConfig.webRoot}/index.php" class="pg-brand">
                        <i class="fas fa-cloud"></i>
                        <span>雲嘉學習網</span>
                    </a>
                    <div class="pg-menu">
                        <div id="pg-admin-links" style="display:flex; gap:5px; align-items:center;">
                            <a href="${PortalConfig.webRoot}/index.php?tab=admin-categories" class="pg-link">
                                <i class="fas fa-folder-tree"></i> 類別管理
                            </a>
                            <a href="${PortalConfig.webRoot}/index.php?tab=dimensions" class="pg-link">
                                <i class="fas fa-layer-group"></i> 維度管理
                            </a>
                            <a href="${PortalConfig.webRoot}/moodle/admin/user.php" class="pg-link">
                                <i class="fas fa-users"></i> 使用者
                            </a>
                            <a href="${PortalConfig.webRoot}/moodle/admin/search.php" class="pg-link">
                                <i class="fas fa-cogs"></i> 網站管理
                            </a>
                        </div>
                    </div>
                </div>
                <div class="pg-right-group">
                    <div id="pg-right-area">
                        <a href="#" id="switch-role-link" class="pg-link" style="display:none; margin-right:10px;">
                            <i class="fas fa-user-graduate"></i> <span id="switch-role-text">切換為學生檢視</span>
                        </a>
                        <div id="custom-edit-mode-container"></div>
                        <div class="pg-dropdown" id="portal-user-menu">
                            <div class="pg-link" style="display:flex; align-items:center; gap:12px;">
                                <span id="custom-user-name">User</span>
                                <div class="user-avatar-circle" id="custom-user-avatar">U</div>
                            </div>
                            <div class="pg-dropdown-content" style="right:0; left:auto;">
                                <a href="${PortalConfig.webRoot}/change_password.php"><i class="fas fa-key"></i> 修改密碼</a>
                                <a href="${PortalConfig.webRoot}/logout.php" id="custom-logout-link"><i class="fas fa-sign-out-alt"></i> 登出系統</a>
                            </div>
                        </div>
                    </div>
                    <button class="mobile-menu-btn" id="mobile-menu-toggle" style="display:none;">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </nav>
            `;
        }
    }

    // 注入 Font Awesome
    function injectFontAwesome() {
        // 改用 ID 檢查，避免被 Moodle 內建的舊版 FA (如果有) 誤判而跳過載入
        if (!document.getElementById('custom-fa-6')) {
            var link = document.createElement('link');
            link.id = 'custom-fa-6';
            link.rel = 'stylesheet';
            link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
            document.head.appendChild(link);
        }
    }

    // 注入導覽列
    function injectNavbar() {
        if (!document.getElementById('portal-global-nav') && !document.querySelector('.nav-v2')) {
            var div = document.createElement('div');
            div.innerHTML = getNavHtml();
            document.body.insertBefore(div.firstElementChild, document.body.firstChild);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // 載入必備樣式與字體
        injectFontAwesome();

        // 執行注入
        injectNavbar();

        // 延遲執行邏輯，確保 DOM 元素已完全生成 (特別是對於動態生成的導覽列)
        setTimeout(initMoodleIntegration, 100);
    });

    // 初始化核心邏輯
    function initMoodleIntegration() {
        // ========================================
        // 1. 取得與填充使用者資訊
        // ========================================
        var userName = "";

        // 1. 優先從 Portal 儲存的 Cookie 中讀取完整姓名 (確保與入口網完全一致)
        var portalNameCookie = getCookie('portal_fullname');
        if (portalNameCookie) {
            try {
                // 使用 decodeURIComponent 解碼 URL 編碼的中文字元
                userName = decodeURIComponent(portalNameCookie).replace(/\+/g, ' ');
            } catch (e) {
                console.warn('Failed to decode portal_fullname cookie', e);
            }
        }

        // 2. 作為備用方案，如果 Cookie 不存在，則從 Moodle 原生選單抓取名字
        if (!userName) {
            var userMenuText = document.querySelector('.usermenu .dropdown-toggle');
            if (userMenuText) {
                userName = userMenuText.textContent.trim().split('\n')[0].trim();
            }
        }

        // 3. 填充 DOM 元素
        if (userName) {
            var userNameEl = document.getElementById('custom-user-name');
            var userAvatarEl = document.getElementById('custom-user-avatar');
            var dropDownNameEl = document.getElementById('custom-dropdown-name');

            if (userNameEl) {
                userNameEl.textContent = userName;
            }
            if (userAvatarEl) {
                // 取名字第一個字當頭像
                userAvatarEl.textContent = userName.charAt(0);
            }
            if (dropDownNameEl) {
                dropDownNameEl.textContent = userName;
            }
        }

        // ========================================
        // 2. 登出連結處理
        // ========================================
        // 確保導覽列內的登出指向正確
        var customLogout = document.getElementById('custom-logout-link');
        if (customLogout) {
            customLogout.href = PortalConfig.webRoot + '/logout.php';
        }

        // 處理 Moodle 頁面上其他可能出現的登出連結
        var logoutLinks = document.querySelectorAll('a[href*="logout.php"]');
        logoutLinks.forEach(function (link) {
            link.href = PortalConfig.webRoot + '/logout.php';
        });

        // (getCookie is already defined in outer scope, redeclare for safety inside initMoodleIntegration)
        function getCookie(name) {
            var value = "; " + document.cookie;
            var parts = value.split("; " + name + "=");
            if (parts.length === 2) return parts.pop().split(";").shift();
            return null;
        }

        // ========================================
        // 3. 權限判斷 — nav-v2 導覽列由 getNavHtml() 根據角色自動生成
        //    只有 root admin 仍需要手動 show pg-admin-links
        // ========================================
        var isAdmin = getCookie('portal_is_admin') === '1';
        var isHospitalAdmin = getCookie('portal_is_hospital_admin') === '1';
        var isCourseCreator = getCookie('portal_is_coursecreator') === '1';

        // 教師/院區管理員在課程編輯頁面時，隱藏麵包屑連結（避免進入課程分類管理）
        if ((isCourseCreator || isHospitalAdmin) && window.location.pathname.includes('/course/edit.php')) {
            var breadcrumbLinks = document.querySelectorAll('#page-header .breadcrumb a');
            breadcrumbLinks.forEach(function (link) {
                link.style.pointerEvents = 'none';
                link.style.color = 'var(--text-muted)';
                link.style.cursor = 'default';
                link.style.textDecoration = 'none';
            });
        }

        // ========================================
        // 4. 簡單的高亮邏輯 (Context based)
        // ========================================
        var path = window.location.pathname;

        if (isAdmin && !isHospitalAdmin) {
            // Root admin — 仍使用 pg-admin-links
            try {
                if (path.indexOf('/admin/user.php') !== -1) {
                    var el = document.querySelector('#pg-admin-links a[href*="/admin/user.php"]');
                    if (el) el.classList.add('pg-link-active');
                } else if (path.indexOf('/admin/') !== -1) {
                    var el = document.querySelector('#pg-admin-links a[href*="/admin/search.php"]');
                    if (el) el.classList.add('pg-link-active');
                }
            } catch (e) { }
        }

        // ========================================
        // 5. 編輯模式開關搬移 (管理員/教師專用)
        // ========================================
        var editModeContainer = document.getElementById('custom-edit-mode-container');
        if (editModeContainer) {
            // 嘗試找到 Moodle 原生的編輯模式開關
            var editSwitch = document.querySelector('.editmode-switch-form') ||
                document.querySelector('form[action*="editmode"]') ||
                document.querySelector('.editing-switch') ||
                document.querySelector('[data-action="editmode"]');

            if (editSwitch && !editModeContainer.contains(editSwitch)) {
                // 搬移元素而不是複製，以保留事件監聽器
                editModeContainer.appendChild(editSwitch);

                // 確保它是顯示的且排版正確
                editSwitch.style.display = 'flex';
                editSwitch.style.alignItems = 'center';
                editSwitch.style.margin = '0';

                // 隱藏原本可能存在的分割線 (Moodle 預設帶有的)
                var divider = editSwitch.querySelector('.divider');
                if (divider) divider.style.display = 'none';

                // 檢查是否有標籤文字，沒有則補上
                var label = editSwitch.querySelector('label');
                if (!label) {
                    label = document.createElement('label');
                    label.textContent = '編輯模式';
                    label.style.marginRight = '8px';
                    label.style.marginBottom = '0';
                    label.style.fontWeight = '500';
                    label.style.color = '#475569';
                    editSwitch.insertBefore(label, editSwitch.firstChild);
                } else {
                    // 確保現有標籤可見
                    label.style.display = 'block';
                    if (label.textContent.trim() === '') {
                        label.textContent = '編輯模式';
                    }
                }
            }
        }

        // ========================================
        // 6. 切換角色功能 (教師專用)
        // ========================================
        if (isCourseCreator || isAdmin || isHospitalAdmin) {
            var switchRoleLink = document.getElementById('switch-role-link');

            if (switchRoleLink) {
                // 取得課程 ID
                var courseId = null;
                var urlParams = new URLSearchParams(window.location.search);

                // 方法 1
                if (typeof M !== 'undefined' && M.cfg && M.cfg.courseId) {
                    courseId = M.cfg.courseId;
                }

                // 方法 2
                if (!courseId && window.location.pathname.includes('/course/view.php')) {
                    courseId = urlParams.get('id');
                }

                // 方法 3
                if (!courseId) {
                    courseId = urlParams.get('course');
                }

                // 方法 4
                if (!courseId) {
                    var courseBreadcrumb = document.querySelector('.breadcrumb a[href*="/course/view.php?id="]');
                    if (courseBreadcrumb) {
                        var match = courseBreadcrumb.href.match(/id=(\d+)/);
                        if (match) courseId = match[1];
                    }
                }

                // 取得 Moodle sesskey
                var sesskey = '';
                if (typeof M !== 'undefined' && M.cfg && M.cfg.sesskey) {
                    sesskey = M.cfg.sesskey;
                } else {
                    var sessKeyInput = document.querySelector('input[name="sesskey"]');
                    if (sessKeyInput) sesskey = sessKeyInput.value;
                }

                // 如果在課程相關頁面內
                var isInCourseContext = (window.location.pathname.includes('/course/view.php') ||
                    window.location.pathname.includes('/course/section.php') ||
                    window.location.pathname.includes('/mod/'));
                if (courseId && sesskey && isInCourseContext) {
                    // 檢查是否已經切換為其他角色
                    var isViewingAsOther = false;

                    // 方法 1
                    if (document.body.classList.contains('userswitchedrole')) {
                        isViewingAsOther = true;
                    }

                    // 方法 2
                    var roleNotification = document.querySelector('.userloggedinas');
                    if (roleNotification &&
                        (roleNotification.textContent.includes('身分檢視') ||
                            roleNotification.textContent.includes('viewing') ||
                            roleNotification.textContent.toLowerCase().includes('switched'))) {
                        isViewingAsOther = true;
                    }

                    // 方法 3
                    if (urlParams.get('switchrole') !== null) {
                        var switchRoleParam = urlParams.get('switchrole');
                        if (switchRoleParam !== '0') {
                            isViewingAsOther = true;
                        }
                    }

                    // 方法 4
                    var returnToRoleLink = document.querySelector('a[href*="switchrole.php"][href*="switchrole=0"]');
                    if (returnToRoleLink) {
                        isViewingAsOther = true;
                    }

                    // 方法 5 (新增強力搜尋)：直接搜尋連結文字
                    if (!isViewingAsOther) {
                        var allLinks = document.querySelectorAll('a');
                        for (var i = 0; i < allLinks.length; i++) {
                            var text = allLinks[i].textContent.trim();
                            if (text.includes('返回我的正常角色') || text.includes('Return to my normal role') || text.includes('回復為原先的角色')) {
                                console.log('Found switched role text:', text, allLinks[i]);
                                isViewingAsOther = true;
                                break;
                            }
                        }
                    }

                    // 計算相對路徑
                    var returnPath = window.location.pathname.replace(PortalConfig.webRoot + '/moodle', '') + window.location.search;
                    var switchRoleText = document.getElementById('switch-role-text');
                    var switchIcon = switchRoleLink.querySelector('i');

                    if (isViewingAsOther) {
                        // 恢復原本角色
                        if (switchIcon) switchIcon.className = 'fas fa-user-tie';
                        if (switchRoleText) switchRoleText.textContent = '恢復教師身分';
                        switchRoleLink.href = PortalConfig.webRoot + '/moodle/course/switchrole.php?id=' + courseId + '&switchrole=0&sesskey=' + sesskey + '&returnurl=' + encodeURIComponent(returnPath);
                    } else {
                        // 切換為學生檢視 (role=5 是學生)
                        if (switchIcon) switchIcon.className = 'fas fa-user-graduate';
                        if (switchRoleText) switchRoleText.textContent = '切換為學生檢視';
                        switchRoleLink.href = PortalConfig.webRoot + '/moodle/course/switchrole.php?id=' + courseId + '&switchrole=5&sesskey=' + sesskey + '&returnurl=' + encodeURIComponent(returnPath);
                    }
                    switchRoleLink.style.display = 'inline-flex';
                }
            }
        }

        // ========================================
        // 7. 強制移除 Sticky Footer
        // ========================================
        // function removeStickyFooter() {
        //     var stickyFooters = document.querySelectorAll(
        //         '.sticky-footer, .sticky-footer-content, [data-region="sticky-footer"], div[class*="sticky-footer"], #page-footer, footer'
        //     );
        //     stickyFooters.forEach(function (element) {
        //         if (element && element.parentNode) {
        //             element.parentNode.removeChild(element);
        //         }
        //     });
        //     document.body.style.paddingBottom = '0';
        //     document.body.style.marginBottom = '0';
        // }

        // removeStickyFooter();
        // setTimeout(removeStickyFooter, 500);
        // setTimeout(removeStickyFooter, 1000);

        // var observer = new MutationObserver(function (mutations) {
        //     removeStickyFooter();
        // });
        // observer.observe(document.body, { childList: true, subtree: true });


        // ========================================
        // 9. 手機版選單/使用者選單 切換邏輯
        // ========================================
        var mobileMenuBtn = document.getElementById('mobile-menu-toggle');
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function () {
                var menu = document.querySelector('.pg-menu');
                if (menu) {
                    menu.classList.toggle('active');
                }
            });
        }

        // 學生版手機選單切換邏輯
        var studentMenuBtn = document.getElementById('navToggle');
        if (studentMenuBtn) {
            studentMenuBtn.addEventListener('click', function () {
                var navMenu = document.getElementById('navMenu');
                if (navMenu) {
                    var isActive = navMenu.classList.contains('active');
                    if (isActive) {
                        navMenu.classList.remove('active');
                        studentMenuBtn.classList.remove('nav-v2__toggle--active');
                    } else {
                        navMenu.classList.add('active');
                        studentMenuBtn.classList.add('nav-v2__toggle--active');
                    }
                }
            });
        }

        // 點擊空白處關閉選單 (Click Outside to Close)
        document.addEventListener('click', function (event) {
            var menu = document.querySelector('.pg-menu');
            var btn = document.getElementById('mobile-menu-toggle');
            var adminUserMenu = document.getElementById('portal-user-menu');

            // 學生版專用水滴選單
            var studentUserMenu = document.getElementById('haUserMenu');
            var studentUserDropdown = document.getElementById('haUserDropdown');

            // 處理手機選單關閉 (Admin)
            if (menu && menu.classList.contains('active')) {
                if (!menu.contains(event.target) && (!btn || !btn.contains(event.target))) {
                    menu.classList.remove('active');
                }
            }

            // 處理手機選單關閉 (Student nav-v2)
            var studentNavMenu = document.getElementById('navMenu');
            var studentToggleBtn = document.getElementById('navToggle');
            if (studentNavMenu && studentNavMenu.classList.contains('active')) {
                if (!studentNavMenu.contains(event.target) && (!studentToggleBtn || !studentToggleBtn.contains(event.target))) {
                    studentNavMenu.classList.remove('active');
                    if (studentToggleBtn) {
                        studentToggleBtn.classList.remove('nav-v2__toggle--active');
                    }
                }
            }

            // 處理原版使用者選單關閉 (Admin/Teacher)
            if (adminUserMenu && adminUserMenu.classList.contains('active')) {
                if (!adminUserMenu.contains(event.target)) {
                    adminUserMenu.classList.remove('active');
                }
            }

            // 處理新版學生使用者選單關閉
            if (studentUserDropdown && studentUserDropdown.classList.contains('active-force')) {
                if (studentUserMenu && !studentUserMenu.contains(event.target)) {
                    studentUserDropdown.classList.remove('active-force');
                    studentUserDropdown.style.opacity = '';
                    studentUserDropdown.style.visibility = '';
                    studentUserDropdown.style.transform = '';
                }
            }
        });

        // 系統管理員 / 開課教師 使用者選單點擊切換 (Toggle)
        var adminUserMenu = document.getElementById('portal-user-menu');
        if (adminUserMenu) {
            var trigger = adminUserMenu.querySelector('.pg-link');
            if (trigger) {
                trigger.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var mobileMenu = document.querySelector('.pg-menu');
                    if (mobileMenu && mobileMenu.classList.contains('active')) {
                        mobileMenu.classList.remove('active');
                    }
                    adminUserMenu.classList.toggle('active');
                });
            }
        }

        // 學生 使用者選單點擊切換 (Toggle)
        var studentUserToggle = document.getElementById('userDropdownToggle');
        var studentUserDropdown = document.getElementById('haUserDropdown');
        if (studentUserToggle && studentUserDropdown) {
            studentUserToggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                // 為了支援點擊展開/收合，加入強制類別
                var isActive = studentUserDropdown.classList.contains('active-force');
                if (isActive) {
                    studentUserDropdown.classList.remove('active-force');
                    studentUserDropdown.style.opacity = '';
                    studentUserDropdown.style.visibility = '';
                    studentUserDropdown.style.transform = '';
                } else {
                    studentUserDropdown.classList.add('active-force');
                    studentUserDropdown.style.opacity = '1';
                    studentUserDropdown.style.visibility = 'visible';
                    studentUserDropdown.style.transform = 'translateY(0)';
                }
            });
        }
    }

    // ========================================
    document.addEventListener('click', function (event) {
        // 只在手機版 (螢幕寬度 < 992px) 執行點擊空白處收起邏輯
        if (window.innerWidth >= 992) return;

        // 針對課程索引 (Course Index)
        const drawer = document.getElementById('theme_boost-drawers-courseindex');

        if (drawer && drawer.classList.contains('show')) {
            // 檢查點擊目標是否在 drawer 以外
            if (!drawer.contains(event.target)) {
                // 檢查點擊目標是否為開啟按鈕 (避免剛點開又馬上關閉)
                const isToggler = event.target.closest('[data-action="toggle-drawer"], [data-toggle="drawer"], .btn-drawer, .drawer-toggler');

                if (!isToggler) {
                    // 嘗試找到關閉按鈕並觸發點擊 (這是最符合 Moodle 原生狀態管理的方式)
                    const closeBtn = drawer.querySelector('[data-action="closedrawer"]');
                    if (closeBtn) {
                        closeBtn.click();
                    } else {
                        // 如果找不到關閉按鈕，直接移除 class
                        drawer.classList.remove('show');

                        // 移除 body 的 overflow: hidden (如果有的話)
                        document.body.classList.remove('drawer-open-left');
                    }
                }
            }
        }
    });


    // ========================================
    // 9. 強制圖層順序 (Z-Index Enforcer)
    // ========================================
    function forceLayering() {
        // 1. 直接移除/隱藏遮罩 (避免圖層堆疊問題)
        const backdrops = document.querySelectorAll('.modal-backdrop, .drawer-backdrop, .offcanvas-backdrop, div[data-region="modal-backdrop"]');
        backdrops.forEach(el => {
            el.style.setProperty('display', 'none', 'important');
            el.style.setProperty('opacity', '0', 'important');
            el.style.setProperty('pointer-events', 'none', 'important');
            el.style.setProperty('width', '0', 'important');
            el.style.setProperty('height', '0', 'important');
        });

        // 2. 拉高側邊欄層級
        const drawers = document.querySelectorAll('.drawer, .drawer-content, #theme_boost-drawers-courseindex, div[data-region="drawer"]');
        drawers.forEach(el => {
            el.style.setProperty('z-index', '1300', 'important');
            el.style.setProperty('visibility', 'visible', 'important');
            el.style.setProperty('pointer-events', 'auto', 'important'); // 強制可點擊
        });

        // 3. 確保導覽列層級
        const nav = document.getElementById('portal-global-nav');
        if (nav) {
            nav.style.setProperty('z-index', '1200', 'important');
        }
        const navV2 = document.querySelector('.nav-v2');
        if (navV2) {
            navV2.style.setProperty('z-index', '1200', 'important');
        }
    }

    // ========================================
    // 10. 防止左上角按鈕點擊跳轉 (Prevent Toggler Redirect)
    // ========================================
    // 用戶回報點擊「開啟課程索引」按鈕會跳回首頁，這裡強制攔截
    // document.addEventListener('click', function (e) {
    //     const toggler = e.target.closest('[data-action="toggle-drawer"], .drawer-toggler, .btn-drawer');
    //     if (toggler) {
    //         // 只阻止跳轉 (href)，不阻止 Moodle 原生的 toggle 事件
    //         if (toggler.tagName === 'A') {
    //             e.preventDefault();
    //         }
    //     }
    // }, true); // 使用捕獲階段確保最先執行

    // ========================================
    // 11. 強制移除提示文字 (Remove Tooltips)
    // ========================================
    function disableTooltips() {
        const togglers = document.querySelectorAll('[data-action="toggle-drawer"], .drawer-toggler, .btn-drawer');
        togglers.forEach(btn => {
            // 移除所有可能觸發 Tooltip 的屬性
            btn.removeAttribute('title');
            btn.removeAttribute('data-toggle');
            btn.removeAttribute('data-original-title');
            btn.removeAttribute('aria-label'); // 有時候 aria-label 也會被轉為 tooltip
        });

        // 隱藏所有已經生成的 Tooltip 元素
        const tooltips = document.querySelectorAll('.tooltip, div[role="tooltip"]');
        tooltips.forEach(t => {
            if (t.textContent.includes('課程索引') || t.textContent.includes('Course index')) {
                t.style.display = 'none';
                t.style.opacity = '0';
            }
        });
    }

    // 初始化
    document.addEventListener('DOMContentLoaded', function () {
        // 啟動強制分層循環 (解決 Moodle JS 動態覆寫問題)
        // 每 100ms 檢查一次，持續 10 秒
        const layerInterval = setInterval(() => {
            forceLayering();
            disableTooltips(); // 加入移除 Tooltips 的檢查
        }, 100);
        setTimeout(() => clearInterval(layerInterval), 10000);

        // 額外綁定點擊事件時也檢查 (針對動態開啟的遮罩)
        document.addEventListener('click', () => {
            setTimeout(forceLayering, 50);
            setTimeout(forceLayering, 300);
            setTimeout(disableTooltips, 50); // 點擊後再次檢查
        });
    });

})();
