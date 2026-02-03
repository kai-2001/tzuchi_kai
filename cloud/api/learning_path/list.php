<?php
// api/learning_path/list.php
// Lists available learning paths for the user's hospital (and global ones)

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("DB Error");
    }
    $conn->set_charset('utf8mb4');

    // Get User Hospital
    $user_hospital_id = $_SESSION['hospital_id'] ?? 0;

    // Select Global (hospital_id IS NULL) OR My Hospital
    // AND is_active = 1
    $sql = "SELECT id, name, description FROM learning_paths 
            WHERE is_active = 1 
            AND (hospital_id IS NULL OR hospital_id = ?) 
            ORDER BY created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_hospital_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $paths = [];
    while ($row = $result->fetch_assoc()) {
        $paths[] = $row;
    }

    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'data' => $paths]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
