<?php
/**
 * 使用者標籤 API Controller
 * app/Controllers/Api/UserTagController.php
 */

class UserTagController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 取得使用者的標籤
     * GET ?action=list&user_id=123
     */
    public function list(): void
    {
        $this->requireHospitalAdmin();
        
        $userId = $this->inputInt('user_id');
        if ($userId <= 0) {
            ApiResponse::error('缺少 user_id');
            return;
        }
        
        $institution = $this->getInstitution();
        
        $db = Database::getInstance();
        $sql = "SELECT ut.id, ut.tag_id, t.name, t.color
                FROM user_tags ut
                JOIN portal_tags t ON t.id = ut.tag_id
                WHERE ut.user_id = ? AND ut.institution = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('is', $userId, $institution);
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
     * 為使用者加上標籤
     * POST ?action=add
     * Body: { user_id, tag_id }
     */
    public function add(): void
    {
        $this->requireHospitalAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = (int)($input['user_id'] ?? 0);
        $tagId = (int)($input['tag_id'] ?? 0);
        
        if ($userId <= 0 || $tagId <= 0) {
            ApiResponse::error('缺少 user_id 或 tag_id');
            return;
        }
        
        $institution = $this->getInstitution();
        
        $db = Database::getInstance();
        $sql = "INSERT IGNORE INTO user_tags (user_id, tag_id, institution) VALUES (?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('iis', $userId, $tagId, $institution);
        $stmt->execute();
        $stmt->close();
        
        ApiResponse::success(null, '標籤已新增');
    }
    
    /**
     * 移除使用者的標籤
     * POST ?action=remove
     * Body: { user_id, tag_id }
     */
    public function remove(): void
    {
        $this->requireHospitalAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = (int)($input['user_id'] ?? 0);
        $tagId = (int)($input['tag_id'] ?? 0);
        
        if ($userId <= 0 || $tagId <= 0) {
            ApiResponse::error('缺少 user_id 或 tag_id');
            return;
        }
        
        $institution = $this->getInstitution();
        
        $db = Database::getInstance();
        $sql = "DELETE FROM user_tags WHERE user_id = ? AND tag_id = ? AND institution = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('iis', $userId, $tagId, $institution);
        $stmt->execute();
        $stmt->close();
        
        ApiResponse::success(null, '標籤已移除');
    }
    
    /**
     * 批量為使用者設定標籤
     * POST ?action=batch_set
     * Body: { user_ids: [1,2,3], tag_ids: [4,5] }
     */
    public function batchSet(): void
    {
        $this->requireHospitalAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $userIds = $input['user_ids'] ?? [];
        $tagIds = $input['tag_ids'] ?? [];
        
        if (empty($userIds)) {
            ApiResponse::error('缺少 user_ids');
            return;
        }
        
        $institution = $this->getInstitution();
        $db = Database::getInstance();
        
        $sql = "INSERT IGNORE INTO user_tags (user_id, tag_id, institution) VALUES (?, ?, ?)";
        $stmt = $db->prepare($sql);
        
        $count = 0;
        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            foreach ($tagIds as $tagId) {
                $tagId = (int)$tagId;
                if ($userId > 0 && $tagId > 0) {
                    $stmt->bind_param('iis', $userId, $tagId, $institution);
                    $stmt->execute();
                    $count++;
                }
            }
        }
        $stmt->close();
        
        ApiResponse::success(['count' => $count], '已為 ' . count($userIds) . ' 位使用者設定標籤');
    }
    
    /**
     * 依標籤取得使用者列表
     * GET ?action=users_by_tag&tag_id=123
     */
    public function usersByTag(): void
    {
        $this->requireHospitalAdmin();
        
        $tagId = $this->inputInt('tag_id');
        if ($tagId <= 0) {
            ApiResponse::error('缺少 tag_id');
            return;
        }
        
        $institution = $this->getInstitution();
        
        $db = Database::getInstance();
        $sql = "SELECT user_id FROM user_tags WHERE tag_id = ? AND institution = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('is', $tagId, $institution);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $userIds = [];
        while ($row = $result->fetch_assoc()) {
            $userIds[] = $row['user_id'];
        }
        $stmt->close();
        
        ApiResponse::success(['user_ids' => $userIds, 'count' => count($userIds)]);
    }
}
