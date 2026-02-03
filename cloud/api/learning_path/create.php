<?php
// api/learning_path/create.php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Auth Check (Hospital Admin or Sys Admin)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Simplified Role Check - adjust as needed
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

    $name = $input['name'] ?? '';
    $desc = $input['description'] ?? '';
    $policy = $input['enroll_policy'] ?? 'open';
    $rules = isset($input['rules']) ? json_encode($input['rules']) : NULL;

    // Determine Hospital ID
    $hospital_id = null;
    if ($is_hospital_admin) {
        $hospital_id = $_SESSION['hospital_id'];
    } else {
        // Sys admin might create global (null) or specific. For now assume Global if sys admin.
        // Or if sys admin creates for specific hospital? 
        // Let's stick to: Hospital Admins create for their hospital.
    }

    if (empty($name))
        throw new Exception("Package name is required");

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error)
        throw new Exception("DB Connection Error");
    $conn->set_charset('utf8mb4');

    $stmt = $conn->prepare("INSERT INTO learning_paths (name, description, hospital_id, enroll_policy, rules, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssiss", $name, $desc, $hospital_id, $policy, $rules);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Course Package created']);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>