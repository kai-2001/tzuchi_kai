<?php
// includes/auth.php - 認證邏輯

/**
 * 檢查使用者是否為開課教師 (teacherplus)
 * 透過 portal_db 的 role 欄位查詢
 * @param string $username 使用者帳號
 * @return bool 是否為開課教師
 */
function check_teacherplus_role($username)
{
    global $db_host, $db_user, $db_pass, $db_name;

    try {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        if ($conn->connect_error) {
            error_log("DB connection error: " . $conn->connect_error);
            return false;
        }

        // 查詢使用者的 role 欄位
        $stmt = $conn->prepare("SELECT role FROM users WHERE username = ?");
        if (!$stmt) {
            // role 欄位可能不存在
            $conn->close();
            return false;
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            $conn->close();
            return ($row['role'] === 'teacherplus');
        }

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log("Role check error: " . $e->getMessage());
    }

    return false;
}


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

            $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
            if (!$stmt) {
                // remember_token 欄位可能不存在
                $conn->close();
                return;
            }

            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user_row = $result->fetch_assoc();
                $_SESSION['user_id'] = $user_row['username'];  // 使用 username 作為 ID
                $_SESSION['username'] = $user_row['username'];
                $_SESSION['fullname'] = !empty($user_row['fullname']) ? $user_row['fullname'] : $user_row['username'];
                $_SESSION['moodle_token'] = base64_encode(openssl_encrypt($user_row['password'], 'AES-256-CBC', $db_pass, 0, substr(md5($db_pass), 0, 16)));
                $_SESSION['is_admin'] = ($user_row['username'] === 'admin');

                // 檢測開課教師角色
                $_SESSION['is_teacherplus'] = check_teacherplus_role($user_row['username']);

                // 設定角色 Cookie (供 Moodle 前端判斷使用)
                setcookie('portal_is_admin', $_SESSION['is_admin'] ? '1' : '0', 0, '/');
                setcookie('portal_is_teacherplus', $_SESSION['is_teacherplus'] ? '1' : '0', 0, '/');
            }

            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            error_log("Auto login error: " . $e->getMessage());
        }
    }
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

    $input_user = $_POST['username'];
    $input_pass = $_POST['password'];
    $remember_me = isset($_POST['remember']);

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return "系統暫時無法連線，請稍後再試。";
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $input_user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        return "帳號或密碼錯誤！";
    }

    $user_row = $result->fetch_assoc();
    $login_success = false;

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

    if (!$login_success) {
        $stmt->close();
        $conn->close();
        return "帳號或密碼錯誤！";
    }

    // 登入成功，設定 Session
    $_SESSION['user_id'] = $user_row['username'];  // 使用 username 作為 ID（資料表沒有 id 欄位）
    $_SESSION['username'] = $user_row['username'];
    $_SESSION['fullname'] = !empty($user_row['fullname']) ? $user_row['fullname'] : $user_row['username'];
    $_SESSION['moodle_token'] = base64_encode(openssl_encrypt($input_pass, 'AES-256-CBC', $db_pass, 0, substr(md5($db_pass), 0, 16)));
    $_SESSION['is_admin'] = ($user_row['username'] === 'admin');

    // 檢測開課教師角色 (teacherplus)
    $_SESSION['is_teacherplus'] = check_teacherplus_role($user_row['username']);

    // 設定角色 Cookie (供 Moodle 前端判斷使用)
    setcookie('portal_is_admin', $_SESSION['is_admin'] ? '1' : '0', 0, '/');
    setcookie('portal_is_teacherplus', $_SESSION['is_teacherplus'] ? '1' : '0', 0, '/');

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

    $stmt->close();
    $conn->close();

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