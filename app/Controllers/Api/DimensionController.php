<?php
/**
 * 維度管理 API Controller
 * app/Controllers/Api/DimensionController.php
 * 
 * 合併原 api/hospital_admin/manage_dimensions.php 和 api/admin/manage_dimensions.php
 */

class DimensionController extends Controller
{
    /**
     * 解析 institution_id（支援從參數或 session 取得）
     */
    private function resolveInstitutionId(): int
    {
        // 優先用參數傳入的 institution_id
        $institutionId = $this->inputInt('institution_id');
        if ($institutionId > 0) {
            return $institutionId;
        }
        
        // 從 session 的院區名稱查 ID
        $instName = $this->getInstitutionName();
        if (!empty($instName)) {
            $stmt = $this->db->prepare("SELECT id FROM institutions WHERE name = ?");
            $stmt->bind_param('s', $instName);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return (int)($row['id'] ?? 0);
        }
        
        return 0;
    }
    
    // ═══════════════════════════════════════════════════
    // 維度類型 CRUD
    // ═══════════════════════════════════════════════════
    
    /**
     * 列出該院區的所有維度類型
     * GET ?route=dimensions/list_types[&institution_id=N]
     */
    public function listTypes(): void
    {
        $this->requireHospitalAdmin();
        $institutionId = $this->resolveInstitutionId();
        
        $stmt = $this->db->prepare(
            "SELECT * FROM dimension_types WHERE institution_id = ? ORDER BY sort_order, id"
        );
        $stmt->bind_param('i', $institutionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $types = [];
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
        $stmt->close();
        
        ApiResponse::success($types);
    }
    
    /**
     * 新增維度類型
     * POST ?route=dimensions/create_type
     * Body: { name, institution_id? }
     */
    public function createType(): void
    {
        $this->requireHospitalAdmin();
        $institutionId = $this->resolveInstitutionId();
        
        $name = $this->inputString('name');
        if (empty($name)) {
            ApiResponse::error('維度名稱不能為空');
            return;
        }
        
        $stmt = $this->db->prepare(
            "INSERT INTO dimension_types (institution_id, name) VALUES (?, ?)"
        );
        $stmt->bind_param('is', $institutionId, $name);
        
        if ($stmt->execute()) {
            ApiResponse::success(['id' => $stmt->insert_id], '維度已建立');
        } else {
            ApiResponse::error('建立失敗: ' . $stmt->error);
        }
        $stmt->close();
    }
    
    /**
     * 刪除維度類型（會連帶刪除其下的群組對照）
     * POST ?route=dimensions/delete_type
     * Body: { id, institution_id? }
     */
    public function deleteType(): void
    {
        $this->requireHospitalAdmin();
        $institutionId = $this->resolveInstitutionId();
        
        $typeId = $this->inputInt('id');
        if ($typeId <= 0) {
            ApiResponse::error('無效的 ID');
            return;
        }
        
        // 院區管理員不能刪除受保護的維度
        if (!$this->isAdmin()) {
            $check = $this->db->prepare("SELECT is_protected FROM dimension_types WHERE id = ?");
            $check->bind_param('i', $typeId);
            $check->execute();
            $row = $check->get_result()->fetch_assoc();
            $check->close();
            
            if ($row && $row['is_protected']) {
                ApiResponse::error('此維度為系統維度，無法刪除');
                return;
            }
        }
        
        $stmt = $this->db->prepare(
            "DELETE FROM dimension_types WHERE id = ? AND institution_id = ?"
        );
        $stmt->bind_param('ii', $typeId, $institutionId);
        
        if ($stmt->execute()) {
            ApiResponse::success(null, '維度已刪除');
        } else {
            ApiResponse::error('刪除失敗');
        }
        $stmt->close();
    }
    
    // ═══════════════════════════════════════════════════
    // 群組-維度對照 CRUD
    // ═══════════════════════════════════════════════════
    
    /**
     * 列出某維度下的群組
     * GET ?route=dimensions/list_cohorts&type_id=N[&institution_id=N]
     */
    public function listCohorts(): void
    {
        $this->requireHospitalAdmin();
        $institutionId = $this->resolveInstitutionId();
        
        $typeId = $this->inputInt('type_id');
        
        $stmt = $this->db->prepare(
            "SELECT cd.*, dt.name as dimension_name 
             FROM cohort_dimensions cd 
             JOIN dimension_types dt ON cd.dimension_type_id = dt.id 
             WHERE dt.id = ? AND dt.institution_id = ?
             ORDER BY cd.sort_order, cd.id"
        );
        $stmt->bind_param('ii', $typeId, $institutionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $cohorts = [];
        while ($row = $result->fetch_assoc()) {
            $cohorts[] = $row;
        }
        $stmt->close();
        
        ApiResponse::success($cohorts);
    }
    
    /**
     * 將群組加入某維度
     * POST ?route=dimensions/add_cohort
     * Body: { type_id, cohort_id, display_name, institution_id? }
     */
    public function addCohort(): void
    {
        $this->requireHospitalAdmin();
        
        $typeId = $this->inputInt('type_id');
        $cohortId = $this->inputInt('cohort_id');
        $displayName = $this->inputString('display_name');
        
        if ($typeId <= 0 || $cohortId <= 0) {
            ApiResponse::error('參數不完整');
            return;
        }
        
        $stmt = $this->db->prepare(
            "INSERT INTO cohort_dimensions (dimension_type_id, moodle_cohort_id, display_name) 
             VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE display_name = ?"
        );
        $stmt->bind_param('iiss', $typeId, $cohortId, $displayName, $displayName);
        
        if ($stmt->execute()) {
            ApiResponse::success(null, '群組已加入維度');
        } else {
            ApiResponse::error('加入失敗: ' . $stmt->error);
        }
        $stmt->close();
    }
    
    /**
     * 從維度移除群組
     * POST ?route=dimensions/remove_cohort
     * Body: { id }
     */
    public function removeCohort(): void
    {
        $this->requireHospitalAdmin();
        
        $cdId = $this->inputInt('id');
        if ($cdId <= 0) {
            ApiResponse::error('無效的 ID');
            return;
        }
        
        $stmt = $this->db->prepare("DELETE FROM cohort_dimensions WHERE id = ?");
        $stmt->bind_param('i', $cdId);
        
        if ($stmt->execute()) {
            ApiResponse::success(null, '群組已從維度移除');
        } else {
            ApiResponse::error('移除失敗');
        }
        $stmt->close();
    }
    
    // ═══════════════════════════════════════════════════
    // 招生用：取得維度化群組清單
    // ═══════════════════════════════════════════════════
    
    /**
     * 取得該院區所有維度及其群組（含完整路徑計算）
     * GET ?route=dimensions/get_grouped[&institution_id=N]
     * 
     * 對應舊版 manage_dimensions.php?action=get_grouped
     */
    public function getGrouped(): void
    {
        $this->requireHospitalAdmin();
        $institutionId = $this->resolveInstitutionId();
        
        $sql = "
            SELECT dt.id as dimension_id, dt.name as dimension_name,
                   cd.id as cd_id, cd.moodle_cohort_id, cd.display_name, cd.parent_cohort_id
            FROM dimension_types dt
            LEFT JOIN cohort_dimensions cd ON dt.id = cd.dimension_type_id
            WHERE dt.institution_id = ?
            ORDER BY dt.sort_order, dt.id, cd.sort_order, cd.id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $institutionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // 收集所有 cohort 以便計算完整路徑
        $allCohorts = [];
        $rawRows = [];
        while ($row = $result->fetch_assoc()) {
            $rawRows[] = $row;
            if ($row['moodle_cohort_id']) {
                $allCohorts[$row['moodle_cohort_id']] = [
                    'id' => $row['moodle_cohort_id'],
                    'name' => $row['display_name'],
                    'parent_cohort_id' => $row['parent_cohort_id']
                ];
            }
        }
        $stmt->close();
        
        // 組成巢狀結構
        $dimensions = [];
        foreach ($rawRows as $row) {
            $dimId = $row['dimension_id'];
            if (!isset($dimensions[$dimId])) {
                $dimensions[$dimId] = [
                    'id' => $dimId,
                    'name' => $row['dimension_name'],
                    'cohorts' => []
                ];
            }
            if ($row['moodle_cohort_id']) {
                $visited = [];
                $pathParts = $this->buildFullPath($row['moodle_cohort_id'], $allCohorts, $visited);
                $fullPath = implode(' / ', $pathParts);
                
                $dimensions[$dimId]['cohorts'][] = [
                    'cd_id' => $row['cd_id'],
                    'cohort_id' => $row['moodle_cohort_id'],
                    'display_name' => $row['display_name'],
                    'full_path' => $fullPath ?: $row['display_name'],
                    'depth' => count($pathParts),
                    'parent_cohort_id' => $row['parent_cohort_id']
                ];
            }
        }
        
        // 按完整路徑排序
        foreach ($dimensions as &$dim) {
            usort($dim['cohorts'], function($a, $b) {
                return strcmp($a['full_path'], $b['full_path']);
            });
        }
        
        ApiResponse::success(array_values($dimensions));
    }
    
    /**
     * 計算群組的完整路徑
     */
    private function buildFullPath(int $cohortId, array &$cohorts, array &$visited): array
    {
        if (!$cohortId || !isset($cohorts[$cohortId]) || isset($visited[$cohortId])) {
            return [];
        }
        $visited[$cohortId] = true;
        $cohort = $cohorts[$cohortId];
        $parentPath = $this->buildFullPath((int)($cohort['parent_cohort_id'] ?? 0), $cohorts, $visited);
        $parentPath[] = $cohort['name'];
        return $parentPath;
    }
}
