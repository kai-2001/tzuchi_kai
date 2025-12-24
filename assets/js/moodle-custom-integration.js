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
    ============================================
*/

(function () {
    // 定義導覽列 HTML
    const navHtml = `
    <nav id="portal-global-nav">
        <!-- 左側區域：Logo + 選單 -->
        <div style="display:flex; align-items:center;">
            <a href="/index.php" class="pg-brand">
                <i class="fas fa-cloud"></i>
                <span>雲嘉學習網</span>
            </a>

            <div class="pg-menu">
                <!-- 學生專用連結 (預設隱藏，由 JS 決定顯示) -->
                <div id="pg-student-links" style="display:none; gap:5px; align-items:center;">
                    <a href="/index.php" class="pg-link">
                        <i class="fas fa-home"></i> 個人主頁
                    </a>
                    <a href="/index.php?tab=quick-enroll" class="pg-link">
                        <i class="fas fa-compass"></i> 探索課程
                    </a>
                    <a href="/index.php?tab=my-courses" class="pg-link">
                        <i class="fas fa-book-open"></i> 我的課程
                    </a>
                </div>

                <!-- 開課教師專用連結 (預設隱藏) -->
                <div id="pg-teacher-links" style="display:none; gap:5px; align-items:center;">
                    <a href="/index.php" class="pg-link">
                        <i class="fas fa-home"></i> 個人主頁
                    </a>
                    <a href="/moodle/course/edit.php" class="pg-link">
                        <i class="fas fa-plus-circle"></i> 新增課程
                    </a>
                    <a href="/index.php?tab=course-management" class="pg-link">
                        <i class="fas fa-tasks"></i> 課程管理
                    </a>
                </div>

                <!-- 管理員專用連結 (預設隱藏) -->
                <div id="pg-admin-links" style="display:none; gap:5px; align-items:center;">
                    <a href="/moodle/course/index.php" class="pg-link">
                        <i class="fas fa-list"></i> 課程列表
                    </a>
                    <a href="/moodle/course/edit.php?category=2" class="pg-link">
                        <i class="fas fa-plus-circle"></i> 新增課程
                    </a>
                    <a href="/moodle/admin/user.php" class="pg-link">
                        <i class="fas fa-users"></i> 使用者
                    </a>
                    <a href="/moodle/admin/search.php" class="pg-link">
                        <i class="fas fa-cogs"></i> 網站管理
                    </a>
                </div>
            </div>
        </div>

        <!-- 右側區域：切換角色 + 編輯模式開關 + 使用者選單 -->
        <div id="pg-right-area">
            <!-- 切換角色按鈕（教師/管理員專用，在課程內才顯示）-->
            <a href="#" id="switch-role-link" class="pg-link" style="display:none; margin-right:10px;">
                <i class="fas fa-user-graduate"></i> <span id="switch-role-text">切換為學生檢視</span>
            </a>

            <!-- 編輯模式開關容器 (將由 JS 填充) -->
            <div id="custom-edit-mode-container"></div>

            <div class="pg-dropdown" id="portal-user-menu">
                <div class="pg-link" style="display:flex; align-items:center; gap:12px;">
                    <span id="custom-user-name">User</span>
                    <div class="user-avatar-circle" id="custom-user-avatar">U</div>
                </div>
                <div class="pg-dropdown-content" style="right:0; left:auto;">
                    <a href="/change_password.php"><i class="fas fa-key"></i> 修改密碼</a>
                    <a href="/logout.php" id="custom-logout-link"><i class="fas fa-sign-out-alt"></i> 登出系統</a>
                </div>
            </div>
        </div>
    </nav>
    `;

    // 注入 Font Awesome
    function injectFontAwesome() {
        if (!document.querySelector('link[href*="font-awesome"]')) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
            document.head.appendChild(link);
        }
    }

    // 注入導覽列
    function injectNavbar() {
        if (!document.getElementById('portal-global-nav')) {
            var div = document.createElement('div');
            div.innerHTML = navHtml;
            document.body.insertBefore(div.firstElementChild, document.body.firstChild);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
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
        var userMenuText = document.querySelector('.usermenu .dropdown-toggle');
        if (userMenuText) {
            // 從 Moodle 原生選單抓取名字
            var userName = userMenuText.textContent.trim().split('\n')[0].trim();
            var userNameEl = document.getElementById('custom-user-name');
            var userAvatarEl = document.getElementById('custom-user-avatar');

            if (userName && userNameEl) {
                userNameEl.textContent = userName;
                if (userAvatarEl) {
                    // 取名字第一個字當頭像
                    userAvatarEl.textContent = userName.charAt(0);
                }
            }
        }

        // ========================================
        // 2. 登出連結處理
        // ========================================
        // 確保導覽列內的登出指向正確
        var customLogout = document.getElementById('custom-logout-link');
        if (customLogout) {
            customLogout.href = '/logout.php';
        }

        // 處理 Moodle 頁面上其他可能出現的登出連結
        var logoutLinks = document.querySelectorAll('a[href*="logout.php"]');
        logoutLinks.forEach(function (link) {
            link.href = '/logout.php';
        });

        // ========================================
        // 3. 權限判斷 (Admin vs TeacherPlus vs Student)
        // ========================================
        function getCookie(name) {
            var value = "; " + document.cookie;
            var parts = value.split("; " + name + "=");
            if (parts.length === 2) return parts.pop().split(";").shift();
            return null;
        }

        var isAdmin = getCookie('portal_is_admin') === '1';
        var isTeacherPlus = getCookie('portal_is_teacherplus') === '1';
        var studentLinks = document.getElementById('pg-student-links');
        var teacherLinks = document.getElementById('pg-teacher-links');
        var adminLinks = document.getElementById('pg-admin-links');

        // 隱藏所有選單，然後根據角色顯示對應選單
        if (studentLinks) studentLinks.style.display = 'none';
        if (teacherLinks) teacherLinks.style.display = 'none';
        if (adminLinks) adminLinks.style.display = 'none';

        if (isAdmin) {
            if (adminLinks) adminLinks.style.display = 'flex';
        } else if (isTeacherPlus) {
            if (teacherLinks) teacherLinks.style.display = 'flex';

            // 教師在新增課程頁面時，隱藏麵包屑連結（避免進入課程分類管理）
            if (window.location.pathname.includes('/course/edit.php')) {
                var breadcrumbLinks = document.querySelectorAll('#page-header .breadcrumb a');
                breadcrumbLinks.forEach(function (link) {
                    link.style.pointerEvents = 'none';
                    link.style.color = 'var(--text-muted)';
                    link.style.cursor = 'default';
                    link.style.textDecoration = 'none';
                });
            }
        } else {
            if (studentLinks) studentLinks.style.display = 'flex';
        }

        // ========================================
        // 4. 簡單的高亮邏輯 (Context based)
        // ========================================
        var path = window.location.pathname;

        if (isAdmin) {
            try {
                if (path.indexOf('/course/edit.php') !== -1) {
                    var el = document.querySelector('#pg-admin-links a[href*="/course/edit.php"]');
                    if (el) el.classList.add('pg-link-active');
                } else if (path.indexOf('/course/') !== -1) {
                    var el = document.querySelector('#pg-admin-links a[href*="/course/index.php"]');
                    if (el) el.classList.add('pg-link-active');
                } else if (path.indexOf('/admin/user.php') !== -1) {
                    var el = document.querySelector('#pg-admin-links a[href*="/admin/user.php"]');
                    if (el) el.classList.add('pg-link-active');
                } else if (path.indexOf('/admin/') !== -1) {
                    var el = document.querySelector('#pg-admin-links a[href*="/admin/search.php"]');
                    if (el) el.classList.add('pg-link-active');
                }
            } catch (e) { }
        } else if (isTeacherPlus) {
            try {
                if (path.indexOf('/course/edit.php') !== -1) {
                    var el = document.querySelector('#pg-teacher-links a[href*="/course/edit.php"]');
                    if (el) el.classList.add('pg-link-active');
                } else if (path.indexOf('/course/view.php') !== -1 || path.indexOf('/mod/') !== -1) {
                    // 在課程內瀏覽時，不高亮任何項目
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
            }
        }

        // ========================================
        // 6. 切換角色功能 (教師專用)
        // ========================================
        if (isTeacherPlus || isAdmin) {
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

                    // 計算相對路徑
                    var returnPath = window.location.pathname.replace('/moodle', '') + window.location.search;
                    var switchRoleText = document.getElementById('switch-role-text');
                    var switchIcon = switchRoleLink.querySelector('i');

                    if (isViewingAsOther) {
                        // 恢復原本角色
                        if (switchIcon) switchIcon.className = 'fas fa-user-tie';
                        if (switchRoleText) switchRoleText.textContent = '恢復教師身分';
                        switchRoleLink.href = '/moodle/course/switchrole.php?id=' + courseId + '&switchrole=0&sesskey=' + sesskey + '&returnurl=' + encodeURIComponent(returnPath);
                    } else {
                        // 切換為學生檢視 (role=5 是學生)
                        if (switchIcon) switchIcon.className = 'fas fa-user-graduate';
                        if (switchRoleText) switchRoleText.textContent = '切換為學生檢視';
                        switchRoleLink.href = '/moodle/course/switchrole.php?id=' + courseId + '&switchrole=5&sesskey=' + sesskey + '&returnurl=' + encodeURIComponent(returnPath);
                    }
                    switchRoleLink.style.display = 'inline-flex';
                }
            }
        }

        // ========================================
        // 7. 強制移除 Sticky Footer
        // ========================================
        function removeStickyFooter() {
            var stickyFooters = document.querySelectorAll(
                '.sticky-footer, .sticky-footer-content, [data-region="sticky-footer"], .bulkactions, div[class*="sticky-footer"], #page-footer, footer'
            );
            stickyFooters.forEach(function (element) {
                if (element && element.parentNode) {
                    element.parentNode.removeChild(element);
                }
            });
            document.body.style.paddingBottom = '0';
            document.body.style.marginBottom = '0';
        }

        removeStickyFooter();
        setTimeout(removeStickyFooter, 500);
        setTimeout(removeStickyFooter, 1000);

        var observer = new MutationObserver(function (mutations) {
            removeStickyFooter();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // ========================================
    // 8. 游標跟隨暗色光暈 (Subtle Dark Aura)
    // ========================================
    function initMouseGlow() {
        if (document.getElementById('mouse-glow')) return;

        const glow = document.createElement('div');
        glow.id = 'mouse-glow';
        glow.className = 'mouse-follow-glow';
        document.body.appendChild(glow);

        document.addEventListener('mousemove', function (e) {
            glow.style.transform = `translate(calc(${e.clientX}px - 50%), calc(${e.clientY}px - 50%))`;
        });
    }

    // 初始化
    document.addEventListener('DOMContentLoaded', function () {
        initMouseGlow();
    });

})();
