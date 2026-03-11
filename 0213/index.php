<?php
// index.php - 主入口 (模組化版本)

session_set_cookie_params(0);
session_start();

// 載入核心模組
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/moodle_api.php';

// 產生 CSRF Token
generate_csrf_token();

// 自動登入檢查
check_auto_login();

// 處理登出
process_logout();

// 處理登入
$error_msg = process_login();

// 取得 Moodle 資料
$is_admin = isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : false;

// 刷新快取（如果 URL 有 ?refresh=1）
if (isset($_GET['refresh']) && $_GET['refresh'] == '1') {
    unset($_SESSION['moodle_cache']);
    unset($_SESSION['moodle_cache_time']);
    header("Location: index.php");
    exit;
}

// AJAX 清除快取（不重導向，供 JavaScript 呼叫）
if (isset($_GET['clear_cache']) && $_GET['clear_cache'] == '1') {
    unset($_SESSION['moodle_cache']);
    unset($_SESSION['moodle_cache_time']);
    echo 'OK';
    exit;
}

// 非同步模式：改由前端 AJAX 載入資料 (預設行為)

// 準備空資料結構 (將由前端填充)
$my_courses_raw = [];
$history_by_year = [];
$available_courses = [];
$latest_announcements = [];
$curriculum_status = [];

// 頁面路由
$page = $_GET['page'] ?? '';
?>
<?php if (!isset($_SESSION['username'])): ?>
    <?php include 'templates/landing.php'; ?>
<?php else: ?>
    <?php
    $is_hospital_admin = isset($_SESSION['is_hospital_admin']) ? $_SESSION['is_hospital_admin'] : false;

    if ($is_hospital_admin):
        // 院區管理員：分頁導覽模式
        $ha_pages = [
            '' => ['file' => 'templates/tabs/hospital_admin_home.php', 'nav' => 'home'],
            'users' => ['file' => 'templates/tabs/hospital_admin_users.php', 'nav' => 'users'],
            'management' => ['file' => 'templates/tabs/hospital_admin_management.php', 'nav' => 'management'],
            'cohorts' => ['file' => 'templates/tabs/hospital_admin_cohorts.php', 'nav' => 'cohorts'],
            'tags' => ['file' => 'templates/tabs/hospital_admin_tags.php', 'nav' => 'tags'],
            'course_create' => ['file' => 'templates/hospital_admin_course_create_page.php', 'nav' => 'management'],
            'course_enrol' => ['file' => 'templates/hospital_admin_course_enrol_page.php', 'nav' => 'management'],
        ];

        $current_page_key = isset($ha_pages[$page]) ? $page : '';
        $current_page = $ha_pages[$current_page_key]['nav'];
        $page_file = $ha_pages[$current_page_key]['file'];

        // 獨立頁面有自己的完整 layout
        $standalone_ha = [];
        if (in_array($current_page_key, $standalone_ha)):
            include $page_file;
        else:
            include 'templates/components/hospital-admin-nav.php';
            include $page_file;
            include 'templates/components/hospital-admin-footer.php';
        endif;

    elseif (isset($_SESSION['is_coursecreator']) && $_SESSION['is_coursecreator']):
        // 開課教師：分頁導覽模式（與院區管理員類似，但精簡）
        $tc_pages = [
            '' => ['file' => 'templates/tabs/teacher_home.php', 'nav' => 'home'],
            'management' => ['file' => 'templates/tabs/teacher_management.php', 'nav' => 'management'],
            'teacher_course_create' => ['file' => 'templates/teacher_course_create_page.php', 'nav' => 'management'],
            'teacher_course_enrol' => ['file' => 'templates/teacher_course_enrol_page.php', 'nav' => 'management'],
        ];

        $current_page_key = isset($tc_pages[$page]) ? $page : '';
        $current_page = $tc_pages[$current_page_key]['nav'];
        $page_file = $tc_pages[$current_page_key]['file'];

        include 'templates/components/teacher-nav.php';
        include $page_file;
        include 'templates/components/hospital-admin-footer.php';

    else:
        // 學生：新的多頁面路由模式
        $student_pages = [
            'student_dashboard' => 'templates/student/student-dashboard.php',
            'student_courses' => 'templates/student/student-courses.php',
            'student_domain_courses' => 'templates/student/student-domain-courses.php',
            'student_degree_audit' => 'templates/student/student-degree-audit.php',
            'student_course_catalog' => 'templates/student/student-course-catalog.php',
        ];

        // 其他角色：舊版 dashboard 模式 或 獨立頁面
        $standalone_pages = [
            'course_create' => 'templates/hospital_admin_course_create_page.php',
            'course_enrol' => 'templates/hospital_admin_course_enrol_page.php'
        ];

        // 判斷是否為新的學生頁面
        if (isset($student_pages[$page]) && file_exists($student_pages[$page])):
            // 生成 PortalConfig 供前端使用
            $is_coursecreator = isset($_SESSION['is_coursecreator']) ? $_SESSION['is_coursecreator'] : false;
            echo "<script>
                window.PortalConfig = {
                    webRoot: '{$web_root}',
                    moodleUrl: '{$moodle_url}',
                    user: {
                        username: '" . (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '') . "',
                        fullname: '" . (isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : '') . "',
                        roles: {
                            admin: " . ($is_admin ? 'true' : 'false') . ",
                            hospitalAdmin: " . ($is_hospital_admin ? 'true' : 'false') . ",
                            courseCreator: " . ($is_coursecreator ? 'true' : 'false') . "
                        }
                    }
                };
            </script>";

            include $student_pages[$page];
        elseif (isset($standalone_pages[$page]) && file_exists($standalone_pages[$page])):
            include $standalone_pages[$page];
        else:
            // 預設首頁邏輯
            if ($page === '' && !$is_admin && !$is_hospital_admin) {
                // 如果是學生且沒帶參數，預設導向新的學生儀表板
                header("Location: index.php?page=student_dashboard");
                exit;
            }
            ?>
            <?php include 'templates/header.php'; ?>
            <?php include 'templates/dashboard.php'; ?>
            <?php include 'templates/footer.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>