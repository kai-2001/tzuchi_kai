<?php
/**
 * 列出院區成員
 * api/hospital_admin/list_members.php
 * 
 * 🚀 改用 user_attributes 的 hospital 屬性來查詢成員
 */
session_start();
require_once '../../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

// 權限檢查 - 允許 hospital_admin 或系統管理員
$is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

if (!$is_hospital_admin && !$is_admin) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => '權限不足']));
}

// 取得登入者的院區 ID（從 Session，由 set_session_from_attributes 設定）
$hospital_id = $_SESSION['hospital_id'] ?? null;
$is_super_admin = $is_admin && !$is_hospital_admin; // 系統管理員（非院區管理員）

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('資料庫連線失敗: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');

    $members = [];

    if ($is_super_admin) {
        // 系統管理員可以看到所有成員
        $sql = "
            SELECT u.id, u.username, u.fullname, u.email, u.role, u.institution,
                   COALESCE(h.name, u.institution, '') as hospital_name,
                   h.id as hospital_id
            FROM users u
            LEFT JOIN user_attributes ua ON u.id = ua.user_id
            LEFT JOIN attribute_values h ON ua.attribute_value_id = h.id
            LEFT JOIN attribute_types at ON h.type_id = at.id AND at.code = 'hospital'
            WHERE u.role IN ('student', 'teacherplus', 'coursecreator')
            GROUP BY u.id
            ORDER BY hospital_name ASC, u.fullname ASC, u.username ASC
        ";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception('查詢失敗: ' . $conn->error);
        }

        while ($row = $result->fetch_assoc()) {
            $members[] = [
                'id' => (int) $row['id'],
                'username' => $row['username'],
                'fullname' => $row['fullname'],
                'email' => $row['email'],
                'role' => $row['role'],
                'institution' => $row['hospital_name']
            ];
        }
    } else {
        // 院區管理員只能看到自己院區的成員（用 hospital 屬性查詢）
        if (!$hospital_id) {
            throw new Exception('無法取得院區資訊');
        }

        // 修正：使用 LEFT JOIN 以包含沒有院區屬性的成員 (Orphans)，讓管理員可以認領
        // 這些成員可能是新註冊或因操作失誤而遺失院區屬性
        $sql = "
            SELECT u.id, u.username, u.fullname, u.email, u.role, COALESCE(h.name, '未分配') as hospital_name, h.hospital_id
            FROM users u
            LEFT JOIN (
                SELECT ua.user_id, av.id as hospital_id, av.name
                FROM user_attributes ua
                JOIN attribute_values av ON ua.attribute_value_id = av.id
                JOIN attribute_types at ON av.type_id = at.id
                WHERE at.code = 'hospital'
            ) h ON u.id = h.user_id
            WHERE (h.hospital_id = ? OR h.hospital_id IS NULL)
              AND u.role IN ('student', 'teacherplus', 'coursecreator')
            ORDER BY 
                CASE WHEN h.hospital_id IS NULL THEN 0 ELSE 1 END, -- 未分配的排在最前面
                u.fullname ASC, 
                u.username ASC
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('準備查詢失敗: ' . $conn->error);
        }
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $members[] = [
                'id' => (int) $row['id'],
                'username' => $row['username'],
                'fullname' => $row['fullname'],
                'email' => $row['email'],
                'role' => $row['role'],
                'institution' => $row['hospital_name'],
                'hospital_id' => $row['hospital_id'] ? (int) $row['hospital_id'] : null
            ];
        }
        $stmt->close();
    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'data' => $members,
        'total' => count($members)
    ]);

} catch (Exception $e) {
    error_log("list_members error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '系統錯誤: ' . $e->getMessage()]);
}
?>