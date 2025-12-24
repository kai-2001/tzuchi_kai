<?php
/**
 * Authentication helper for SOAP
 */
require_once __DIR__ . '/config.php';

/**
 * Unified login function
 */
function user_login($username, $password)
{
    if (defined('AUTH_MODE') && AUTH_MODE === 'local') {
        return local_login($username, $password);
    } else {
        return soap_login($username, $password);
    }
}

/**
 * Local database login (Test environment fallback)
 */
function local_login($username, $password)
{
    global $conn;
    $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && !empty($user['password']) && password_verify($password, $user['password'])) {
        return true;
    }
    return false;
}

function soap_login($username, $password)
{
    try {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $client = new SoapClient(null, [
            'location' => SOAP_LOCATION,
            'uri' => SOAP_URI,
            'trace' => 1,
            'exceptions' => true,
            'stream_context' => $context
        ]);

        // Supervisor's snippet: $client->login($acc, md5($pwd))
        $result = $client->login($username, md5($password));

        // If it's a '1' or an array/object, it's a success
        if ($result == '1' || is_array($result) || is_object($result)) {
            return $result;
        }
        return false;
    } catch (Exception $e) {
        error_log("SOAP Login Error: " . $e->getMessage());
        return 'error'; // Indicate service unavailable
    }
}

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function is_manager()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
}
?>