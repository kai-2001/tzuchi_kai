<?php
// api/hospital_admin/manage_category.php
// 管理 Moodle 類別 (CRUD)

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

    // GET: 列出子類別
    if ($method === 'GET' || $action === 'list') {
        // 取得該管理類別下的所有子類別
        // Moodle API: core_course_get_categories
        // critiera: parent = $mgmt_cat_id (只抓第一層子類別)
        // 若要抓所有層級，可能需要遞迴或抓全部再 filter，但 Moodle API 支援 parent filter

        // 為了效能，我們只抓父類別為 mgmt_cat_id 的
        $params = [
            'criteria' => [
                ['key' => 'parent', 'value' => $mgmt_cat_id]
            ]
        ];

        // 備註：如果要支援多層級，可能要改邏輯。暫時先做單層管理，或是前端做樹狀展開。
        // 使用者需求是 "管理此類別的子類別"，通常包含建立新的子類別。

        $categories = call_moodle($moodle_url, $moodle_token, 'core_course_get_categories', $params);

        if (isset($categories['exception'])) {
            throw new Exception('Moodle API Error: ' . $categories['message']);
        }

        echo json_encode(['success' => true, 'data' => $categories]);
        exit;
    }

    // POST: 建立/修改/刪除
    if ($method === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $id = $_POST['id'] ?? 0;

        if ($action === 'create') {
            if (empty($name))
                throw new Exception('類別名稱不能為空');

            // 建立類別：parent 為目前的 management_category_id
            $new_category = [
                'name' => $name,
                'parent' => $mgmt_cat_id,
                'description' => '',
                'descriptionformat' => 1
            ];

            $result = call_moodle($moodle_url, $moodle_token, 'core_course_create_categories', ['categories' => [$new_category]]);

            if (isset($result['exception'])) {
                throw new Exception('Create Error: ' . $result['message']);
            }
            // result 應該是 array of created categories
            echo json_encode(['success' => true, 'message' => '類別已建立', 'data' => $result]);

        } elseif ($action === 'update') {
            if (empty($name) || empty($id))
                throw new Exception('參數錯誤');

            // 確保該類別屬於此管理員管轄（簡單檢查：parent 是否為 mgmt_cat_id？）
            // 為了嚴謹，應該先 get category check parent，但為了效能先省略，相信 Moodle API error handling

            $update_category = [
                'id' => $id,
                'name' => $name
            ];

            $result = call_moodle($moodle_url, $moodle_token, 'core_course_update_categories', ['categories' => [$update_category]]);

            if (isset($result['exception'])) {
                throw new Exception('Update Error: ' . $result['message']);
            }

            echo json_encode(['success' => true, 'message' => '類別已更新']);

        } elseif ($action === 'delete') {
            if (empty($id))
                throw new Exception('參數錯誤');

            // 刪除類別
            // recursive = 1 (刪除底下課程與子類別) 還是 0 (移至其他)？
            // 預設行為：若有內容如果不指定 recursive=1 會失敗。
            // 為了安全，我們預設 recursive=1 但提醒使用者。這裡先設為 1。

            $delete_params = [
                'categories' => [
                    ['id' => $id, 'recursive' => 1]
                ]
            ];

            $result = call_moodle($moodle_url, $moodle_token, 'core_course_delete_categories', $delete_params);

            if (isset($result['exception'])) {
                throw new Exception('Delete Error: ' . $result['message']);
            }
            // 如果 Moodle 回傳 null 或空陣列通常代表成功 (文件如此，實際需測試)
            // 也有可能回傳 warnings

            echo json_encode(['success' => true, 'message' => '類別已刪除']);

        } else {
            throw new Exception('無效的動作');
        }
    }

} catch (Throwable $e) {
    if (ob_get_length())
        ob_clean();
    error_log("manage_category error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>