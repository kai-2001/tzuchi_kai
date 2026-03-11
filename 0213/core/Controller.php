<?php
/**
 * 基礎控制器
 * core/Controller.php
 */

abstract class Controller
{
    protected Database $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * 取得 JSON 請求體
     */
    protected function getJsonInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            return json_decode($input, true) ?? [];
        }
        return [];
    }
    
    /**
     * 取得請求參數（支援 GET、POST、JSON）
     */
    protected function input(string $key, $default = null)
    {
        // 優先順序: JSON > POST > GET
        $json = $this->getJsonInput();
        if (isset($json[$key])) {
            return $json[$key];
        }
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }
        return $default;
    }
    
    /**
     * 取得整數參數
     */
    protected function inputInt(string $key, int $default = 0): int
    {
        return (int) $this->input($key, $default);
    }
    
    /**
     * 取得字串參數（已 trim）
     */
    protected function inputString(string $key, string $default = ''): string
    {
        return trim((string) $this->input($key, $default));
    }
    
    /**
     * 取得布林參數
     */
    protected function inputBool(string $key, bool $default = false): bool
    {
        $value = $this->input($key, $default);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * 取得陣列參數
     */
    protected function inputArray(string $key, array $default = []): array
    {
        $value = $this->input($key, $default);
        if (is_array($value)) {
            return $value;
        }
        return $default;
    }
    
    /**
     * 檢查是否為院區管理員
     */
    protected function isHospitalAdmin(): bool
    {
        return !empty($_SESSION['is_hospital_admin']);
    }
    
    /**
     * 檢查是否為系統管理員
     */
    protected function isAdmin(): bool
    {
        return !empty($_SESSION['is_admin']);
    }
    
    /**
     * 要求院區管理員權限（否則回傳錯誤）
     */
    protected function requireHospitalAdmin(): void
    {
        if (!$this->isHospitalAdmin() && !$this->isAdmin() && !$this->isCourseCreator()) {
            ApiResponse::forbidden('需要院區管理員或開課教師權限');
        }
    }

    /**
     * 檢查是否為開課教師
     */
    protected function isCourseCreator(): bool
    {
        return !empty($_SESSION['is_coursecreator']);
    }

    /**
     * 取得開課教師的類別 IDs
     */
    protected function getCourseCreatorCategoryIds(): array
    {
        return $_SESSION['coursecreator_category_ids'] ?? [];
    }
    
    /**
     * 要求系統管理員權限
     */
    protected function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            ApiResponse::forbidden('需要系統管理員權限');
        }
    }
    
    /**
     * 取得當前使用者的機構 ID
     */
    protected function getInstitutionId(): int
    {
        return (int) ($_SESSION['institution_id'] ?? 0);
    }
    
    /**
     * 取得當前使用者的機構名稱
     */
    protected function getInstitutionName(): string
    {
        return $_SESSION['institution'] ?? '';
    }
    
    /**
     * 取得管理類別 ID
     */
    protected function getManagementCategoryId(): int
    {
        return (int) ($_SESSION['management_category_id'] ?? 0);
    }
}
