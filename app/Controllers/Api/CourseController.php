<?php
/**
 * Course Management API 控制器
 * app/Controllers/Api/CourseController.php
 * 
 * 處理課程管理相關的 API 請求
 * 遷移自: manage_course.php + course_visibility_exclusions.php
 */

class CourseController extends Controller
{
    private MoodleService $moodle;

    public function __construct()
    {
        parent::__construct();
        $this->moodle = new MoodleService();
    }

    // ==========================================
    // 課程 CRUD
    // ==========================================

    /**
     * 列出課程（含必修標記）
     * GET ?route=courses/list&category_id=X&recursive=1
     */
    public function list(): void
    {
        $this->requireHospitalAdmin();

        $mgmtCatId = $this->getManagementCategoryId();
        if ($mgmtCatId <= 0) {
            ApiResponse::error('未設定管理類別 ID');
            return;
        }

        $categoryId = $this->inputInt('category_id', $mgmtCatId);
        $recursive = $this->inputString('recursive') === '1';

        try {
            $categoryIds = [$categoryId];
            $catNames = [];

            if ($recursive) {
                $allCats = $this->moodle->call('core_course_get_categories', []);
                if (!isset($allCats['exception'])) {
                    // 建立 parent -> children mapping
                    $catByParent = [];
                    foreach ($allCats as $cat) {
                        $parent = $cat['parent'] ?? 0;
                        $catByParent[$parent][] = $cat['id'];
                    }

                    // BFS 找所有子類別
                    $queue = [$categoryId];
                    while (!empty($queue)) {
                        $current = array_shift($queue);
                        if (isset($catByParent[$current])) {
                            foreach ($catByParent[$current] as $childId) {
                                if (!in_array($childId, $categoryIds)) {
                                    $categoryIds[] = $childId;
                                    $queue[] = $childId;
                                }
                            }
                        }
                    }

                    // 建立名稱 mapping
                    foreach ($allCats as $cat) {
                        $catNames[$cat['id']] = $cat['name'];
                    }
                }
            }

            // 抓取每個類別的課程
            $allCourses = [];
            foreach ($categoryIds as $catId) {
                $courses = $this->moodle->call('core_course_get_courses_by_field', [
                    'field' => 'category',
                    'value' => $catId
                ]);

                if (!isset($courses['exception'])) {
                    $courseList = $courses['courses'] ?? $courses;
                    foreach ($courseList as $c) {
                        if ($c['id'] == 1)
                            continue; // 排除站台首頁

                        // 取得學生數量
                        $enrolledCount = 0;
                        try {
                            $enrolledUsers = $this->moodle->call('core_enrol_get_enrolled_users', [
                                'courseid' => $c['id']
                            ]);
                            if (!isset($enrolledUsers['exception']) && is_array($enrolledUsers)) {
                                $enrolledCount = count($enrolledUsers);
                            }
                        } catch (Exception $e) {
                            // 忽略
                        }

                        $allCourses[] = [
                            'id' => $c['id'],
                            'fullname' => $c['fullname'],
                            'shortname' => $c['shortname'],
                            'categoryid' => $c['categoryid'],
                            'categoryname' => $catNames[$c['categoryid']] ?? '',
                            'visible' => $c['visible'],
                            'startdate' => $c['startdate'],
                            'enrolledusercount' => $enrolledCount,
                            'enrollmentmethods' => $c['enrollmentmethods'] ?? []
                        ];
                    }
                }
            }

            // 查詢必修課程狀態
            $mandatoryCourseIds = [];
            try {
                $conn = $this->db->getConnection();
                $stmt = $conn->prepare("SELECT moodle_course_id FROM portal_mandatory_courses WHERE moodle_category_id = ? AND is_mandatory = 1");
                $stmt->bind_param("i", $categoryId);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $mandatoryCourseIds[$row['moodle_course_id']] = true;
                }
                $stmt->close();
            } catch (Exception $e) {
                // 忽略
            }

            foreach ($allCourses as &$course) {
                $course['is_mandatory'] = isset($mandatoryCourseIds[$course['id']]) ? 1 : 0;
            }

            // 查詢可見度規則狀態
            $visibilityRules = [];
            try {
                $vrRes = $conn->query("SELECT course_id, is_active FROM course_visibility_rules");
                if ($vrRes) {
                    while ($row = $vrRes->fetch_assoc()) {
                        $visibilityRules[(int) $row['course_id']] = (int) $row['is_active'];
                    }
                }
            } catch (Exception $e) {
            }

            foreach ($allCourses as &$course) {
                $course['has_rules'] = isset($visibilityRules[$course['id']]) ? 1 : 0;
                $course['is_active'] = $visibilityRules[$course['id']] ?? null;
            }

            // 教師擁有權標記：如果有傳 teacher_uid，查詢該教師擁有的課程
            $teacherUid = $this->inputInt('teacher_uid');
            if ($teacherUid > 0) {
                try {
                    $moodleDb = \MoodleDatabase::getInstance();
                    $ownedIds = $moodleDb->getTeacherCourseIds($teacherUid);
                    $ownedMap = array_flip($ownedIds);
                    foreach ($allCourses as &$course) {
                        $course['is_owner'] = isset($ownedMap[$course['id']]) ? 1 : 0;
                    }
                } catch (Exception $e) {
                    error_log("[CourseList] Teacher ownership check failed: " . $e->getMessage());
                }
            }

            ApiResponse::success($allCourses);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 取得單一課程
     * GET ?route=courses/get&id=X
     */
    public function get(): void
    {
        $this->requireHospitalAdmin();

        $courseId = $this->inputInt('id');
        if ($courseId <= 0) {
            ApiResponse::error('請提供課程 ID');
            return;
        }

        try {
            $result = $this->moodle->call('core_course_get_courses', [
                'options' => ['ids' => [$courseId]]
            ]);

            if (isset($result['exception']) || empty($result)) {
                ApiResponse::error('課程不存在');
                return;
            }

            $course = $result[0];
            ApiResponse::success([
                'course' => [
                    'id' => $course['id'],
                    'fullname' => $course['fullname'],
                    'shortname' => $course['shortname'],
                    'categoryid' => $course['categoryid']
                ]
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 取得院區類別樹
     * GET ?route=courses/get_categories
     */
    public function getCategories(): void
    {
        $this->requireHospitalAdmin();

        $mgmtCatId = $this->getManagementCategoryId();
        if ($mgmtCatId <= 0) {
            ApiResponse::error('未設定管理類別 ID');
            return;
        }

        try {
            $allCats = $this->moodle->call('core_course_get_categories', []);

            if (isset($allCats['exception'])) {
                ApiResponse::error('無法取得類別');
                return;
            }

            // 找出院區所有子類別
            $allowedCatIds = [$mgmtCatId => true];
            do {
                $added = false;
                foreach ($allCats as $cat) {
                    $parent = $cat['parent'] ?? 0;
                    $catId = $cat['id'];
                    if (isset($allowedCatIds[$parent]) && !isset($allowedCatIds[$catId])) {
                        $allowedCatIds[$catId] = true;
                        $added = true;
                    }
                }
            } while ($added);

            // 過濾並建立路徑
            $catMap = [];
            foreach ($allCats as $cat) {
                if (isset($allowedCatIds[$cat['id']])) {
                    $catMap[$cat['id']] = $cat;
                }
            }

            $categories = [];
            foreach ($catMap as $cat) {
                $path = [];
                $current = $cat;
                while ($current) {
                    array_unshift($path, $current['name']);
                    $current = isset($catMap[$current['parent']]) ? $catMap[$current['parent']] : null;
                }
                $categories[] = [
                    'id' => $cat['id'],
                    'name' => $cat['name'],
                    'path' => implode(' / ', $path),
                    'parent' => $cat['parent'],
                    'depth' => $cat['depth'] ?? 0
                ];
            }

            usort($categories, fn($a, $b) => strcmp($a['path'], $b['path']));

            ApiResponse::success(['categories' => $categories]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 建立課程
     * POST ?route=courses/create
     */
    public function create(): void
    {
        $this->requireHospitalAdmin();

        $mgmtCatId = $this->getManagementCategoryId();
        $fullname = trim($this->inputString('fullname'));
        $shortname = trim($this->inputString('shortname'));
        $categoryId = $this->inputInt('category_id', $this->inputInt('categoryid', $mgmtCatId));
        $visible = $this->inputInt('visible', 0);
        $summary = $this->inputString('summary');
        $idnumber = trim($this->inputString('idnumber'));
        $startdate = $this->inputString('startdate');
        $enddate = $this->inputString('enddate');
        $enddateEnabled = !empty($this->inputString('enddate_enabled'));

        // 擴充參數
        $format = $this->inputString('format') ?: 'topics';
        $lang = $this->inputString('lang');
        $newsitems = $this->inputInt('newsitems', 5);
        $showgrades = $this->inputInt('showgrades', 1);
        $showreports = $this->inputInt('showreports', 0);
        $maxbytes = $this->inputInt('maxbytes', 0);
        $enablecompletion = $this->inputInt('enablecompletion', 1);
        $groupmode = $this->inputInt('groupmode', 0);
        $groupmodeforce = $this->inputInt('groupmodeforce', 0);

        if (empty($fullname) || empty($shortname)) {
            ApiResponse::error('課程全名與簡稱不能為空');
            return;
        }

        try {
            $newCourse = [
                'fullname' => $fullname,
                'shortname' => $shortname,
                'categoryid' => (int) $categoryId,
                'visible' => $visible,
                'summary' => $summary,
                'summaryformat' => 1,
                'format' => $format,
                'newsitems' => $newsitems,
                'showgrades' => $showgrades,
                'showreports' => $showreports,
                'enablecompletion' => $enablecompletion,
                'groupmode' => $groupmode,
                'groupmodeforce' => $groupmodeforce
            ];

            if (!empty($lang))
                $newCourse['lang'] = $lang;
            if ($maxbytes > 0)
                $newCourse['maxbytes'] = $maxbytes;
            if (!empty($idnumber))
                $newCourse['idnumber'] = $idnumber;
            if (!empty($startdate))
                $newCourse['startdate'] = strtotime($startdate);
            if ($enddateEnabled && !empty($enddate))
                $newCourse['enddate'] = strtotime($enddate);

            $result = $this->moodle->call('core_course_create_courses', ['courses' => [$newCourse]]);

            if (isset($result['exception'])) {
                ApiResponse::error('Create Error: ' . ($result['message'] ?? 'Unknown'));
                return;
            }

            $courseId = $result[0]['id'] ?? 0;
            ApiResponse::success([
                'course_id' => $courseId,
                'data' => $result
            ], '課程已建立');
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 更新課程
     * POST ?route=courses/update
     */
    public function update(): void
    {
        $this->requireHospitalAdmin();

        $id = $this->inputInt('id');
        $fullname = trim($this->inputString('fullname'));
        $shortname = trim($this->inputString('shortname'));

        if ($id <= 0 || empty($fullname) || empty($shortname)) {
            ApiResponse::error('參數錯誤');
            return;
        }

        try {
            $result = $this->moodle->call('core_course_update_courses', [
                'courses' => [
                    [
                        'id' => $id,
                        'fullname' => $fullname,
                        'shortname' => $shortname
                    ]
                ]
            ]);

            if (isset($result['exception'])) {
                ApiResponse::error('Update Error: ' . ($result['message'] ?? 'Unknown'));
                return;
            }

            ApiResponse::success(null, '課程已更新');
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 刪除課程
     * POST ?route=courses/delete
     */
    public function delete(): void
    {
        $this->requireHospitalAdmin();

        $id = $this->inputInt('id');
        if ($id <= 0) {
            ApiResponse::error('參數錯誤');
            return;
        }

        try {
            $result = $this->moodle->call('core_course_delete_courses', [
                'courseids' => [$id]
            ]);

            if (
                isset($result['exception']) ||
                (isset($result['warnings']) && count($result['warnings']) > 0)
            ) {
                $msg = isset($result['exception'])
                    ? ($result['message'] ?? 'Unknown')
                    : ($result['warnings'][0]['message'] ?? '未知錯誤');
                ApiResponse::error('Delete Error: ' . $msg);
                return;
            }

            ApiResponse::success(null, '課程已刪除');
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 隱藏/顯示課程
     * POST ?route=courses/toggle_visible
     */
    public function toggleVisible(): void
    {
        $this->requireHospitalAdmin();

        $id = $this->inputInt('id');
        $visible = $this->inputInt('visible');

        if ($id <= 0) {
            ApiResponse::error('參數錯誤');
            return;
        }

        try {
            // 判斷是否走 is_active 切換
            $hasRules = isset($_POST['has_rules']) && $_POST['has_rules'] === '1';
            if ($hasRules) {
                try {
                    $conn = $this->db->getConnection();
                    $stmtAct = $conn->prepare("UPDATE course_visibility_rules SET is_active = ? WHERE course_id = ?");
                    $stmtAct->bind_param("ii", $visible, $id);
                    $stmtAct->execute();
                    $stmtAct->close();
                } catch (\Exception $e) {
                    error_log("is_active toggle error: " . $e->getMessage());
                }
                ApiResponse::success(['visible' => $visible], $visible ? '已啟用規則' : '已暫停規則');
                return;
            }

            $result = $this->moodle->call('core_course_update_courses', [
                'courses' => [['id' => $id, 'visible' => $visible]]
            ]);

            if (isset($result['exception'])) {
                ApiResponse::error('Update Error: ' . ($result['message'] ?? 'Unknown'));
                return;
            }

            ApiResponse::success(null, '課程狀態已更新');
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    // ==========================================
    // 招生
    // ==========================================

    /**
     * 取得課程所屬必修類別資訊
     * GET ?route=courses/get_mandatory_info&course_id=N
     */
    public function getMandatoryInfo(): void
    {
        $this->requireHospitalAdmin();

        $courseId = $this->inputInt('course_id');
        if ($courseId <= 0) {
            ApiResponse::error('缺少課程 ID');
            return;
        }

        try {
            $info = $this->getMandatoryInfoForCourse($courseId);
            ApiResponse::success($info);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 查詢課程所屬類別的必修設定（私有方法）
     */
    private function getMandatoryInfoForCourse(int $courseId): array
    {
        // 1. 取得課程的 categoryId
        $courseData = $this->moodle->call('core_course_get_courses', [
            'options' => ['ids' => [$courseId]]
        ]);
        if (isset($courseData['exception']) || empty($courseData)) {
            return ['is_mandatory' => false, 'visibility' => 'all', 'mandatory_user_ids' => []];
        }
        $categoryId = $courseData[0]['categoryid'] ?? 0;
        if ($categoryId <= 0) {
            return ['is_mandatory' => false, 'visibility' => 'all', 'mandatory_user_ids' => []];
        }

        // 2. 查 portal_category_settings
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT is_mandatory_category, visibility FROM portal_category_settings WHERE moodle_category_id = ?");
        $stmt->bind_param('i', $categoryId);
        $stmt->execute();
        $settings = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$settings || !$settings['is_mandatory_category']) {
            return ['is_mandatory' => false, 'visibility' => 'all', 'mandatory_user_ids' => [], 'category_id' => $categoryId];
        }

        // 3. 查必修對象 (moodle_user_id)
        $stmt = $conn->prepare("SELECT moodle_user_id FROM portal_category_requirements WHERE moodle_category_id = ? AND moodle_user_id > 0");
        $stmt->bind_param('i', $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();
        $mandatoryUserIds = [];
        while ($row = $result->fetch_assoc()) {
            $mandatoryUserIds[] = (int) $row['moodle_user_id'];
        }
        $stmt->close();

        return [
            'is_mandatory' => true,
            'visibility' => $settings['visibility'] ?? 'all',
            'mandatory_user_ids' => $mandatoryUserIds,
            'mandatory_user_count' => count($mandatoryUserIds),
            'category_id' => $categoryId
        ];
    }

    /**
     * 單課招生
     * POST ?route=courses/enrol_users
     */
    public function enrolUsers(): void
    {
        $this->requireHospitalAdmin();

        $courseId = $this->inputInt('course_id');
        $userIdsStr = $this->inputString('user_ids');
        $userIds = array_filter(array_map('intval', explode(',', $userIdsStr)));

        if ($courseId <= 0) {
            ApiResponse::error('請提供課程 ID');
            return;
        }
        if (empty($userIds)) {
            ApiResponse::error('請提供使用者 ID');
            return;
        }

        try {
            // 檢查必修類別規則
            $mandatoryInfo = $this->getMandatoryInfoForCourse($courseId);
            $autoMergedCount = 0;
            $blockedUsers = [];

            if ($mandatoryInfo['is_mandatory']) {
                $mandatoryUserIds = $mandatoryInfo['mandatory_user_ids'];
                $visibility = $mandatoryInfo['visibility'];

                if ($visibility === 'mandatory_only') {
                    // 僅他們可見：只能招篩選對象，擋外人
                    $blockedUsers = array_diff($userIds, $mandatoryUserIds);
                    if (!empty($blockedUsers)) {
                        ApiResponse::error(
                            '此課程屬於「僅限必修對象可見」的類別，有 ' . count($blockedUsers) .
                            ' 位使用者不在必修篩選範圍內，無法招生。'
                        );
                        return;
                    }
                } else {
                    // 全部可見：自動合併必修對象

                    $missingMandatory = array_diff($mandatoryUserIds, $userIds);

                    if (!empty($missingMandatory)) {

                        $userIds = array_unique(array_merge($userIds, $missingMandatory));

                        $autoMergedCount = count($missingMandatory);

                    }

                }

            }



            // 前端傳來的 user_ids 已經是 Moodle User IDs

            $enrolments = [];

            foreach ($userIds as $muid) {

                $enrolments[] = [

                    'roleid' => 5,

                    'userid' => $muid,

                    'courseid' => $courseId

                ];

            }



            $result = $this->moodle->call('enrol_manual_enrol_users', [

                'enrolments' => $enrolments

            ]);

            if (isset($result['exception'])) {
                ApiResponse::error('招生失敗: ' . ($result['message'] ?? '未知錯誤'));
                return;
            }

            // 直接招生後，確保這些學員不在「開放選修排除名單」中
            // （course_visibility_exclusions 代表「不適用」而不是「已加入」）
            $conn = $this->db->getConnection();
            $this->ensureVisibilityTable($conn);
            $delStmt = $conn->prepare("DELETE FROM course_visibility_exclusions WHERE course_id = ? AND user_id = ?");
            foreach ($userIds as $uid) {
                $delStmt->bind_param("ii", $courseId, $uid);
                $delStmt->execute();
            }
            $delStmt->close();

            // 設 Moodle visible=1
            $this->moodle->call('core_course_update_courses', [
                'courses' => [['id' => $courseId, 'visible' => 1]]
            ]);

            $message = '已成功招生 ' . count($userIds) . ' 位學員';
            if ($autoMergedCount > 0) {
                $message .= '（含自動合併 ' . $autoMergedCount . ' 位必修對象）';
            }

            ApiResponse::success([
                'enrolled_count' => count($userIds),
                'auto_merged_count' => $autoMergedCount
            ], $message);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 退選（從課程移除學員）
     * POST ?route=courses/unenrol_users
     */
    public function unenrolUsers(): void
    {
        $this->requireHospitalAdmin();

        $courseId = $this->inputInt('course_id');
        $userIdsStr = $this->inputString('user_ids');
        $userIds = array_filter(array_map('intval', explode(',', $userIdsStr)));

        if ($courseId <= 0) {
            ApiResponse::error('請提供課程 ID');
            return;
        }
        if (empty($userIds)) {
            ApiResponse::error('請提供使用者 ID');
            return;
        }

        try {
            // 需要將接收到的 Portal ID 轉換成 Moodle ID
            $conn = $this->db->getConnection();
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $types = str_repeat('i', count($userIds));
            $stmt = $conn->prepare("SELECT username FROM users WHERE id IN ($placeholders)");
            $stmt->bind_param($types, ...$userIds);
            $stmt->execute();
            $res = $stmt->get_result();
            $usernames = [];
            while ($row = $res->fetch_assoc()) {
                $usernames[] = $row['username'];
            }
            $stmt->close();

            $moodleUserIds = [];
            if (!empty($usernames)) {
                $moodle = new MoodleService();
                foreach ($usernames as $uname) {
                    $mUserRes = $moodle->call('core_user_get_users_by_field', [
                        'field' => 'username',
                        'values' => [$uname]
                    ]);
                    if (!isset($mUserRes['exception']) && !empty($mUserRes) && isset($mUserRes[0]['id'])) {
                        $moodleUserIds[] = (int) $mUserRes[0]['id'];
                    }
                }
            }

            // 1. 取得課程已招生的學員（需要 enrolment id）
            $enrolledUsers = $this->moodle->call('core_enrol_get_enrolled_users', [
                'courseid' => $courseId
            ]);
            if (isset($enrolledUsers['exception'])) {
                ApiResponse::error('無法取得課程學員: ' . ($enrolledUsers['message'] ?? ''));
                return;
            }

            // 建立 moodleUserId -> enrollmentId 對應
            $unenrolments = [];
            foreach ($enrolledUsers as $eu) {
                if (in_array((int) $eu['id'], $moodleUserIds)) {
                    // 找 manual enrolment 的 enrolid
                    if (!empty($eu['enrolledcourses'])) {
                        foreach ($eu['enrolledcourses'] as $ec) {
                            if ((int) $ec['id'] === $courseId) {
                                // 直接用 userid
                                $unenrolments[] = (int) $eu['id'];
                                break;
                            }
                        }
                    }
                    // 如果沒找到也加入（fallback）
                    if (!in_array((int) $eu['id'], $unenrolments)) {
                        $unenrolments[] = (int) $eu['id'];
                    }
                }
            }

            // 2. 呼叫 Moodle 退選 API
            $removedCount = 0;
            foreach ($unenrolments as $uid) {
                $result = $this->moodle->call('enrol_manual_unenrol_users', [
                    'enrolments' => [
                        ['userid' => $uid, 'courseid' => $courseId]
                    ]
                ]);
                if (!isset($result['exception'])) {
                    $removedCount++;
                }
            }

            // 3. 從 course_visibility_exclusions 移除
            $conn = $this->db->getConnection();
            $this->ensureVisibilityTable($conn);
            $delStmt = $conn->prepare("DELETE FROM course_visibility_exclusions WHERE course_id = ? AND user_id = ?");
            foreach ($userIds as $uid) {
                $delStmt->bind_param("ii", $courseId, $uid);
                $delStmt->execute();
            }
            $delStmt->close();

            ApiResponse::success([
                'removed_count' => $removedCount
            ], '已從課程移除 ' . $removedCount . ' 位學員');
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 批次招生
     * POST ?route=courses/batch_enrol (JSON body)
     */
    public function batchEnrol(): void
    {
        $this->requireHospitalAdmin();

        $input = json_decode(file_get_contents('php://input'), true);
        $courseIds = $input['course_ids'] ?? [];
        $userIds = $input['user_ids'] ?? [];

        if (empty($courseIds)) {
            ApiResponse::error('未指定課程');
            return;
        }
        if (empty($userIds)) {
            ApiResponse::error('未指定人員');
            return;
        }

        try {
            // 前端傳來的 user_ids 已經是 Moodle User IDs（與 enrolUsers 一致）
            $enrolments = [];
            foreach ($courseIds as $cid) {
                foreach ($userIds as $muid) {
                    $enrolments[] = [
                        'roleid' => 5,
                        'userid' => intval($muid),
                        'courseid' => intval($cid)
                    ];
                }
            }

            set_time_limit(0);
            ignore_user_abort(true);

            // 提高逾時門檻 (大批次招生可能耗時較長)
            $this->moodle->setTimeout(120, 10);

            // 分批呼叫
            $batchSize = 50;
            $enrolErrors = [];
            for ($i = 0; $i < count($enrolments); $i += $batchSize) {
                $batch = array_slice($enrolments, $i, $batchSize);
                $result = $this->moodle->call('enrol_manual_enrol_users', ['enrolments' => $batch]);
                if (isset($result['exception'])) {
                    $enrolErrors[] = $result['message'] ?? $result['exception'];
                    error_log("[batchEnrol] Moodle enrol error: " . json_encode($result));
                }
            }

            // 設 visible=1
            foreach ($courseIds as $cid) {
                $this->moodle->call('core_course_update_courses', [
                    'courses' => [['id' => intval($cid), 'visible' => 1]]
                ]);
            }

            if (!empty($enrolErrors)) {
                ApiResponse::error('部分招生失敗: ' . implode('; ', $enrolErrors));
                return;
            }

            ApiResponse::success(null, '已完成招生：' . count($userIds) . ' 人 x ' . count($courseIds) . ' 門課程');
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 啟用自選報名
     * POST ?route=courses/enable_self_enrol
     */
    public function enableSelfEnrol(): void
    {
        $this->requireHospitalAdmin();

        $courseId = $this->inputInt('course_id');
        if ($courseId <= 0) {
            ApiResponse::error('缺少課程 ID');
            return;
        }

        try {
            $enrolMethods = $this->moodle->call('core_enrol_get_course_enrolment_methods', [
                'courseid' => $courseId
            ]);

            $hasSelfEnrol = false;
            $selfEnrolId = null;

            if (!isset($enrolMethods['exception'])) {
                foreach ($enrolMethods as $method) {
                    if ($method['type'] === 'self') {
                        $hasSelfEnrol = true;
                        $selfEnrolId = $method['id'];
                        break;
                    }
                }
            }

            if (!$hasSelfEnrol) {
                ApiResponse::success([
                    'has_self_enrol' => false
                ], '課程已設為可見。請在 Moodle 後台手動啟用自選報名方式。');
            } else {
                ApiResponse::success([
                    'has_self_enrol' => true,
                    'enrol_id' => $selfEnrolId
                ], '自選報名已啟用');
            }
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    // ==========================================
    // 必修管理
    // ==========================================

    /**
     * 設定/取消課程必修
     * POST ?route=courses/set_mandatory
     */
    public function setMandatory(): void
    {
        $this->requireHospitalAdmin();

        $courseId = $this->inputInt('course_id');
        $categoryId = $this->inputInt('category_id');
        $isMandatory = $this->inputInt('is_mandatory');
        $displayOrder = $this->inputInt('display_order');

        if ($courseId <= 0 || $categoryId <= 0) {
            ApiResponse::error('參數錯誤');
            return;
        }

        try {
            $conn = $this->db->getConnection();

            if ($isMandatory) {
                $stmt = $conn->prepare("
                    INSERT INTO portal_mandatory_courses 
                        (moodle_course_id, moodle_category_id, is_mandatory, display_order)
                    VALUES (?, ?, 1, ?)
                    ON DUPLICATE KEY UPDATE
                        is_mandatory = 1,
                        display_order = VALUES(display_order)
                ");
                $stmt->bind_param("iii", $courseId, $categoryId, $displayOrder);
                $message = '已設為必修課程';
            } else {
                $stmt = $conn->prepare("
                    UPDATE portal_mandatory_courses 
                    SET is_mandatory = 0 
                    WHERE moodle_course_id = ? AND moodle_category_id = ?
                ");
                $stmt->bind_param("ii", $courseId, $categoryId);
                $message = '已取消必修';
            }

            $stmt->execute();
            $stmt->close();

            ApiResponse::success(null, $message);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 取得課程的必修設定
     * GET ?route=courses/get_mandatory&course_id=X
     */
    public function getMandatory(): void
    {
        $this->requireHospitalAdmin();

        $courseId = $this->inputInt('course_id');
        if ($courseId <= 0) {
            ApiResponse::error('缺少課程 ID');
            return;
        }

        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT * FROM portal_mandatory_courses WHERE moodle_course_id = ?");
            $stmt->bind_param("i", $courseId);
            $stmt->execute();
            $result = $stmt->get_result();
            $setting = $result->fetch_assoc();
            $stmt->close();

            if (!$setting) {
                $setting = [
                    'moodle_course_id' => $courseId,
                    'is_mandatory' => 0,
                    'display_order' => 0
                ];
            }

            ApiResponse::success(['setting' => $setting]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 取得類別下所有必修課程
     * GET ?route=courses/get_category_mandatory&category_id=X
     */
    public function getCategoryMandatory(): void
    {
        $this->requireHospitalAdmin();

        $categoryId = $this->inputInt('category_id');
        if ($categoryId <= 0) {
            ApiResponse::error('缺少類別 ID');
            return;
        }

        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT moodle_course_id, is_mandatory, display_order 
                FROM portal_mandatory_courses 
                WHERE moodle_category_id = ? AND is_mandatory = 1
                ORDER BY display_order ASC
            ");
            $stmt->bind_param("i", $categoryId);
            $stmt->execute();
            $result = $stmt->get_result();

            $mandatoryCourses = [];
            while ($row = $result->fetch_assoc()) {
                $mandatoryCourses[$row['moodle_course_id']] = [
                    'is_mandatory' => (int) $row['is_mandatory'],
                    'display_order' => (int) $row['display_order']
                ];
            }
            $stmt->close();

            ApiResponse::success(['mandatory_courses' => $mandatoryCourses]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    // ==========================================
    // 課程可見度
    // ==========================================

    /**
     * 新增可見度
     * POST ?route=courses/visibility/add
     */
    public function visibilityAdd(): void
    {
        $this->requireHospitalAdmin();

        $courseId = $this->inputInt('course_id');
        $userIdsStr = $this->inputString('user_ids');
        $filterSnapshot = $this->inputString('filter_snapshot') ?: null;

        if ($courseId <= 0) {
            ApiResponse::error('缺少課程 ID');
            return;
        }
        try {
            $conn = $this->db->getConnection();
            $this->ensureVisibilityTable($conn);

            // 先清除此課程的舊排除記錄
            $delStmt = $conn->prepare("DELETE FROM course_visibility_exclusions WHERE course_id = ?");
            $delStmt->bind_param("i", $courseId);
            $delStmt->execute();
            $delStmt->close();

            $addedCount = 0;
            if (!empty($userIdsStr)) {
                $userIdArray = array_map('intval', explode(',', $userIdsStr));
                foreach ($userIdArray as $userId) {
                    if ($userId > 0) {
                        $stmt = $conn->prepare("INSERT INTO course_visibility_exclusions (course_id, user_id, filter_snapshot) VALUES (?, ?, ?)");
                        $stmt->bind_param("iis", $courseId, $userId, $filterSnapshot);
                        if ($stmt->execute()) {
                            $addedCount += $stmt->affected_rows;
                        }
                        $stmt->close();
                    }
                }
            }

            ApiResponse::success([
                'added_count' => $addedCount
            ], "已更新排除名單（{$addedCount} 筆）");
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 移除可見度
     * POST ?route=courses/visibility/remove
     */
    public function visibilityRemove(): void
    {
        $this->requireHospitalAdmin();

        $courseId = $this->inputInt('course_id');
        $userId = $this->inputInt('user_id'); // Portal ID
        $moodleIdInput = $this->inputInt('moodle_id'); // From explicitly populated frontend

        if ($courseId <= 0) {
            ApiResponse::error('缺少課程 ID');
            return;
        }

        try {
            $conn = $this->db->getConnection();
            $this->ensureVisibilityTable($conn);

            $deletedCount = 0;
            if ($userId > 0) {
                // 從排除名單中移除 (使用 Portal ID)
                $stmt = $conn->prepare("DELETE FROM course_visibility_exclusions WHERE course_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $courseId, $userId);
                $stmt->execute();
                $deletedCount = $stmt->affected_rows;
                $stmt->close();
            }

            // 找出 Moodle 對應的 ID
            $moodleId = 0;
            $moodle = new MoodleService();

            if ($moodleIdInput > 0) {
                $moodleId = $moodleIdInput;
            } else if ($userId > 0) {
                // 舊邏輯：找出 Moodle 對應的 ID
                $conn2 = $this->db->getConnection();
                $uStmt = $conn2->prepare("SELECT username FROM users WHERE id = ?");
                $uStmt->bind_param("i", $userId);
                $uStmt->execute();
                $uRes = $uStmt->get_result();
                if ($uRow = $uRes->fetch_assoc()) {
                    $username = $uRow['username'];
                    $mUserRes = $moodle->call('core_user_get_users_by_field', [
                        'field' => 'username',
                        'values' => [$username]
                    ]);
                    if (!isset($mUserRes['exception']) && !empty($mUserRes) && isset($mUserRes[0]['id'])) {
                        $moodleId = $mUserRes[0]['id'];
                    }
                }
                $uStmt->close();
            }

            if ($moodleId <= 0 && $userId <= 0) {
                ApiResponse::error('請指定要移除的用戶 ID');
                return;
            }

            // 從 Moodle 資料庫直接刪除選課記錄（支援所有選課方式）
            $moodleUnenrolled = false;
            $moodleMsg = '';
            if ($moodleId > 0) {
                $moodleDb = \MoodleDatabase::getInstance();
                $result = $moodleDb->unenrolUser((int) $moodleId, $courseId);
                $moodleUnenrolled = $result['success'];
                $moodleMsg = $result['message'];
                error_log("[visibilityRemove] DB unenrol result: " . json_encode($result));
            }

            ApiResponse::success([
                'deleted_count' => $deletedCount,
                'moodle_unenrolled' => $moodleUnenrolled,
                'moodle_id_used' => $moodleId
            ], $moodleUnenrolled ? "已移除記錄" : ($moodleMsg ?: "Moodle 退選失敗"));
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 取得課程已招生學員（含 institution）
     * GET ?route=courses/enrolled_users&course_id=X
     */
    public function getEnrolledUsers(): void
    {
        $this->requireHospitalAdmin();

        $courseId = $this->inputInt('course_id');
        if ($courseId <= 0) {
            ApiResponse::error('缺少課程 ID');
            return;
        }

        try {
            $moodle = new MoodleService();
            $enrolledUsers = $moodle->call('core_enrol_get_enrolled_users', [
                'courseid' => $courseId
            ]);
            if (isset($enrolledUsers['exception'])) {
                ApiResponse::error($enrolledUsers['message'] ?? '取得招生名單失敗');
                return;
            }

            $usernames = [];
            $usersMap = [];
            foreach ($enrolledUsers as $u) {
                $uname = strtolower(trim($u['username'] ?? ''));
                if ($uname) {
                    $usernames[] = $uname;
                    $usersMap[$uname] = [
                        'id' => $u['id'], // Default to Moodle ID
                        'moodle_id' => $u['id'], // Explicitly keep Moodle ID
                        'fullname' => $u['fullname'] ?? '',
                        'username' => $uname,
                        'institution' => $u['institution'] ?? $u['department'] ?? ''
                    ];
                }
            }

            if (!empty($usernames)) {
                $conn = $this->db->getConnection();
                $placeholders = implode(',', array_fill(0, count($usernames), '?'));
                $types = str_repeat('s', count($usernames));

                $stmt = $conn->prepare("SELECT id as portal_id, username, fullname, institution FROM users WHERE LOWER(username) IN ($placeholders)");
                $stmt->bind_param($types, ...$usernames);
                $stmt->execute();
                $res = $stmt->get_result();

                while ($row = $res->fetch_assoc()) {
                    $uname = strtolower(trim($row['username']));
                    if (isset($usersMap[$uname])) {
                        // 覆寫回 Portal ID，因為前端刪除時使用的是 Portal ID
                        $usersMap[$uname]['id'] = (int) $row['portal_id'];
                        $usersMap[$uname]['portal_id'] = (int) $row['portal_id'];
                        $usersMap[$uname]['fullname'] = $row['fullname'] ?: $usersMap[$uname]['fullname'];
                        $usersMap[$uname]['institution'] = $row['institution'] ?: $usersMap[$uname]['institution'];
                    }
                }
                $stmt->close();
            }

            ApiResponse::success(array_values($usersMap));
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 列出課程的可見用戶（含姓名、所屬）
     * GET ?route=courses/visibility/list_by_course&course_id=X
     */
    public function visibilityListByCourse(): void
    {
        $this->requireHospitalAdmin();

        $courseId = $this->inputInt('course_id');
        if ($courseId <= 0) {
            ApiResponse::error('缺少課程 ID');
            return;
        }

        try {
            $conn = $this->db->getConnection();
            $this->ensureVisibilityTable($conn);

            // 1. 取得 course_visibility_exclusions 中的 user_id（Moodle user ID）
            $stmt = $conn->prepare("SELECT user_id, filter_snapshot, created_at FROM course_visibility_exclusions WHERE course_id = ?");
            $stmt->bind_param("i", $courseId);
            $stmt->execute();
            $result = $stmt->get_result();

            $visEntries = [];
            $moodleUserIds = [];
            $filterSnapshotRaw = null;
            while ($row = $result->fetch_assoc()) {
                $visEntries[] = $row;
                $moodleUserIds[] = (int) $row['user_id'];
                if (!$filterSnapshotRaw && !empty($row['filter_snapshot'])) {
                    $filterSnapshotRaw = $row['filter_snapshot'];
                }
            }
            $stmt->close();

            $users = [];
            if (!empty($moodleUserIds)) {
                $placeholders = implode(',', array_fill(0, count($moodleUserIds), '?'));
                $types = str_repeat('i', count($moodleUserIds));

                // 2. 從 Portal 取得用戶名稱
                // course_visibility_exclusions 存的是 Moodle ID，因此要對應 users 表的 moodle_user_id (或 username)
                $stmt = $conn->prepare("SELECT id as portal_id, moodle_user_id, username, fullname, institution FROM users WHERE moodle_user_id IN ($placeholders)");
                $stmt->bind_param($types, ...$moodleUserIds);
                $stmt->execute();
                $mResult = $stmt->get_result();

                $createdAtMap = [];
                foreach ($visEntries as $v) {
                    $createdAtMap[(int) $v['user_id']] = $v['created_at'];
                }

                while ($row = $mResult->fetch_assoc()) {
                    $moodleId = (int) $row['moodle_user_id'];
                    $portalId = (int) $row['portal_id'];
                    $users[] = [
                        'user_id' => $moodleId, // Original field used by frontend
                        'moodle_id' => $moodleId,
                        'portal_id' => $portalId,
                        'id' => $portalId,
                        'fullname' => $row['fullname'] ?: $row['username'],
                        'username' => $row['username'],
                        'institution' => $row['institution'] ?? '',
                        'created_at' => $createdAtMap[$moodleId] ?? ''
                    ];
                }
                $stmt->close();
            }

            ApiResponse::success([
                'course_id' => $courseId,
                'users' => $users,
                'filter_snapshot' => $filterSnapshotRaw
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 列出用戶可見的課程
     * GET ?route=courses/visibility/list_by_user&user_id=X
     */
    public function visibilityListByUser(): void
    {
        $this->requireHospitalAdmin();

        $userId = $this->inputInt('user_id');
        if ($userId <= 0) {
            ApiResponse::error('缺少用戶 ID');
            return;
        }

        try {
            $conn = $this->db->getConnection();
            $this->ensureVisibilityTable($conn);

            $stmt = $conn->prepare("SELECT course_id, created_at FROM course_visibility_exclusions WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            $courses = [];
            while ($row = $result->fetch_assoc()) {
                $courses[] = $row;
            }
            $stmt->close();

            ApiResponse::success([
                'user_id' => $userId,
                'courses' => $courses
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 儲存課程選修規則 (Rule-based Visibility)
     * POST ?route=courses/visibility/save_rules
     */
    public function saveVisibilityRules(): void
    {
        $this->requireHospitalAdmin();

        $courseId = $this->inputInt('course_id');
        $ruleSnapshot = $this->inputString('rule_snapshot'); // JSON string from frontend

        if ($courseId <= 0) {
            ApiResponse::error('缺少課程 ID');
            return;
        }

        try {
            $conn = $this->db->getConnection();
            $this->ensureVisibilityRulesTable($conn);

            // 如果前後端傳來的規則是空的，視為刪除規則
            if (empty($ruleSnapshot) || $ruleSnapshot === 'null' || $ruleSnapshot === '[]') {
                $stmt = $conn->prepare("DELETE FROM course_visibility_rules WHERE course_id = ?");
                $stmt->bind_param("i", $courseId);
                $stmt->execute();
                $stmt->close();
                // 也清除排除名單
                $delExcl = $conn->prepare("DELETE FROM course_visibility_exclusions WHERE course_id = ?");
                $delExcl->bind_param("i", $courseId);
                $delExcl->execute();
                $delExcl->close();
                // 恢復 Moodle visible=1
                try {
                    $this->moodle->call('core_course_update_courses', [
                        'courses' => [['id' => (int) $courseId, 'visible' => 1]]
                    ]);
                } catch (\Exception $ve) {
                    error_log('visible toggle: ' . $ve->getMessage());
                }
                ApiResponse::success(['action' => 'deleted'], '課程選修條件已清除，課程已恢復公開');
                return;
            }

            // 否則新增或更新規則 (使用 INSERT ... ON DUPLICATE KEY UPDATE)
            $stmt = $conn->prepare("
                INSERT INTO course_visibility_rules (course_id, rule_snapshot) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE rule_snapshot = VALUES(rule_snapshot)
            ");
            $stmt->bind_param("is", $courseId, $ruleSnapshot);
            $stmt->execute();
            $stmt->close();

            // 清除舊排除名單（規則改變時需重新設定）
            $delExcl2 = $conn->prepare("DELETE FROM course_visibility_exclusions WHERE course_id = ?");
            $delExcl2->bind_param("i", $courseId);
            $delExcl2->execute();
            $delExcl2->close();
            // 設 Moodle visible=0（限制選修）
            try {
                $this->moodle->call('core_course_update_courses', [
                    'courses' => [['id' => (int) $courseId, 'visible' => 0]]
                ]);
            } catch (\Exception $ve) {
                error_log('visible toggle: ' . $ve->getMessage());
            }
            ApiResponse::success(['action' => 'saved'], '課程已設為限制選修');
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 取得課程選修規則
     * GET ?route=courses/visibility/get_rules&course_id=X
     */
    public function getVisibilityRules(): void
    {
        $this->requireHospitalAdmin();

        $courseId = $this->inputInt('course_id');
        if ($courseId <= 0) {
            ApiResponse::error('缺少課程 ID');
            return;
        }

        try {
            $conn = $this->db->getConnection();
            $this->ensureVisibilityRulesTable($conn);

            $stmt = $conn->prepare("SELECT rule_snapshot, created_at FROM course_visibility_rules WHERE course_id = ?");
            $stmt->bind_param("i", $courseId);
            $stmt->execute();
            $result = $stmt->get_result();

            $ruleData = null;
            $resolvedGroups = [];
            if ($row = $result->fetch_assoc()) {
                $ruleData = $row;

                // 解析 rule_snapshot 並解析 cohort ID 對應的名稱
                $snapshot = json_decode($row['rule_snapshot'], true);
                if ($snapshot && !empty($snapshot['filter_groups'])) {
                    // 收集所有 cohort IDs
                    $allCohortIds = [];
                    foreach ($snapshot['filter_groups'] as $group) {
                        foreach ($group as $cohortId) {
                            $allCohortIds[] = intval($cohortId);
                        }
                    }
                    $allCohortIds = array_values(array_unique($allCohortIds));

                    // 查詢 cohort 名稱和所屬維度
                    $cohortMap = [];
                    $dimMap = [];
                    if (!empty($allCohortIds)) {
                        // 從 Moodle 取得 cohort 名稱
                        try {
                            $cohorts = $this->moodle->call('core_cohort_get_cohorts', [
                                'cohortids' => $allCohortIds
                            ]);
                            if (!isset($cohorts['exception']) && is_array($cohorts)) {
                                foreach ($cohorts as $c) {
                                    $cohortMap[intval($c['id'])] = $c['name'] ?? ('Cohort #' . $c['id']);
                                }
                            }
                        } catch (\Exception $e) {
                            // Moodle 查詢失敗，繼續使用 ID
                        }

                        // 從 cohort_dimensions 表取得維度歸屬
                        try {
                            $placeholders = implode(',', array_fill(0, count($allCohortIds), '?'));
                            $types = str_repeat('i', count($allCohortIds));
                            $dimStmt = $conn->prepare("
                                SELECT cd.moodle_cohort_id, dt.name as dimension_name 
                                FROM cohort_dimensions cd 
                                JOIN dimension_types dt ON cd.dimension_type_id = dt.id 
                                WHERE cd.moodle_cohort_id IN ($placeholders)
                            ");
                            $dimStmt->bind_param($types, ...$allCohortIds);
                            $dimStmt->execute();
                            $dimResult = $dimStmt->get_result();
                            while ($dimRow = $dimResult->fetch_assoc()) {
                                $dimMap[intval($dimRow['moodle_cohort_id'])] = $dimRow['dimension_name'];
                            }
                            $dimStmt->close();
                        } catch (\Exception $e) {
                            // 維度表查詢失敗，繼續
                        }
                    }

                    // 組裝解析後的分組
                    foreach ($snapshot['filter_groups'] as $group) {
                        $resolvedGroup = [];
                        foreach ($group as $cohortId) {
                            $cid = intval($cohortId);
                            $resolvedGroup[] = [
                                'cohort_id' => $cid,
                                'name' => $cohortMap[$cid] ?? ('群組 #' . $cid),
                                'dimension' => $dimMap[$cid] ?? '未知'
                            ];
                        }
                        $resolvedGroups[] = $resolvedGroup;
                    }
                }
            }
            $stmt->close();


            // 查詢被明確授權的人員
            $excludedUsers = [];
            try {
                $guStmt = $conn->prepare("SELECT cv.user_id, u.fullname, u.username FROM course_visibility_exclusions cv LEFT JOIN users u ON cv.user_id = u.id WHERE cv.course_id = ?");
                $guStmt->bind_param("i", $courseId);
                $guStmt->execute();
                $guRes = $guStmt->get_result();
                while ($guRow = $guRes->fetch_assoc()) {
                    $excludedUsers[] = $guRow;
                }
                $guStmt->close();
            } catch (\Exception $e) {
            }

            ApiResponse::success([
                'rules' => $ruleData,
                'resolved_groups' => $resolvedGroups,
                'excluded_users' => $excludedUsers
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    // ==========================================
    // 私有輔助方法
    // ==========================================

    /**
     * 確保 course_visibility_rules 表存在
     */
    private function ensureVisibilityRulesTable(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS course_visibility_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL UNIQUE,
            rule_snapshot TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_course (course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * 確保 course_visibility_exclusions 表存在
     */
    private function ensureVisibilityTable(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS course_visibility_exclusions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            user_id INT NOT NULL,
            filter_snapshot TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_course_user (course_id, user_id),
            INDEX idx_course (course_id),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // 確保 filter_snapshot 欄位存在（已有表可能缺少）
        $res = $conn->query("SHOW COLUMNS FROM course_visibility_exclusions LIKE 'filter_snapshot'");
        if ($res && $res->num_rows === 0) {
            $conn->query("ALTER TABLE course_visibility_exclusions ADD COLUMN filter_snapshot TEXT DEFAULT NULL");
        }
    }
}
