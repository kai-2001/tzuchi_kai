<?php
/**
 * User API 控制器
 * app/Controllers/Api/UserController.php
 * 
 * 處理使用者相關的 API 請求
 */

class UserController extends Controller
{
    private MoodleService $moodle;
    
    public function __construct()
    {
        parent::__construct();
        $this->moodle = new MoodleService();
    }
    
    /**
     * 列出機構內的使用者（從主群組）
     * GET ?route=users
     */
    public function list(): void
    {
        $this->requireHospitalAdmin();
        
        $institutionName = $this->getInstitutionName();
        
        try {
            // 從 institutions 表取得主群組 idnumber
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT cohort_idnumber FROM institutions WHERE name = ?");
            $stmt->bind_param('s', $institutionName);
            $stmt->execute();
            $result = $stmt->get_result();
            $institution = $result->fetch_assoc();
            $stmt->close();
            
            if (!$institution || empty($institution['cohort_idnumber'])) {
                ApiResponse::success(['users' => [], 'total' => 0, 'message' => '未設定主群組']);
                return;
            }
            
            $cohortIdnumber = $institution['cohort_idnumber'];
            
            // 從 Moodle 取得該群組的成員
            $moodleConn = $this->db->getMoodleConnection();
            
            // 找到群組 ID
            $stmt = $moodleConn->prepare("SELECT id FROM mdl_cohort WHERE idnumber = ?");
            $stmt->bind_param('s', $cohortIdnumber);
            $stmt->execute();
            $cohortResult = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$cohortResult) {
                ApiResponse::success(['users' => [], 'total' => 0, 'message' => '找不到主群組']);
                return;
            }
            
            $cohortId = $cohortResult['id'];
            
            // 直接查詢成員（簡化版，不分頁）
            $sql = "SELECT u.id, u.username, u.firstname, u.lastname, 
                           CONCAT(u.firstname, ' ', u.lastname) as fullname, u.email
                    FROM mdl_user u
                    JOIN mdl_cohort_members cm ON u.id = cm.userid
                    WHERE cm.cohortid = ? AND u.deleted = 0
                    ORDER BY u.lastname, u.firstname
                    LIMIT 100";
            
            $stmt = $moodleConn->prepare($sql);
            $stmt->bind_param('i', $cohortId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            $stmt->close();
            
            ApiResponse::success([
                'users' => $users,
                'total' => count($users)
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }
    
    /**
     * 取得單一使用者
     * GET ?route=users/show&id=123
     */
    public function show(): void
    {
        $this->requireHospitalAdmin();
        
        $userId = $this->inputInt('id');
        $username = $this->inputString('username');
        
        if ($userId <= 0 && empty($username)) {
            ApiResponse::error('缺少 id 或 username');
            return;
        }
        
        try {
            $conn = $this->db->getConnection();
            
            if ($userId > 0) {
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND institution = ?");
                $stmt->bind_param('is', $userId, $this->getInstitutionName());
            } else {
                $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND institution = ?");
                $stmt->bind_param('ss', $username, $this->getInstitutionName());
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if (!$user) {
                ApiResponse::notFound('找不到使用者');
                return;
            }
            
            // 取得 Moodle 使用者資訊
            $moodleUser = null;
            if (!empty($user['moodle_user_id'])) {
                $moodleUser = $this->moodle->getUserById($user['moodle_user_id']);
            }
            
            ApiResponse::success([
                'user' => $user,
                'moodle_user' => $moodleUser
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }
    
    /**
     * 搜尋 Moodle 使用者（供選擇用）
     * GET ?route=users/search_moodle&query=xxx
     */
    public function searchMoodle(): void
    {
        $this->requireHospitalAdmin();
        
        $query = $this->inputString('query');
        if (strlen($query) < 2) {
            ApiResponse::error('搜尋字詞至少需 2 個字元');
            return;
        }
        
        try {
            // 使用 Moodle API 搜尋
            $result = $this->moodle->call('core_user_get_users', [
                'criteria' => [
                    ['key' => 'firstname', 'value' => '%' . $query . '%'],
                ]
            ]);
            
            if (isset($result['exception'])) {
                // 嘗試用 username 搜尋
                $result = $this->moodle->call('core_user_get_users', [
                    'criteria' => [
                        ['key' => 'username', 'value' => '%' . $query . '%'],
                    ]
                ]);
            }
            
            $users = $result['users'] ?? [];
            
            // 格式化輸出
            $formatted = array_map(fn($u) => [
                'id' => $u['id'],
                'username' => $u['username'],
                'fullname' => $u['fullname'] ?? ($u['firstname'] . ' ' . $u['lastname']),
                'email' => $u['email'] ?? ''
            ], array_slice($users, 0, 20));
            
            ApiResponse::success([
                'users' => $formatted,
                'count' => count($formatted)
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }
    
    /**
     * 取得使用者的課程
     * GET ?route=users/courses&user_id=123
     */
    public function courses(): void
    {
        $this->requireHospitalAdmin();
        
        $moodleUserId = $this->inputInt('moodle_user_id');
        if ($moodleUserId <= 0) {
            ApiResponse::error('缺少 moodle_user_id');
            return;
        }
        
        try {
            $courses = $this->moodle->getUserCourses($moodleUserId);
            
            if (MoodleService::hasError($courses)) {
                ApiResponse::error('無法取得課程');
                return;
            }
            
            ApiResponse::success([
                'courses' => $courses,
                'count' => count($courses)
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }
    
    /**
     * 取得使用者所屬的群組
     * GET ?route=users/cohorts&moodle_user_id=123
     */
    public function cohorts(): void
    {
        $this->requireHospitalAdmin();
        
        $moodleUserId = $this->inputInt('moodle_user_id');
        if ($moodleUserId <= 0) {
            ApiResponse::error('缺少 moodle_user_id');
            return;
        }
        
        try {
            // 從 Moodle DB 查詢使用者的群組
            $moodleConn = $this->db->getMoodleConnection();
            $stmt = $moodleConn->prepare("
                SELECT c.id, c.name, c.idnumber, c.description
                FROM mdl_cohort c
                JOIN mdl_cohort_members cm ON c.id = cm.cohortid
                WHERE cm.userid = ?
            ");
            $stmt->bind_param('i', $moodleUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $cohorts = [];
            while ($row = $result->fetch_assoc()) {
                $cohorts[] = $row;
            }
            $stmt->close();
            
            ApiResponse::success([
                'cohorts' => $cohorts,
                'count' => count($cohorts)
            ]);
        } catch (Exception $e) {
            ApiResponse::serverError($e->getMessage());
        }
    }
}
