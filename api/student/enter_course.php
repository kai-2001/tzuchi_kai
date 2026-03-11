<?php
/**
 * 課程存取 API
 * 
 * 前端進入課程前呼叫此 API。
 * 直接查 Moodle 確認該學員是否已報名，如果已報名且課程隱藏，自動設為可見。
 */

require_once '../../includes/config.php';
require_once '../../includes/moodle_api.php';
header('Content-Type: application/json; charset=utf-8');

session_start();

$course_id = (int)($_GET['id'] ?? 0);
$moodle_user_id = $_SESSION['moodle_user_id'] ?? 0;

// 讀完 session 立刻釋放鎖，避免阻塞其他請求（SSO、清快取等）
session_write_close();

if ($course_id <= 0 || $moodle_user_id <= 0) {
    echo json_encode(['success' => true, 'action' => 'skip']);
    exit;
}

try {
    // 1. 查 Moodle 課程是否隱藏
    $course_info = call_moodle($moodle_url, $moodle_token, 'core_course_get_courses', [
        'options' => ['ids' => [$course_id]]
    ]);
    
    if (isset($course_info['exception']) || empty($course_info)) {
        echo json_encode(['success' => true, 'action' => 'skip']);
        exit;
    }
    
    $current_visible = $course_info[0]['visible'] ?? 1;
    
    // 課程已經是可見的，不用做什麼
    if ($current_visible == 1) {
        echo json_encode(['success' => true, 'action' => 'already_visible']);
        exit;
    }
    
    // 2. 課程是隱藏的 → 查該學員是否已報名
    $enrolled_users = call_moodle($moodle_url, $moodle_token, 'core_enrol_get_enrolled_users', [
        'courseid' => $course_id
    ]);
    
    $is_enrolled = false;
    if (!isset($enrolled_users['exception']) && is_array($enrolled_users)) {
        foreach ($enrolled_users as $user) {
            if (($user['id'] ?? 0) == $moodle_user_id) {
                $is_enrolled = true;
                break;
            }
        }
    }
    
    // 3. 已報名 + 課程隱藏 → 自動設為可見
    if ($is_enrolled) {
        call_moodle($moodle_url, $moodle_token, 'core_course_update_courses', [
            'courses' => [[
                'id' => $course_id,
                'visible' => 1
            ]]
        ]);
        error_log("enter_course: Auto-unhid course $course_id for enrolled user $moodle_user_id");
        echo json_encode(['success' => true, 'action' => 'unhidden']);
        exit;
    }
    
    echo json_encode(['success' => true, 'action' => 'not_enrolled']);
    
} catch (Exception $e) {
    error_log("enter_course API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
