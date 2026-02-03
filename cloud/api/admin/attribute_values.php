<?php
/**
 * 屬性值管理 API
 * api/admin/attribute_values.php
 * 
 * GET: 取得屬性值列表（可依類型/醫院篩選）
 * POST: 新增屬性值
 * PUT: 更新屬性值
 * DELETE: 刪除/停用屬性值
 */
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// 權限檢查
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];
$user_hospital_id = $_SESSION['hospital_id'] ?? null;

// GET 允許登入者，其他操作需要管理員權限
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !$is_admin && !$is_hospital_admin) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => '權限不足']));
}

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('資料庫連線失敗');
    }
    $conn->set_charset('utf8mb4');

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // 取得屬性值列表
            $type_id = isset($_GET['type_id']) ? (int) $_GET['type_id'] : null;
            $type_code = $_GET['type_code'] ?? null;

            // Security: Enforce hospital scope if user belongs to a hospital
            // Even if is_admin is true (e.g. debugging or misconfiguration), 
            // if a hospital is assigned, we limit scope to it.
            if ($user_hospital_id) {
                $hospital_id = $user_hospital_id;
            } else {
                $hospital_id = isset($_GET['hospital_id']) ? (int) $_GET['hospital_id'] : null;
            }

            $include_global = !isset($_GET['hospital_only']) || $_GET['hospital_only'] !== '1';

            $conditions = ["av.is_active = 1"];
            $params = [];
            $types = '';

            // 如果傳 type_code，先查 type_id
            if ($type_code && !$type_id) {
                $stmt = $conn->prepare("SELECT id FROM attribute_types WHERE code = ?");
                $stmt->bind_param("s", $type_code);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $type_id = $result ? (int) $result['id'] : null;
                $stmt->close();
            }

            if ($type_id) {
                // Special case: If fetching Department (1), also fetch Units (3) for hierarchy
                if ($type_id == 1) {
                    $conditions[] = "av.type_id IN (1, 3)";
                } else {
                    $conditions[] = "av.type_id = ?";
                    $params[] = $type_id;
                    $types .= 'i';
                }
            }

            if ($hospital_id !== null) {
                if ($include_global) {
                    $conditions[] = "(av.hospital_id = ? OR av.hospital_id IS NULL)";
                } else {
                    $conditions[] = "av.hospital_id = ?";
                }
                $params[] = $hospital_id;
                $types .= 'i';
            }

            $where = implode(" AND ", $conditions);

            // DEBUG LOGGING
            $log_data = "Req by user_hid=" . ($user_hospital_id ?? 'NULL') .
                ", is_ha=" . ($is_hospital_admin ? 1 : 0) . ", is_admin=" . ($is_admin ? 1 : 0) . "\n" .
                "Params: hospital_id=" . ($hospital_id ?? 'NULL') . "\n" .
                "SQL Where: $where\n" .
                "Params List: " . json_encode($params) . "\n";
            file_put_contents('debug_attrs.log', $log_data, FILE_APPEND);

            $sql = "
                SELECT 
                    av.id, av.type_id, av.code, av.name, av.hospital_id, av.parent_id,
                    av.display_order, av.is_active,
                    at.code as type_code, at.name as type_name,
                    h.name as hospital_name
                FROM attribute_values av
                JOIN attribute_types at ON av.type_id = at.id
                LEFT JOIN hospitals h ON av.hospital_id = h.id
                WHERE $where
                ORDER BY av.type_id, av.display_order, av.id
            ";

            if (!empty($params)) {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($sql);
            }

            $values = [];
            while ($row = $result->fetch_assoc()) {
                $values[] = $row;
            }

            echo json_encode(['success' => true, 'data' => $values]);
            break;

        case 'POST':
            // 新增屬性值
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            $type_id = (int) ($data['type_id'] ?? 0);
            $code = trim($data['code'] ?? '');
            $name = trim($data['name'] ?? '');
            $hospital_id = !empty($data['hospital_id']) ? (int) $data['hospital_id'] : null;
            $display_order = (int) ($data['display_order'] ?? 0);

            $parent_id = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;

            // Enforcement: If creating a child (parent_id is set), FORCE type_id = 3 (Unit)
            if (!empty($parent_id)) {
                $type_id = 3; // Unit / Ward
            }

            if ($type_id <= 0) {
                throw new Exception('請選擇屬性類型');
            }
            if (empty($name)) {
                throw new Exception('屬性值名稱為必填');
            }

            // 院區管理員只能新增該院專屬的屬性
            if ($is_hospital_admin && !$is_admin) {
                // 檢查屬性類型是否為 hospital scope
                $stmt = $conn->prepare("SELECT scope FROM attribute_types WHERE id = ?");
                $stmt->bind_param("i", $type_id);
                $stmt->execute();
                $type_info = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                // 允許特例：若有指定 parent_id，則可以新增（視為該院區的子屬性）
                if ($type_info['scope'] !== 'hospital' && empty($parent_id)) {
                    throw new Exception('院區管理員只能新增院區專屬屬性，或在現有屬性下新增子單位');
                }

                $hospital_id = $user_hospital_id;
            }

            // Logic Enhancement: Ensure hospital_id is set for hierarchical units
            // 1. If Parent is Local, Child MUST be Local (Inherit)
            if (!empty($parent_id)) {
                $stmt = $conn->prepare("SELECT hospital_id FROM attribute_values WHERE id = ?");
                $stmt->bind_param("i", $parent_id);
                $stmt->execute();
                $p_res = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($p_res && $p_res['hospital_id']) {
                    $hospital_id = $p_res['hospital_id'];
                }
            }

            // 2. If hospital_id is still NULL, but we have a valid user_hospital_id (even for Admin testing), use it
            // This handles cases like creating "1A" under Global "Department", but scoped to the current Admin's hospital
            if (empty($hospital_id) && !empty($user_hospital_id)) {
                $hospital_id = $user_hospital_id;
            }

            $stmt = $conn->prepare("
                INSERT INTO attribute_values (type_id, code, name, hospital_id, parent_id, display_order) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issiii", $type_id, $code, $name, $hospital_id, $parent_id, $display_order);

            if (!$stmt->execute()) {
                throw new Exception('新增失敗: ' . $stmt->error);
            }

            $new_id = $conn->insert_id;
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => '屬性值已新增',
                'id' => $new_id
            ]);
            break;

        case 'PUT':
            // 更新屬性值
            $data = json_decode(file_get_contents('php://input'), true);

            $id = (int) ($data['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('無效的屬性值 ID');
            }

            // 院區管理員權限檢查
            if ($is_hospital_admin && !$is_admin) {
                $stmt = $conn->prepare("SELECT hospital_id FROM attribute_values WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $attr = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$attr || $attr['hospital_id'] != $user_hospital_id) {
                    throw new Exception('只能編輯本院的屬性值');
                }
            }

            $updates = [];
            $params = [];
            $param_types = '';

            if (isset($data['code'])) {
                $updates[] = "code = ?";
                $params[] = trim($data['code']);
                $param_types .= 's';
            }
            if (isset($data['name'])) {
                $updates[] = "name = ?";
                $params[] = trim($data['name']);
                $param_types .= 's';
            }
            if (isset($data['display_order'])) {
                $updates[] = "display_order = ?";
                $params[] = (int) $data['display_order'];
                $param_types .= 'i';
            }
            if (isset($data['is_active'])) {
                $updates[] = "is_active = ?";
                $params[] = (int) $data['is_active'];
                $param_types .= 'i';
            }
            if (isset($data['parent_id'])) {
                $updates[] = "parent_id = ?";
                $params[] = $data['parent_id'] ? (int) $data['parent_id'] : null;
                $param_types .= 'i';
            }

            if (empty($updates)) {
                throw new Exception('沒有要更新的欄位');
            }

            $params[] = $id;
            $param_types .= 'i';

            $sql = "UPDATE attribute_values SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($param_types, ...$params);

            if (!$stmt->execute()) {
                throw new Exception('更新失敗: ' . $stmt->error);
            }
            $stmt->close();

            echo json_encode(['success' => true, 'message' => '屬性值已更新']);
            break;

        case 'DELETE':
            // 停用屬性值
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int) ($data['id'] ?? $_GET['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('無效的屬性值 ID');
            }

            // 院區管理員權限檢查
            if ($is_hospital_admin && !$is_admin) {
                $stmt = $conn->prepare("SELECT hospital_id FROM attribute_values WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $attr = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$attr || $attr['hospital_id'] != $user_hospital_id) {
                    throw new Exception('只能刪除本院的屬性值');
                }
            }

            // 1. Recursive: Find all descendant IDs (Children, Grandchildren...)
            $all_ids = [$id];
            $queue = [$id];
            while (!empty($queue)) {
                $pid = array_shift($queue);
                // Safe query for children
                $stmt_sub = $conn->prepare("SELECT id FROM attribute_values WHERE parent_id = ?");
                $stmt_sub->bind_param("i", $pid);
                $stmt_sub->execute();
                $res_sub = $stmt_sub->get_result();
                while ($row = $res_sub->fetch_assoc()) {
                    $cid = (int) $row['id'];
                    if (!in_array($cid, $all_ids)) {
                        $all_ids[] = $cid;
                        $queue[] = $cid;
                    }
                }
                $stmt_sub->close();
            }

            // Allow multiple IDs in query
            $ids_str = implode(',', $all_ids);

            // 2. Check usage for ALL target IDs
            $usage = 0;
            if (!empty($all_ids)) {
                $check_sql = "SELECT COUNT(*) as cnt FROM user_attributes WHERE attribute_value_id IN ($ids_str)";
                $chk_res = $conn->query($check_sql);
                if ($chk_res) {
                    $usage = $chk_res->fetch_assoc()['cnt'];
                }
            }

            // 3. Execute Delete (Soft or Hard)
            if ($usage > 0) {
                // Soft delete all if any usage exists
                $update_sql = "UPDATE attribute_values SET is_active = 0 WHERE id IN ($ids_str)";
                if ($conn->query($update_sql)) {
                    echo json_encode(['success' => true, 'message' => "已停用此項目及其所有下層子單位 (共 " . count($all_ids) . " 筆)，因仍有關聯的使用者。"]);
                } else {
                    throw new Exception("停用失敗: " . $conn->error);
                }
            } else {
                // Hard delete all if no usage
                $delete_sql = "DELETE FROM attribute_values WHERE id IN ($ids_str)";
                if ($conn->query($delete_sql)) {
                    echo json_encode(['success' => true, 'message' => "已永久刪除此項目及其所有下層子單位 (共 " . count($all_ids) . " 筆)。"]);
                } else {
                    throw new Exception("刪除失敗: " . $conn->error);
                }
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