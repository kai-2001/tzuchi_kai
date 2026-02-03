<?php
// api/learning_path/update.php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$is_hospital_admin = isset($_SESSION['is_hospital_admin']) && $_SESSION['is_hospital_admin'];

if (!$is_admin && !$is_hospital_admin) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input)
        throw new Exception("Invalid JSON");

    $id = $input['id'] ?? 0;
    if (!$id)
        throw new Exception("ID required");

    $name = $input['name'] ?? '';
    $desc = $input['description'] ?? '';
    $policy = $input['enroll_policy'] ?? 'open';
    $rules = isset($input['rules']) ? json_encode($input['rules']) : NULL;

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error)
        throw new Exception("DB Connection Error");
    $conn->set_charset('utf8mb4');

    // Permission Check: Ensure user owns this package (or is sys admin)
    // If hospital admin, can only update if learning_paths.hospital_id == session.hospital_id
    if ($is_hospital_admin) {
        $check = $conn->prepare("SELECT hospital_id FROM learning_paths WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $res = $check->get_result();
        if ($row = $res->fetch_assoc()) {
            if ($row['hospital_id'] != $_SESSION['hospital_id']) {
                throw new Exception("You do not have permission to edit this package.");
            }
        } else {
            throw new Exception("Package not found");
        }
        $check->close();
    }

    $stmt = $conn->prepare("UPDATE learning_paths SET name=?, description=?, enroll_policy=?, rules=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("ssssi", $name, $desc, $policy, $rules, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Updated successfully']);
    } else {
        throw new Exception("Update failed: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>