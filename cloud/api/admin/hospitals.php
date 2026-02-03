<?php
/**
 * 醫院管理 API
 * api/admin/hospitals.php
 * 
 * GET: 取得醫院列表
 * POST: 新增醫院
 * PUT: 更新醫院
 * DELETE: 刪除/停用醫院
 */
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// 權限檢查 - 只有系統管理員可以管理醫院
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$username = $_SESSION['username'] ?? '';

// GET 不需要管理員權限，其他操作需要
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !$is_admin) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => '權限不足，需要系統管理員']));
}

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('資料庫連線失敗');
    }
    $conn->set_charset('utf8mb4');

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // 取得醫院列表
            $include_inactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === '1';
            $where = $include_inactive ? '' : 'WHERE is_active = 1';

            $result = $conn->query("
                SELECT id, code, name, moodle_category_id, is_active, display_order 
                FROM hospitals 
                $where
                ORDER BY display_order, id
            ");

            $hospitals = [];
            while ($row = $result->fetch_assoc()) {
                $hospitals[] = $row;
            }

            echo json_encode(['success' => true, 'data' => $hospitals]);
            break;

        case 'POST':
            // 新增醫院
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            $code = trim($data['code'] ?? '');
            $name = trim($data['name'] ?? '');
            $moodle_category_id = !empty($data['moodle_category_id']) ? (int) $data['moodle_category_id'] : null;
            $display_order = (int) ($data['display_order'] ?? 0);

            if (empty($name)) {
                throw new Exception('醫院名稱為必填');
            }

            // 檢查代碼是否重複
            if (!empty($code)) {
                $stmt = $conn->prepare("SELECT id FROM hospitals WHERE code = ?");
                $stmt->bind_param("s", $code);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('醫院代碼已存在');
                }
                $stmt->close();
            }

            $stmt = $conn->prepare("
                INSERT INTO hospitals (code, name, moodle_category_id, display_order) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("ssii", $code, $name, $moodle_category_id, $display_order);

            if (!$stmt->execute()) {
                throw new Exception('新增失敗: ' . $stmt->error);
            }

            $new_id = $conn->insert_id;
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => '醫院已新增',
                'id' => $new_id
            ]);
            break;

        case 'PUT':
            // 更新醫院
            $data = json_decode(file_get_contents('php://input'), true);

            $id = (int) ($data['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('無效的醫院 ID');
            }

            $updates = [];
            $params = [];
            $types = '';

            if (isset($data['code'])) {
                $updates[] = "code = ?";
                $params[] = trim($data['code']);
                $types .= 's';
            }
            if (isset($data['name'])) {
                $updates[] = "name = ?";
                $params[] = trim($data['name']);
                $types .= 's';
            }
            if (isset($data['moodle_category_id'])) {
                $updates[] = "moodle_category_id = ?";
                $params[] = (int) $data['moodle_category_id'];
                $types .= 'i';
            }
            if (isset($data['display_order'])) {
                $updates[] = "display_order = ?";
                $params[] = (int) $data['display_order'];
                $types .= 'i';
            }
            if (isset($data['is_active'])) {
                $updates[] = "is_active = ?";
                $params[] = (int) $data['is_active'];
                $types .= 'i';
            }

            if (empty($updates)) {
                throw new Exception('沒有要更新的欄位');
            }

            $params[] = $id;
            $types .= 'i';

            $sql = "UPDATE hospitals SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if (!$stmt->execute()) {
                throw new Exception('更新失敗: ' . $stmt->error);
            }
            $stmt->close();

            echo json_encode(['success' => true, 'message' => '醫院已更新']);
            break;

        case 'DELETE':
            // 停用醫院（軟刪除）
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int) ($data['id'] ?? $_GET['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('無效的醫院 ID');
            }

            // 檢查是否有使用者關聯
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE hospital_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($result['cnt'] > 0) {
                // 有使用者，改為停用
                $stmt = $conn->prepare("UPDATE hospitals SET is_active = 0 WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => true, 'message' => '醫院已停用（仍有 ' . $result['cnt'] . ' 位使用者）']);
            } else {
                // 沒有使用者，可以直接刪除
                $stmt = $conn->prepare("DELETE FROM hospitals WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => true, 'message' => '醫院已刪除']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => '不支援的請求方法']);
    }

    $conn->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>