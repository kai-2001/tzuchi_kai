<?php
/**
 * Category API 控制器
 * app/Controllers/Api/CategoryController.php
 * 
 * 處理類別（課程分類）相關的 API 請求
 */

class CategoryController extends Controller
{
    private MoodleService $moodle;

    public function __construct()
    {
        parent::__construct();
        $this->moodle = new MoodleService();
    }

    /**
     * 列出所有類別
     * GET ?route=categories
     */
    public function list(): void
    {
        $this->requireHospitalAdmin();

        try {
            $categories = $this->moodle->getCategories();

            if (MoodleService::hasError($categories)) {
                ApiResponse::error('無法取得類別');
                return;
            }

            ApiResponse::success([
                'categories' => $categories,
                'count' => count($categories)
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 列出機構內的類別（含子類別）
     * GET ?route=categories/tree
     */
    public function tree(): void
    {
        $this->requireHospitalAdmin();

        $rootCategoryId = $this->getManagementCategoryId();

        try {
            $allCategories = $this->moodle->getCategories();

            if (MoodleService::hasError($allCategories)) {
                ApiResponse::error('無法取得類別');
                return;
            }

            // 過濾只保留本機構的類別
            $filteredCategories = $this->filterInstitutionCategories($allCategories, $rootCategoryId);

            // 建立樹狀結構
            $tree = $this->buildCategoryTree($filteredCategories, $rootCategoryId);

            ApiResponse::success([
                'tree' => $tree,
                'flat' => $filteredCategories,
                'root_id' => $rootCategoryId,
                'count' => count($filteredCategories)
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 取得單一類別
     * GET ?route=categories/show&id=123
     */
    public function show(): void
    {
        $this->requireHospitalAdmin();

        $categoryId = $this->inputInt('id');
        if ($categoryId <= 0) {
            ApiResponse::error('缺少 id');
            return;
        }

        try {
            $result = $this->moodle->call('core_course_get_categories', [
                'criteria' => [['key' => 'id', 'value' => $categoryId]]
            ]);

            if (empty($result) || isset($result['exception'])) {
                ApiResponse::notFound('找不到類別');
                return;
            }

            // 檢查權限
            if (!$this->hasAccessToCategory($categoryId)) {
                ApiResponse::forbidden('無權存取此類別');
                return;
            }

            ApiResponse::success(['category' => $result[0]]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 建立類別
     * POST ?route=categories/create
     */
    public function create(): void
    {
        $this->requireHospitalAdmin();

        $name = $this->inputString('name');
        $parentId = $this->inputInt('parent_id', $this->getManagementCategoryId());
        $description = $this->inputString('description');

        if (empty($name)) {
            ApiResponse::error('類別名稱不能為空');
            return;
        }

        // 檢查父類別權限
        if (!$this->hasAccessToCategory($parentId)) {
            ApiResponse::forbidden('無權在此類別下建立子類別');
            return;
        }

        try {
            $result = $this->moodle->call('core_course_create_categories', [
                'categories' => [
                    [
                        'name' => $name,
                        'parent' => $parentId,
                        'description' => $description,
                        'descriptionformat' => 1
                    ]
                ]
            ]);

            if (isset($result['exception']) || isset($result['error'])) {
                ApiResponse::error('建立類別失敗: ' . ($result['message'] ?? 'Unknown'));
                return;
            }

            // 同時建立對應的群組（若勾選）
            $createCohort = $this->inputString('create_cohort');
            $newCategoryId = $result[0]['id'] ?? 0;
            if ($newCategoryId > 0 && !empty($createCohort)) {
                $this->createCohortForCategory($newCategoryId, $name, $parentId);
            }

            ApiResponse::success([
                'category' => $result[0] ?? null,
                'message' => '類別已建立'
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 更新類別
     * POST ?route=categories/update
     */
    public function update(): void
    {
        $this->requireHospitalAdmin();

        $categoryId = $this->inputInt('id');
        $name = $this->inputString('name');

        if ($categoryId <= 0) {
            ApiResponse::error('缺少 id');
            return;
        }

        if (!$this->hasAccessToCategory($categoryId)) {
            ApiResponse::forbidden('無權編輯此類別');
            return;
        }

        try {
            $updateData = ['id' => $categoryId];
            if (!empty($name))
                $updateData['name'] = $name;

            $result = $this->moodle->call('core_course_update_categories', [
                'categories' => [$updateData]
            ]);

            if (isset($result['exception'])) {
                ApiResponse::error('更新失敗');
                return;
            }

            ApiResponse::success(null, '類別已更新');
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 刪除類別
     * POST ?route=categories/delete
     */
    public function delete(): void
    {
        $this->requireHospitalAdmin();

        $categoryId = $this->inputInt('id');

        if ($categoryId <= 0) {
            ApiResponse::error('缺少 id');
            return;
        }

        // 不能刪除根類別
        if ($categoryId == $this->getManagementCategoryId()) {
            ApiResponse::error('不能刪除根類別');
            return;
        }

        if (!$this->hasAccessToCategory($categoryId)) {
            ApiResponse::forbidden('無權刪除此類別');
            return;
        }

        try {
            $result = $this->moodle->call('core_course_delete_categories', [
                'categories' => [['id' => $categoryId]]
            ]);

            if (isset($result['exception'])) {
                ApiResponse::error('刪除失敗: ' . ($result['message'] ?? 'Unknown'));
                return;
            }

            ApiResponse::success(null, '類別已刪除');
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    // ==================
    // 私有輔助方法
    // ==================

    /**
     * 過濾只保留機構內的類別
     */
    private function filterInstitutionCategories(array $categories, int $rootId): array
    {
        $allowedIds = [$rootId => true];

        // 遞迴找出所有子類別
        do {
            $added = false;
            foreach ($categories as $cat) {
                $parentId = $cat['parent'] ?? 0;
                $catId = $cat['id'];
                if (isset($allowedIds[$parentId]) && !isset($allowedIds[$catId])) {
                    $allowedIds[$catId] = true;
                    $added = true;
                }
            }
        } while ($added);

        return array_filter($categories, fn($cat) => isset($allowedIds[$cat['id']]));
    }

    /**
     * 建立樹狀結構
     */
    private function buildCategoryTree(array $categories, int $parentId = 0): array
    {
        $tree = [];
        foreach ($categories as $cat) {
            if (($cat['parent'] ?? 0) == $parentId) {
                $cat['children'] = $this->buildCategoryTree($categories, $cat['id']);
                $tree[] = $cat;
            }
        }
        // 依名稱排序
        usort($tree, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $tree;
    }

    /**
     * 檢查是否有權存取類別
     */
    private function hasAccessToCategory(int $categoryId): bool
    {
        // 系統管理員權限
        if ($this->isAdmin()) {
            return true;
        }

        $rootId = $this->getManagementCategoryId();
        $isHospitalAdmin = $this->isHospitalAdmin();
        $isCourseCreator = $this->isCourseCreator();

        $allCategories = $this->moodle->getCategories();
        if (MoodleService::hasError($allCategories)) {
            return false;
        }

        // 院區管理員邏輯 (原邏輯)
        if ($isHospitalAdmin || $rootId > 0) {
            if ($categoryId == $rootId)
                return true;
            $allowedIds = $this->filterInstitutionCategories($allCategories, $rootId);
            $allowedIdArray = array_column($allowedIds, 'id');
            if (in_array($categoryId, $allowedIdArray)) {
                return true;
            }
        }

        // 教師邏輯 (新增)
        if ($isCourseCreator) {
            $teacherCatIds = $this->getCourseCreatorCategoryIds();
            if (in_array($categoryId, $teacherCatIds))
                return true; // 直接指派的類別

            // 檢查是否為直接指派類別的子孫類別
            $catMap = [];
            foreach ($allCategories as $cat) {
                $catMap[$cat['id']] = $cat['parent'] ?? 0;
            }

            // 往上找父類別，如果任一祖先在 $teacherCatIds 中，則允許存取
            $currentParentId = $catMap[$categoryId] ?? 0;
            while ($currentParentId > 0) {
                if (in_array($currentParentId, $teacherCatIds)) {
                    return true;
                }
                $currentParentId = $catMap[$currentParentId] ?? 0;
            }
        }

        return false;
    }

    /**
     * 為類別建立對應的群組
     */
    private function createCohortForCategory(int $categoryId, string $categoryName, int $parentCategoryId = 0): void
    {
        $idnumber = 'cat_' . $categoryId;

        $result = $this->moodle->call('core_cohort_create_cohorts', [
            'cohorts' => [
                [
                    'categorytype' => ['type' => 'id', 'value' => (string) $categoryId],
                    'name' => $categoryName,
                    'idnumber' => $idnumber,
                    'description' => '自動建立，對應類別：' . $categoryName,
                    'descriptionformat' => 1
                ]
            ]
        ]);

        // Log if cohort creation failed
        if (isset($result['exception']) || isset($result['error'])) {
            error_log('Cohort creation failed for category ' . $categoryId . ': ' . json_encode($result));
            return;
        }

        $newCohortId = $result[0]['id'] ?? 0;
        if ($newCohortId <= 0)
            return;

        // Create cohort_dimensions entry in Portal DB
        // Find parent category's cohort to link as parent
        try {
            $conn = $this->db->getConnection();
            $moodleConn = $this->db->getMoodleConnection();

            // Find the parent category's cohort ID by looking up cohorts in parent category's context
            $parentCohortId = 0;
            $dimensionTypeId = 0;
            if ($parentCategoryId > 0) {
                $stmt = $moodleConn->prepare("
                    SELECT c.id FROM mdl_cohort c 
                    JOIN mdl_context ctx ON c.contextid = ctx.id 
                    WHERE ctx.contextlevel = 40 AND ctx.instanceid = ?
                    LIMIT 1
                ");
                $stmt->bind_param('i', $parentCategoryId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $parentCohortId = $row['id'] ?? 0;
                $stmt->close();

                // Get the parent's dimension type
                if ($parentCohortId > 0) {
                    $stmt = $conn->prepare("SELECT dimension_type_id FROM cohort_dimensions WHERE moodle_cohort_id = ?");
                    $stmt->bind_param('i', $parentCohortId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $dimensionTypeId = $row['dimension_type_id'] ?? 0;
                    $stmt->close();
                }
            }

            // Insert cohort_dimensions entry
            if ($dimensionTypeId > 0) {
                $stmt = $conn->prepare("INSERT INTO cohort_dimensions (dimension_type_id, moodle_cohort_id, display_name, parent_cohort_id) VALUES (?, ?, ?, ?)");
                $parentVal = $parentCohortId > 0 ? $parentCohortId : null;
                $stmt->bind_param('iisi', $dimensionTypeId, $newCohortId, $categoryName, $parentVal);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log('Cohort dimensions entry failed for cohort ' . $newCohortId . ': ' . $e->getMessage());
        }
    }

    /**
     * 取得類別的 context ID
     */
    private function getCategoryContextId(int $categoryId): int
    {
        $moodleConn = $this->db->getMoodleConnection();
        $stmt = $moodleConn->prepare("SELECT id FROM mdl_context WHERE contextlevel = 40 AND instanceid = ?");
        $stmt->bind_param('i', $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row['id'] ?? 0;
    }

    // ═══════════════════════════════════════════════════
    // Phase 2C 新增方法
    // ═══════════════════════════════════════════════════

    /**
     * 列出子類別（含必修標記、子類別數量計算）
     * GET ?route=categories/list_children[&parent=N]
     * 
     * 相容 legacy manage_category.php?action=list
     */
    public function listChildren(): void
    {
        $this->requireHospitalAdmin();

        $mgmtCatId = $this->getManagementCategoryId();
        $isCourseCreator = $this->isCourseCreator();

        $parentId = $this->inputInt('parent', $mgmtCatId);

        if ($mgmtCatId > 0 && $parentId === 0) {
            $parentId = $mgmtCatId;
        }

        try {
            $allCategories = $this->moodle->getCategories();
            if (MoodleService::hasError($allCategories)) {
                ApiResponse::error('無法取得類別');
                return;
            }

            // 權限檢查
            if (!$this->isAdmin()) {
                if ($isCourseCreator && !$this->isHospitalAdmin()) {
                    // 教師展開特定節點，檢查權限
                    if ($parentId > 0 && !$this->hasAccessToCategory($parentId)) {
                        echo json_encode(['success' => true, 'data' => [], 'current_parent' => $parentId]);
                        return;
                    }
                } else if ($mgmtCatId > 0 && $parentId != $mgmtCatId) {
                    // 院區管理員檢查
                    if (!$this->hasAccessToCategory($parentId)) {
                        $parentId = $mgmtCatId;
                    }
                }
            }

            // 過濾直接子類別並計算 childcount
            $filtered = [];
            foreach ($allCategories as $cat) {
                if ($cat['parent'] == $parentId) {
                    // 再次檢查子類別是否在權限允許範圍內（特別針對教師可能只有部分子樹權限）
                    if ($isCourseCreator && !$this->isHospitalAdmin() && !$this->isAdmin()) {
                        if (!$this->hasAccessToCategory($cat['id'])) {
                            continue;
                        }
                    }

                    $childcount = 0;
                    foreach ($allCategories as $child) {
                        if ($child['parent'] == $cat['id']) {
                            // 同樣檢查孫代權限以決定是否有資料夾 icon
                            if ($isCourseCreator && !$this->isHospitalAdmin() && !$this->isAdmin()) {
                                if ($this->hasAccessToCategory($child['id'])) {
                                    $childcount++;
                                }
                            } else {
                                $childcount++;
                            }
                        }
                    }
                    $cat['childcount'] = $childcount;
                    $cat['coursecount'] = $cat['coursecount'] ?? 0;
                    $filtered[] = $cat;
                }
            }

            // 查詢必修設定
            $catIds = array_column($filtered, 'id');
            if (!empty($catIds)) {
                $placeholders = implode(',', array_fill(0, count($catIds), '?'));
                $conn = $this->db->getConnection();
                $stmt = $conn->prepare(
                    "SELECT moodle_category_id, is_mandatory_category, required_pass_count 
                     FROM portal_category_settings WHERE moodle_category_id IN ($placeholders)"
                );
                $types = str_repeat('i', count($catIds));
                $stmt->bind_param($types, ...$catIds);
                $stmt->execute();
                $result = $stmt->get_result();
                $mandatoryCats = [];
                while ($row = $result->fetch_assoc()) {
                    $mandatoryCats[$row['moodle_category_id']] = $row;
                }
                $stmt->close();

                foreach ($filtered as &$cat) {
                    $cat['is_mandatory'] = isset($mandatoryCats[$cat['id']])
                        && $mandatoryCats[$cat['id']]['is_mandatory_category'] == 1;
                }
            }

            echo json_encode(['success' => true, 'data' => $filtered, 'current_parent' => $parentId]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 取得特定類別從管理根目錄到自身的完整路徑 (用於還原樹狀結構狀態)
     * GET ?route=categories/get_path&category_id=123
     */
    public function getPath(): void
    {
        $this->requireHospitalAdmin();

        $categoryId = $this->inputInt('category_id');
        if ($categoryId <= 0) {
            ApiResponse::error('缺少類別 ID');
            return;
        }

        try {
            $allCategories = $this->moodle->getCategories();
            if (MoodleService::hasError($allCategories)) {
                ApiResponse::error('無法取得類別');
                return;
            }

            // 用 id 建 index
            $catMap = [];
            foreach ($allCategories as $cat) {
                $catMap[$cat['id']] = $cat;
            }

            if (!isset($catMap[$categoryId])) {
                ApiResponse::error('找不到指定的類別');
                return;
            }

            // 權限檢查
            if (!$this->hasAccessToCategory($categoryId)) {
                ApiResponse::forbidden('無權存取此類別');
                return;
            }

            $mgmtCatId = $this->getManagementCategoryId();
            $path = [];
            $currentId = $categoryId;

            // 往上找 parent，直到遇到 mgmtCatId 或 0
            while ($currentId > 0 && isset($catMap[$currentId])) {
                $cat = $catMap[$currentId];
                array_unshift($path, [
                    'id' => $cat['id'],
                    'name' => $cat['name'],
                    'parent' => $cat['parent']
                ]);

                if ($currentId == $mgmtCatId || $currentId == 0) {
                    break;
                }
                $currentId = $cat['parent'];
            }

            echo json_encode(['success' => true, 'path' => $path]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 列出所有類別（精簡欄位，用於前端快取）
     * GET ?route=categories/list_all
     */
    public function listAll(): void
    {
        $this->requireHospitalAdmin();

        $mgmtCatId = $this->getManagementCategoryId();

        try {
            $allCategories = $this->moodle->getCategories();
            if (MoodleService::hasError($allCategories)) {
                ApiResponse::error('無法取得類別');
                return;
            }

            $filtered = [];
            foreach ($allCategories as $cat) {
                if ($mgmtCatId > 0) {
                    $pathIds = array_filter(explode('/', $cat['path']), 'strlen');
                    if (!in_array($mgmtCatId, $pathIds))
                        continue;
                }
                $filtered[] = ['id' => $cat['id'], 'name' => $cat['name'], 'parent' => $cat['parent']];
            }

            echo json_encode(['success' => true, 'data' => $filtered]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 列出完整樹狀結構（含層級深度）
     * GET ?route=categories/list_tree
     */
    public function listTree(): void
    {
        $this->requireHospitalAdmin();

        $mgmtCatId = $this->getManagementCategoryId();

        try {
            $allCategories = $this->moodle->getCategories();
            if (MoodleService::hasError($allCategories)) {
                ApiResponse::error('無法取得類別');
                return;
            }

            $childrenMap = [];
            foreach ($allCategories as $cat) {
                $childrenMap[$cat['parent']][] = $cat;
            }

            $tree = $this->buildFlatTree($mgmtCatId, $childrenMap, 0, $mgmtCatId);
            echo json_encode(['success' => true, 'data' => $tree]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 遞迴建立扁平樹狀結構
     */
    private function buildFlatTree(int $parentId, array &$childrenMap, int $depth, int $mgmtCatId): array
    {
        $result = [];
        if (!isset($childrenMap[$parentId]))
            return $result;

        $children = $childrenMap[$parentId];
        usort($children, fn($a, $b) => ($a['sortorder'] ?? 0) <=> ($b['sortorder'] ?? 0));

        foreach ($children as $cat) {
            if ($mgmtCatId > 0 && $depth == 0 && $cat['id'] != $mgmtCatId && $cat['parent'] != $mgmtCatId) {
                continue;
            }
            $cat['depth'] = $depth;
            $result[] = $cat;
            $result = array_merge($result, $this->buildFlatTree($cat['id'], $childrenMap, $depth + 1, 0));
        }
        return $result;
    }

    /**
     * 取得類別設定（必修設定等）
     * GET ?route=categories/get_settings&category_id=N
     */
    public function getSettings(): void
    {
        $this->requireHospitalAdmin();

        $categoryId = $this->inputInt('category_id');
        if ($categoryId <= 0) {
            ApiResponse::error('缺少類別 ID');
            return;
        }

        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM portal_category_settings WHERE moodle_category_id = ?");
        $stmt->bind_param('i', $categoryId);
        $stmt->execute();
        $settings = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$settings) {
            $settings = [
                'moodle_category_id' => $categoryId,
                'is_mandatory_category' => 0,
                'required_pass_count' => 0,
                'period_months' => 0,
                'require_order' => 0,
                'visibility' => 'all'
            ];
        }

        // 查詢已指定的必修對象數量及名單
        $mandatoryUserCount = 0;
        $mandatoryUsers = [];
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM portal_category_requirements WHERE moodle_category_id = ?");
            $stmt->bind_param('i', $categoryId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $mandatoryUserCount = $row['cnt'] ?? 0;
            $stmt->close();

            if ($mandatoryUserCount > 0) {
                $stmt = $conn->prepare("
                    SELECT r.id as requirement_id, r.user_id, r.moodle_user_id, r.filter_snapshot, u.username, u.fullname, u.institution
                    FROM portal_category_requirements r
                    LEFT JOIN users u ON r.user_id = u.id
                    WHERE r.moodle_category_id = ?
                    ORDER BY u.fullname
                ");
                $stmt->bind_param('i', $categoryId);
                $stmt->execute();
                $result = $stmt->get_result();
                $filterSnapshot = null;
                while ($row = $result->fetch_assoc()) {
                    if (!$filterSnapshot && !empty($row['filter_snapshot'])) {
                        $filterSnapshot = $row['filter_snapshot'];
                    }
                    $mandatoryUsers[] = [
                        'requirement_id' => (int) $row['requirement_id'],
                        'user_id' => $row['user_id'],
                        'id' => $row['user_id'],
                        'username' => $row['username'] ?? '',
                        'fullname' => $row['fullname'] ?? $row['username'] ?? '未知',
                        'institution' => $row['institution'] ?? ''
                    ];
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            // 表可能不存在，忽略
        }
        $settings['mandatory_user_count'] = $mandatoryUserCount;
        $settings['mandatory_users'] = $mandatoryUsers;

        // 解析 filter_snapshot 中的 cohort ID 為名稱
        $filterData = json_decode($filterSnapshot ?? '[]', true);
        if (!empty($filterData) && is_array($filterData)) {
            // 收集所有 cohort ID
            $allCohortIds = [];
            foreach ($filterData as $group) {
                if (!empty($group['category']))
                    $allCohortIds[] = (int) $group['category'];
                if (!empty($group['location']))
                    $allCohortIds[] = (int) $group['location'];
                if (!empty($group['attribute']))
                    $allCohortIds[] = (int) $group['attribute'];
            }
            // 查詢 cohort 名稱
            $cohortNames = [];
            if (!empty($allCohortIds)) {
                $placeholders = implode(',', array_fill(0, count($allCohortIds), '?'));
                $types = str_repeat('i', count($allCohortIds));
                $stmt = $conn->prepare("SELECT moodle_cohort_id, display_name FROM cohort_dimensions WHERE moodle_cohort_id IN ($placeholders)");
                $stmt->bind_param($types, ...$allCohortIds);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $cohortNames[(int) $row['moodle_cohort_id']] = $row['display_name'];
                }
                $stmt->close();
            }
            // 替換 ID 為名稱
            foreach ($filterData as &$group) {
                if (!empty($group['category'])) {
                    $cid = (int) $group['category'];
                    $group['category_name'] = $cohortNames[$cid] ?? $group['category_name'] ?? $group['category'];
                }
                if (!empty($group['location'])) {
                    $lid = (int) $group['location'];
                    $group['location_name'] = $cohortNames[$lid] ?? $group['location_name'] ?? $group['location'];
                }
                if (!empty($group['attribute'])) {
                    $aid = (int) $group['attribute'];
                    $group['attribute_name'] = $cohortNames[$aid] ?? $group['attribute_name'] ?? $group['attribute'];
                }
            }
            unset($group);
        }
        $settings['filter_snapshot'] = $filterData;

        echo json_encode(['success' => true, 'settings' => $settings]);
    }

    /**
     * 更新類別設定
     * POST ?route=categories/update_settings
     */
    public function updateSettings(): void
    {
        $this->requireHospitalAdmin();

        $categoryId = $this->inputInt('category_id');
        if ($categoryId <= 0) {
            ApiResponse::error('缺少類別 ID');
            return;
        }

        $isMandatory = $this->inputInt('is_mandatory_category');
        $requiredCount = $this->inputInt('required_pass_count');
        $periodMonths = $this->inputInt('period_months');
        $requireOrder = $this->inputInt('require_order');
        $visibility = $this->inputString('visibility') ?: 'all';

        if (!in_array($visibility, ['all', 'mandatory_only'])) {
            $visibility = 'all';
        }

        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            INSERT INTO portal_category_settings 
                (moodle_category_id, is_mandatory_category, required_pass_count, period_months, require_order, visibility)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                is_mandatory_category = VALUES(is_mandatory_category),
                required_pass_count = VALUES(required_pass_count),
                period_months = VALUES(period_months),
                require_order = VALUES(require_order),
                visibility = VALUES(visibility),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->bind_param('iiiiis', $categoryId, $isMandatory, $requiredCount, $periodMonths, $requireOrder, $visibility);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => '類別設定已更新']);
    }

    /**
     * 檢查必修類別衝突
     * POST ?route=categories/check_mandatory_conflicts
     * 在設定必修類別時，檢查底下課程是否有衝突
     */
    public function checkMandatoryConflicts(): void
    {
        $this->requireHospitalAdmin();

        $categoryId = $this->inputInt('category_id');
        $visibility = $this->inputString('visibility') ?: 'all';
        $requiredPassCount = $this->inputInt('required_pass_count');

        if ($categoryId <= 0) {
            ApiResponse::error('缺少類別 ID');
            return;
        }

        try {
            $conn = $this->db->getConnection();
            $moodle = new MoodleService();

            // 1. 優先使用前端傳入的待儲存名單，若沒有則從 DB 查
            $inputIds = $this->inputString('moodle_user_ids');
            if (!empty($inputIds)) {
                $mandatoryMoodleIds = array_filter(array_map('intval', explode(',', $inputIds)));
            } else {
                $stmt = $conn->prepare("SELECT moodle_user_id FROM portal_category_requirements WHERE moodle_category_id = ? AND moodle_user_id > 0");
                $stmt->bind_param('i', $categoryId);
                $stmt->execute();
                $result = $stmt->get_result();
                $mandatoryMoodleIds = [];
                while ($row = $result->fetch_assoc()) {
                    $mandatoryMoodleIds[] = (int) $row['moodle_user_id'];
                }
                $stmt->close();
            }

            if (empty($mandatoryMoodleIds)) {
                ApiResponse::success(['has_conflicts' => false, 'conflicts' => []]);
                return;
            }

            // 2. 取得該類別底下的所有課程
            $courses = $moodle->call('core_course_get_courses_by_field', [
                'field' => 'category',
                'value' => $categoryId
            ]);
            $courseList = $courses['courses'] ?? $courses ?? [];
            if (isset($courseList['exception']))
                $courseList = [];

            $conflicts = [];
            // 追蹤每個必修對象被招進了幾堂課
            $userEnrolCount = array_fill_keys($mandatoryMoodleIds, 0);
            $userNames = []; // userId -> fullname

            foreach ($courseList as $course) {
                if ($course['id'] == 1)
                    continue;

                // 取得課程已招生的學員
                $enrolledUsers = $moodle->call('core_enrol_get_enrolled_users', [
                    'courseid' => $course['id']
                ]);
                if (isset($enrolledUsers['exception']))
                    $enrolledUsers = [];

                $enrolledIds = array_map(fn($u) => (int) $u['id'], $enrolledUsers);

                // 計算每個必修對象的招生數
                foreach ($enrolledUsers as $u) {
                    $uid = (int) $u['id'];
                    if (isset($userEnrolCount[$uid])) {
                        $userEnrolCount[$uid]++;
                        if (!isset($userNames[$uid])) {
                            $userNames[$uid] = $u['fullname'] ?? $u['username'] ?? '';
                        }
                    }
                }

                if ($visibility === 'mandatory_only') {
                    // 僅他們可見：檢查課程中是否有「範圍外」的人
                    $extraUsers = array_diff($enrolledIds, $mandatoryMoodleIds);
                    if (!empty($extraUsers)) {
                        $extraNames = [];
                        foreach ($enrolledUsers as $u) {
                            if (in_array((int) $u['id'], $extraUsers)) {
                                $extraNames[] = ['id' => $u['id'], 'fullname' => $u['fullname'] ?? $u['username'] ?? ''];
                            }
                        }
                        $conflicts[] = [
                            'course_id' => $course['id'],
                            'course_name' => $course['fullname'],
                            'type' => 'extra_users',
                            'count' => count($extraUsers),
                            'users' => array_slice($extraNames, 0, 10)
                        ];
                    }
                } else {
                    // 全部可見：檢查課程中是否「缺少」必修對象
                    $missingIds = array_diff($mandatoryMoodleIds, $enrolledIds);
                    if (!empty($missingIds) && !empty($enrolledIds)) {
                        // 只有課程已經有人時才檢查（空課程不算衝突）
                        $conflicts[] = [
                            'course_id' => $course['id'],
                            'course_name' => $course['fullname'],
                            'type' => 'missing_users',
                            'count' => count($missingIds)
                        ];
                    }
                }
            }

            // 3. 檢查招生數不足的必修對象（只在 required_pass_count > 0 時）
            if ($requiredPassCount > 0 && !empty($courseList)) {
                $insufficientUsers = [];
                foreach ($userEnrolCount as $uid => $count) {
                    if ($count < $requiredPassCount) {
                        $insufficientUsers[] = [
                            'id' => $uid,
                            'fullname' => $userNames[$uid] ?? '(ID:' . $uid . ')',
                            'enrolled_count' => $count
                        ];
                    }
                }
                if (!empty($insufficientUsers)) {
                    $conflicts[] = [
                        'type' => 'insufficient_enrollment',
                        'required_count' => $requiredPassCount,
                        'count' => count($insufficientUsers),
                        'users' => array_slice($insufficientUsers, 0, 20)
                    ];
                }
            }

            ApiResponse::success([
                'has_conflicts' => !empty($conflicts),
                'conflicts' => $conflicts,
                'mandatory_user_count' => count($mandatoryMoodleIds)
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 搜尋符合條件的使用者
     * POST ?route=categories/search_users_by_filter
     */
    public function searchUsersByFilter(): void
    {
        $this->requireHospitalAdmin();

        $json = $this->getJsonInput();
        $filterGroups = $json['filter_groups'] ?? [];
        $operators = $json['operators'] ?? [];
        $tagIds = $json['tag_ids'] ?? [];

        if (empty($filterGroups) && empty($tagIds)) {
            ApiResponse::error('缺少篩選條件');
            return;
        }

        try {
            $moodleConn = $this->db->getMoodleConnection();
            $resultUsers = null;

            foreach ($filterGroups as $index => $group) {
                $cohortIds = [];
                if (!empty($group['category']))
                    $cohortIds[] = (int) $group['category'];
                if (!empty($group['location']))
                    $cohortIds[] = (int) $group['location'];
                if (!empty($group['attribute']))
                    $cohortIds[] = (int) $group['attribute'];
                if (empty($cohortIds))
                    continue;

                $count = count($cohortIds);
                $idsStr = implode(',', $cohortIds);

                $sql = "SELECT u.id, u.username, CONCAT(u.lastname, u.firstname) as fullname, u.email
                        FROM mdl_user u
                        INNER JOIN (SELECT userid FROM mdl_cohort_members 
                            WHERE cohortid IN ($idsStr) GROUP BY userid HAVING COUNT(DISTINCT cohortid) = $count
                        ) cm ON u.id = cm.userid
                        WHERE u.deleted = 0 AND u.suspended = 0";

                $groupUsers = [];
                $result = $moodleConn->query($sql);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $groupUsers[$row['id']] = [
                            'id' => $row['id'],
                            'username' => $row['username'],
                            'fullname' => $row['fullname'],
                            'email' => $row['email'],
                            'moodle_id' => $row['id']
                        ];
                    }
                }

                if ($resultUsers === null) {
                    $resultUsers = $groupUsers;
                } else {
                    $op = $operators[$index - 1] ?? 'or';
                    $resultUsers = ($op === 'and')
                        ? array_intersect_key($resultUsers, $groupUsers)
                        : $resultUsers + $groupUsers;
                }
            }

            // 標籤篩選
            if (!empty($tagIds)) {
                $tagIds = array_filter(array_map('intval', $tagIds));
                if (!empty($tagIds)) {
                    $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
                    $types = str_repeat('i', count($tagIds));
                    $conn = $this->db->getConnection();
                    $stmt = $conn->prepare("SELECT name FROM portal_tags WHERE id IN ($placeholders)");
                    $stmt->bind_param($types, ...$tagIds);
                    $stmt->execute();
                    $tagResult = $stmt->get_result();
                    $tagNames = [];
                    while ($row = $tagResult->fetch_assoc())
                        $tagNames[] = $row['name'];
                    $stmt->close();

                    if (!empty($tagNames)) {
                        $namePlaceholders = implode(',', array_fill(0, count($tagNames), '?'));
                        $nameTypes = str_repeat('s', count($tagNames));
                        $tagSql = "SELECT DISTINCT u.id, u.username, CONCAT(u.lastname, u.firstname) as fullname, u.email
                                   FROM mdl_tag_instance ti JOIN mdl_tag t ON ti.tagid = t.id
                                   JOIN mdl_user u ON ti.itemid = u.id
                                   WHERE ti.itemtype = 'user' AND t.rawname IN ($namePlaceholders)
                                   AND u.deleted = 0 AND u.suspended = 0";
                        $tagStmt = $moodleConn->prepare($tagSql);
                        $tagStmt->bind_param($nameTypes, ...$tagNames);
                        $tagStmt->execute();
                        $tagUsersResult = $tagStmt->get_result();
                        $tagUsers = [];
                        while ($row = $tagUsersResult->fetch_assoc()) {
                            $tagUsers[$row['id']] = [
                                'id' => $row['id'],
                                'username' => $row['username'],
                                'fullname' => $row['fullname'],
                                'email' => $row['email'],
                                'moodle_id' => $row['id']
                            ];
                        }
                        $tagStmt->close();
                        $resultUsers = ($resultUsers === null) ? $tagUsers : array_intersect_key($resultUsers, $tagUsers);
                    }
                }
            }

            $users = $resultUsers ?? [];

            // 修正 user_id 為 Portal DB 的 id
            if (!empty($users)) {
                $usernames = array_column($users, 'username');

                // 為了避免 prepared statement 超過限制，這裡使用安全的字串串接
                $safeUsernames = [];
                $conn = $this->db->getConnection();
                foreach ($usernames as $u) {
                    $safeUsernames[] = "'" . $conn->real_escape_string($u) . "'";
                }
                $inClause = implode(',', $safeUsernames);

                $res = $conn->query("SELECT id, username FROM users WHERE username IN ($inClause)");
                $portalUserIds = [];
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $portalUserIds[$row['username']] = $row['id'];
                    }
                }

                foreach ($users as &$u) {
                    // 若 Portal DB 尚未有紀錄，預設為 0
                    $u['id'] = $portalUserIds[$u['username']] ?? 0;
                }
            }

            echo json_encode(['success' => true, 'users' => array_values($users), 'count' => count($users)]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 儲存必修需求
     * POST ?route=categories/save_mandatory_requirements
     */
    public function saveMandatoryRequirements(): void
    {
        $this->requireHospitalAdmin();

        $json = $this->getJsonInput();
        $categoryId = (int) ($json['category_id'] ?? 0);
        $requiredCount = (int) ($json['required_pass_count'] ?? 1);
        $periodMonths = (int) ($json['period_months'] ?? 0);
        $userIds = $json['user_ids'] ?? [];
        $filterSnapshot = json_encode($json['filter_groups'] ?? []);

        if ($categoryId <= 0) {
            ApiResponse::error('缺少類別 ID');
            return;
        }
        if (empty($userIds)) {
            ApiResponse::error('沒有選擇使用者');
            return;
        }

        $deadline = ($periodMonths > 0) ? date('Y-m-d', strtotime("+$periodMonths months")) : null;
        $createdBy = $_SESSION['username'] ?? 'admin';

        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("DELETE FROM portal_category_requirements WHERE moodle_category_id = ?");
        $stmt->bind_param('i', $categoryId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO portal_category_requirements 
                (moodle_category_id, user_id, moodle_user_id, required_pass_count, deadline, filter_snapshot, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $inserted = 0;
        foreach ($userIds as $uid) {
            $userId = (int) $uid['id'];
            $moodleUserId = (int) ($uid['moodle_id'] ?? 0);
            $stmt->bind_param('iiiisss', $categoryId, $userId, $moodleUserId, $requiredCount, $deadline, $filterSnapshot, $createdBy);
            if ($stmt->execute())
                $inserted++;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'message' => "已為 $inserted 位使用者設定必修需求", 'inserted_count' => $inserted]);
    }

    /**
     * 從必修名單移除單一使用者
     * POST ?route=categories/requirement/remove
     */
    public function requirementRemove(): void
    {
        $this->requireHospitalAdmin();

        error_log("[requirementRemove] POST=" . json_encode($_POST) . " REQUEST=" . json_encode($_REQUEST));

        $categoryId = $this->inputInt('category_id');
        $requirementId = intval($_POST['requirement_id'] ?? $_REQUEST['requirement_id'] ?? 0);
        $userId = $this->inputInt('user_id');

        if ($categoryId <= 0) {
            ApiResponse::error('缺少類別 ID');
            return;
        }
        if ($requirementId <= 0 && $userId <= 0) {
            ApiResponse::error('缺少 requirement_id 或 user_id');
            return;
        }

        try {
            $conn = $this->db->getConnection();
            $deletedCount = 0;

            // 優先用 PK 刪（最可靠）
            if ($requirementId > 0) {
                $stmt = $conn->prepare("DELETE FROM portal_category_requirements WHERE id = ? AND moodle_category_id = ?");
                $stmt->bind_param("ii", $requirementId, $categoryId);
                $stmt->execute();
                $deletedCount = $stmt->affected_rows;
                $stmt->close();
            }

            // fallback: 用 user_id
            if ($deletedCount === 0 && $userId > 0) {
                $stmt = $conn->prepare("DELETE FROM portal_category_requirements WHERE moodle_category_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $categoryId, $userId);
                $stmt->execute();
                $deletedCount = $stmt->affected_rows;
                $stmt->close();
            }

            // fallback: 用 moodle_user_id
            if ($deletedCount === 0 && $userId > 0) {
                $stmt = $conn->prepare("DELETE FROM portal_category_requirements WHERE moodle_category_id = ? AND moodle_user_id = ?");
                $stmt->bind_param("ii", $categoryId, $userId);
                $stmt->execute();
                $deletedCount = $stmt->affected_rows;
                $stmt->close();
            }

            ApiResponse::success([
                'deleted_count' => $deletedCount
            ], $deletedCount > 0 ? "已將該使用者從必修名單中移除" : "找不到該使用者的必修記錄");
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 取得所有必修類別
     * GET ?route=categories/get_mandatory_categories
     */
    public function getMandatoryCategories(): void
    {
        $this->requireHospitalAdmin();

        try {
            $conn = $this->db->getConnection();
            $result = $conn->query("SELECT * FROM portal_category_settings WHERE is_mandatory_category = 1");

            $settings = [];
            while ($row = $result->fetch_assoc()) {
                $settings[] = $row;
            }

            if (!empty($settings)) {
                $allCats = $this->moodle->getCategories();
                $catNames = [];
                if (!MoodleService::hasError($allCats)) {
                    foreach ($allCats as $cat)
                        $catNames[$cat['id']] = $cat['name'];
                }
                foreach ($settings as &$s) {
                    $s['category_name'] = $catNames[$s['moodle_category_id']] ?? '未知類別';
                }
            }

            echo json_encode(['success' => true, 'categories' => $settings]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 批次建立部門類別與群組（系統管理員用）
     * POST ?route=categories/batch_create
     */
    public function batchCreate(): void
    {
        $this->requireAdmin();

        $institutionId = $this->inputInt('institution_id');
        $mode = $this->inputString('mode') ?: 'single';

        if ($institutionId <= 0) {
            ApiResponse::error('未選擇院區');
            return;
        }

        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM institutions WHERE id = ?");
        $stmt->bind_param('i', $institutionId);
        $stmt->execute();
        $inst = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$inst) {
            ApiResponse::error('找不到該院區');
            return;
        }

        $categoriesToCreate = [];
        if ($mode === 'single') {
            $name = $this->inputString('category_name');
            if (!empty($name))
                $categoriesToCreate[] = $name;
        } else {
            $names = $this->inputString('category_names');
            foreach (explode("\n", $names) as $line) {
                $line = trim($line);
                if (!empty($line))
                    $categoriesToCreate[] = $line;
            }
        }

        if (empty($categoriesToCreate)) {
            ApiResponse::error('沒有輸入任何有效的部門名稱');
            return;
        }

        try {
            $moodleConn = $this->db->getMoodleConnection();
            $cohortIdnumber = $inst['cohort_idnumber'];

            $stmt = $moodleConn->prepare("
                SELECT ctx.instanceid as category_id
                FROM mdl_cohort c JOIN mdl_context ctx ON ctx.id = c.contextid
                WHERE c.idnumber = ? AND ctx.contextlevel = 40
            ");
            $stmt->bind_param('s', $cohortIdnumber);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $mgmtCatId = $row['category_id'] ?? 0;
            $stmt->close();

            if (!$mgmtCatId) {
                ApiResponse::error("無法找到院區 [{$inst['name']}] 在 Moodle 的根類別");
                return;
            }

            $log = [];
            $countSuccess = 0;

            foreach ($categoriesToCreate as $catName) {
                $logEntry = "處理 [$catName]: ";
                try {
                    $stmt = $moodleConn->prepare("SELECT id FROM mdl_course_categories WHERE parent = ? AND name = ?");
                    $stmt->bind_param('is', $mgmtCatId, $catName);
                    $stmt->execute();
                    $catRow = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $targetCatId = 0;
                    if ($catRow) {
                        $targetCatId = $catRow['id'];
                        $logEntry .= "類別已存在 (ID: $targetCatId), ";
                    } else {
                        $createRes = $this->moodle->call('core_course_create_categories', [
                            'categories' => [['name' => $catName, 'parent' => $mgmtCatId, 'description' => '由系統管理員自動建立', 'descriptionformat' => 1]]
                        ]);
                        if (isset($createRes[0]['id'])) {
                            $targetCatId = $createRes[0]['id'];
                            $logEntry .= "類別建立成功 (ID: $targetCatId), ";
                        } else {
                            $logEntry .= "類別建立失敗, ";
                            $log[] = $logEntry;
                            continue;
                        }
                    }

                    // Context ID
                    $targetCtxId = 0;
                    for ($i = 0; $i < 3; $i++) {
                        $stmt = $moodleConn->prepare("SELECT id FROM mdl_context WHERE instanceid = ? AND contextlevel = 40");
                        $stmt->bind_param('i', $targetCatId);
                        $stmt->execute();
                        $ctxRow = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if ($ctxRow) {
                            $targetCtxId = $ctxRow['id'];
                            break;
                        }
                        usleep(500000);
                    }

                    if (!$targetCtxId) {
                        $logEntry .= "無法取得 Context ID。";
                        $log[] = $logEntry;
                        continue;
                    }

                    $stmt = $moodleConn->prepare("SELECT id FROM mdl_cohort WHERE contextid = ? AND name = ?");
                    $stmt->bind_param('is', $targetCtxId, $catName);
                    $stmt->execute();
                    $cohRow = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($cohRow) {
                        $logEntry .= "群組已存在 (ID: {$cohRow['id']})。";
                    } else {
                        $newIdnumber = $cohortIdnumber . '_' . $targetCatId . '_' . uniqid();
                        $cohCreateRes = $this->moodle->call('core_cohort_create_cohorts', [
                            'cohorts' => [['categorytype' => ['type' => 'id', 'value' => $targetCatId], 'name' => $catName, 'idnumber' => $newIdnumber, 'description' => '自動建立']]
                        ]);
                        if (isset($cohCreateRes[0]['id'])) {
                            $newCohortId = $cohCreateRes[0]['id'];
                            $logEntry .= "群組建立成功 (ID: $newCohortId)";

                            $dimStmt = $conn->prepare("SELECT id FROM dimension_types WHERE institution_id = ? AND name = '職類' LIMIT 1");
                            $dimStmt->bind_param('i', $institutionId);
                            $dimStmt->execute();
                            $dimStmt->bind_result($dimensionTypeId);
                            $dimFound = $dimStmt->fetch();
                            $dimStmt->close();

                            if ($dimFound && $dimensionTypeId > 0) {
                                $insStmt = $conn->prepare("INSERT INTO cohort_dimensions (dimension_type_id, moodle_cohort_id, display_name, parent_category_id) VALUES (?, ?, ?, ?)");
                                $insStmt->bind_param('iisi', $dimensionTypeId, $newCohortId, $catName, $targetCatId);
                                $insStmt->execute();
                                $insStmt->close();
                                $logEntry .= "，已歸入職類維度。";
                            }
                            $countSuccess++;
                        } else {
                            $logEntry .= "群組建立失敗。";
                        }
                    }
                } catch (Exception $e) {
                    $logEntry .= "錯誤: " . $e->getMessage();
                }
                $log[] = $logEntry;
            }

            echo json_encode(['success' => true, 'message' => "處理完成！成功建立: $countSuccess", 'log' => implode("\n", $log)]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }
}
