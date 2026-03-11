<?php
/**
 * Cohort API 控制器
 * app/Controllers/Api/CohortController.php
 * 
 * 處理群組相關的 API 請求
 */

class CohortController extends Controller
{
    private MoodleService $moodle;

    public function __construct()
    {
        parent::__construct();
        $this->moodle = new MoodleService();
    }

    /**
     * 列出群組
     * GET ?action=list
     */
    public function list(): void
    {
        $this->requireHospitalAdmin();

        $categoryId = $this->getManagementCategoryId();
        if ($categoryId <= 0) {
            ApiResponse::error('未設定管理類別');
            return;
        }

        try {
            $cohorts = $this->moodle->searchCohorts($categoryId);

            if (MoodleService::hasError($cohorts)) {
                ApiResponse::error('無法取得群組: ' . MoodleService::getErrorMessage($cohorts));
                return;
            }

            ApiResponse::success([
                'cohorts' => $cohorts,
                'count' => count($cohorts)
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 列出群組（含維度資訊）- 向後相容版本
     * GET ?action=list_with_dimensions
     * 
     * 邏輯：
     * 1. 用 institutions.cohort_idnumber 找 Moodle 主群組
     * 2. 查主群組建在哪個類別
     * 3. 返回該類別及所有子類別的群組
     */
    public function listWithDimensions(): void
    {
        $this->requireHospitalAdmin();

        $institutionName = $this->getInstitutionName();

        if (empty($institutionName)) {
            ApiResponse::error('未設定使用者院區，請重新登入');
            return;
        }

        try {
            $conn = $this->db->getConnection();

            // 1. 從 Portal DB 取得機構的 cohort_idnumber（精確匹配，避免跨院區）
            $cohortIdnumber = '';
            $institutionId = 0;
            $stmt = $conn->prepare("SELECT id, cohort_idnumber FROM institutions WHERE name = ? LIMIT 1");
            $stmt->bind_param('s', $institutionName);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $institutionId = $result['id'] ?? 0;
            $cohortIdnumber = $result['cohort_idnumber'] ?? '';
            $stmt->close();

            if (empty($cohortIdnumber)) {
                ApiResponse::error('找不到機構的 cohort_idnumber (institution: ' . $institutionName . ')');
                return;
            }

            // 2. 使用 MoodleDatabase 服務查詢
            $moodleDb = \MoodleDatabase::getInstance();

            // 用 cohort_idnumber 找主群組所在的類別
            $mainCohort = $moodleDb->getCohortByIdnumber($cohortIdnumber);

            if (!$mainCohort || empty($mainCohort['category_id'])) {
                ApiResponse::error("找不到主群組 (idnumber: $cohortIdnumber) 或其類別");
                return;
            }

            $categoryId = (int) $mainCohort['category_id'];

            // 3. 取得該類別及所有子類別的群組
            $categoryIds = $moodleDb->getAllDescendantCategoryIds($categoryId);
            $moodleCohorts = $moodleDb->getCohortsInCategories($categoryIds);

            // 5. 從 Portal DB 的 cohort_dimensions 取得維度資訊
            $moodleCohortIds = array_column($moodleCohorts, 'id');
            $cohortDimMap = [];
            if (!empty($moodleCohortIds)) {
                $placeholders = implode(',', array_fill(0, count($moodleCohortIds), '?'));
                $types = str_repeat('i', count($moodleCohortIds));

                $stmt = $conn->prepare("
                    SELECT cd.moodle_cohort_id, cd.display_name, cd.parent_cohort_id,
                           dt.id as dimension_type_id, dt.name as dimension_name
                    FROM cohort_dimensions cd
                    JOIN dimension_types dt ON cd.dimension_type_id = dt.id
                    WHERE cd.moodle_cohort_id IN ($placeholders)
                ");
                $stmt->bind_param($types, ...$moodleCohortIds);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $cohortDimMap[$row['moodle_cohort_id']] = $row;
                }
                $stmt->close();
            }

            // 6. 合併資訊
            $cohorts = [];
            foreach ($moodleCohorts as $c) {
                $cohortId = $c['id'];
                $dim = $cohortDimMap[$cohortId] ?? null;

                // 計算完整階層路徑
                $pathParts = [];
                $currentId = $cohortId;
                $visited = [];
                while ($currentId && isset($cohortDimMap[$currentId]) && !isset($visited[$currentId])) {
                    $visited[$currentId] = true;
                    $currentDim = $cohortDimMap[$currentId];
                    array_unshift($pathParts, $currentDim['display_name'] ?? '');
                    $currentId = $currentDim['parent_cohort_id'] ?? null;
                }
                $fullPath = implode(' / ', array_filter($pathParts));

                $cohorts[] = [
                    'id' => $cohortId,
                    'name' => $c['name'] ?? '',
                    'idnumber' => $c['idnumber'] ?? '',
                    'description' => $c['description'] ?? '',
                    'member_count' => $c['member_count'] ?? 0,
                    'dimension_type_id' => $dim['dimension_type_id'] ?? null,
                    'dimension_name' => $dim['dimension_name'] ?? null,
                    'parent_cohort_id' => $dim['parent_cohort_id'] ?? null,
                    'display_name' => $dim['display_name'] ?? null,
                    'full_path' => $fullPath ?: ($dim['display_name'] ?? $c['name'] ?? ''),
                ];
            }

            ApiResponse::success($cohorts);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 取得群組成員
     * GET ?action=get_members&cohort_id=123
     */
    public function getMembers(): void
    {
        $this->requireHospitalAdmin();

        $cohortId = $this->inputInt('cohort_id');
        if ($cohortId <= 0) {
            ApiResponse::error('缺少 cohort_id');
            return;
        }

        try {
            // 直接查 Moodle DB（比 Web API 更可靠）
            $moodleDb = \MoodleDatabase::getInstance();
            $conn = $moodleDb->getConnection();

            $stmt = $conn->prepare("
                SELECT u.id, u.username, u.firstname, u.lastname, u.email
                FROM mdl_cohort_members cm
                JOIN mdl_user u ON cm.userid = u.id
                WHERE cm.cohortid = ? AND u.deleted = 0
                ORDER BY u.lastname, u.firstname
            ");
            $stmt->bind_param('i', $cohortId);
            $stmt->execute();
            $result = $stmt->get_result();

            $members = [];
            while ($row = $result->fetch_assoc()) {
                $members[] = [
                    'id' => (int) $row['id'],
                    'username' => $row['username'],
                    'fullname' => trim($row['lastname'] . ' ' . $row['firstname']),
                    'email' => $row['email'] ?? ''
                ];
            }
            $stmt->close();

            ApiResponse::success([
                'cohort_id' => $cohortId,
                'members' => $members,
                'count' => count($members)
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 新增群組成員（支援單一或批次）
     * POST ?action=add_member
     * 參數: cohort_id + user_id (單一) 或 user_ids (JSON array)
     */
    public function addMember(): void
    {
        $this->requireHospitalAdmin();

        $cohortId = $this->inputInt('cohort_id');

        if ($cohortId <= 0) {
            ApiResponse::error('缺少 cohort_id');
            return;
        }

        // 支援批次 user_ids (JSON array) 或單一 user_id
        $userIdsJson = $this->inputString('user_ids');
        $userIds = [];

        if (!empty($userIdsJson)) {
            $decoded = json_decode($userIdsJson, true);
            if (is_array($decoded)) {
                $userIds = array_filter(array_map('intval', $decoded));
            }
        }

        // fallback: 單一 user_id
        if (empty($userIds)) {
            $userId = $this->inputInt('user_id');
            if ($userId > 0) {
                $userIds = [$userId];
            }
        }

        if (empty($userIds)) {
            ApiResponse::error('缺少 user_id 或 user_ids');
            return;
        }

        try {
            $added = 0;
            $errors = [];

            foreach ($userIds as $uid) {
                $success = $this->moodle->addCohortMember($cohortId, $uid);
                if ($success) {
                    $added++;
                } else {
                    $errors[] = $uid;
                }
            }

            if ($added > 0) {
                $msg = "已加入 {$added} 位成員";
                if (!empty($errors)) {
                    $msg .= "，" . count($errors) . " 位失敗";
                }
                ApiResponse::success(['added' => $added, 'failed' => count($errors)], $msg);
            } else {
                ApiResponse::error('新增成員失敗');
            }
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 移除群組成員（支援單一或批次）
     * POST ?action=remove_member
     * 參數: cohort_id + user_id (單一) 或 user_ids (JSON array)
     */
    public function removeMember(): void
    {
        $this->requireHospitalAdmin();

        $cohortId = $this->inputInt('cohort_id');

        if ($cohortId <= 0) {
            ApiResponse::error('缺少 cohort_id');
            return;
        }

        // 支援批次 user_ids (JSON array) 或單一 user_id
        $userIdsJson = $this->inputString('user_ids');
        $userIds = [];

        if (!empty($userIdsJson)) {
            $decoded = json_decode($userIdsJson, true);
            if (is_array($decoded)) {
                $userIds = array_filter(array_map('intval', $decoded));
            }
        }

        if (empty($userIds)) {
            $userId = $this->inputInt('user_id');
            if ($userId > 0) {
                $userIds = [$userId];
            }
        }

        if (empty($userIds)) {
            ApiResponse::error('缺少 user_id 或 user_ids');
            return;
        }

        try {
            $removed = 0;
            foreach ($userIds as $uid) {
                if ($this->moodle->removeCohortMember($cohortId, $uid)) {
                    $removed++;
                }
            }

            if ($removed > 0) {
                ApiResponse::success(['removed' => $removed], "已移除 {$removed} 位成員");
            } else {
                ApiResponse::error('移除成員失敗');
            }
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 搜尋用戶（加入成員用）- 只顯示同院區用戶
     * GET ?route=cohorts/search_users&search=xxx
     */
    public function searchUsers(): void
    {
        $this->requireHospitalAdmin();

        $search = trim($this->inputString('search'));

        try {
            $institutionName = $this->getInstitutionName();
            $conn = $this->db->getConnection();
            $moodleDb = \MoodleDatabase::getInstance();
            $moodleConn = $moodleDb->getConnection();

            // 1. 取得院區的 cohort_idnumber 和 management_category_id
            $stmt = $conn->prepare("SELECT id, cohort_idnumber, management_category_id FROM institutions WHERE name = ? LIMIT 1");
            $stmt->bind_param('s', $institutionName);
            $stmt->execute();
            $inst = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$inst) {
                ApiResponse::error('找不到院區資料');
                return;
            }

            $cohortIdnumber = $inst['cohort_idnumber'];
            $mgmtCatId = (int) $inst['management_category_id'];

            // 2. 從 Moodle DB 取得同院區用戶
            //    來源 A: 院區主群組的成員
            //    來源 B: 在院區類別下的課程有選課的用戶
            $userIds = [];

            // 來源 A: 主群組成員
            $stmt = $moodleConn->prepare("
                SELECT cm.userid 
                FROM mdl_cohort_members cm
                JOIN mdl_cohort c ON cm.cohortid = c.id
                WHERE c.idnumber = ?
            ");
            $stmt->bind_param('s', $cohortIdnumber);
            $stmt->execute();
            $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) {
                $userIds[(int) $row['userid']] = true;
            }
            $stmt->close();

            // 來源 B: 院區類別下所有課程的選課用戶
            if ($mgmtCatId > 0) {
                $catIds = $moodleDb->getAllDescendantCategoryIds($mgmtCatId);
                if (!empty($catIds)) {
                    $placeholders = implode(',', array_fill(0, count($catIds), '?'));
                    $types = str_repeat('i', count($catIds));

                    $sql = "SELECT DISTINCT ue.userid
                            FROM mdl_user_enrolments ue
                            JOIN mdl_enrol e ON ue.enrolid = e.id
                            JOIN mdl_course c ON e.courseid = c.id
                            WHERE c.category IN ($placeholders)";
                    $stmt = $moodleConn->prepare($sql);
                    $stmt->bind_param($types, ...$catIds);
                    $stmt->execute();
                    $r = $stmt->get_result();
                    while ($row = $r->fetch_assoc()) {
                        $userIds[(int) $row['userid']] = true;
                    }
                    $stmt->close();
                }
            }

            // 排除 admin/guest
            unset($userIds[0], $userIds[1]);

            if (empty($userIds)) {
                ApiResponse::success(['users' => [], 'count' => 0]);
                return;
            }

            // 3. 取得用戶詳細資訊（含搜尋過濾）
            $allIds = array_keys($userIds);
            $users = [];

            foreach (array_chunk($allIds, 50) as $chunk) {
                $userData = $this->moodle->call('core_user_get_users_by_field', [
                    'field' => 'id',
                    'values' => $chunk
                ]);
                if (isset($userData['exception']))
                    continue;

                foreach ($userData as $u) {
                    if ($u['id'] <= 1)
                        continue;
                    $fullname = $u['fullname'] ?? ($u['firstname'] . ' ' . $u['lastname']);

                    // 搜尋過濾
                    if (strlen($search) >= 1) {
                        $haystack = mb_strtolower($fullname . ' ' . $u['username'] . ' ' . ($u['email'] ?? ''));
                        if (mb_strpos($haystack, mb_strtolower($search)) === false) {
                            continue;
                        }
                    }

                    $users[] = [
                        'id' => $u['id'],
                        'username' => $u['username'],
                        'fullname' => $fullname,
                        'email' => $u['email'] ?? ''
                    ];
                }
            }

            // 按名稱排序
            usort($users, fn($a, $b) => strcmp($a['fullname'], $b['fullname']));

            ApiResponse::success(['users' => $users, 'count' => count($users)]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 刪除群組
     * POST ?route=cohorts/delete
     */
    public function delete(): void
    {
        $this->requireHospitalAdmin();

        $cohortId = $this->inputInt('id');
        if ($cohortId <= 0) {
            ApiResponse::error('參數錯誤');
            return;
        }

        try {
            $conn = $this->db->getConnection();

            // 檢查是否為主群組（不可刪除）
            $stmt = $conn->prepare("
                SELECT dt.name FROM cohort_dimensions cd 
                JOIN dimension_types dt ON cd.dimension_type_id = dt.id 
                WHERE cd.moodle_cohort_id = ?
            ");
            $stmt->bind_param('i', $cohortId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if ($row && $row['name'] === '主群組') {
                ApiResponse::error('主群組不能刪除');
                return;
            }

            // 從 Moodle 刪除
            $result = $this->moodle->call('core_cohort_delete_cohorts', ['cohortids' => [$cohortId]]);
            if (isset($result['exception'])) {
                ApiResponse::error('刪除失敗: ' . ($result['message'] ?? 'Unknown'));
                return;
            }

            // 從 Portal DB 刪除
            $stmt = $conn->prepare("DELETE FROM cohort_dimensions WHERE moodle_cohort_id = ?");
            $stmt->bind_param('i', $cohortId);
            $stmt->execute();
            $stmt->close();

            ApiResponse::success(null, '群組已刪除');
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 建立群組
     * POST ?route=cohorts/create
     */
    public function create(): void
    {
        $this->requireHospitalAdmin();

        $name = trim($this->inputString('name'));
        $idnumber = trim($this->inputString('idnumber'));
        $categoryId = $this->inputInt('category_id', $this->getManagementCategoryId());
        $dimensionTypeId = $this->inputInt('dimension_type_id');
        $parentCohortId = $this->inputInt('parent_cohort_id');

        if (empty($name)) {
            ApiResponse::error('名稱不能為空');
            return;
        }

        try {
            // 自動生成 idnumber
            if (empty($idnumber)) {
                $idnumber = 'cohort_' . time() . '_' . rand(1000, 9999);
            }

            // 在 Moodle 建立
            $result = $this->moodle->call('core_cohort_create_cohorts', [
                'cohorts' => [
                    [
                        'categorytype' => ['type' => 'id', 'value' => $categoryId],
                        'name' => $name,
                        'idnumber' => $idnumber,
                        'description' => '',
                        'descriptionformat' => 1,
                        'visible' => 1
                    ]
                ]
            ]);

            if (isset($result['exception'])) {
                ApiResponse::error('建立失敗: ' . ($result['message'] ?? 'Unknown'));
                return;
            }

            $newCohortId = $result[0]['id'] ?? 0;

            // 在 Portal DB 建立維度對應
            if ($newCohortId > 0 && ($dimensionTypeId > 0 || $parentCohortId > 0)) {
                $conn = $this->db->getConnection();

                // 如果沒指定維度但有父群組，繼承父群組的維度
                if ($parentCohortId > 0 && $dimensionTypeId <= 0) {
                    $stmt = $conn->prepare("SELECT dimension_type_id FROM cohort_dimensions WHERE moodle_cohort_id = ?");
                    $stmt->bind_param('i', $parentCohortId);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    $dimensionTypeId = $result['dimension_type_id'] ?? 0;
                    $stmt->close();
                }

                $stmt = $conn->prepare("INSERT INTO cohort_dimensions (dimension_type_id, moodle_cohort_id, display_name, parent_cohort_id) VALUES (?, ?, ?, ?)");
                $parentVal = $parentCohortId > 0 ? $parentCohortId : null;
                $stmt->bind_param('iisi', $dimensionTypeId, $newCohortId, $name, $parentVal);
                $stmt->execute();
                $stmt->close();
            }

            $msg = '群組已建立';
            if ($parentCohortId > 0) {
                $msg .= '（已設定父群組）';
            }

            ApiResponse::success([
                'cohort_id' => $newCohortId,
                'parent_cohort_id' => $parentCohortId
            ], $msg);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 更新群組維度
     * POST ?route=cohorts/update_dimension
     */
    public function updateDimension(): void
    {
        $this->requireHospitalAdmin();

        $cohortId = $this->inputInt('cohort_id');
        $dimensionTypeId = $this->inputInt('dimension_type_id');
        $parentCohortId = $this->inputInt('parent_cohort_id') ?: null;

        if ($cohortId <= 0) {
            ApiResponse::error('參數錯誤');
            return;
        }

        try {
            $conn = $this->db->getConnection();
            $institutionId = $this->getInstitutionId();

            // 刪除舊關聯
            $stmt = $conn->prepare("DELETE FROM cohort_dimensions WHERE moodle_cohort_id = ?");
            $stmt->bind_param('i', $cohortId);
            $stmt->execute();
            $stmt->close();

            // 建立新關聯
            if ($dimensionTypeId > 0) {
                // 取得群組名稱
                $cohortInfo = $this->moodle->call('core_cohort_get_cohorts', ['cohortids' => [$cohortId]]);
                $cohortName = $cohortInfo[0]['name'] ?? '';

                // 如果沒有指定父群組，嘗試自動設定
                if (!$parentCohortId) {
                    // 取得維度名稱
                    $stmt = $conn->prepare("SELECT name FROM dimension_types WHERE id = ?");
                    $stmt->bind_param('i', $dimensionTypeId);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    $dimName = $result['name'] ?? '';
                    $stmt->close();

                    // 取得主群組 ID
                    $stmt = $conn->prepare("
                        SELECT cd.moodle_cohort_id 
                        FROM cohort_dimensions cd
                        JOIN dimension_types dt ON cd.dimension_type_id = dt.id
                        WHERE dt.institution_id = ? AND dt.name = '主群組'
                        LIMIT 1
                    ");
                    $stmt->bind_param('i', $institutionId);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    $mainCohortId = $result['moodle_cohort_id'] ?? null;
                    $stmt->close();

                    // 非主群組維度，設定父為主群組
                    if ($dimName !== '主群組' && $mainCohortId) {
                        $parentCohortId = $mainCohortId;
                    }
                }

                $stmt = $conn->prepare("INSERT INTO cohort_dimensions (dimension_type_id, moodle_cohort_id, display_name, parent_cohort_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('iisi', $dimensionTypeId, $cohortId, $cohortName, $parentCohortId);
                $stmt->execute();
                $stmt->close();
            }

            ApiResponse::success(null, '維度已更新');
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 多組篩選成員（組內 AND，組間可選 AND/OR）
     * POST ?route=cohorts/get_members_by_groups
     * Body: { filter_groups: [[id1,id2],[id3,id4]], operators: ['or'] }
     */
    public function getMembersByGroups(): void
    {
        $this->requireHospitalAdmin();

        // 讀取 JSON body（前端用 fetch + Content-Type: application/json 發送）
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $filterGroups = $input['filter_groups'] ?? [];
        $operators = $input['operators'] ?? [];
        $tagIds = $input['tag_ids'] ?? [];  // 新增：標籤篩選

        // 移除調試 log（生產環境）
        // file_put_contents(__DIR__ . '/../../../debug_filter.log', "...", FILE_APPEND);

        if (empty($filterGroups) && empty($tagIds)) {
            // 如果完全沒有篩選條件，回傳同院區的所有使用者
            try {
                $institutionName = $this->getInstitutionName();
                $conn = $this->db->getConnection();
                $moodleDb = \MoodleDatabase::getInstance();
                $moodleConn = $moodleDb->getConnection();

                // 1. 取得院區的 cohort_idnumber
                $stmt = $conn->prepare("SELECT id, cohort_idnumber, management_category_id FROM institutions WHERE name = ? LIMIT 1");
                $stmt->bind_param('s', $institutionName);
                $stmt->execute();
                $inst = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$inst) {
                    ApiResponse::success(['users' => [], 'count' => 0]);
                    return;
                }

                $cohortIdnumber = $inst['cohort_idnumber'];
                $mgmtCatId = (int) $inst['management_category_id'];

                $userIds = [];

                // 來源 A: 主群組成員
                $stmt = $moodleConn->prepare("
                    SELECT cm.userid 
                    FROM mdl_cohort_members cm
                    JOIN mdl_cohort c ON cm.cohortid = c.id
                    WHERE c.idnumber = ?
                ");
                $stmt->bind_param('s', $cohortIdnumber);
                $stmt->execute();
                $r = $stmt->get_result();
                while ($row = $r->fetch_assoc()) {
                    $userIds[(int) $row['userid']] = true;
                }
                $stmt->close();

                // 來源 B: 院區類別下所有課程的選課用戶
                if ($mgmtCatId > 0) {
                    $catIds = $moodleDb->getAllDescendantCategoryIds($mgmtCatId);
                    if (!empty($catIds)) {
                        $placeholders = implode(',', array_fill(0, count($catIds), '?'));
                        $types = str_repeat('i', count($catIds));

                        $sql = "SELECT DISTINCT ue.userid
                                FROM mdl_user_enrolments ue
                                JOIN mdl_enrol e ON ue.enrolid = e.id
                                JOIN mdl_course c ON e.courseid = c.id
                                WHERE c.category IN ($placeholders)";
                        $stmt = $moodleConn->prepare($sql);
                        $stmt->bind_param($types, ...$catIds);
                        $stmt->execute();
                        $r = $stmt->get_result();
                        while ($row = $r->fetch_assoc()) {
                            $userIds[(int) $row['userid']] = true;
                        }
                        $stmt->close();
                    }
                }

                // 排除 admin/guest
                unset($userIds[0], $userIds[1], $userIds[2]);

                if (empty($userIds)) {
                    ApiResponse::success(['users' => [], 'count' => 0]);
                    return;
                }

                $allIds = array_keys($userIds);
                
                // 取回名稱對照
                $localNameMap = [];
                $placeholders = implode(',', array_fill(0, count($allIds), '?'));
                $types = str_repeat('i', count($allIds));
                
                $stmt = $moodleConn->prepare("SELECT id, username FROM mdl_user WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$allIds);
                $stmt->execute();
                $mRes = $stmt->get_result();
                $usernames = [];
                while ($mRow = $mRes->fetch_assoc()) {
                    $usernames[] = $mRow['username'];
                }
                $stmt->close();

                if (!empty($usernames)) {
                    $uPlaceholders = implode(',', array_fill(0, count($usernames), '?'));
                    $uTypes = str_repeat('s', count($usernames));
                    $pStmt = $conn->prepare("SELECT username, fullname FROM users WHERE username IN ($uPlaceholders)");
                    $pStmt->bind_param($uTypes, ...$usernames);
                    $pStmt->execute();
                    $pRes = $pStmt->get_result();
                    while ($pRow = $pRes->fetch_assoc()) {
                        $localNameMap[$pRow['username']] = $pRow['fullname'];
                    }
                    $pStmt->close();
                }

                $users = [];
                foreach (array_chunk($allIds, 50) as $chunk) {
                    $userData = $this->moodle->call('core_user_get_users_by_field', ['field' => 'id', 'values' => $chunk]);
                    if (!isset($userData['exception'])) {
                        foreach ($userData as $u) {
                            $uname = $u['username'] ?? '';
                            $displayName = $localNameMap[$uname] ?? $u['fullname'] ?? ($u['firstname'] . ' ' . $u['lastname']);

                            $users[] = [
                                'id' => $u['id'],
                                'username' => $uname,
                                'fullname' => $displayName,
                                'email' => $u['email'] ?? ''
                            ];
                        }
                    }
                }

                ApiResponse::success(['users' => $users, 'count' => count($users)]);
                return;
            } catch (\Exception $e) {
                // Ignore fallback to empty
            }
            ApiResponse::success(['users' => [], 'count' => 0]);
            return;
        }

        try {
            $resultIds = null;

            // 處理群組篩選（如果有的話）
            if (!empty($filterGroups)) {
                foreach ($filterGroups as $index => $group) {
                    $cohortIds = array_filter(array_map('intval', $group));
                    if (empty($cohortIds))
                        continue;

                    // 取得每個群組的成員
                    $memberSets = [];
                    foreach ($cohortIds as $cid) {
                        $members = $this->moodle->getCohortMembers($cid);
                        $memberSets[$cid] = $members;
                    }

                    // 組內計算交集 (AND)
                    $groupCommonIds = array_shift($memberSets) ?? [];
                    foreach ($memberSets as $ids) {
                        $groupCommonIds = array_intersect($groupCommonIds, $ids);
                    }
                    $groupCommonIds = array_values($groupCommonIds);

                    // 第一組直接設定結果
                    if ($resultIds === null) {
                        $resultIds = $groupCommonIds;
                    } else {
                        $operator = $operators[$index - 1] ?? 'or';
                        if ($operator === 'and') {
                            $resultIds = array_intersect($resultIds, $groupCommonIds);
                        } else {
                            $resultIds = array_merge($resultIds, $groupCommonIds);
                        }
                    }
                }
            }

            // 處理標籤篩選（如果有的話）
            if (!empty($tagIds)) {
                $tagUserIds = $this->getUsersByTags($tagIds);

                if ($resultIds === null) {
                    // 只有標籤篩選，沒有群組篩選
                    $resultIds = $tagUserIds;
                } else {
                    // 標籤與群組篩選取交集（必須同時符合）
                    $resultIds = array_intersect($resultIds, $tagUserIds);
                }
            }

            $allMatchedIds = array_values(array_unique($resultIds ?? []));

            // 取得本地用戶名稱對照
            $localNameMap = [];
            if (!empty($allMatchedIds)) {
                $placeholders = implode(',', array_fill(0, count($allMatchedIds), '?'));
                $types = str_repeat('i', count($allMatchedIds));
                $conn = $this->db->getConnection();

                // 修正：從 Moodle 取得 username 列表
                $moodleConn = $this->db->getMoodleConnection();
                $stmt = $moodleConn->prepare("SELECT id, username FROM mdl_user WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$allMatchedIds);
                $stmt->execute();
                $mRes = $stmt->get_result();
                $usernames = [];
                while ($mRow = $mRes->fetch_assoc()) {
                    $usernames[] = $mRow['username'];
                }
                $stmt->close();

                if (!empty($usernames)) {
                    $uPlaceholders = implode(',', array_fill(0, count($usernames), '?'));
                    $uTypes = str_repeat('s', count($usernames));
                    $pStmt = $conn->prepare("SELECT username, fullname FROM users WHERE username IN ($uPlaceholders)");
                    $pStmt->bind_param($uTypes, ...$usernames);
                    $pStmt->execute();
                    $pRes = $pStmt->get_result();
                    while ($pRow = $pRes->fetch_assoc()) {
                        $localNameMap[$pRow['username']] = $pRow['fullname'];
                    }
                    $pStmt->close();
                }
            }

            // 取得用戶詳細資訊
            $users = [];
            foreach (array_chunk($allMatchedIds, 50) as $chunk) {
                $userData = $this->moodle->call('core_user_get_users_by_field', ['field' => 'id', 'values' => $chunk]);
                if (!isset($userData['exception'])) {
                    foreach ($userData as $u) {
                        $uname = $u['username'] ?? '';
                        $displayName = $localNameMap[$uname] ?? $u['fullname'] ?? ($u['firstname'] . ' ' . $u['lastname']);

                        $users[] = [
                            'id' => $u['id'],
                            'username' => $uname,
                            'fullname' => $displayName,
                            'email' => $u['email'] ?? ''
                        ];
                    }
                }
            }

            ApiResponse::success(['users' => $users, 'count' => count($users)]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 取得符合標籤的使用者 ID 列表
     */
    private function getUsersByTags(array $tagIds): array
    {
        $tagIds = array_filter(array_map('intval', $tagIds));
        if (empty($tagIds)) {
            return [];
        }

        // 1. 從 portal_tags 取得標籤名稱
        $conn = $this->db->getConnection();
        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        $types = str_repeat('i', count($tagIds));

        $stmt = $conn->prepare("SELECT name FROM portal_tags WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$tagIds);
        $stmt->execute();
        $result = $stmt->get_result();

        $tagNames = [];
        while ($row = $result->fetch_assoc()) {
            $tagNames[] = $row['name'];
        }
        $stmt->close();

        if (empty($tagNames)) {
            return [];
        }

        // 2. 從 Moodle 的 mdl_tag + mdl_tag_instance 查找有這些標籤的使用者
        $moodleDb = \MoodleDatabase::getInstance();
        $moodleConn = $moodleDb->getConnection();

        $namePlaceholders = implode(',', array_fill(0, count($tagNames), '?'));
        $nameTypes = str_repeat('s', count($tagNames));

        $sql = "SELECT DISTINCT ti.itemid as user_id
                FROM mdl_tag_instance ti
                JOIN mdl_tag t ON ti.tagid = t.id
                WHERE ti.itemtype = 'user' 
                AND t.rawname IN ($namePlaceholders)";

        $stmt = $moodleConn->prepare($sql);
        $stmt->bind_param($nameTypes, ...$tagNames);
        $stmt->execute();
        $result = $stmt->get_result();

        $userIds = [];
        while ($row = $result->fetch_assoc()) {
            $userIds[] = (int) $row['user_id'];
        }
        $stmt->close();

        return $userIds;
    }

    /**
     * 取得多群組共同成員
     * POST ?route=cohorts/get_common_members
     */
    public function getCommonMembers(): void
    {
        $this->requireHospitalAdmin();

        $cohortIdsStr = $this->inputString('cohort_ids');
        $cohortIds = array_filter(array_map('intval', explode(',', $cohortIdsStr)));

        if (empty($cohortIds)) {
            ApiResponse::success(['users' => []]);
            return;
        }

        try {
            // 取得每個群組的成員
            $memberSets = [];
            foreach ($cohortIds as $cid) {
                $memberSets[$cid] = $this->moodle->getCohortMembers($cid);
            }

            // 計算交集
            $commonIds = array_shift($memberSets) ?? [];
            foreach ($memberSets as $ids) {
                $commonIds = array_intersect($commonIds, $ids);
            }
            $commonIds = array_values($commonIds);

            // 取得用戶詳細資訊
            $users = [];
            foreach (array_chunk($commonIds, 50) as $chunk) {
                $userData = $this->moodle->call('core_user_get_users_by_field', ['field' => 'id', 'values' => $chunk]);
                if (!isset($userData['exception'])) {
                    foreach ($userData as $u) {
                        $users[] = [
                            'id' => $u['id'],
                            'username' => $u['username'],
                            'fullname' => $u['fullname'],
                            'email' => $u['email'] ?? ''
                        ];
                    }
                }
            }

            ApiResponse::success(['users' => $users, 'count' => count($users)]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    // ==================
    // 私有輔助方法
    // ==================

    /**
     * 取得維度類型
     */
    private function getDimensionTypes(mysqli $conn, string $institutionName): array
    {
        $types = [];
        $stmt = $conn->prepare("
            SELECT dt.id, dt.name, dt.description 
            FROM dimension_types dt
            JOIN institutions i ON dt.institution_id = i.id
            WHERE i.name = ?
            ORDER BY dt.id
        ");
        $stmt->bind_param('s', $institutionName);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
        $stmt->close();
        return $types;
    }

    /**
     * 取得群組維度對應表
     */
    private function getCohortDimensions(mysqli $conn, string $institutionName): array
    {
        $dimensions = [];
        $stmt = $conn->prepare("
            SELECT cd.cohort_id, dt.name as dimension_name, cd.value
            FROM cohort_dimensions cd
            JOIN dimension_types dt ON cd.dimension_type_id = dt.id
            JOIN institutions i ON dt.institution_id = i.id
            WHERE i.name = ?
        ");
        $stmt->bind_param('s', $institutionName);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $cohortId = $row['cohort_id'];
            if (!isset($dimensions[$cohortId])) {
                $dimensions[$cohortId] = [];
            }
            $dimensions[$cohortId][$row['dimension_name']] = $row['value'];
        }
        $stmt->close();
        return $dimensions;
    }
}
