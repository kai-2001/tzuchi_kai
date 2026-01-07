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
    // If not logged in, try to restore from cookie
    if (!isset($_SESSION['user_id'])) {
        check_remember_me();
    }
    return isset($_SESSION['user_id']);
}

function is_manager()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
}

/**
 * Enable "Remember Me" for a user
 */
function remember_me($user_id)
{
    global $conn;

    // 1. Generate Selector and Validator
    $selector = bin2hex(random_bytes(6)); // 12 chars
    $validator = random_bytes(32); // 32 bytes
    $token_hash = hash('sha256', $validator);

    // 2. Expiry (30 days)
    $expires_at = date('Y-m-d H:i:s', time() + (86400 * 30));

    // 3. Store in DB
    $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, selector, token, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $selector, $token_hash, $expires_at);
    $stmt->execute();

    // 4. Set Cookie (selector:validator)
    // HttpOnly, Secure if possible
    $cookie_value = $selector . ':' . bin2hex($validator);
    setcookie('remember_me', $cookie_value, time() + (86400 * 30), '/', '', false, true);
}

/**
 * Check "Remember Me" cookie and restore session
 */
function check_remember_me()
{
    global $conn;

    if (isset($_COOKIE['remember_me']) && !isset($_SESSION['user_id'])) {
        $parts = explode(':', $_COOKIE['remember_me']);
        if (count($parts) === 2) {
            $selector = $parts[0];
            $validator = hex2bin($parts[1]);

            // Find token by selector
            $stmt = $conn->prepare("SELECT id, user_id, token, expires_at FROM user_tokens WHERE selector = ? AND expires_at > NOW()");
            $stmt->bind_param("s", $selector);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if ($result) {
                // Verify Validator
                if (hash_equals($result['token'], hash('sha256', $validator))) {
                    // Valid! Restore Session
                    $user_id = $result['user_id'];

                    // Get User Info
                    $u_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $u_stmt->bind_param("i", $user_id);
                    $u_stmt->execute();
                    $user = $u_stmt->get_result()->fetch_assoc();

                    if ($user) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        if (!empty($user['display_name'])) {
                            $_SESSION['display_name'] = $user['display_name'];
                        }

                        // Rotate Token (Security)
                        // Delete old used token
                        $del = $conn->prepare("DELETE FROM user_tokens WHERE id = ?");
                        $del->bind_param("i", $result['id']);
                        $del->execute();

                        // Issue new one
                        remember_me($user_id);
                    }
                }
            }
        }
    }
}
?>