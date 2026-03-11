<?php
/**
 * 統計 API 控制器
 * app/Controllers/Api/StatsController.php
 */

class StatsController extends Controller
{
    /**
     * 取得儀表板統計數據
     * GET /api/stats
     */
    public function index(): void
    {
        $this->requireHospitalAdmin();
        
        $institutionName = $this->getInstitutionName();
        $mgmtCatId = $this->getManagementCategoryId();
        
        try {
            $conn = $this->db->getConnection();
            
            // 取得使用者數量
            $userCount = 0;
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE institution = ?");
            $stmt->bind_param('s', $institutionName);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $userCount = (int) $row['cnt'];
            }
            $stmt->close();
            
            // 取得群組數量（使用 MoodleService）
            $moodle = new MoodleService();
            $cohorts = $moodle->searchCohorts($mgmtCatId);
            $cohortCount = count($cohorts);
            
            ApiResponse::success([
                'users' => $userCount,
                'cohorts' => $cohortCount,
                'institution' => $institutionName,
                'category_id' => $mgmtCatId
            ]);
            
        } catch (Exception $e) {
            ApiResponse::serverError('取得統計資料失敗: ' . $e->getMessage());
        }
    }
    
    /**
     * 健康檢查
     * GET /api/stats/health
     */
    public function health(): void
    {
        $checks = [
            'database' => false,
            'session' => session_status() === PHP_SESSION_ACTIVE,
            'time' => date('Y-m-d H:i:s')
        ];
        
        // 測試資料庫連線
        try {
            $conn = $this->db->getConnection();
            $checks['database'] = $conn->ping();
        } catch (Exception $e) {
            $checks['database_error'] = $e->getMessage();
        }
        
        $allOk = $checks['database'] && $checks['session'];
        
        if ($allOk) {
            ApiResponse::success($checks, 'OK');
        } else {
            ApiResponse::error('Health check failed', 500, $checks);
        }
    }
    
    /**
     * 取得儀表板統計數據（學生/教師數量）
     * GET /api/v2/index.php?route=stats/dashboard
     * 
     * 對應舊版 get_stats.php 的邏輯
     */
    public function dashboard(): void
    {
        $this->requireHospitalAdmin();
        
        $institution = $this->getInstitutionName();
        $isAdmin = $this->isAdmin();
        $isHospitalAdmin = $this->isHospitalAdmin();
        $showAll = ($isAdmin && !$isHospitalAdmin && empty($institution));
        
        try {
            $conn = $this->db->getConnection();
            
            if ($showAll) {
                // 系統管理員看所有成員
                $students = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'student'")->fetch_assoc()['cnt'];
                $teachers = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'coursecreator'")->fetch_assoc()['cnt'];
            } else {
                // 院區管理員只看自己院區
                $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE institution = ? AND role = 'student'");
                $stmt->bind_param("s", $institution);
                $stmt->execute();
                $students = $stmt->get_result()->fetch_assoc()['cnt'];
                $stmt->close();
                
                $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE institution = ? AND role = 'coursecreator'");
                $stmt->bind_param("s", $institution);
                $stmt->execute();
                $teachers = $stmt->get_result()->fetch_assoc()['cnt'];
                $stmt->close();
            }
            
            ApiResponse::success([
                'total' => (int)$students + (int)$teachers,
                'students' => (int)$students,
                'teachers' => (int)$teachers
            ]);
            
        } catch (Exception $e) {
            error_log("StatsController::dashboard error: " . $e->getMessage());
            ApiResponse::serverError('系統錯誤');
        }
    }
}
