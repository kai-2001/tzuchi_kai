<?php
/**
 * Logger Class - Unified Logging System
 * 
 * Provides consistent logging format across the application
 */

class Logger
{
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const INFO = 'INFO';
    const DEBUG = 'DEBUG';
    const CRITICAL = 'CRITICAL';

    /**
     * Log a message with specified level
     * 
     * @param string $level Log level (ERROR, WARNING, INFO, DEBUG, CRITICAL)
     * @param string $module Module/component name
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function log($level, $module, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        $logMessage = "[{$timestamp}] [{$level}] [{$module}] {$message}";
        if ($contextStr) {
            $logMessage .= " | Context: {$contextStr}";
        }

        error_log($logMessage);

        // For critical errors, could send email notification
        if ($level === self::CRITICAL && defined('ADMIN_EMAIL') && ADMIN_EMAIL) {
            self::sendCriticalNotification($module, $message, $context);
        }
    }

    /**
     * Log an error message
     */
    public static function error($module, $message, $context = [])
    {
        self::log(self::ERROR, $module, $message, $context);
    }

    /**
     * Log a warning message
     */
    public static function warning($module, $message, $context = [])
    {
        self::log(self::WARNING, $module, $message, $context);
    }

    /**
     * Log an info message
     */
    public static function info($module, $message, $context = [])
    {
        self::log(self::INFO, $module, $message, $context);
    }

    /**
     * Log a debug message (only in development)
     */
    public static function debug($module, $message, $context = [])
    {
        if (defined('APP_ENV') && APP_ENV === 'development') {
            self::log(self::DEBUG, $module, $message, $context);
        }
    }

    /**
     * Log a critical error
     */
    public static function critical($module, $message, $context = [])
    {
        self::log(self::CRITICAL, $module, $message, $context);
    }

    /**
     * Send critical error notification to admin
     * 
     * @param string $module
     * @param string $message
     * @param array $context
     */
    private static function sendCriticalNotification($module, $message, $context)
    {
        // Only send email if ADMIN_EMAIL is defined and not empty
        if (!defined('ADMIN_EMAIL') || !ADMIN_EMAIL) {
            return;
        }

        $subject = "[CRITICAL] Speech System Error - {$module}";
        $body = "Critical Error Detected\n\n";
        $body .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Module: {$module}\n";
        $body .= "Message: {$message}\n";
        $body .= "Context: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        $body .= "\nPlease check the system logs for more details.";

        // Use mail() or a more sophisticated mail library
        @mail(ADMIN_EMAIL, $subject, $body);
    }

    /**
     * Log unauthorized access attempt
     * 
     * @param string $module
     * @param array $context
     */
    public static function logUnauthorizedAccess($module, $context = [])
    {
        $defaultContext = [
            'user_id' => $_SESSION['user_id'] ?? 'guest',
            'username' => $_SESSION['username'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];

        $mergedContext = array_merge($defaultContext, $context);
        self::warning($module, 'Unauthorized access attempt', $mergedContext);
    }
}
