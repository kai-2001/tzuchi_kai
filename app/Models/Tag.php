<?php
/**
 * 標籤 Model
 * app/Models/Tag.php
 * 
 * 處理院區隔離的標籤管理
 */

require_once __DIR__ . '/../../core/Model.php';

class Tag extends Model
{
    protected static string $table = 'portal_tags';
    
    /**
     * 取得院區可見標籤（系統模板 + 院區專屬）
     */
    public static function getVisibleTags(string $institutionCode): array
    {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM portal_tags 
                WHERE is_active = 1 
                AND (institution_code IS NULL OR institution_code = ?)
                ORDER BY is_template DESC, sort_order ASC, name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('s', $institutionCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $tags = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $tags;
    }
    
    /**
     * 僅取得系統模板標籤
     */
    public static function getTemplateTags(): array
    {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM portal_tags 
                WHERE is_template = 1 AND is_active = 1
                ORDER BY sort_order ASC, name ASC";
        
        $result = $db->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
    /**
     * 取得院區專屬標籤
     */
    public static function getInstitutionTags(string $institutionCode): array
    {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM portal_tags 
                WHERE institution_code = ? AND is_active = 1
                ORDER BY sort_order ASC, name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('s', $institutionCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $tags = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $tags;
    }
    
    /**
     * 建立院區專屬標籤
     */
    public static function createForInstitution(array $data, string $institutionCode, int $createdBy): int
    {
        $data['institution_code'] = $institutionCode;
        $data['is_template'] = 0;
        $data['created_by'] = $createdBy;
        
        return self::create($data);
    }
    
    /**
     * 檢查標籤名稱是否已存在（同院區或全域）
     */
    public static function nameExists(string $name, string $institutionCode, ?int $excludeId = null): bool
    {
        $db = Database::getInstance();
        
        $sql = "SELECT id FROM portal_tags 
                WHERE name = ? 
                AND (institution_code IS NULL OR institution_code = ?)";
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ssi', $name, $institutionCode, $excludeId);
        } else {
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ss', $name, $institutionCode);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        
        return $exists;
    }
    
    /**
     * 根據名稱查詢標籤（限院區可見範圍）
     */
    public static function findByName(string $name, string $institutionCode): ?array
    {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM portal_tags 
                WHERE name = ? 
                AND (institution_code IS NULL OR institution_code = ?)
                AND is_active = 1
                LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $name, $institutionCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $tag = $result->fetch_assoc();
        $stmt->close();
        
        return $tag ?: null;
    }
    
    /**
     * 批次根據名稱查詢或建立標籤
     * 用於批次匯入時處理標籤
     */
    public static function findOrCreateByNames(array $names, string $institutionCode, int $createdBy): array
    {
        $tagIds = [];
        
        foreach ($names as $name) {
            $name = trim($name);
            if (empty($name)) continue;
            
            $tag = self::findByName($name, $institutionCode);
            
            if ($tag) {
                $tagIds[] = $tag['id'];
            } else {
                // 自動建立新標籤（院區專屬）
                $newId = self::createForInstitution([
                    'name' => $name,
                    'color' => '#6b7280'
                ], $institutionCode, $createdBy);
                $tagIds[] = $newId;
            }
        }
        
        return $tagIds;
    }
    
    /**
     * 檢查是否可編輯（只能編輯自己院區的標籤）
     */
    public static function canEdit(int $tagId, string $institutionCode): bool
    {
        $tag = self::find($tagId);
        
        if (!$tag) return false;
        
        // 系統模板不可編輯
        if ($tag['is_template']) return false;
        
        // 只能編輯自己院區的
        return $tag['institution_code'] === $institutionCode;
    }
}
