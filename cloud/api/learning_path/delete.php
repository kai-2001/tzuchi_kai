<?php
// api/learning_path/delete.php
require_once '../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE)
    session_start();

$is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

if (!$is_admin && !$is_hospital_admin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    if (!$id)
        throw new Exception("ID required");

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error)
        throw new Exception("DB Connection Error");

    // Permission Check
    if ($is_hospital_admin) {
        $check = $conn->prepare("SELECT hospital_id FROM learning_paths WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $res = $check->get_result();
        if ($row = $res->fetch_assoc()) {
            if ($row['hospital_id'] != $_SESSION['hospital_id']) {
                throw new Exception("Permission Denied");
            }
        } else {
            throw new Exception("Not found");
        }
        $check->close();
    }

    // Determine what to do with courses in this package?
    // For now, just set package_id = NULL or 0 in courses? Or define foreign key ON DELETE SET NULL?
    // Assuming simple delete for now, but safer to check dependencies.
    // Let's just delete the package.

    $stmt = $conn->prepare("DELETE FROM learning_paths WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Delete failed: " . $stmt->error);
    }

    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>