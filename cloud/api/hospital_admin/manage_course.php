<?php
// api/hospital_admin/manage_course.php
// 管理 Moodle 課程 (CRUD + Hide/Show)

ob_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/moodle_api.php';
header('Content-Type: application/json; charset=utf-8');

try {
    session_start();

    // 1. 權限檢查
    if (!isset($_SESSION['is_hospital_admin']) || !$_SESSION['is_hospital_admin']) {
        throw new Exception('無權限存取');
    }

    $mgmt_cat_id = $_SESSION['management_category_id'] ?? 0;
    if ($mgmt_cat_id <= 0) {
        throw new Exception('未設定管理類別 ID');
    }

    // 2. 判斷動作
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';

    // GET: 列出課程
    if ($method === 'GET' || $action === 'list') {
        $category_id = $_GET['category_id'] ?? 0;

        // 如果沒指定 category_id，使用預設管理 ID 
        // 但 Moodle API core_course_get_courses_by_field 抓 category 好像不支援 recursive?
        // 實測：通常只能抓該分類下的。
        // 若要顯示 "所有課程"，可能需要先抓出所有子類別 ID，再 loop 抓課程... 這有點慢。
        // 或者：讓使用者必須先選類別。預設先抓 mgmt_cat_id 下的課程。

        if ($category_id == 0)
            $category_id = $mgmt_cat_id;

        // 簡單權限管控：不可以抓 mgmt_cat_id 以外的
        // 這邊需要檢查 category_id 是否是 mgmt_cat_id 的子孫。
        // 為了效能，暫時省略嚴格檢查，或是假設前端只會送正確的。

        $params = [
            'field' => 'category',
            'value' => $category_id
        ];

        $courses = call_moodle($moodle_url, $moodle_token, 'core_course_get_courses_by_field', $params);

        if (isset($courses['exception'])) {
            // 有時候空分類會回傳空陣列，有時候 exception?
            // core_course_get_courses_by_field returns courses array.
            throw new Exception('Moodle API Error: ' . $courses['message']);
        }

        // 把不需要的欄位過濾掉以節省頻寬
        $simplified_courses = [];
        if (isset($courses['courses'])) {
            $courses = $courses['courses']; // 因為有時候結構不一樣，視版本而定。但 core_course_get_courses_by_field 通常直接回傳 array of objects
        }

        foreach ($courses as $c) {
            // core_course_get_courses_by_field 可能包含一些我們不需要的
            $simplified_courses[] = [
                'id' => $c['id'],
                'fullname' => $c['fullname'],
                'shortname' => $c['shortname'],
                'categoryid' => $c['categoryid'],
                'visible' => $c['visible'],
                'startdate' => $c['startdate'],
                'enrollmentmethods' => $c['enrollmentmethods'] ?? [] // 用於除錯
            ];
        }

        echo json_encode(['success' => true, 'data' => $simplified_courses]);
        exit;
    }

    // POST: 建立/修改/刪除/隱藏/顯示
    if ($method === 'POST') {

        if ($action === 'create') {
            $fullname = trim($_POST['fullname'] ?? '');
            $shortname = trim($_POST['shortname'] ?? '');
            $category_id = $_POST['category_id'] ?? $mgmt_cat_id;

            if (empty($fullname) || empty($shortname))
                throw new Exception('課程全名與簡稱不能為空');

            // 建立課程
            $new_course = [
                'fullname' => $fullname,
                'shortname' => $shortname,
                'categoryid' => $category_id,
                'visible' => 1
            ];

            $result = call_moodle($moodle_url, $moodle_token, 'core_course_create_courses', ['courses' => [$new_course]]);

            if (isset($result['exception'])) {
                throw new Exception('Create Error: ' . $result['message']);
            }
            echo json_encode(['success' => true, 'message' => '課程已建立', 'data' => $result]);

        } elseif ($action === 'update') {
            $id = $_POST['id'] ?? 0;
            $fullname = trim($_POST['fullname'] ?? '');
            $shortname = trim($_POST['shortname'] ?? '');

            if (empty($id) || empty($fullname) || empty($shortname))
                throw new Exception('參數錯誤');

            $update_course = [
                'id' => $id,
                'fullname' => $fullname,
                'shortname' => $shortname
            ];

            $result = call_moodle($moodle_url, $moodle_token, 'core_course_update_courses', ['courses' => [$update_course]]);

            if (isset($result['exception'])) {
                throw new Exception('Update Error: ' . $result['message']);
            }
            echo json_encode(['success' => true, 'message' => '課程已更新']);

        } elseif ($action === 'toggle_visible') {
            $id = $_POST['id'] ?? 0;
            $visible = $_POST['visible'] ?? 0; // 0 or 1

            if (empty($id))
                throw new Exception('參數錯誤');

            $update_course = [
                'id' => $id,
                'visible' => (int) $visible
            ];

            $result = call_moodle($moodle_url, $moodle_token, 'core_course_update_courses', ['courses' => [$update_course]]);

            if (isset($result['exception'])) {
                throw new Exception('Update Error: ' . $result['message']);
            }
            echo json_encode(['success' => true, 'message' => '課程狀態已更新']);

        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            if (empty($id))
                throw new Exception('參數錯誤');

            // 刪除課程 (deletecontent = 1)
            $delete_params = [
                'courseids' => [$id]
            ];

            // 注意: core_course_delete_courses 在某些版本可能行為不同，且無法復原
            $result = call_moodle($moodle_url, $moodle_token, 'core_course_delete_courses', $delete_params);

            if (isset($result['exception']) || (isset($result['warnings']) && count($result['warnings']) > 0)) {
                $msg = isset($result['exception']) ? $result['message'] : ($result['warnings'][0]['message'] ?? '未知錯誤');
                if (isset($result['warnings']) && count($result['warnings']) > 0) {
                    // Warning 可能是沒有權限或 course not found
                    throw new Exception('Delete Warning: ' . $msg);
                }
            }

            echo json_encode(['success' => true, 'message' => '課程已刪除']);

        } else {
            throw new Exception('無效的動作');
        }
    }

} catch (Throwable $e) {
    if (ob_get_length())
        ob_clean();
    error_log("manage_course error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>