<?php
/**
 * 變更成員角色
 * api/hospital_admin/change_role.php
 */
session_start();
// 開啟緩衝區，防止有些 include 檔或 hook 輸出額外訊息導致 JSON 格式錯誤
ob_start();

require_once '../../includes/config.php';
require_once '../../includes/functions.php';  // call_moodle needed
require_once '../../includes/moodle_api.php'; // moodle_assign_role needed
header('Content-Type: application/json; charset=utf-8');

// 權限檢查 - 允許 hospital_admin 或系統管理員
$is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

if (!$is_hospital_admin && !$is_admin) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => '權限不足']));
}

$institution = $_SESSION['institution'] ?? '';

$id = (int) ($_POST['id'] ?? 0);
$new_role = $_POST['role'] ?? '';

if ($id <= 0) {
    die(json_encode(['success' => false, 'error' => '無效的成員 ID']));
}

// 只允許 student 和 coursecreator 互轉
if (!in_array($new_role, ['student', 'coursecreator'])) {
    die(json_encode(['success' => false, 'error' => '無效的角色']));
}

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('資料庫連線失敗');
    }
    $conn->set_charset('utf8mb4');

    // 確認成員存在且可操作
    if (!empty($institution)) {
        // hospital_admin 只能操作自己院區的成員
        $stmt = $conn->prepare("
            SELECT id, username, role FROM users 
            WHERE id = ? AND institution = ? AND role IN ('student', 'coursecreator')
        ");
        $stmt->bind_param("is", $id, $institution);
    } else {
        // 系統管理員可以操作所有成員
        $stmt = $conn->prepare("
            SELECT id, username, role, institution FROM users 
            WHERE id = ? AND role IN ('student', 'coursecreator')
        ");
        $stmt->bind_param("i", $id);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        die(json_encode(['success' => false, 'error' => '成員不存在或無權限操作']));
    }
    $member = $result->fetch_assoc();
    $username = $member['username'];
    $target_institution = $member['institution'];
    $stmt->close();

    // 更新 Portal 資料庫角色
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param("si", $new_role, $id);

    if (!$stmt->execute()) {
        throw new Exception('角色變更失敗: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    // 🚀 同步到 Moodle - 在院區類別下分配或移除 coursecreator 角色
    $category_id = $_SESSION['management_category_id'] ?? 0;

    if ($category_id > 0) {
        if ($new_role === 'coursecreator') {
            moodle_assign_role($username, $category_id, 'coursecreator');
        } else {
            moodle_unassign_role($username, $category_id, 'coursecreator');
        }
    } else {

        error_log("Moodle role sync skipped for $username: no category_id in session");
    }

    // 🚀 同步到 Cohort (補漏網之魚：如果之前沒加進去)
    // 🚀 同步到 Cohort (補漏網之魚：如果之前沒加進去)
    $cohort_id = get_institution_cohort($target_institution);
    if ($cohort_id) {
        moodle_add_cohort_member($username, $cohort_id);
    }

    $role_label = $new_role === 'coursecreator' ? '開課教師' : '學生';

    // ... code before ...

    // 清除前面的任何輸出，確保只回傳 JSON
    if (ob_get_length())
        ob_clean();

    echo json_encode([
        'success' => true,
        'message' => "角色已變更為「{$role_label}」並同步到 Moodle"
    ]);

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    error_log("change_role error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '系統錯誤']);
}
?>