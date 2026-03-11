<?php
/**
 * api/student/get_optional_courses.php
 * 取得學生可選修的課程
 * 
 * 根據 course_visibility 表格，回傳該用戶可以看到但尚未報名的課程
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db_connect.php';
header('Content-Type: application/json; charset=utf-8');

try {
    session_start();

    // 確認用戶已登入
    if (!isset($_SESSION['username'])) {
        echo json_encode(['success' => true, 'courses' => []]);
        exit;
    }

    // course_visibility 表存的是 Moodle UID，所以我們需要用 Moodle UID 來查詢
    $moodle_uid = isset($_SESSION['moodle_uid']) ? (int) $_SESSION['moodle_uid'] : null;

    // 如果 moodle_uid 不在直接 session 中，嘗試從快取獲取
    if (!$moodle_uid && isset($_SESSION['moodle_cache']['courses']['moodle_uid'])) {
        $moodle_uid = (int) $_SESSION['moodle_cache']['courses']['moodle_uid'];
    }

    if (!$moodle_uid) {
        // 如果沒有 moodle_uid，返回空陣列
        echo json_encode(['success' => true, 'courses' => [], 'debug' => 'no moodle_uid in session']);
        exit;
    }

    // 先取得學生的 User ID 與相關維度、標籤資訊
    $portal_uid = $_SESSION['user_id'] ?? 0;
    $user_cohorts = [];
    $user_tags = [];

    if ($portal_uid > 0) {
        $stmt = $conn->prepare("
            SELECT cm.cohort_id 
            FROM cohort_members cm 
            WHERE cm.user_id = ?
        ");
        $stmt->bind_param("i", $portal_uid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $user_cohorts[] = (int) $row['cohort_id'];
        }
        $stmt->close();

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

    $visible_course_ids = [];

    // 1. 從新的 course_visibility_rules (動態規則)
    $stmt = $conn->query("SELECT course_id, rule_snapshot FROM course_visibility_rules");
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $cid = (int) $row['course_id'];
            $rules = json_decode($row['rule_snapshot'], true);

            if (is_array($rules)) {
                $filter_groups = $rules['filter_groups'] ?? [];
                $operators = $rules['operators'] ?? [];
                $rule_tags = $rules['tag_ids'] ?? [];

                $tag_matched = empty($rule_tags);
                if (!empty($rule_tags)) {
                    $tag_matched = count(array_intersect($rule_tags, $user_tags)) > 0;
                }

                $group_matched = empty($filter_groups);
                if (!$group_matched) {
                    $overall_group_matched = false;
                    for ($i = 0; $i < count($filter_groups); $i++) {
                        $group = $filter_groups[$i];
                        $current_group_matched = count(array_intersect($group, $user_cohorts)) === count($group);

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

                if ($tag_matched && $group_matched) {
                    $visible_course_ids[] = $cid;
                }
            }
        }
        $stmt->close();
    }

    // 2. 向後相容：舊版建立的指定人員 (從 course_visibility 拿)
    $stmt = $conn->prepare("SELECT course_id FROM course_visibility WHERE user_id = ?");
    $stmt->bind_param("i", $moodle_uid);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $visible_course_ids[] = (int) $row['course_id'];
    }
    $stmt->close();

    $visible_course_ids = array_unique($visible_course_ids);

    if (empty($visible_course_ids)) {
        echo json_encode(['success' => true, 'courses' => []]);
        exit;
    }

    // 取得用戶已報名的課程（使用 Moodle UID）
    $enrolled_course_ids = [];
    if ($moodle_uid) {
        $enrolled_courses = call_moodle($moodle_url, $moodle_token, 'core_enrol_get_users_courses', [
            'userid' => $moodle_uid
        ]);

        if (!isset($enrolled_courses['exception']) && is_array($enrolled_courses)) {
            foreach ($enrolled_courses as $course) {
                $enrolled_course_ids[] = (int) $course['id'];
            }
        }
    }

    // 過濾掉已報名的課程
    $optional_course_ids = array_diff($visible_course_ids, $enrolled_course_ids);

    if (empty($optional_course_ids)) {
        echo json_encode(['success' => true, 'courses' => []]);
        exit;
    }

    // 取得課程詳情
    $courses = [];
    foreach ($optional_course_ids as $course_id) {
        $course_info = call_moodle($moodle_url, $moodle_token, 'core_course_get_courses_by_field', [
            'field' => 'id',
            'value' => $course_id
        ]);

        if (!isset($course_info['exception']) && !empty($course_info['courses'])) {
            $c = $course_info['courses'][0];
            $courses[] = [
                'id' => $c['id'],
                'fullname' => $c['fullname'],
                'shortname' => $c['shortname'],
                'summary' => strip_tags($c['summary'] ?? '')
            ];
        }
    }

    echo json_encode(['success' => true, 'courses' => $courses]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>