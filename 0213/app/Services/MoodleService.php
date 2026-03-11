<?php
/**
 * Moodle API 服務封裝
 * app/Services/MoodleService.php
 */

class MoodleService
{
    private string $url;
    private string $token;
    private int $timeout = 30;
    private int $connectTimeout = 10;
    
    public function __construct(?string $url = null, ?string $token = null)
    {
        $this->url = $url ?? $GLOBALS['moodle_url'] ?? '';
        $this->token = $token ?? $GLOBALS['moodle_token'] ?? '';
    }
    
    /**
     * 呼叫 Moodle Web Service API
     */
    public function call(string $function, array $params = []): array
    {
        $serverUrl = $this->url . '/webservice/rest/server.php' 
            . '?wstoken=' . $this->token 
            . '&wsfunction=' . $function 
            . '&moodlewsrestformat=json';
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $serverUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($curl);
        
        if (curl_errno($curl)) {
            $errorCode = curl_errno($curl);
            $errorMsg = curl_error($curl);
            curl_close($curl);
            
            if ($errorCode === 28) {
                return ['error' => 'TIMEOUT', 'message' => '連線逾時'];
            }
            return ['error' => 'CURL_ERROR', 'message' => $errorMsg];
        }
        
        curl_close($curl);
        return json_decode($response, true) ?? ['error' => 'INVALID_JSON'];
    }
    
    // ==================
    // 使用者相關
    // ==================
    
    /**
     * 根據欄位取得使用者
     */
    public function getUsersByField(string $field, array $values): array
    {
        return $this->call('core_user_get_users_by_field', [
            'field' => $field,
            'values' => $values
        ]);
    }
    
    /**
     * 根據使用者名稱取得使用者
     */
    public function getUserByUsername(string $username): ?array
    {
        $result = $this->getUsersByField('username', [$username]);
        return $result[0] ?? null;
    }
    
    /**
     * 根據使用者 ID 取得使用者
     */
    public function getUserById(int $id): ?array
    {
        $result = $this->getUsersByField('id', [$id]);
        return $result[0] ?? null;
    }
    
    // ==================
    // 群組 (Cohort) 相關
    // ==================
    
    /**
     * 搜尋群組
     */
    public function searchCohorts(int $categoryId, string $query = ''): array
    {
        $result = $this->call('core_cohort_search_cohorts', [
            'query' => $query,
            'context' => [
                'contextid' => 0,
                'contextlevel' => 'coursecat',
                'instanceid' => $categoryId
            ],
            'includes' => 'all'
        ]);
        
        if (isset($result['exception'])) {
            return ['error' => $result['message'] ?? 'Unknown error'];
        }
        
        return $result['cohorts'] ?? [];
    }
    
    /**
     * 取得群組成員
     */
    public function getCohortMembers(int $cohortId): array
    {
        $result = $this->call('core_cohort_get_cohort_members', [
            'cohortids' => [$cohortId]
        ]);
        
        if (isset($result[0]['userids'])) {
            return $result[0]['userids'];
        }
        
        return [];
    }
    
    /**
     * 新增群組成員
     */
    public function addCohortMember(int $cohortId, int $userId): bool
    {
        $result = $this->call('core_cohort_add_cohort_members', [
            'members' => [[
                'cohorttype' => ['type' => 'id', 'value' => $cohortId],
                'usertype' => ['type' => 'id', 'value' => $userId]
            ]]
        ]);
        
        // API 成功時回傳 null 或空 (void)
        return !isset($result['exception']) && !isset($result['error']);
    }
    
    /**
     * 移除群組成員
     */
    public function removeCohortMember(int $cohortId, int $userId): bool
    {
        $result = $this->call('core_cohort_delete_cohort_members', [
            'members' => [[
                'cohortid' => $cohortId,
                'userid' => $userId
            ]]
        ]);
        
        return !isset($result['exception']) && !isset($result['error']);
    }
    
    // ==================
    // 類別相關
    // ==================
    
    /**
     * 取得所有類別
     */
    public function getCategories(): array
    {
        $result = $this->call('core_course_get_categories', []);
        return is_array($result) && !isset($result['exception']) ? $result : [];
    }
    
    /**
     * 取得子類別
     */
    public function getChildCategories(int $parentId): array
    {
        $all = $this->getCategories();
        return array_filter($all, fn($cat) => ($cat['parent'] ?? 0) == $parentId);
    }
    
    // ==================
    // 課程相關
    // ==================
    
    /**
     * 取得使用者的課程
     */
    public function getUserCourses(int $userId): array
    {
        return $this->call('core_enrol_get_users_courses', [
            'userid' => $userId
        ]);
    }
    
    /**
     * 取得網站資訊（用於健康檢查）
     */
    public function getSiteInfo(): array
    {
        return $this->call('core_webservice_get_site_info', []);
    }
    
    // ==================
    // 工具方法
    // ==================
    
    /**
     * 設定逾時
     */
    public function setTimeout(int $timeout, int $connectTimeout = 10): self
    {
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        return $this;
    }
    
    /**
     * 檢查回應是否有錯誤
     */
    public static function hasError(array $result): bool
    {
        return isset($result['error']) || isset($result['exception']);
    }
    
    /**
     * 取得錯誤訊息
     */
    public static function getErrorMessage(array $result): string
    {
        return $result['message'] ?? $result['error'] ?? 'Unknown error';
    }
}
