<?php
// api/hospital_admin/manage_tags.php
// 標籤管理 API (CRUD)

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/moodle_api.php';
header('Content-Type: application/json; charset=utf-8');

try {
    session_start();

    // 1. 權限檢查
    $is_hospital_admin = $_SESSION['is_hospital_admin'] ?? false;
    $is_sys_admin = $_SESSION['is_admin'] ?? false;
    if (!$is_hospital_admin && !$is_sys_admin) {
        throw new Exception('無權限存取');
    }

    $mgmt_cat_id = $_SESSION['management_category_id'] ?? 0;
    
    // 初始化資料庫連線 (Moodle)
    $moodle_conn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
    if ($moodle_conn->connect_error) throw new Exception("Moodle DB Connection failed");
    $moodle_conn->set_charset("utf8mb4");

    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'list') {
        // 列出所有標籤 (從 mdl_tag 表)
        // 為了簡單起見，列出全系統標籤，但實作上可以考慮過濾
        $sql = "SELECT id, name, rawname FROM mdl_tag ORDER BY name ASC";
        $result = $moodle_conn->query($sql);
        $tags = [];
        while($row = $result->fetch_assoc()) {
            $tags[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $tags]);

    } elseif ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) throw new Exception('標籤名稱不能為空');

        // 檢查標籤是否已存在
        $stmt = $moodle_conn->prepare("SELECT id FROM mdl_tag WHERE name = ? OR rawname = ?");
        $lower_name = mb_strtolower($name);
        $stmt->bind_param("ss", $lower_name, $name);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            throw new Exception('標籤已存在');
        }
        $stmt->close();

        // 建立標籤
        // 在 Moodle 中，標籤通常是與物件關聯時自動產生的
        // 我們這裡使用 Moodle API 來建立標籤會比較安全
        // 如果沒有直接建立標籤的 API，我們可以直接寫入 DB 但需注意欄位
        
        $userid = 2; // Admin or system user
        $tag_coll_id = 1; // Default collection
        $stmt = $moodle_conn->prepare("INSERT INTO mdl_tag (userid, tagcollid, name, rawname, isstandard, flag, timemodified) VALUES (?, ?, ?, ?, 1, 0, ?)");
        $now = time();
        $stmt->bind_param("iissi", $userid, $tag_coll_id, $lower_name, $name, $now);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => '標籤已建立']);
        } else {
            throw new Exception('標籤建立失敗: ' . $stmt->error);
        }
        $stmt->close();

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('無效的 ID');

        // 刪除標籤及其與物件的關聯 (Tag Instances)
        $moodle_conn->query("DELETE FROM mdl_tag_instance WHERE tagid = $id");
        $stmt = $moodle_conn->prepare("DELETE FROM mdl_tag WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => '標籤已刪除']);
        } else {
            throw new Exception('標籤刪除失敗');
        }
        $stmt->close();

    } else {
        throw new Exception('未知的動作');
    }

    $moodle_conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
