<?php
/**
 * 使用者屬性管理 API
 * api/admin/user_attributes.php
 * 
 * GET: 取得使用者的屬性
 * POST/PUT: 更新使用者的屬性（覆蓋方式）
 */
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// 權限檢查
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];

if (!$is_admin && !$is_hospital_admin) {
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
            // 取得使用者的屬性
            $user_id = (int) ($_GET['user_id'] ?? 0);
            if ($user_id <= 0) {
                throw new Exception('請提供 user_id');
            }

            $sql = "
                SELECT 
                    ua.id, ua.attribute_value_id,
                    av.name as value_name, av.code as value_code,
                    at.id as type_id, at.code as type_code, at.name as type_name
                FROM user_attributes ua
                JOIN attribute_values av ON ua.attribute_value_id = av.id
                JOIN attribute_types at ON av.type_id = at.id
                WHERE ua.user_id = ?
                ORDER BY at.display_order, av.display_order
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $attributes = [];
            while ($row = $result->fetch_assoc()) {
                $type_code = $row['type_code'];
                if (!isset($attributes[$type_code])) {
                    $attributes[$type_code] = [
                        'type_id' => (int) $row['type_id'],
                        'type_name' => $row['type_name'],
                        'values' => []
                    ];
                }
                $attributes[$type_code]['values'][] = [
                    'id' => (int) $row['attribute_value_id'],
                    'name' => $row['value_name'],
                    'code' => $row['value_code']
                ];
            }
            $stmt->close();

            echo json_encode(['success' => true, 'data' => $attributes]);
            break;

        case 'POST':
        case 'PUT':
            // 更新使用者的屬性（覆蓋方式）
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            $user_id = (int) ($data['user_id'] ?? 0);
            $attribute_value_ids = $data['attribute_value_ids'] ?? [];

            if ($user_id <= 0) {
                throw new Exception('請提供 user_id');
            }

            if (!is_array($attribute_value_ids)) {
                throw new Exception('attribute_value_ids 必須是陣列');
            }

            $current_user_id = null;
            // 取得操作者 ID
            if (isset($_SESSION['username'])) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->bind_param("s", $_SESSION['username']);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $current_user_id = $result ? (int) $result['id'] : null;
                $stmt->close();
            }

            // 開始事務
            $conn->begin_transaction();

            try {
                // 刪除現有屬性
                $stmt = $conn->prepare("DELETE FROM user_attributes WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();

                // 插入新屬性
                if (!empty($attribute_value_ids)) {
                    $stmt = $conn->prepare("
                        INSERT INTO user_attributes (user_id, attribute_value_id, assigned_by) 
                        VALUES (?, ?, ?)
                    ");

                    foreach ($attribute_value_ids as $attr_id) {
                        $attr_id = (int) $attr_id;
                        if ($attr_id > 0) {
                            $stmt->bind_param("iii", $user_id, $attr_id, $current_user_id);
                            $stmt->execute();
                        }
                    }
                    $stmt->close();
                }

                $conn->commit();
                echo json_encode([
                    'success' => true,
                    'message' => '使用者屬性已更新',
                    'count' => count($attribute_value_ids)
                ]);

            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
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