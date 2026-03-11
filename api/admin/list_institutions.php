<?php
// api/admin/list_institutions.php
// 列出所有院區

require_once __DIR__ . '/../../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    session_start();

    // 權限檢查
    $is_admin = $_SESSION['is_admin'] ?? false;
    $is_hospital_admin = $_SESSION['is_hospital_admin'] ?? false;
    
    if (!$is_admin && !$is_hospital_admin) {
        throw new Exception('無權限存取');
    }

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) throw new Exception("DB Connection failed");
    $conn->set_charset("utf8mb4");

    // 如果是院區管理員，只回傳他自己的院區
    if ($is_hospital_admin && !$is_admin) {
        $inst_name = $_SESSION['institution'] ?? '';
        $stmt = $conn->prepare("SELECT * FROM institutions WHERE name = ?");
        $stmt->bind_param("s", $inst_name);
    } else {
        // 系統管理員可以看到所有院區
        $stmt = $conn->prepare("SELECT * FROM institutions ORDER BY id");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $institutions = [];
    while ($row = $result->fetch_assoc()) {
        $institutions[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $institutions]);

    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
