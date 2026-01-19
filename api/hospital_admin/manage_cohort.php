<?php
// api/hospital_admin/manage_cohort.php
// 管理 Moodle Cohort (CRUD + 成員管理)

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

    // Cohort 可以在 System Context (id=1) 或 Category Context
    // 為了簡化，我們假設管理員只能管理建立在自己 Category 下的 Cohort
    // 或者，如果是 System Cohort，則必須包含 Institution 名稱 (這比較難管理)
    // 這裡採用：管理該管理員 Category 下的 Cohort。

    $mgmt_cat_id = $_SESSION['management_category_id'] ?? 0;
    if ($mgmt_cat_id <= 0) {
        throw new Exception('未設定管理類別 ID');
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';

    // GET: 列出 Cohorts
    if ($method === 'GET' || $action === 'list') {
        // Moodle API 尚未直接提供 get_cohorts_by_category?
        // core_cohort_get_cohorts 其實沒有 filter, 只有 cohortids?
        // core_cohort_search_cohorts?
        // core_course_get_categories 回傳的並沒包含 cohort。

        // 查閱 Moodle Webservice API: core_cohort_search_cohorts
        // contextid: 可以指定 Category 的 Context ID。
        // 但是我們只知道 Category ID。
        // Category Context ID 通常不是 Category ID。
        // 既然無法輕易得知 Category Context ID，我們改用 search query (empty) + includes (system matches?)

        // 或者，我們先列出所有 Cohort，再依照 visible/name 過濾？
        // core_cohort_get_cohorts 本身只能依 ID 取。
        // core_cohort_search_cohorts 是搜尋。

        $params = [
            'query' => '', // Empty query to get all? Or specific prefix?
            'context' => [
                'contextid' => 0, // 0 usually means all? No.
                'contextlevel' => 'category',
                'instanceid' => $mgmt_cat_id
            ],
            'includes' => 'parents' // or 'self'
        ];

        // Moodle 3.x+ core_cohort_search_cohorts
        $result = call_moodle($moodle_url, $moodle_token, 'core_cohort_search_cohorts', $params);

        if (isset($result['exception'])) {
            // 嘗試用另一種方式：如果是舊版 Moodle
            throw new Exception('Moodle API Error: ' . $result['message']);
        }

        $cohorts = $result['cohorts'] ?? [];
        echo json_encode(['success' => true, 'data' => $cohorts]);
        exit;
    }

    // POST: 建立/修改/刪除/成員
    if ($method === 'POST') {

        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $idnumber = trim($_POST['idnumber'] ?? ''); // 選填

            if (empty($name))
                throw new Exception('名稱不能為空');

            // 重要：建立在 Category Context
            $new_cohort = [
                'categorytype' => ['type' => 'id', 'value' => $mgmt_cat_id],
                'name' => $name,
                'idnumber' => $idnumber,
                'description' => '',
                'descriptionformat' => 1
            ];

            $result = call_moodle($moodle_url, $moodle_token, 'core_cohort_create_cohorts', ['cohorts' => [$new_cohort]]);

            if (isset($result['exception'])) {
                throw new Exception('Create Error: ' . $result['message']);
            }
            // 回傳通常包含 id
            echo json_encode(['success' => true, 'message' => '群組已建立']);

        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            if (empty($id))
                throw new Exception('參數錯誤');

            $result = call_moodle($moodle_url, $moodle_token, 'core_cohort_delete_cohorts', ['cohortids' => [$id]]);

            if (isset($result['exception'])) {
                throw new Exception('Delete Error: ' . $result['message']);
            }
            echo json_encode(['success' => true, 'message' => '群組已刪除']);

        } elseif ($action === 'get_members') {
            // 取得某 Cohort 的成員
            $cohort_id = $_POST['cohort_id'] ?? $_GET['cohort_id'] ?? 0;
            if (empty($cohort_id))
                throw new Exception('參數錯誤');

            $result = call_moodle($moodle_url, $moodle_token, 'core_cohort_get_cohort_members', ['cohortids' => [$cohort_id]]);

            if (isset($result['exception'])) {
                throw new Exception('Get Members Error: ' . $result['message']);
            }

            // result is array of { cohortid: x, userids: [...] }
            $user_ids = $result[0]['userids'] ?? [];

            // 這裡只拿到 user ID，我們需要 user details。
            // 呼叫 core_user_get_users_by_field 批次取

            $users_details = [];
            if (!empty($user_ids)) {
                $u_params = ['field' => 'id', 'values' => $user_ids];
                $users_info = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);
                if (!isset($users_info['exception'])) {
                    foreach ($users_info as $u) {
                        $users_details[] = [
                            'id' => $u['id'],
                            'username' => $u['username'],
                            'fullname' => $u['fullname'],
                            'email' => $u['email']
                        ];
                    }
                }
            }

            echo json_encode(['success' => true, 'data' => $users_details]);

        } elseif ($action === 'add_member') {
            // 加入成員
            $cohort_id = $_POST['cohort_id'] ?? 0;
            $username = trim($_POST['username'] ?? ''); // 支援輸入 username

            if (empty($cohort_id) || empty($username))
                throw new Exception('參數錯誤');

            // 先依 username 查 user id
            $u_params = ['field' => 'username', 'values' => [$username]];
            $users = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $u_params);

            if (empty($users) || isset($users['exception'])) {
                throw new Exception('找不到該使用者: ' . $username);
            }
            $user_id = $users[0]['id'];

            $member_param = [
                'members' => [
                    ['cohorttype' => ['type' => 'id', 'value' => $cohort_id], 'usertype' => ['type' => 'id', 'value' => $user_id]]
                ]
            ];

            $result = call_moodle($moodle_url, $moodle_token, 'core_cohort_add_cohort_members', $member_param);

            if (isset($result['exception']) || (isset($result['warnings']) && count($result['warnings']) > 0)) {
                $msg = isset($result['exception']) ? $result['message'] : ($result['warnings'][0]['message'] ?? '未知錯誤');
                throw new Exception('Add Member Error: ' . $msg);
            }

            echo json_encode(['success' => true, 'message' => '成員已加入']);

        } elseif ($action === 'remove_member') {
            $cohort_id = $_POST['cohort_id'] ?? 0;
            $user_id = $_POST['user_id'] ?? 0;

            if (empty($cohort_id) || empty($user_id))
                throw new Exception('參數錯誤');

            $member_param = [
                'members' => [
                    ['cohortid' => $cohort_id, 'userid' => $user_id]
                ]
            ];

            $result = call_moodle($moodle_url, $moodle_token, 'core_cohort_delete_cohort_members', $member_param);

            if (isset($result['exception'])) {
                throw new Exception('Remove Member Error: ' . $result['message']);
            }
            echo json_encode(['success' => true, 'message' => '成員已移除']);

        } else {
            throw new Exception('無效的動作');
        }
    }

} catch (Throwable $e) {
    if (ob_get_length())
        ob_clean();
    error_log("manage_cohort error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>