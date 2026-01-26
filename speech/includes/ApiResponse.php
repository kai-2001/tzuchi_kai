<?php
/**
 * Speech Portal - Unified API Response
 * 
 * Standardized JSON response format for AJAX requests.
 */

class ApiResponse
{
    /**
     * Success response
     */
    public static function success($data = null, string $message = '', string $redirect = ''): array
    {
        $response = ['status' => 'ok'];
        if ($message)
            $response['msg'] = $message;
        if ($data !== null)
            $response['data'] = $data;
        if ($redirect)
            $response['redirect'] = $redirect;
        return $response;
    }

    /**
     * Error response
     */
    public static function error(string $message, string $code = 'error'): array
    {
        return [
            'status' => $code,
            'msg' => $message
        ];
    }

    /**
     * Login required response
     */
    public static function loginRequired(string $redirectUrl = 'login.php'): array
    {
        return [
            'status' => 'login_required',
            'msg' => '請先登入',
            'redirect' => $redirectUrl
        ];
    }

    /**
     * Validation error response
     */
    public static function validationError(array $errors): array
    {
        return [
            'status' => 'validation_error',
            'msg' => reset($errors) ?: '驗證失敗',
            'errors' => $errors
        ];
    }

    /**
     * Send JSON response and exit
     */
    public static function send(array $response): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
