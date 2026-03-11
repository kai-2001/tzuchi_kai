<?php
/**
 * API 回應格式化
 * core/ApiResponse.php
 */

class ApiResponse
{
    /**
     * 成功回應
     */
    public static function success($data = null, string $message = ''): void
    {
        self::send([
            'success' => true,
            'data' => $data,
            'message' => $message
        ]);
    }

    /**
     * 錯誤回應
     */
    public static function error(string $message, int $code = 400, $details = null): void
    {
        http_response_code($code);
        self::send([
            'success' => false,
            'error' => $message,
            'code' => $code,
            'details' => $details
        ]);
    }

    /**
     * 未授權
     */
    public static function unauthorized(string $message = '未授權存取'): void
    {
        self::error($message, 401);
    }

    /**
     * 禁止存取
     */
    public static function forbidden(string $message = '無權限'): void
    {
        self::error($message, 403);
    }

    /**
     * 找不到資源
     */
    public static function notFound(string $message = '找不到資源'): void
    {
        self::error($message, 404);
    }

    /**
     * 伺服器錯誤
     */
    public static function serverError(string $message = '伺服器錯誤'): void
    {
        self::error($message, 500);
    }

    /**
     * 發送 JSON 回應
     */
    private static function send(array $data): void
    {
        // 清掉任何意外的 PHP 輸出（如警告、通知）
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
