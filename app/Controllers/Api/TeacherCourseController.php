<?php
/**
 * 教師課程 API Controller
 * app/Controllers/Api/TeacherCourseController.php
 * 
 * 提供教師專屬的課程管理 API，
 * 只回傳被指派的類別及其子類別。
 */

class TeacherCourseController extends Controller
{
    private MoodleService $moodle;

    public function __construct()
    {
        parent::__construct();
        $this->moodle = new MoodleService();
    }
    /**
     * 列出教師被指派的類別及其子類別
     * GET ?route=teacher/courses/list_categories
     * 
     * 從 session 讀取 coursecreator_category_ids，
     * 回傳這些類別及其所有子類別的樹狀結構。
     */
    public function listCategories(): void
    {
        // 確認已登入且為開課教師
        if (!isset($_SESSION['user_id'])) {
            ApiResponse::error('未登入', 401);
            return;
        }

        $teacherCatIds = $_SESSION['coursecreator_category_ids'] ?? [];
        error_log("[TeacherCourse] session user=" . ($_SESSION['username'] ?? 'none') 
            . " is_coursecreator=" . ($_SESSION['is_coursecreator'] ?? 'none')
            . " category_ids=" . json_encode($teacherCatIds)
            . " mgmt_cat=" . ($_SESSION['management_category_id'] ?? 'none'));
        if (empty($teacherCatIds)) {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }

        try {
            $allCategories = $this->moodle->getCategories();
            if (MoodleService::hasError($allCategories)) {
                ApiResponse::error('無法取得類別');
                return;
            }

            // 建立 ID -> category 對照表
            $catMap = [];
            foreach ($allCategories as $cat) {
                $catMap[$cat['id']] = $cat;
            }

            // 展開教師類別 IDs：包含指定的類別 + 所有子孫類別
            $allowedIds = [];
            foreach ($teacherCatIds as $catId) {
                $catId = (int) $catId;
                if (isset($catMap[$catId])) {
                    $allowedIds[$catId] = true;
                }
            }
            // 遞迴展開子類別
            do {
                $added = false;
                foreach ($allCategories as $cat) {
                    $parentId = $cat['parent'] ?? 0;
                    $catId = $cat['id'];
                    if (isset($allowedIds[$parentId]) && !isset($allowedIds[$catId])) {
                        $allowedIds[$catId] = true;
                        $added = true;
                    }
                }
            } while ($added);

            // 篩選頂層指派類別（教師直接被指派的）
            $topLevelCats = [];
            foreach ($teacherCatIds as $catId) {
                $catId = (int) $catId;
                if (isset($catMap[$catId])) {
                    $cat = $catMap[$catId];
                    // 計算直接子類別數量（只計被允許的）
                    $childcount = 0;
                    foreach ($allCategories as $child) {
                        if ($child['parent'] == $catId && isset($allowedIds[$child['id']])) {
                            $childcount++;
                        }
                    }
                    $cat['childcount'] = $childcount;
                    $cat['coursecount'] = $cat['coursecount'] ?? 0;
                    $topLevelCats[] = $cat;
                }
            }

            // 查詢必修設定
            $catIds = array_column($topLevelCats, 'id');
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

                foreach ($topLevelCats as &$cat) {
                    $cat['is_mandatory'] = isset($mandatoryCats[$cat['id']])
                        && $mandatoryCats[$cat['id']]['is_mandatory_category'] == 1;
                }
            }

            echo json_encode(['success' => true, 'data' => $topLevelCats]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }
}
