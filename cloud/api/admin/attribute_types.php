<?php
/**
 * 屬性類型 API
 * api/admin/attribute_types.php
 * 
 * GET: 取得屬性類型列表
 */
session_start();
require_once '../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception('資料庫連線失敗');
    }
    $conn->set_charset('utf8mb4');

    // 只支援 GET，屬性類型通常不需要動態修改
    $include_inactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === '1';
    $where = $include_inactive ? '' : 'WHERE is_active = 1';

    $result = $conn->query("
        SELECT id, code, name, scope, is_multi_select, display_order 
        FROM attribute_types 
        $where
        ORDER BY display_order, id
    ");

    $types = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_multi_select'] = (bool) $row['is_multi_select'];
        $types[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $types]);
    $conn->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>