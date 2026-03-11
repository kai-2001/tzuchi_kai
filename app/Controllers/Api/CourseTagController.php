<?php
/**
 * 課程標籤 API Controller
 * app/Controllers/Api/CourseTagController.php
 */

class CourseTagController extends Controller
{
    private MoodleService $moodle;
    
    public function __construct()
    {
        parent::__construct();
        $this->moodle = new MoodleService();
    }
    
    /**
     * 取得課程的標籤
     * GET ?action=list&course_id=123
     */
    public function list(): void
    {
        $this->requireHospitalAdmin();
        
        $courseId = $this->inputInt('course_id');
        if ($courseId <= 0) {
            ApiResponse::error('缺少 course_id');
            return;
        }
        
        $institution = $this->getInstitutionName();
        
        $db = Database::getInstance();
        $sql = "SELECT ct.id, ct.tag_id, t.name, t.color
                FROM course_tags ct
                JOIN portal_tags t ON t.id = ct.tag_id
                WHERE ct.course_id = ? AND ct.institution = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('is', $courseId, $institution);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tags = [];
        while ($row = $result->fetch_assoc()) {
            $tags[] = $row;
        }
        $stmt->close();
        
        ApiResponse::success($tags);
    }
    
    /**
     * 為課程加上標籤
     * POST ?action=add
     * Body: { course_id, tag_id }
     */
    public function add(): void
    {
        $this->requireHospitalAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $courseId = (int)($input['course_id'] ?? 0);
        $tagId = (int)($input['tag_id'] ?? 0);
        
        if ($courseId <= 0 || $tagId <= 0) {
            ApiResponse::error('缺少 course_id 或 tag_id');
            return;
        }
        
        $institution = $this->getInstitutionName();
        
        $db = Database::getInstance();
        $sql = "INSERT IGNORE INTO course_tags (course_id, tag_id, institution) VALUES (?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('iis', $courseId, $tagId, $institution);
        $stmt->execute();
        $stmt->close();
        
        ApiResponse::success(null, '標籤已新增');
    }
    
    /**
     * 移除課程的標籤
     * POST ?action=remove
     * Body: { course_id, tag_id }
     */
    public function remove(): void
    {
        $this->requireHospitalAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $courseId = (int)($input['course_id'] ?? 0);
        $tagId = (int)($input['tag_id'] ?? 0);
        
        if ($courseId <= 0 || $tagId <= 0) {
            ApiResponse::error('缺少 course_id 或 tag_id');
            return;
        }
        
        $institution = $this->getInstitutionName();
        
        $db = Database::getInstance();
        $sql = "DELETE FROM course_tags WHERE course_id = ? AND tag_id = ? AND institution = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('iis', $courseId, $tagId, $institution);
        $stmt->execute();
        $stmt->close();
        
        ApiResponse::success(null, '標籤已移除');
    }
    
    /**
     * 設定課程的所有標籤（覆蓋）
     * POST ?action=set
     * Body: { course_id, tag_ids: [1, 2, 3] }
     */
    public function set(): void
    {
        $this->requireHospitalAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $courseId = (int)($input['course_id'] ?? 0);
        $tagIds = $input['tag_ids'] ?? [];
        
        if ($courseId <= 0) {
            ApiResponse::error('缺少 course_id');
            return;
        }
        
        $institution = $this->getInstitutionName();
        $db = Database::getInstance();
        
        // 先刪除該課程的所有標籤
        $sql = "DELETE FROM course_tags WHERE course_id = ? AND institution = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('is', $courseId, $institution);
        $stmt->execute();
        $stmt->close();
        
        // 新增標籤
        if (!empty($tagIds)) {
            $sql = "INSERT INTO course_tags (course_id, tag_id, institution) VALUES (?, ?, ?)";
            $stmt = $db->prepare($sql);
            
            foreach ($tagIds as $tagId) {
                $tagId = (int)$tagId;
                if ($tagId > 0) {
                    $stmt->bind_param('iis', $courseId, $tagId, $institution);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
        
        ApiResponse::success(null, '標籤已更新');
    }
    
    /**
     * 取得所有可用標籤
     * GET ?action=available
     */
    public function available(): void
    {
        $this->requireHospitalAdmin();  // 需要登入權限才能讀取標籤
        
        $institution = $this->getInstitutionName();
        $db = Database::getInstance();
        
        // 取得系統範本標籤（is_template=1）+ 院區自訂標籤
        $sql = "SELECT id, name, color, 
                CASE WHEN is_template = 1 THEN 'template' ELSE 'custom' END as type
                FROM portal_tags 
                WHERE is_template = 1 OR institution_code = ?
                ORDER BY is_template DESC, name";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('s', $institution);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tags = [];
        while ($row = $result->fetch_assoc()) {
            $tags[] = $row;
        }
        $stmt->close();
        
        ApiResponse::success($tags);
    }
    
    /**
     * 新增院區標籤
     * POST ?route=tags/course/create
     * Body: { name: "標籤名稱", color: "#3b82f6" }
     * 
     * 對應舊版 course_tags.php?action=create 的邏輯
     */
    public function create(): void
    {
        $this->requireHospitalAdmin();
        
        $name = $this->inputString('name');
        $color = $this->inputString('color') ?: '#3b82f6';
        $institution = $this->getInstitutionName();
        
        if (empty($name)) {
            ApiResponse::error('請輸入標籤名稱');
            return;
        }
        
        $db = Database::getInstance();
        
        // 檢查是否已存在
        $checkStmt = $db->prepare("SELECT id FROM portal_tags WHERE name = ? AND institution_code = ?");
        $checkStmt->bind_param('ss', $name, $institution);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $checkStmt->close();
            ApiResponse::error('此標籤名稱已存在');
            return;
        }
        $checkStmt->close();
        
        // 新增
        $stmt = $db->prepare("INSERT INTO portal_tags (name, color, institution_code, is_template) VALUES (?, ?, ?, 0)");
        $stmt->bind_param('sss', $name, $color, $institution);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        
        ApiResponse::success(['id' => $newId, 'name' => $name, 'color' => $color]);
    }
}
