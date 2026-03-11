<?php
/**
 * api/student/enrol_course.php
 * 學生自行報名課程（支援隱藏課程）
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/moodle_api.php';
require_once '../../includes/db_connect.php';
header('Content-Type: application/json; charset=utf-8');

try {
    session_start();

    // 確認用戶已登入
    if (!isset($_SESSION['username'])) {
        throw new Exception('請先登入');
    }

    $course_id = (int) ($_POST['course_id'] ?? 0);

    if ($course_id <= 0) {
        throw new Exception('缺少課程 ID');
    }

    // 獲取 Moodle UID (course_visibility 表存的是 Moodle UID)
    $moodle_uid = isset($_SESSION['moodle_uid']) ? (int) $_SESSION['moodle_uid'] : null;
    if (!$moodle_uid && isset($_SESSION['moodle_cache']['courses']['moodle_uid'])) {
        $moodle_uid = (int) $_SESSION['moodle_cache']['courses']['moodle_uid'];
    }

    if (!$moodle_uid) {
        throw new Exception('無法取得 Moodle 用戶 ID');
    }

    // 檢查用戶是否有權限報名此課程（使用全新的 Rule-based 邏輯）
    $has_permission = false;

    // 先取得學生的 User ID 與相關維度、標籤資訊
    $portal_uid = $_SESSION['user_id'] ?? 0;
    $user_cohorts = [];
    $user_tags = [];

    if ($moodle_uid > 0) {
        $mconn_temp = new mysqli($db_host, $db_user, $db_pass, 'moodle');
        if (!$mconn_temp->connect_error) {
            $mconn_temp->set_charset('utf8mb4');
            $stmt = $mconn_temp->prepare("
                SELECT cohortid 
                FROM mdl_cohort_members 
                WHERE userid = ?
            ");
            $stmt->bind_param("i", $moodle_uid);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $user_cohorts[] = (int) $row['cohortid'];
            }
            $stmt->close();
            $mconn_temp->close();
        }
    }

    if ($portal_uid > 0) {

        $stmt = $conn->prepare("
            SELECT tag_id 
            FROM user_tags 
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $portal_uid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $user_tags[] = (int) $row['tag_id'];
        }
        $stmt->close();
    }

    // 取得該課程的報名規則
    $stmt = $conn->prepare("SELECT rule_snapshot FROM course_visibility_rules WHERE course_id = ? AND is_active = 1");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rule_exists = false;

    if ($result->num_rows > 0) {
        $rule_exists = true;
        $row = $result->fetch_assoc();
        $rules = json_decode($row['rule_snapshot'], true);

        if (is_array($rules)) {
            $filter_groups = $rules['filter_groups'] ?? [];
            $operators = $rules['operators'] ?? [];
            $rule_tags = $rules['tag_ids'] ?? [];

            // 1. 檢查標籤條件 (如果有設定)
            $tag_matched = empty($rule_tags); // 如果沒設定標籤，預設為 true
            if (!empty($rule_tags)) {
                $tag_matched = count(array_intersect($rule_tags, $user_tags)) > 0;
            }

            // 2. 檢查群組條件
            $group_matched = empty($filter_groups);
            if (!$group_matched) {
                $overall_group_matched = false;
                for ($i = 0; $i < count($filter_groups); $i++) {
                    $group = $filter_groups[$i];
                    // 如果這個群組是空的，預設為不吻合，除非我們有特別處理空群組的邏輯
                    if (empty($group)) {
                        $current_group_matched = false;
                    } else {
                        // 學生擁有的 cohort 必須包含這個 group 裡的所有 cohort
                        $current_group_matched = count(array_intersect($group, $user_cohorts)) === count($group);
                    }

                    if ($i === 0) {
                        $overall_group_matched = $current_group_matched;
                    } else {
                        $op = strtolower($operators[$i - 1] ?? 'or');
                        if ($op === 'and') {
                            $overall_group_matched = $overall_group_matched && $current_group_matched;
                        } else {
                            $overall_group_matched = $overall_group_matched || $current_group_matched;
                        }
                    }
                }
                $group_matched = $overall_group_matched;
            }

            // 標籤跟群組條件必須同時滿足 (若有設定的話)
            if ($tag_matched && $group_matched) {
                $has_permission = true;
            }
        }
    }
    $stmt->close();

    // 如果沒有設定 rules，或者沒有過關，則檢查是否為純公開課程或在排除名單等
    if (!$has_permission) {
        // 先檢查是否在排除名單中
        $stmt_ex = $conn->prepare("SELECT id FROM course_visibility_exclusions WHERE course_id = ? AND user_id = ?");
        $stmt_ex->bind_param("ii", $course_id, $portal_uid);
        $stmt_ex->execute();
        $is_excluded = $stmt_ex->get_result()->num_rows > 0;
        $stmt_ex->close();

        if ($is_excluded) {
            throw new Exception('您無法報名此課程 (在排除名單中)');
        }

        if (!$rule_exists) {
            // 如果這門課完全沒有設定 active rules，我們得確認它在 Moodle 裡面是不是公開的 (visible = 1)
            // 或者是保留向後相容的舊 course_visibility 設定

            // 檢查舊表
            $stmt = $conn->prepare("SELECT id FROM course_visibility WHERE course_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $course_id, $moodle_uid);
            $stmt->execute();
            $old_res = $stmt->get_result();
            if ($old_res->num_rows > 0) {
                $has_permission = true;
            }
            $stmt->close();

            if (!$has_permission) {
                // 如果連舊表都沒有，去 Moodle 檢查是否本身就 visible = 1
                $mconn_check = new mysqli($db_host, $db_user, $db_pass, 'moodle');
                if (!$mconn_check->connect_error) {
                    $mconn_check->set_charset('utf8mb4');
                    $stmt_vis = $mconn_check->prepare("SELECT visible FROM mdl_course WHERE id = ?");
                    $stmt_vis->bind_param("i", $course_id);
                    $stmt_vis->execute();
                    $res_vis = $stmt_vis->get_result();
                    if ($res_vis->num_rows > 0) {
                        $c_row = $res_vis->fetch_assoc();
                        if ((int) $c_row['visible'] === 1) {
                            $has_permission = true;
                        }
                    }
                    $stmt_vis->close();
                    $mconn_check->close();
                }
            }
        }
    }

    if (!$has_permission) {
        throw new Exception('您沒有權限報名此課程');
    }

    // ===== 最終完美混合解法：Web Service 報名 + 資料庫偽裝 =====
    // 1. 先用 Moodle 官方 API 進行手動報名 (這是唯一能完美觸發 Moodle 所有快取與事件更新的正軌作法)
    global $moodle_url, $moodle_token;

    $enrolments = [
        [
            'roleid' => 5, // student
            'userid' => $moodle_uid,
            'courseid' => $course_id
        ]
    ];

    $moodle_api_result = call_moodle($moodle_url, $moodle_token, 'enrol_manual_enrol_users', ['enrolments' => $enrolments]);

    if (isset($moodle_api_result['exception'])) {
        throw new Exception('報名失敗：' . ($moodle_api_result['message'] ?? '未知錯誤'));
    }

    // 2. API 報名成功並清除了 Moodle 底層 MUC 快取後，我們再進資料庫把它「偷天換日」改成「自行選課 (self)」
    $moodle_db_name = 'moodle';
    $mconn = new mysqli($db_host, $db_user, $db_pass, $moodle_db_name);

    if (!$mconn->connect_error) {
        $mconn->set_charset('utf8mb4');

        // 取得該課程的 self (自行選課) 實例 ID
        $stmt = $mconn->prepare("SELECT id FROM mdl_enrol WHERE courseid = ? AND enrol = 'self' LIMIT 1");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $self_enrol_id = (int) $res->fetch_assoc()['id'];
            $stmt->close();

            // 取得剛剛 API 產生的 manual 實例 ID
            $stmt = $mconn->prepare("SELECT id FROM mdl_enrol WHERE courseid = ? AND enrol = 'manual' LIMIT 1");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $manual_res = $stmt->get_result();

            if ($manual_res->num_rows > 0) {
                $manual_enrol_id = (int) $manual_res->fetch_assoc()['id'];
                $stmt->close();

                // 偷天換日 1：將 mdl_user_enrolments 中的 enrolid 從 manual 換成 self
                $stmt = $mconn->prepare("UPDATE mdl_user_enrolments SET enrolid = ? WHERE userid = ? AND enrolid = ?");
                $stmt->bind_param("iii", $self_enrol_id, $moodle_uid, $manual_enrol_id);
                $stmt->execute();
                $stmt->close();

                // 偷天換日 2：更新 mdl_role_assignments，將 component 換成 enrol_self，itemid 換成 self_enrol_id
                $stmt = $mconn->prepare("SELECT id FROM mdl_context WHERE contextlevel = 50 AND instanceid = ?");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $ctx_res = $stmt->get_result();

                if ($ctx_res->num_rows > 0) {
                    $context_id = (int) $ctx_res->fetch_assoc()['id'];
                    $stmt->close();

                    $stmt = $mconn->prepare("UPDATE mdl_role_assignments SET component = 'enrol_self', itemid = ? WHERE userid = ? AND contextid = ? AND component = 'enrol_manual'");
                    $stmt->bind_param("iii", $self_enrol_id, $moodle_uid, $context_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt->close();
                }
            } else {
                $stmt->close();
            }
        } else {
            $stmt->close();
        }
        $mconn->close();
    }

    // 報名成功後，寫入 course_visibility，讓 Moodle 端的 local 插件（如 local_course_visibility）可以放行該學生進入隱藏課程
    $stmt = $conn->prepare("INSERT IGNORE INTO course_visibility (course_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $course_id, $moodle_uid); // course_visibility 的 user_id 依賴 moodle_uid
    $stmt->execute();
    $stmt->close();

    // 清除快取
    unset($_SESSION['moodle_cache']);

    echo json_encode(['success' => true, 'message' => '報名成功']);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>