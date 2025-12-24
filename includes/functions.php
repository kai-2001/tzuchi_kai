<?php
// includes/functions.php - 共用函式庫

/**
 * 產生 CSRF Token
 */
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 驗證 CSRF Token
 */
function verify_csrf_token($token)
{
    return isset($token) && $token === $_SESSION['csrf_token'];
}

/**
 * 呼叫 Moodle Web Service API
 * @param string $url Moodle 伺服器網址
 * @param string $token Web Service Token
 * @param string $func API 函式名稱
 * @param array $params 請求參數
 * @return array|null API 回應
 */
function call_moodle($url, $token, $func, $params = [])
{
    $serverurl = $url . '/webservice/rest/server.php' . '?wstoken=' . $token . '&wsfunction=' . $func . '&moodlewsrestformat=json';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $serverurl);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, CURL_TIMEOUT);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, CURL_CONNECT_TIMEOUT);
    $resp = curl_exec($curl);
    curl_close($curl);
    return json_decode($resp, true);
}

/**
 * 平行呼叫多個 Moodle Web Service API
 * @param string $url Moodle 伺服器網址
 * @param string $token Web Service Token
 * @param array $requests 請求陣列，每個元素包含 ['key' => 識別鍵, 'func' => API函式, 'params' => 參數]
 * @return array 以 key 為索引的回應陣列
 * 
 * 範例:
 * $requests = [
 *     ['key' => 'users', 'func' => 'core_user_get_users_by_field', 'params' => [...]],
 *     ['key' => 'courses', 'func' => 'core_enrol_get_users_courses', 'params' => [...]]
 * ];
 * $results = call_moodle_parallel($url, $token, $requests);
 * // $results['users'] 包含使用者資料
 * // $results['courses'] 包含課程資料
 */
function call_moodle_parallel($url, $token, $requests)
{
    if (empty($requests)) {
        return [];
    }

    $multi_handle = curl_multi_init();
    $curl_handles = [];
    $results = [];

    // 初始化所有 cURL 請求
    foreach ($requests as $index => $request) {
        $func = $request['func'];
        $params = isset($request['params']) ? $request['params'] : [];
        $key = isset($request['key']) ? $request['key'] : $index;

        $serverurl = $url . '/webservice/rest/server.php' . '?wstoken=' . $token . '&wsfunction=' . $func . '&moodlewsrestformat=json';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $serverurl);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, CURL_TIMEOUT);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, CURL_CONNECT_TIMEOUT);

        curl_multi_add_handle($multi_handle, $curl);
        $curl_handles[$key] = $curl;
    }

    // 執行所有請求
    $running = null;
    do {
        curl_multi_exec($multi_handle, $running);
        curl_multi_select($multi_handle);
    } while ($running > 0);

    // 收集結果
    foreach ($curl_handles as $key => $curl) {
        $content = curl_multi_getcontent($curl);
        $results[$key] = json_decode($content, true);
        curl_multi_remove_handle($multi_handle, $curl);
        curl_close($curl);
    }

    curl_multi_close($multi_handle);

    return $results;
}


/**
 * 安全輸出 HTML
 */
function h($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>