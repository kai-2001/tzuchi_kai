<?php
// includes/auth.php - 認證邏輯
require_once __DIR__ . '/attribute_helper.php';


/**
 * 自動登入檢查 (Remember Me)
 */
function check_auto_login()
{
    global $db_host, $db_user, $db_pass, $db_name;

    if (!isset($_SESSION['user_id']) && isset($_COOKIE['portal_remember'])) {
        $token = $_COOKIE['portal_remember'];

        try {
            $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
            if ($conn->connect_error) {
                return;
            }

            $stmt = $conn->prepare("SELECT id, username, fullname, role, institution, remember_token FROM users WHERE remember_token = ?");
            if (!$stmt) {
                $conn->close();
                return;
            }

            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user_row = $result->fetch_assoc();
                $_SESSION['user_id'] = $user_row['username'];
                $_SESSION['username'] = $user_row['username'];
                $_SESSION['fullname'] = !empty($user_row['fullname']) ? $user_row['fullname'] : $user_row['username'];
                $_SESSION['db_user_id'] = $user_row['id']; // 資料庫 ID

                // 🚀 改用屬性判斷角色
                set_session_from_attributes($user_row['id'], $conn);

                // 同步 Moodle 角色與權限（取得類別 ID）
                $sync_result = sync_user_moodle_role($user_row['username'], $conn);
                $_SESSION['management_category_id'] = $sync_result['category_id'];
                setcookie('portal_manage_cat_id', $_SESSION['management_category_id'], 0, '/');
            }

            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            error_log("Auto login error: " . $e->getMessage());
        }
    }
}

/**
 * SOAP 認證
 */
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

        $result = $client->login($username, md5($password));

        if ($result == '1' || is_array($result) || is_object($result)) {
            return $result;
        }
        return false;
    } catch (Exception $e) {
        error_log("SOAP Login Error: " . $e->getMessage());
        return 'error';
    }
}

/**
 * 同步 Moodle 角色與分類權限
 * @param string $username 使用者帳號
 * @param mysqli $conn 資料庫連線
 * @return array ['category_id' => int, 'portal_role' => string]
 */
function sync_user_moodle_role($username, $conn)
{
    $default_result = ['category_id' => 0, 'portal_role' => 'student'];

    // 直接呼叫 Helper function (DB 查詢)
    require_once __DIR__ . '/moodle_api.php';
    $moodle_data = moodle_get_user_role_context($username);

    if (!$moodle_data || empty($moodle_data['portal_role'])) {
        return $default_result;
    }

    // 取得目前的本地角色
    $current_role = 'student';
    $stmt = $conn->prepare("SELECT role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $current_role = $row['role'];
    }
    $stmt->close();

    // 判斷是否需要更新
    // 注意: 我們不隨便降級 Admin (root admin)
    // 但如果是普通 hospital_admin 被拔權限，會被降級
    if ($current_role === 'admin') {
        // 如果是超級管理員，我們只更新分類 ID，不改角色
        return [
            'category_id' => (int) $moodle_data['category_id'],
            'portal_role' => 'admin'
        ];
    }

    $new_role = $moodle_data['portal_role'];

    // 如果角色不同，更新資料庫
    if ($current_role !== $new_role) {
        $up_stmt = $conn->prepare("UPDATE users SET role = ? WHERE username = ?");
        $up_stmt->bind_param("ss", $new_role, $username);
        $up_stmt->execute();
        $up_stmt->close();
    }

    return [
        'category_id' => (int) $moodle_data['category_id'],
        'portal_role' => $new_role
    ];
}

/**
 * 處理登入請求
 * @return string 錯誤訊息（成功則為空）
 */
