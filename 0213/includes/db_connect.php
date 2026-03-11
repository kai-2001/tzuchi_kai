<?php
/**
 * includes/db_connect.php
 * Centralized Database Connection
 * 
 * Depends on: includes/config.php (for $db_host, $db_user, etc.)
 */

require_once __DIR__ . '/config.php';

// Check if connection already exists to avoid duplication
if (!isset($conn)) {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        // Log error but don't show details to user for security
        error_log("DB Connection Failed: " . $conn->connect_error);
        die("系統暫時無法連線，請稍後再試。");
    }

    $conn->set_charset("utf8mb4");
}
?>