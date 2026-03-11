<?php
/**
 * 教師課程建立頁面 - 複刻 Moodle course/edit.php 外觀
 * templates/teacher_course_create_page.php
 * 
 * 從 hospital_admin_course_create_page.php 複製並精簡
 * 差異：使用 coursecreator_category_ids 過濾類別
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_api.php';

// 確認登入
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $web_root . '/index.php?page=login');
    exit;
}

// 取得教師被指派的類別 IDs
$teacher_cat_ids = $_SESSION['coursecreator_category_ids'] ?? [];
$institution_name = $_SESSION['institution_name'] ?? '教師';

// 取得類別
$categories = [];
try {
    $all_cats = call_moodle($moodle_url, $moodle_token, 'core_course_get_categories', []);
    if (!isset($all_cats['exception'])) {
        // 過濾只顯示教師被指派的類別及其子類別
        $allowed_cat_ids = [];
        foreach ($teacher_cat_ids as $tid) {
            $allowed_cat_ids[(int) $tid] = true;
        }
        do {
            $added = false;
            foreach ($all_cats as $cat) {
                $parent = $cat['parent'] ?? 0;
                $cat_id = $cat['id'];
                if (isset($allowed_cat_ids[$parent]) && !isset($allowed_cat_ids[$cat_id])) {
                    $allowed_cat_ids[$cat_id] = true;
                    $added = true;
                }
            }
        } while ($added);

        $cat_map = [];
        foreach ($all_cats as $cat) {
            if (isset($allowed_cat_ids[$cat['id']])) {
                $cat_map[$cat['id']] = $cat;
            }
        }

        foreach ($cat_map as $cat) {
            $path = [];
            $current = $cat;
            while ($current) {
                array_unshift($path, $current['name']);
                $current = isset($cat_map[$current['parent']]) ? $cat_map[$current['parent']] : null;
            }
            $categories[] = [
                'id' => $cat['id'],
                'name' => $cat['name'],
                'path' => implode(' / ', $path)
            ];
        }
        usort($categories, function ($a, $b) {
            return strcmp($a['path'], $b['path']);
        });
    }
} catch (Exception $e) {
    // 忽略錯誤
}

// 預設類別
$default_cat_id = $_GET['category'] ?? ($categories[0]['id'] ?? '');
$default_cat_path = '';
foreach ($categories as $cat) {
    if ($cat['id'] == $default_cat_id) {
        $default_cat_path = $cat['path'];
        break;
    }
}
?>
<!-- 頁面專用 CSS -->
<link rel="stylesheet" href="<?php echo $web_root; ?>/assets/css/pages/course_create.css?v=<?php echo time(); ?>">
<!-- Quill 編輯器 -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<div class="page-section">

    <!-- Breadcrumb -->
    <nav class="breadcrumb-nav">
        <a href="<?php echo $web_root; ?>/index.php">雲嘉學習網</a>
        <span>/</span>
        <a href="<?php echo $web_root; ?>/index.php?page=management">課程管理</a>
        <span>/</span>
        <span style="color: var(--bs-gray-800);">新增課程</span>
    </nav>

    <!-- Tabs -->
    <div class="nav-tabs-container">
        <div class="nav-tabs">
            <a href="#" class="nav-tab active" data-tab="category" onclick="switchTab('category', this)">類別</a>
            <a href="#" class="nav-tab" data-tab="batch" onclick="switchTab('batch', this)">批次建立課程</a>
            <div class="nav-tab-dropdown">
                <a href="#" class="nav-tab" onclick="toggleMoreDropdown(event)">更多 <i class="fas fa-chevron-down"
                        style="font-size: 0.7rem; margin-left: 0.25rem;"></i></a>
                <div class="dropdown-menu" id="moreDropdown">
                    <a href="<?php echo $web_root; ?>/index.php?page=management" class="dropdown-item">課程列表</a>

                    <div class="dropdown-divider"></div>
                    <span class="dropdown-item disabled">複製課程 <small style="color: #fbbf24;">即將推出</small></span>
                    <span class="dropdown-item disabled">批次修改 <small style="color: #fbbf24;">即將推出</small></span>
                    <span class="dropdown-item disabled">匯出報表 <small style="color: #fbbf24;">即將推出</small></span>
                    <span class="dropdown-item disabled">課程歸檔 <small style="color: #fbbf24;">即將推出</small></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Card - Category Tab -->
    <div id="tab-category" class="form-card">
        <div class="page-header">
            <h1 class="page-title">新增課程</h1>
            <a href="#" class="expand-all" onclick="toggleAllSections()">展開全部</a>
        </div>

        <form id="courseCreateForm" method="POST"
            action="<?php echo $web_root; ?>/api/v2/index.php?route=courses/create">
            <input type="hidden" name="action" value="create">

            <!-- 一般 Section -->
            <div class="form-section">
                <div class="section-header" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down section-chevron"></i>
                    <h2 class="section-title">一般</h2>
                </div>
                <div class="section-content">
                    <!-- 課程全名 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>課程全名</label>
                            <span class="form-label-addon">
                                <span class="icon-req">!</span>
                                <span class="icon-help" title="課程的完整名稱，顯示在課程列表中">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <input type="text" name="fullname" class="form-control" style="max-width: 350px;" required>
                        </div>
                    </div>

                    <!-- 課程簡稱 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>課程簡稱</label>
                            <span class="form-label-addon">
                                <span class="icon-req">!</span>
                                <span class="icon-help" title="用於導覽和報表的簡短名稱">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <input type="text" name="shortname" class="form-control" style="max-width: 180px;" required>
                        </div>
                    </div>

                    <!-- 課程類別 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>課程類別</label>
                            <span class="form-label-addon">
                                <span class="icon-req">!</span>
                                <span class="icon-help" title="選擇課程所屬的類別">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <div id="categoryTagContainer"
                                style="<?php echo $default_cat_path ? '' : 'display:none;'; ?>">
                                <span class="category-tag">
                                    <span>× </span>
                                    <span id="categoryTagText"><?php echo htmlspecialchars($default_cat_path); ?></span>
                                    <span class="remove" onclick="clearCategory()">×</span>
                                </span>
                            </div>
                            <select name="categoryid" id="categorySelect" class="form-control" style="max-width: 300px;"
                                required onchange="onCategoryChange()">
                                <option value="">搜尋</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"
                                        data-path="<?php echo htmlspecialchars($cat['path']); ?>" <?php echo $cat['id'] == $default_cat_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['path']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- 課程可見度 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>課程可見度</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="學生是否能看到此課程">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <span class="visibility-btn" id="visibilityBtn" onclick="toggleVisibility()">顯示</span>
                            <input type="hidden" name="visible" id="visibleInput" value="1">
                        </div>
                    </div>

                    <!-- 課程開始日期 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>課程開始日期</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="課程開始的日期">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <div class="date-selector">
                                <input type="number" name="start_day" class="form-control form-control-sm"
                                    value="<?php echo date('d'); ?>" min="1" max="31">
                                <select name="start_month" class="form-control form-control-sm">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>>
                                            <?php echo $m; ?>月
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <input type="number" name="start_year" class="form-control form-control-sm"
                                    value="<?php echo date('Y'); ?>" min="2020" max="2030" style="width: 80px;">
                                <input type="number" name="start_hour" class="form-control form-control-sm" value="00"
                                    min="0" max="23">
                                <input type="number" name="start_minute" class="form-control form-control-sm" value="00"
                                    min="0" max="59">
                                <button type="button" class="calendar-btn"><i class="far fa-calendar"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- 課程結束日期 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>課程結束日期</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="課程結束的日期">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <div class="date-selector" id="endDateGroup">
                                <label class="form-check-inline" style="margin-right: 0.5rem;">
                                    <input type="checkbox" name="enddate_enabled" id="enddateEnabled"
                                        onchange="toggleEndDate()">
                                    啟用
                                </label>
                                <span id="endDateFields" class="date-selector" style="opacity: 0.5;">
                                    <input type="number" name="end_day"
                                        class="form-control form-control-sm end-date-field"
                                        value="<?php echo date('d'); ?>" min="1" max="31" disabled>
                                    <select name="end_month" class="form-control form-control-sm end-date-field"
                                        disabled>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>><?php echo $m; ?>月</option>
                                        <?php endfor; ?>
                                    </select>
                                    <input type="number" name="end_year"
                                        class="form-control form-control-sm end-date-field"
                                        value="<?php echo date('Y') + 1; ?>" min="2020" max="2030" style="width: 80px;"
                                        disabled>
                                    <input type="number" name="end_hour"
                                        class="form-control form-control-sm end-date-field" value="00" min="0" max="23"
                                        disabled>
                                    <input type="number" name="end_minute"
                                        class="form-control form-control-sm end-date-field" value="00" min="0" max="59"
                                        disabled>
                                    <button type="button" class="calendar-btn end-date-field" disabled><i
                                            class="far fa-calendar"></i></button>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 課程編號 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>課程編號</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="課程的識別編號">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <input type="text" name="idnumber" class="form-control" style="max-width: 180px;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 說明 Section -->
            <div class="form-section">
                <div class="section-header" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down section-chevron"></i>
                    <h2 class="section-title">說明</h2>
                </div>
                <div class="section-content">
                    <!-- 課程摘要 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>課程摘要</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="課程的簡短描述">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <div id="summaryEditor" style="height: 150px; max-width: 600px; background: white;">
                            </div>
                            <input type="hidden" name="summary" id="summaryInput">
                        </div>
                    </div>

                    <!-- 課程圖片 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>課程圖片</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="上傳課程封面圖片">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <div
                                style="padding: 16px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; max-width: 400px;">
                                <p style="margin: 0; color: #64748b; font-size: 0.875rem;">
                                    <i class="fas fa-info-circle" style="margin-right: 6px;"></i>
                                    課程圖片需在建立課程後，至課程設定頁面上傳。
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 課程格式 Section -->
            <div class="form-section">
                <div class="section-header collapsed" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down section-chevron"></i>
                    <h2 class="section-title">課程格式</h2>
                </div>
                <div class="section-content collapsed">
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>格式</label>
                        </div>
                        <div class="col-form-input">
                            <select name="format" class="form-control" style="max-width: 250px;">
                                <option value="topics">主題格式</option>
                                <option value="weeks">每週格式</option>
                                <option value="single">單一活動格式</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 外觀 Section -->
            <div class="form-section">
                <div class="section-header collapsed" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down section-chevron"></i>
                    <h2 class="section-title">外觀</h2>
                </div>
                <div class="section-content collapsed">
                    <!-- 語言 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>強制語言</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="強制所有使用者使用此語言">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <select name="lang" class="form-control" style="max-width: 200px;">
                                <option value="">不強制</option>
                                <option value="zh_tw">繁體中文 (zh_tw)</option>
                                <option value="en">English (en)</option>
                            </select>
                        </div>
                    </div>

                    <!-- 新聞筆數 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>新聞筆數</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="課程首頁顯示的最新消息筆數">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <select name="newsitems" class="form-control" style="max-width: 100px;">
                                <option value="0">0</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5" selected>5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                                <option value="9">9</option>
                                <option value="10">10</option>
                            </select>
                        </div>
                    </div>

                    <!-- 顯示成績簿 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>顯示成績簿</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="學生是否可以查看成績簿">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <select name="showgrades" class="form-control" style="max-width: 100px;">
                                <option value="1" selected>是</option>
                                <option value="0">否</option>
                            </select>
                        </div>
                    </div>

                    <!-- 顯示活動報表 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>顯示活動報表</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="學生是否可以查看自己的活動報告">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <select name="showreports" class="form-control" style="max-width: 100px;">
                                <option value="0" selected>否</option>
                                <option value="1">是</option>
                            </select>
                        </div>
                    </div>

                    <!-- 顯示活動日期 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>顯示活動日期</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="在課程頁面上顯示活動的截止日期">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <span
                                style="display: inline-block; width: 100px; padding: 8px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; color: #334155; font-size: 0.9rem;">是</span>
                            <div style="margin-top: 6px; font-size: 0.8rem; color: #64748b;">
                                <i class="fas fa-info-circle" style="margin-right: 4px;"></i>
                                依循 Moodle 預設值，如需調整請至課程設定
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 檔案與上傳 Section -->
            <div class="form-section">
                <div class="section-header collapsed" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down section-chevron"></i>
                    <h2 class="section-title">檔案與上傳</h2>
                </div>
                <div class="section-content collapsed">
                    <!-- 最大上傳檔案大小 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>最大上傳檔案大小</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="課程中允許上傳的最大檔案大小">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <select name="maxbytes" class="form-control" style="max-width: 200px;">
                                <option value="0" selected>網站預設 (512 MB)</option>
                                <option value="536870912">512 MB</option>
                                <option value="268435456">256 MB</option>
                                <option value="134217728">128 MB</option>
                                <option value="67108864">64 MB</option>
                                <option value="33554432">32 MB</option>
                                <option value="16777216">16 MB</option>
                                <option value="10485760">10 MB</option>
                                <option value="5242880">5 MB</option>
                                <option value="2097152">2 MB</option>
                                <option value="1048576">1 MB</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 完成度的追蹤 Section -->
            <div class="form-section">
                <div class="section-header collapsed" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down section-chevron"></i>
                    <h2 class="section-title">完成度的追蹤</h2>
                </div>
                <div class="section-content collapsed">
                    <!-- 啟用完成度追蹤 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>啟用完成度追蹤</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="如果啟用，活動完成條件可以在活動設定頁面設置">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <select name="enablecompletion" class="form-control" style="max-width: 100px;">
                                <option value="1" selected>是</option>
                                <option value="0">否</option>
                            </select>
                        </div>
                    </div>

                    <!-- 顯示活動完成條件 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>顯示活動完成條件</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="在課程頁面上顯示每個活動的完成條件">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <span
                                style="display: inline-block; width: 100px; padding: 8px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; color: #334155; font-size: 0.9rem;">是</span>
                            <div style="margin-top: 6px; font-size: 0.8rem; color: #64748b;">
                                <i class="fas fa-info-circle" style="margin-right: 4px;"></i>
                                依循 Moodle 預設值，如需調整請至課程設定
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 群組 Section -->
            <div class="form-section">
                <div class="section-header collapsed" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down section-chevron"></i>
                    <h2 class="section-title">群組</h2>
                </div>
                <div class="section-content collapsed">
                    <!-- 群組模式 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>群組模式</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="課程的預設群組模式設定">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <select name="groupmode" class="form-control" style="max-width: 180px;">
                                <option value="0" selected>沒有群組</option>
                                <option value="1">分開的群組</option>
                                <option value="2">可見的群組</option>
                            </select>
                        </div>
                    </div>

                    <!-- 強制群組模式 -->
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>強制群組模式</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="如果啟用，群組模式會強制套用到所有活動">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <select name="groupmodeforce" class="form-control" style="max-width: 100px;">
                                <option value="0" selected>否</option>
                                <option value="1">是</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 標籤 Section -->
            <div class="form-section">
                <div class="section-header collapsed" onclick="toggleSection(this)">
                    <i class="fas fa-chevron-down section-chevron"></i>
                    <h2 class="section-title">標籤</h2>
                </div>
                <div class="section-content collapsed">
                    <div class="fitem">
                        <div class="col-form-label">
                            <label>標籤</label>
                            <span class="form-label-addon">
                                <span class="icon-help" title="輸入與此課程相關的標籤">?</span>
                            </span>
                        </div>
                        <div class="col-form-input">
                            <div
                                style="padding: 16px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; max-width: 300px;">
                                <p style="margin: 0; color: #64748b; font-size: 0.875rem;">
                                    <i class="fas fa-info-circle" style="margin-right: 6px;"></i>
                                    課程標籤需在建立課程後，至課程設定頁面新增。
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="button" class="btn btn-primary btn-accent" onclick="submitForm('filter')">
                    <i class="fas fa-filter"></i> 儲存並篩選招生
                </button>
                <button type="button" class="btn btn-primary" onclick="submitForm('view')">
                    <i class="fas fa-eye"></i> 儲存並顯示
                </button>
                <button type="button" class="btn btn-secondary" onclick="submitForm('return')">
                    <i class="fas fa-arrow-left"></i> 儲存返回
                </button>
                <button type="button" class="btn btn-secondary" onclick="history.back()">
                    <i class="fas fa-times"></i> 取消
                </button>
            </div>

            <!-- Required Note -->
            <div class="required-note">
                <span class="icon-req">!</span>
                <span>必填</span>
            </div>
        </form>
    </div>

    <!-- ===== 批次建立課程 Tab Panel ===== -->
    <div id="tab-batch" class="form-card" style="display: none;">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-upload"
                    style="margin-right: 10px; color: var(--primary);"></i>批次建立課程 <span class="icon-help"
                    title="上傳 CSV 檔案批次建立課程">?</span></h1>
            <a href="#" class="expand-all" onclick="toggleAllBatchSections()">全部收合</a>
        </div>

        <!-- CSV 欄位說明 Section (moved to top for visibility) -->
        <div class="form-section">
            <div class="section-header" onclick="toggleSection(this)">
                <i class="fas fa-chevron-down section-chevron"></i>
                <h2 class="section-title">CSV 欄位說明</h2>
            </div>
            <div class="section-content">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="background: #f1f5f9;">
                            <th style="padding: 10px; border: 1px solid #e2e8f0; text-align: left;">欄位名稱</th>
                            <th style="padding: 10px; border: 1px solid #e2e8f0; text-align: left;">說明</th>
                            <th style="padding: 10px; border: 1px solid #e2e8f0; text-align: center;">必填</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;"><code>fullname</code></td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;">課程全名</td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0; text-align: center;"><span
                                    style="color: #ef4444;">✓</span></td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;"><code>shortname</code></td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;">課程簡稱（必須唯一）</td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0; text-align: center;"><span
                                    style="color: #ef4444;">✓</span></td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;"><code>category</code></td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;">類別名稱或 ID（例如「台北慈濟」或「6」，留空則使用預設類別）
                            </td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0; text-align: center;"></td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;"><code>visible</code></td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;">可見度（1=顯示, 0=隱藏）</td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0; text-align: center;"></td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;"><code>summary</code></td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0;">課程摘要</td>
                            <td style="padding: 10px; border: 1px solid #e2e8f0; text-align: center;"></td>
                        </tr>
                    </tbody>
                </table>
                <div style="margin-top: 16px;">
                    <a href="#" onclick="downloadTemplate(); return false;"
                        style="color: var(--primary); text-decoration: none;">
                        <i class="fas fa-download"></i> 下載範例 CSV 模板
                    </a>
                </div>
            </div>
        </div>

        <!-- 一般 Section -->
        <div class="form-section">
            <div class="section-header" onclick="toggleSection(this)">
                <i class="fas fa-chevron-down section-chevron"></i>
                <h2 class="section-title">一般</h2>
            </div>
            <div class="section-content">
                <!-- 檔案 -->
                <div class="fitem">
                    <div class="col-form-label">
                        <label>檔案</label>
                        <span class="form-label-addon">
                            <span class="icon-req">!</span>
                            <span class="icon-help" title="上傳包含課程資訊的 CSV 檔案">?</span>
                        </span>
                    </div>
                    <div class="col-form-input">
                        <div style="margin-bottom: 12px;">
                            <select id="fileSource" class="form-control" style="max-width: 200px;">
                                <option value="upload">選擇一檔案...</option>
                            </select>
                        </div>
                        <div class="file-upload-area" id="dropZone" ondrop="handleDrop(event)"
                            ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)"
                            onclick="document.getElementById('csvFileInput').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p style="color: var(--text-muted); margin: 8px 0;">若要新增檔案，請將檔案拖放到這裡。</p>
                            <input type="file" id="csvFileInput" accept=".csv" style="display: none;"
                                onchange="handleFileSelect(this)">
                        </div>
                        <div id="selectedFileName"
                            style="display: none; margin-top: 8px; padding: 8px 12px; background: #eff6ff; border-radius: 6px; color: var(--primary);">
                            <i class="fas fa-file-csv"></i> <span id="fileNameText"></span>
                            <a href="#" onclick="clearUpload(); return false;"
                                style="margin-left: 8px; color: #ef4444;"><i class="fas fa-times"></i></a>
                        </div>
                    </div>
                </div>

                <!-- CSV 分隔符號 -->
                <div class="fitem">
                    <div class="col-form-label">
                        <label>CSV分隔符號</label>
                        <span class="form-label-addon">
                            <span class="icon-help" title="CSV 檔案中用於分隔欄位的字元">?</span>
                        </span>
                    </div>
                    <div class="col-form-input">
                        <input type="text" id="csvDelimiter" class="form-control" value="," style="max-width: 80px;">
                    </div>
                </div>

                <!-- 編碼 -->
                <div class="fitem">
                    <div class="col-form-label">
                        <label>編碼</label>
                        <span class="form-label-addon">
                            <span class="icon-help" title="CSV 檔案的字元編碼">?</span>
                        </span>
                    </div>
                    <div class="col-form-input">
                        <select id="csvEncoding" class="form-control" style="max-width: 150px;">
                            <option value="UTF-8" selected>UTF-8</option>
                            <option value="BIG5">Big5 (繁體中文)</option>
                            <option value="GB2312">GB2312 (簡體中文)</option>
                        </select>
                    </div>
                </div>

                <!-- 預覽幾行 -->
                <div class="fitem">
                    <div class="col-form-label">
                        <label>預覽幾行</label>
                        <span class="form-label-addon">
                            <span class="icon-help" title="預覽時顯示的資料列數">?</span>
                        </span>
                    </div>
                    <div class="col-form-input">
                        <input type="number" id="previewRows" class="form-control" value="10" min="1" max="100"
                            style="max-width: 80px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- 匯入的選項 Section -->
        <div class="form-section">
            <div class="section-header" onclick="toggleSection(this)">
                <i class="fas fa-chevron-down section-chevron"></i>
                <h2 class="section-title">匯入的選項</h2>
            </div>
            <div class="section-content">
                <!-- 上傳模式 -->
                <div class="fitem">
                    <div class="col-form-label">
                        <label>上傳模式</label>
                        <span class="form-label-addon">
                            <span class="icon-help" title="選擇如何處理已存在的課程">?</span>
                        </span>
                    </div>
                    <div class="col-form-input">
                        <select id="uploadMode" class="form-control" style="max-width: 350px;">
                            <option value="create_only" selected>只建立新課程，忽略已經存在的課程</option>
                            <option value="create_or_update">建立新課程，或更新既有課程</option>
                        </select>
                    </div>
                </div>

                <!-- 預設類別 -->
                <div class="fitem">
                    <div class="col-form-label">
                        <label>預設類別</label>
                        <span class="form-label-addon">
                            <span class="icon-help" title="當 CSV 未指定 categoryid 時使用的類別">?</span>
                        </span>
                    </div>
                    <div class="col-form-input">
                        <select id="defaultCategory" class="form-control" style="max-width: 300px;">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $mgmt_cat_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['path']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>



        <!-- 預覽按鈕 -->
        <div class="form-actions" style="justify-content: center; margin-top: 24px;">
            <button type="button" class="btn btn-primary" onclick="previewCSV()">
                <i class="fas fa-eye"></i> 預覽
            </button>
        </div>

        <!-- 預覽區域 -->
        <div id="uploadPreview" style="display: none; margin-top: 24px;">
            <h3 style="font-size: 1rem; margin-bottom: 12px; color: var(--text-primary);">
                <i class="fas fa-list" style="color: var(--primary);"></i>
                預覽 <span id="previewCount">0</span> 筆課程
            </h3>
            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead style="position: sticky; top: 0; background: #f1f5f9;">
                        <tr>
                            <th style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: left;">課程全名
                            </th>
                            <th style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: left;">課程簡稱
                            </th>
                            <th style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: center;">類別
                            </th>
                            <th style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: center;">狀態
                            </th>
                        </tr>
                    </thead>
                    <tbody id="previewBody"></tbody>
                </table>
            </div>
            <div class="form-actions" style="margin-top: 16px;">
                <button type="button" class="btn btn-primary" onclick="executeBatchUpload(false)">
                    <i class="fas fa-rocket"></i> 開始批次建立
                </button>
                <button type="button" class="btn btn-primary btn-accent" onclick="executeBatchUpload(true)">
                    <i class="fas fa-user-plus"></i> 儲存並招生
                </button>
                <button type="button" class="btn btn-secondary" onclick="clearUpload()">
                    <i class="fas fa-times"></i> 清除
                </button>
            </div>
        </div>

        <!-- 結果區域 -->
        <div id="uploadResult" style="display: none; margin-top: 24px;">
            <h3 style="font-size: 1rem; margin-bottom: 12px;">
                <i class="fas fa-check-circle" style="color: #22c55e;"></i>
                批次建立完成
            </h3>
            <div id="resultSummary" style="padding: 16px; background: #f0fdf4; border-radius: 8px; color: #166534;">
            </div>
        </div>

        <!-- 必填提示 -->
        <div class="required-note">
            <span class="icon-req">!</span>
            <span>必填</span>
        </div>
    </div>
</div>

<script>
    // 初始化 Quill 編輯器
    var quill = new Quill('#summaryEditor', {
        theme: 'snow',
        placeholder: '輸入課程摘要...',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                ['link'],
                ['clean']
            ]
        }
    });

    // 表單提交前同步 Quill 內容到 hidden input
    document.getElementById('courseCreateForm').addEventListener('submit', function () {
        document.getElementById('summaryInput').value = quill.root.innerHTML;
    });

    function toggleSection(header) {
        header.classList.toggle('collapsed');
        header.nextElementSibling.classList.toggle('collapsed');
    }

    // 下拉選單功能
    function toggleMoreDropdown(e) {
        e.preventDefault();
        e.stopPropagation();
        const dropdown = document.getElementById('moreDropdown');
        dropdown.classList.toggle('show');
    }

    // 點擊其他地方關閉下拉選單
    document.addEventListener('click', function (e) {
        const dropdown = document.getElementById('moreDropdown');
        const dropdownContainer = document.querySelector('.nav-tab-dropdown');
        if (dropdown && !dropdownContainer.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });

    function toggleAllSections() {
        const headers = document.querySelectorAll('.section-header');
        const allCollapsed = Array.from(headers).every(h => h.classList.contains('collapsed'));
        headers.forEach(h => {
            if (allCollapsed) {
                h.classList.remove('collapsed');
                h.nextElementSibling.classList.remove('collapsed');
            } else {
                h.classList.add('collapsed');
                h.nextElementSibling.classList.add('collapsed');
            }
        });
        document.querySelector('.expand-all').textContent = allCollapsed ? '收合全部' : '展開全部';
    }

    function onCategoryChange() {
        const select = document.getElementById('categorySelect');
        const opt = select.options[select.selectedIndex];
        if (select.value) {
            document.getElementById('categoryTagText').textContent = opt.dataset.path || opt.textContent;
            document.getElementById('categoryTagContainer').style.display = 'block';
        }
    }

    function clearCategory() {
        document.getElementById('categorySelect').value = '';
        document.getElementById('categoryTagContainer').style.display = 'none';
    }

    function toggleVisibility() {
        const btn = document.getElementById('visibilityBtn');
        const input = document.getElementById('visibleInput');
        if (input.value === '1') {
            input.value = '0';
            btn.textContent = '隱藏';
        } else {
            input.value = '1';
            btn.textContent = '顯示';
        }
    }

    function toggleEndDate() {
        const enabled = document.getElementById('enddateEnabled').checked;
        const fields = document.querySelectorAll('.end-date-field');
        const wrapper = document.getElementById('endDateFields');
        fields.forEach(f => f.disabled = !enabled);
        wrapper.style.opacity = enabled ? '1' : '0.5';
    }

    // Form submission
    let submitAction = 'filter'; // default action

    async function submitForm(action) {
        submitAction = action;

        const form = document.getElementById('courseCreateForm');
        // Sync Quill content before form submission
        document.getElementById('summaryInput').value = quill.root.innerHTML;
        const formData = new FormData(form);

        // Combine dates
        const startDate = new Date(
            formData.get('start_year'),
            formData.get('start_month') - 1,
            formData.get('start_day'),
            formData.get('start_hour'),
            formData.get('start_minute')
        );
        formData.set('startdate', startDate.toISOString().split('T')[0] + ' ' +
            formData.get('start_hour').padStart(2, '0') + ':' +
            formData.get('start_minute').padStart(2, '0'));

        if (formData.get('enddate_enabled')) {
            const endDate = new Date(
                formData.get('end_year'),
                formData.get('end_month') - 1,
                formData.get('end_day'),
                formData.get('end_hour'),
                formData.get('end_minute')
            );
            formData.set('enddate', endDate.toISOString().split('T')[0] + ' ' +
                formData.get('end_hour').padStart(2, '0') + ':' +
                formData.get('end_minute').padStart(2, '0'));
        }

        // Validate required fields
        if (!formData.get('fullname') || !formData.get('shortname') || !formData.get('categoryid')) {
            showToast('請填寫必填欄位：課程全名、課程簡稱、課程類別', 'error');
            return;
        }

        // 儲存並派訓：先隱藏，完成後 API 會自動設為顯示
        // 儲存並顯示 / 儲存返回：直接使用表單的可見度（預設顯示）
        if (submitAction === 'filter') {
            formData.set('visible', '0');
        }

        // 禁用所有操作按鈕，防止連點
        const actionBtns = document.querySelectorAll('#tab-category .form-actions .btn');
        actionBtns.forEach((btn) => {
            btn.disabled = true;
            btn.style.opacity = '0.6';
            btn.style.cursor = 'not-allowed';
        });

        // 還原按鈕狀態的輔助函式
        function restoreButtons() {
            actionBtns.forEach((btn) => {
                btn.disabled = false;
                btn.style.opacity = '';
                btn.style.cursor = '';
            });
        }

        try {
            const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/create', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                // ApiResponse::success 包一層 data，所以 course_id 在 data.data.course_id
                const payload = data.data || {};
                const courseId = payload.course_id || (Array.isArray(payload.data) && payload.data[0] ? payload.data[0].id : null);
                console.log('[課程建立] payload:', payload, '取得 courseId:', courseId);

                switch (submitAction) {
                    case 'filter':
                        // 跳轉到招生篩選頁面
                        console.log('[課程建立] API 回應:', data, '課程ID:', courseId);
                        if (courseId && courseId > 0) {
                            showToast('課程已建立！正在前往招生頁面...', 'success');
                            setTimeout(() => {
                                window.location.href = PortalConfig.webRoot + '/index.php?page=teacher_course_enrol&course_id=' + courseId;
                            }, 1500);
                        } else {
                            showToast('課程已建立！', 'success');
                            setTimeout(() => { window.location.href = PortalConfig.webRoot + '/index.php?page=courses'; }, 1500);
                        }
                        break;
                    case 'view':
                        // 跳轉到 Moodle 課程頁面（透過 SSO）
                        if (courseId) {
                            showToast('課程已建立！正在前往課程頁面...', 'success');
                            const targetUrl = `<?php echo $moodle_url; ?>/course/view.php?id=${courseId}`;
                            try {
                                const ssoRes = await fetch(`${PortalConfig.webRoot}/get_sso_url.php?url=` + encodeURIComponent(targetUrl));
                                const ssoData = await ssoRes.json();
                                if (ssoData.success && ssoData.sso_url) {
                                    window.location.href = ssoData.sso_url;
                                } else {
                                    window.location.href = targetUrl;
                                }
                            } catch (e) {
                                window.location.href = targetUrl;
                            }
                        } else {
                            showToast('課程已建立，但無法取得課程ID', 'warning');
                            setTimeout(() => {
                                const catId = formData.get('categoryid');
                                window.location.href = PortalConfig.webRoot + '/index.php?page=management' + (catId ? '&select_cat=' + catId : '');
                            }, 1500);
                        }
                        break;
                    case 'return':
                    default:
                        // 返回管理頁面並選中該類別
                        showToast('課程已成功建立！', 'success');
                        setTimeout(() => {
                            const catId2 = formData.get('categoryid');
                            window.location.href = PortalConfig.webRoot + '/index.php?page=management' + (catId2 ? '&select_cat=' + catId2 : '');
                        }, 1500);
                        break;
                }
            } else {
                showToast('錯誤：' + (data.error || '建立失敗'), 'error');
                restoreButtons();
            }
        } catch (err) {
            console.error(err);
            showToast('發生錯誤', 'error');
            restoreButtons();
        }
    }

    // Init
    onCategoryChange();

    // ========================================
    // Tab 切換功能
    // ========================================
    function switchTab(tabName, clickedTab) {
        event.preventDefault();

        // 切換 tab active 狀態
        document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
        clickedTab.classList.add('active');

        // 切換面板顯示
        document.querySelectorAll('.form-card').forEach(panel => {
            panel.style.display = 'none';
        });

        const targetPanel = document.getElementById('tab-' + tabName);
        if (targetPanel) {
            targetPanel.style.display = 'block';
        }
    }

    // ========================================
    // 批次上傳相關功能
    // ========================================
    let csvData = [];
    let csvFileContent = null;
    let csvFileName = '';

    // 類別名稱對應表（名稱 → ID）
    const categoryMap = {
        <?php foreach ($categories as $cat): ?>
                                                                    "<?= addslashes($cat['name']) ?>": <?= $cat['id'] ?>,
        <?php endforeach; ?>
    };

    // 解析類別：支援 ID 或名稱
    function resolveCategoryId(value) {
        if (!value || value.trim() === '') return null;
        value = value.trim();
        // 如果是純數字，當作 ID
        if (/^\d+$/.test(value)) {
            return parseInt(value);
        }
        // 否則查找名稱
        if (categoryMap[value] !== undefined) {
            return categoryMap[value];
        }
        // 找不到對應，返回原值（讓後端處理錯誤）
        return value;
    }

    // 取得類別名稱（用於顯示）
    function getCategoryName(idOrName) {
        if (!idOrName) return '';
        // 反向查找
        for (const [name, id] of Object.entries(categoryMap)) {
            if (id == idOrName || name == idOrName) {
                return name;
            }
        }
        return idOrName;
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('dropZone').classList.add('dragover');
    }

    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('dropZone').classList.remove('dragover');
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('dropZone').classList.remove('dragover');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            loadFile(files[0]);
        }
    }

    function handleFileSelect(input) {
        if (input.files.length > 0) {
            loadFile(input.files[0]);
        }
    }

    function loadFile(file) {
        if (!file.name.endsWith('.csv')) {
            alert('請上傳 CSV 檔案');
            return;
        }

        csvFileName = file.name;
        const encoding = document.getElementById('csvEncoding').value;

        const reader = new FileReader();
        reader.onload = function (e) {
            csvFileContent = e.target.result;
            // 顯示檔案名稱
            document.getElementById('fileNameText').textContent = csvFileName;
            document.getElementById('selectedFileName').style.display = 'block';
        };
        reader.readAsText(file, encoding);
    }

    function previewCSV() {
        if (!csvFileContent) {
            alert('請先選擇 CSV 檔案');
            return;
        }
        parseCSV(csvFileContent);
    }

    function parseCSV(text) {
        const delimiter = document.getElementById('csvDelimiter').value || ',';
        const previewRowsLimit = parseInt(document.getElementById('previewRows').value) || 10;

        const lines = text.split('\n').filter(line => line.trim());
        if (lines.length < 2) {
            alert('CSV 檔案至少需要標題行和一筆資料');
            return;
        }

        const headers = parseCSVLine(lines[0], delimiter).map(h => h.trim().toLowerCase().replace(/"/g, ''));
        csvData = [];

        for (let i = 1; i < lines.length; i++) {
            const values = parseCSVLine(lines[i], delimiter);
            const row = {};
            headers.forEach((h, idx) => {
                row[h] = values[idx] ? values[idx].trim().replace(/^"|"$/g, '') : '';
            });

            // 處理 category 欄位（支援名稱或 ID）
            const categoryValue = row.category || row.categoryid || '';
            row.categoryid = resolveCategoryId(categoryValue);
            row.categoryName = getCategoryName(categoryValue);

            if (row.fullname && row.shortname) {
                csvData.push(row);
            }
        }

        if (csvData.length === 0) {
            alert('未找到有效的課程資料。請確保 CSV 包含 fullname 和 shortname 欄位。');
            return;
        }

        showPreview(previewRowsLimit);
    }

    function parseCSVLine(line, delimiter = ',') {
        const result = [];
        let current = '';
        let inQuotes = false;

        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            if (char === '"') {
                inQuotes = !inQuotes;
            } else if (char === delimiter && !inQuotes) {
                result.push(current);
                current = '';
            } else {
                current += char;
            }
        }
        result.push(current);
        return result;
    }

    function showPreview(limit = 10) {
        document.getElementById('uploadPreview').style.display = 'block';
        document.getElementById('uploadResult').style.display = 'none';
        document.getElementById('previewCount').textContent = csvData.length;

        const defaultCat = document.getElementById('defaultCategory').value;
        const defaultCatName = getCategoryName(defaultCat) || defaultCat;
        const displayData = csvData.slice(0, limit);

        const tbody = document.getElementById('previewBody');
        tbody.innerHTML = displayData.map((row, idx) => {
            const catDisplay = row.categoryName || defaultCatName;
            return `
                <tr>
                    <td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">${escapeHtml(row.fullname)}</td>
                    <td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">${escapeHtml(row.shortname)}</td>
                    <td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: center;">${escapeHtml(catDisplay)}</td>
                    <td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: center; color: #94a3b8;">待建立</td>
                </tr>
            `}).join('');

        if (csvData.length > limit) {
            tbody.innerHTML += `
                    <tr>
                        <td colspan="4" style="padding: 12px; text-align: center; color: var(--text-muted); font-style: italic;">
                            ... 還有 ${csvData.length - limit} 筆資料未顯示
                        </td>
                    </tr>
                `;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function clearUpload() {
        csvData = [];
        csvFileContent = null;
        csvFileName = '';
        document.getElementById('csvFileInput').value = '';
        document.getElementById('selectedFileName').style.display = 'none';
        document.getElementById('uploadPreview').style.display = 'none';
        document.getElementById('uploadResult').style.display = 'none';
    }

    function toggleAllBatchSections() {
        const batchPanel = document.getElementById('tab-batch');
        const headers = batchPanel.querySelectorAll('.section-header');
        const allCollapsed = Array.from(headers).every(h => h.classList.contains('collapsed'));
        headers.forEach(h => {
            if (allCollapsed) {
                h.classList.remove('collapsed');
                h.nextElementSibling.classList.remove('collapsed');
            } else {
                h.classList.add('collapsed');
                h.nextElementSibling.classList.add('collapsed');
            }
        });
        const link = batchPanel.querySelector('.expand-all');
        link.textContent = allCollapsed ? '全部收合' : '展開全部';
    }

    async function executeBatchUpload(withEnrol = false) {
        if (csvData.length === 0) {
            alert('沒有可上傳的資料');
            return;
        }

        const defaultCat = document.getElementById('defaultCategory').value;
        const btn = event.target;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 處理中...';

        let successCount = 0;
        let failCount = 0;
        const errors = [];
        const createdCourseIds = [];

        for (let i = 0; i < csvData.length; i++) {
            const row = csvData[i];
            try {
                const formData = new FormData();
                formData.append('action', 'create');
                formData.append('fullname', row.fullname);
                formData.append('shortname', row.shortname);
                formData.append('categoryid', row.categoryid || defaultCat);
                formData.append('visible', row.visible || '1');
                formData.append('summary', row.summary || '');

                const res = await fetch(PortalConfig.webRoot + '/api/v2/index.php?route=courses/create', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    successCount++;
                    const courseId = data.course_id || (data.data && data.data[0] ? data.data[0].id : null);
                    if (courseId) createdCourseIds.push(courseId);
                    updateRowStatus(i, 'success', '已建立');
                } else {
                    failCount++;
                    errors.push(`${row.shortname}: ${data.error || '未知錯誤'}`);
                    updateRowStatus(i, 'error', data.error || '失敗');
                }
            } catch (err) {
                failCount++;
                errors.push(`${row.shortname}: ${err.message}`);
                updateRowStatus(i, 'error', '網路錯誤');
            }
        }

        btn.disabled = false;
        btn.innerHTML = withEnrol
            ? '<i class="fas fa-user-plus"></i> 儲存並招生'
            : '<i class="fas fa-rocket"></i> 開始批次建立';

        // 成功後處理
        if (successCount > 0) {
            if (withEnrol && createdCourseIds.length > 0) {
                // 跳轉到招生頁面，帶上所有新建課程的 ID
                const courseIdsParam = createdCourseIds.join(',');
                alert(`成功建立 ${successCount} 個課程${failCount > 0 ? `，${failCount} 個失敗` : ''}，即將前往招生頁面...`);
                window.location.href = `${PortalConfig.webRoot}/index.php?page=teacher_course_enrol&course_ids=${courseIdsParam}`;
            } else {
                alert(`成功建立 ${successCount} 個課程${failCount > 0 ? `，${failCount} 個失敗` : ''}`);
                window.location.href = PortalConfig.webRoot + '/index.php?page=management';
            }
            return;
        }

        // 全部失敗才顯示錯誤結果
        document.getElementById('uploadResult').style.display = 'block';
        document.getElementById('resultSummary').innerHTML = `
                <span style="color: #dc2626;">全部 ${failCount} 個課程建立失敗</span>
                <br><br><strong>錯誤詳情：</strong><br>${errors.join('<br>')}
            `;
    }

    function updateRowStatus(index, status, text) {
        const rows = document.getElementById('previewBody').querySelectorAll('tr');
        if (rows[index]) {
            const statusCell = rows[index].lastElementChild;
            if (status === 'success') {
                statusCell.innerHTML = '<span style="color: #22c55e;"><i class="fas fa-check"></i> ' + text + '</span>';
            } else {
                statusCell.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-times"></i> ' + text + '</span>';
            }
        }
    }

    function downloadTemplate() {
        event.preventDefault();
        const template = 'fullname,shortname,categoryid,visible,summary\n"範例課程一","SAMPLE001",<?php echo !empty($teacher_cat_ids) ? (int) $teacher_cat_ids[0] : 0; ?>,1,"這是範例課程的摘要"\n"範例課程二","SAMPLE002",<?php echo !empty($teacher_cat_ids) ? (int) $teacher_cat_ids[0] : 0; ?>,1,"另一個範例課程"';
        const blob = new Blob(['\uFEFF' + template], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'batch_courses_template.csv';
        a.click();
        URL.revokeObjectURL(url);
    }
</script>
</div>