function process_login()
{
    global $db_host, $db_user, $db_pass, $db_name;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['username'])) {
        return '';
    }

    // 驗證 CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        return "安全驗證失敗，請重新整理頁面後再試。";
    }

    $input_user = strtolower(trim($_POST['username']));
    $input_pass = $_POST['password'];
    $remember_me = isset($_POST['remember']);

    // 使用共用連線設定
    /** @var mysqli $conn */
    require __DIR__ . '/db_connect.php';
    if (!isset($conn) || $conn->connect_error) {
        return "系統暫時無法連線，請稍後再試。";
    }

    $login_success = false;
    $user_row = null;

    if (defined('AUTH_MODE') && AUTH_MODE === 'soap') {
        // --- SOAP 模式 ---
        $soap_result = soap_login($input_user, $input_pass);

        if ($soap_result === 'error') {
            return "登入服務暫時無法使用，請稍後再試。";
        }

        if ($soap_result) {
            $login_success = true;
            // 檢查本地資料庫是否已有此使用者
            $stmt = $conn->prepare("SELECT id, username, fullname, password, role, institution FROM users WHERE username = ?");
            $stmt->bind_param("s", $input_user);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                // 自動註冊 (同步本地資料庫)
                $stmt->close();
                $fullname = "";
                if (is_array($soap_result) && isset($soap_result['sn'])) {
                    $fullname = $soap_result['sn'];
                } elseif (is_object($soap_result) && isset($soap_result->sn)) {
                    $fullname = $soap_result->sn;
                }

                // Call centralized Moodle sync logic
                // 這裡我們需要 include moodle_api.php 才能使用 ensure_moodle_user_exists
                // 但為了避免重複 include 或路徑問題，我們檢查一下
                if (!function_exists('ensure_moodle_user_exists')) {
                    require_once __DIR__ . '/moodle_api.php';
                }

                $email = $input_user . "@example.com";
                ensure_moodle_user_exists($input_user, $fullname, $email);

                // --- 3. 自動註冊 (同步本地資料庫) ---
                $ins_stmt = $conn->prepare("INSERT INTO users (username, fullname, password, role, email) VALUES (?, ?, ?, 'student', ?)");
                $hashed_pass = password_hash($input_pass, PASSWORD_DEFAULT);
                $ins_stmt->bind_param("ssss", $input_user, $fullname, $hashed_pass, $email);
                $ins_stmt->execute();
                $ins_stmt->close();

                // 重新讀取剛建立的使用者
                $stmt = $conn->prepare("SELECT id, username, fullname, password, role, institution FROM users WHERE username = ?");
                $stmt->bind_param("s", $input_user);
                $stmt->execute();
                $result = $stmt->get_result();
            }
            $user_row = $result->fetch_assoc();
            $stmt->close();
        }
    } else {
        // --- 本地模式 ---
        $stmt = $conn->prepare("SELECT id, username, fullname, password, role, institution FROM users WHERE username = ?");
        $stmt->bind_param("s", $input_user);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user_row = $result->fetch_assoc();
            if (password_verify($input_pass, $user_row['password'])) {
                $login_success = true;
            } elseif ($user_row['password'] === $input_pass) {
                // 舊的明碼密碼，升級為雜湊
                $login_success = true;
                $new_hash = password_hash($input_pass, PASSWORD_DEFAULT);
                $up_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $up_stmt->bind_param("si", $new_hash, $user_row['id']);
                $up_stmt->execute();
                $up_stmt->close();
            }
        }
        $stmt->close();
    }

    if (!$login_success) {
        return "帳號或密碼錯誤！";
    }

    // 登入成功，設定 Session
    $_SESSION['user_id'] = $user_row['username'];
    $_SESSION['username'] = $user_row['username'];
    $_SESSION['fullname'] = !empty($user_row['fullname']) ? $user_row['fullname'] : $user_row['username'];
    $_SESSION['db_user_id'] = $user_row['id']; // 資料庫 ID

    // 🚀 改用屬性判斷角色
    set_session_from_attributes($user_row['id'], $conn);

    // 同步 Moodle 角色與權限（取得類別 ID）
    $sync_result = sync_user_moodle_role($user_row['username'], $conn);
    $_SESSION['management_category_id'] = $sync_result['category_id'];
    setcookie('portal_manage_cat_id', $_SESSION['management_category_id'], 0, '/');

    // 處理 Remember Me
    if ($remember_me) {
        $token = bin2hex(random_bytes(32));
        setcookie('portal_remember', $token, time() + (86400 * 30), "/");

        $up_stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE username = ?");
        if ($up_stmt) {
            $up_stmt->bind_param("ss", $token, $user_row['username']);
            $up_stmt->execute();
            $up_stmt->close();
        }
    }

    header("Location: index.php");
    exit;
}

/**
 * 處理登出
 */
function process_logout()
{
    if (isset($_GET['logout'])) {
        session_destroy();
        if (isset($_COOKIE['portal_remember'])) {
            setcookie('portal_remember', '', time() - 3600, '/');
        }
        header("Location: logout.php");
        exit;
    }
}
?>