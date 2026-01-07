<?php
require_once 'includes/config.php';
session_unset();
session_destroy();

// Clear Remember Me Cookie
if (isset($_COOKIE['remember_me'])) {
    $parts = explode(':', $_COOKIE['remember_me']);
    if (count($parts) === 2) {
        $selector = $parts[0];
        $stmt = $conn->prepare("DELETE FROM user_tokens WHERE selector = ?");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
    }
    setcookie('remember_me', '', time() - 3600, '/', '', false, true);
}

// Redirect back to homepage
header("Location: index.php");
exit;
?>