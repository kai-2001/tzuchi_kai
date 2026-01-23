<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'includes/config.php';
require_once 'includes/auth.php';

$error = '';

// --- SSO Receiver Logic ---
if (isset($_GET['data']) && isset($_GET['sig'])) {
    require_once '../includes/config.php'; // Get $moodle_sso_secret from main portal

    $encdata = $_GET['data'];
    $signature = $_GET['sig'];
    $secret = $moodle_sso_secret;

    // Decrypt (AES-256-CBC)
    $decoded = base64_decode($encdata);
    if ($decoded !== false && strpos($decoded, '::') !== false) {
        list($ciphertext, $iv) = explode('::', $decoded, 2);
        $data = openssl_decrypt($ciphertext, 'aes-256-cbc', $secret, 0, $iv);

        if ($data !== false) {
            // Verify HMAC
            $expected_sig = hash_hmac('sha256', $data, $secret);
            if (hash_equals($expected_sig, $signature)) {
                $payload = json_decode($data, true);
                if ($payload && isset($payload['username'])) {
                    $username = $payload['username'];

                    // Successful SSO - Login User
                    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();

                    // Campus ID logic would need to be handled here if SSO provides it, otherwise leaves as NULL for manual assignment

                    if (!$user) {
                        $stmt = $conn->prepare("INSERT INTO users (username, role) VALUES (?, 'member')");
                        $stmt->bind_param("s", $username);
                        $stmt->execute();
                        $user_id = $conn->insert_id;
                        $role = 'member';
                    } else {
                        $user_id = $user['id'];
                        $role = $user['role'];
                    }

                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;
                    $_SESSION['campus_id'] = $user['campus_id'] ?? null;

                    header("Location: index.php");
                    exit;
                }
            }
        }
    }
    $error = 'SSO 驗證失敗。';
}
// --- End SSO Logic ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $auth_result = user_login($username, $password);

    if ($auth_result) {
        // Success - Check or create local user
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            // First time login, create as member
            $role = ($username === 'admin') ? 'manager' : 'member';
            $stmt = $conn->prepare("INSERT INTO users (username, role) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $role);
            $stmt->execute();
            $user_id = $conn->insert_id;
        } else {
            $user_id = $user['id'];
            $role = $user['role'];
        }

        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['campus_id'] = $user['campus_id'] ?? null;

        if (is_array($auth_result) && isset($auth_result['sn'])) {
            $display_name = $auth_result['sn'];
            $_SESSION['display_name'] = $display_name;

            // Campus Mapping Logic (Dynamic from DB)
            $soap_branch = strtolower($auth_result['branch'] ?? '');
            $campus_id = null;

            if ($soap_branch) {
                // Find campus by branch_code
                $c_stmt = $conn->prepare("SELECT id FROM campuses WHERE branch_code = ?");
                $c_stmt->bind_param("s", $soap_branch);
                $c_stmt->execute();
                $c_res = $c_stmt->get_result();
                if ($row = $c_res->fetch_assoc()) {
                    $campus_id = $row['id'];
                }
            }

            if ($campus_id) {
                $_SESSION['campus_id'] = $campus_id;
                // Update DB
                $up_stmt = $conn->prepare("UPDATE users SET display_name = ?, campus_id = ? WHERE id = ?");
                $up_stmt->bind_param("sii", $display_name, $campus_id, $user_id);
            } else {
                // Update DB Name only
                $up_stmt = $conn->prepare("UPDATE users SET display_name = ? WHERE id = ?");
                $up_stmt->bind_param("si", $display_name, $user_id);
            }
            $up_stmt->execute();

        } elseif (is_object($auth_result) && isset($auth_result->sn)) {
            $display_name = $auth_result->sn;
            $_SESSION['display_name'] = $display_name;

            // Campus Mapping Logic (Dynamic from DB)
            $soap_branch = strtolower($auth_result->branch ?? '');
            $campus_id = null;

            if ($soap_branch) {
                // Find campus by branch_code
                $c_stmt = $conn->prepare("SELECT id FROM campuses WHERE branch_code = ?");
                $c_stmt->bind_param("s", $soap_branch);
                $c_stmt->execute();
                $c_res = $c_stmt->get_result();
                if ($row = $c_res->fetch_assoc()) {
                    $campus_id = $row['id'];
                }
            }

            if ($campus_id) {
                $_SESSION['campus_id'] = $campus_id;
                $up_stmt = $conn->prepare("UPDATE users SET display_name = ?, campus_id = ? WHERE id = ?");
                $up_stmt->bind_param("sii", $display_name, $campus_id, $user_id);
            } else {
                $up_stmt = $conn->prepare("UPDATE users SET display_name = ? WHERE id = ?");
                $up_stmt->bind_param("si", $display_name, $user_id);
            }
            $up_stmt->execute();
        }

        // --- Remember Me Logic ---
        if (isset($_POST['remember_me'])) {
            remember_me($user_id);
        }

        header("Location: index.php");
        exit;
    } elseif ($auth_result === 'error') {
        $error = '登入服務暫時無法使用，請稍後再試。';
    } else {
        $error = '帳號或密碼錯誤。';
    }
}

// ============================================
// VIEW: Render the template
// ============================================
include 'templates/login.php';
?>