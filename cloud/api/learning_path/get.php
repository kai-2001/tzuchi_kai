<?php
// api/learning_path/get.php
require_once '../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE)
    session_start();

try {
    $id = $_GET['id'] ?? 0;
    if (!$id)
        throw new Exception("ID required");

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset('utf8mb4');

    $stmt = $conn->prepare("SELECT id, name, description, hospital_id, enroll_policy, rules FROM learning_paths WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        // Decode rules if they are JSON string (depends on DB driver, usually returns string)
        if ($row['rules'] && is_string($row['rules'])) {
            $row['rules'] = json_decode($row['rules'], true);
        }
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        throw new Exception("Not found");
    }

    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>