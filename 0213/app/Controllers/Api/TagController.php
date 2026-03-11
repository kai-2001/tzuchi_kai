<?php
/**
 * 標籤控制器
 * app/Controllers/Api/TagController.php
 * 
 * 處理標籤 CRUD 與院區隔離
 */

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../Models/Tag.php';

class TagController extends Controller
{
    /**
     * 列出可見標籤（系統模板 + 院區專屬）
     * GET /tags
     */
    public function list(): void
    {
        $institutionCode = $_GET['institution'] ?? '';
        
        if (empty($institutionCode)) {
            ApiResponse::error('缺少 institution 參數');
            return;
        }
        
        $tags = Tag::getVisibleTags($institutionCode);
        
        // 分組回傳
        $templates = [];
        $custom = [];
        
        foreach ($tags as $tag) {
            if ($tag['is_template']) {
                $templates[] = $tag;
            } else {
                $custom[] = $tag;
            }
        }
        
        ApiResponse::success([
            'templates' => $templates,
            'custom' => $custom,
            'total' => count($tags)
        ]);
    }
    
    /**
     * 僅列出系統模板標籤
     * GET /tags/templates
     */
    public function templates(): void
    {
        $tags = Tag::getTemplateTags();
        ApiResponse::success($tags);
    }
    
    /**
     * 新增院區專屬標籤
     * POST /tags
     */
    public function create(): void
    {
        $input = $this->getJsonInput();
        
        $name = trim($input['name'] ?? '');
        $institutionCode = $input['institution'] ?? '';
        $color = $input['color'] ?? '#6b7280';
        $description = $input['description'] ?? null;
        $createdBy = $input['created_by'] ?? null;
        
        // 驗證
        if (empty($name)) {
            ApiResponse::error('標籤名稱不可為空');
            return;
        }
        
        if (empty($institutionCode)) {
            ApiResponse::error('缺少 institution 參數');
            return;
        }
        
        if (strlen($name) > 100) {
            ApiResponse::error('標籤名稱不可超過 100 字');
            return;
        }
        
        // 檢查重複
        if (Tag::nameExists($name, $institutionCode)) {
            ApiResponse::error("標籤「{$name}」已存在");
            return;
        }
        
        // 建立
        $data = [
            'name' => $name,
            'color' => $color
        ];
        
        if ($description) {
            $data['description'] = $description;
        }
        
        $id = Tag::createForInstitution($data, $institutionCode, $createdBy ?? 0);
        
        $newTag = Tag::find($id);
        
        ApiResponse::success([
            'message' => '標籤建立成功',
            'tag' => $newTag
        ]);
    }
    
    /**
     * 更新標籤
     * PUT /tags/update
     */
    public function update(): void
    {
        $input = $this->getJsonInput();
        
        $id = (int) ($input['id'] ?? 0);
        $institutionCode = $input['institution'] ?? '';
        
        if ($id <= 0) {
            ApiResponse::error('缺少標籤 ID');
            return;
        }
        
        // 檢查權限
        if (!Tag::canEdit($id, $institutionCode)) {
            ApiResponse::error('無權編輯此標籤（系統模板或非本院區）');
            return;
        }
        
        // 準備更新資料
        $updateData = [];
        
        if (isset($input['name'])) {
            $name = trim($input['name']);
            if (empty($name)) {
                ApiResponse::error('標籤名稱不可為空');
                return;
            }
            if (Tag::nameExists($name, $institutionCode, $id)) {
                ApiResponse::error("標籤「{$name}」已存在");
                return;
            }
            $updateData['name'] = $name;
        }
        
        if (isset($input['color'])) {
            $updateData['color'] = $input['color'];
        }
        
        if (isset($input['description'])) {
            $updateData['description'] = $input['description'];
        }
        
        if (isset($input['is_active'])) {
            $updateData['is_active'] = $input['is_active'] ? 1 : 0;
        }
        
        if (empty($updateData)) {
            ApiResponse::error('沒有要更新的資料');
            return;
        }
        
        Tag::update($id, $updateData);
        
        $updatedTag = Tag::find($id);
        
        ApiResponse::success([
            'message' => '標籤更新成功',
            'tag' => $updatedTag
        ]);
    }
    
    /**
     * 刪除標籤（軟刪除）
     * DELETE /tags/delete
     */
    public function delete(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $institutionCode = $_GET['institution'] ?? $_POST['institution'] ?? '';
        
        if ($id <= 0) {
            ApiResponse::error('缺少標籤 ID');
            return;
        }
        
        // 檢查權限
        if (!Tag::canEdit($id, $institutionCode)) {
            ApiResponse::error('無權刪除此標籤（系統模板或非本院區）');
            return;
        }
        
        // 軟刪除（設為 inactive）
        Tag::update($id, ['is_active' => 0]);
        
        ApiResponse::success([
            'message' => '標籤已刪除'
        ]);
    }
    
    /**
     * 批次查詢或建立標籤（供批次匯入使用）
     * POST /tags/find_or_create
     */
    public function findOrCreate(): void
    {
        $input = $this->getJsonInput();
        
        $names = $input['names'] ?? [];
        $institutionCode = $input['institution'] ?? '';
        $createdBy = $input['created_by'] ?? 0;
        
        if (empty($names) || !is_array($names)) {
            ApiResponse::error('names 必須是陣列');
            return;
        }
        
        if (empty($institutionCode)) {
            ApiResponse::error('缺少 institution 參數');
            return;
        }
        
        $tagIds = Tag::findOrCreateByNames($names, $institutionCode, $createdBy);
        
        ApiResponse::success([
            'tag_ids' => $tagIds,
            'count' => count($tagIds)
        ]);
    }
    
    /**
     * 建立系統模板標籤（僅限系統管理員）
     * POST /tags/create_template
     */
    public function createTemplate(): void
    {
        $input = $this->getJsonInput();
        
        $name = trim($input['name'] ?? '');
        $color = $input['color'] ?? '#6b7280';
        $description = $input['description'] ?? null;
        
        // 驗證
        if (empty($name)) {
            ApiResponse::error('標籤名稱不可為空');
            return;
        }
        
        if (strlen($name) > 100) {
            ApiResponse::error('標籤名稱不可超過 100 字');
            return;
        }
        
        // 檢查是否已存在同名模板
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM portal_tags WHERE name = ? AND is_template = 1");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            ApiResponse::error("模板標籤「{$name}」已存在");
            return;
        }
        $stmt->close();
        
        // 建立模板標籤
        $data = [
            'name' => $name,
            'color' => $color,
            'institution_code' => null,
            'is_template' => 1,
            'sort_order' => 0
        ];
        
        if ($description) {
            $data['description'] = $description;
        }
        
        $id = Tag::create($data);
        $newTag = Tag::find($id);
        
        ApiResponse::success([
            'message' => '模板標籤建立成功',
            'tag' => $newTag
        ]);
    }
    
    /**
     * 刪除模板標籤（僅限系統管理員）
     * POST /tags/delete_template
     */
    public function deleteTemplate(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        
        if ($id <= 0) {
            ApiResponse::error('缺少標籤 ID');
            return;
        }
        
        $tag = Tag::find($id);
        if (!$tag || !$tag['is_template']) {
            ApiResponse::error('找不到該模板標籤');
            return;
        }
        
        // 軟刪除
        Tag::update($id, ['is_active' => 0]);
        
        ApiResponse::success(['message' => '模板標籤已刪除']);
    }
}
