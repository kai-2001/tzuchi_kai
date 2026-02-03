<?php
// Fetch Moodle Course Categories and Assigned Users (Teachers)

// Prevent noise interfering with JSON
error_reporting(E_ERROR | E_PARSE);
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Clear any output accumulated so far (like BOMs or included whitespace)
ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (!function_exists('call_moodle')) {
    function call_moodle($url, $token, $function, $params)
    {
        $serverurl = $url . '/webservice/rest/server.php' . '?wstoken=' . $token . '&wsfunction=' . $function . '&moodlewsrestformat=json';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $serverurl);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($curl);
        curl_close($curl);
        return json_decode($resp, true);
    }
}

// Helper: Flattened list to Tree
function buildCategoryTree(array $elements, $parentId = 0)
{
    $branch = [];
    foreach ($elements as $element) {
        if ($element['parent'] == $parentId) {
            $children = buildCategoryTree($elements, $element['id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

try {
    // Check Permissions
    if (!isset($_SESSION['is_admin']) && !isset($_SESSION['is_hospital_admin'])) {
        throw new Exception('Insufficient permissions');
    }

    // Establish DB Connection once for both actions
    $m_db_host = 'localhost';
    $m_db_user = 'root';
    $m_db_pass = 'root123';
    $m_db_name = 'moodle';
    $m_prefix = 'mdl_';

    $m_conn = new mysqli($m_db_host, $m_db_user, $m_db_pass, $m_db_name);
    if ($m_conn->connect_error) {
        throw new Exception("Moodle DB Connection Error: " . $m_conn->connect_error);
    }
    $m_conn->set_charset('utf8mb4');

    $action = $_GET['action'] ?? 'tree';

    if ($action === 'tree') {
        // DIRECT SQL for Categories
        $sql = "SELECT id, name, parent FROM {$m_prefix}course_categories ORDER BY sortorder ASC";
        $result = $m_conn->query($sql);

        if (!$result) {
            throw new Exception("DB Error: " . $m_conn->error);
        }

        $cats = [];
        while ($row = $result->fetch_assoc()) {
            $cats[] = $row;
        }

        // Moodle API returns array, we have array. buildCategoryTree expects flat array.
        $tree = buildCategoryTree($cats);
        echo json_encode(['success' => true, 'data' => $tree]);

        // Close connection? we might close later or let PHP handle it.
        // But for clarity let's not close here if we shared connection code logic, 
        // but currently actions are if/else.
        $m_conn->close();

    } elseif ($action === 'users') {
        $cat_id = (int) ($_GET['cat_id'] ?? 0);
        if ($cat_id <= 0)
            throw new Exception('Invalid Category ID');

        // DIRECT SQL APPROACH (Bypassing faulty Web Service)
        // Credentials from cloud/moodle/config.php
        $m_db_host = 'localhost';
        $m_db_user = 'root';
        $m_db_pass = 'root123';
        $m_db_name = 'moodle';
        $m_prefix = 'mdl_';

        // Use output buffering to catch connection errors
        $m_conn = new mysqli($m_db_host, $m_db_user, $m_db_pass, $m_db_name);
        if ($m_conn->connect_error) {
            throw new Exception("Moodle DB Connection Error: " . $m_conn->connect_error);
        }
        $m_conn->set_charset('utf8mb4');

        // 1. Get Context ID for Category (Level 40)
        // contextlevel 40 = CONTEXT_COURSECAT
        $sql_ctx = "SELECT id FROM {$m_prefix}context WHERE contextlevel = 40 AND instanceid = ?";
        $stmt = $m_conn->prepare($sql_ctx);
        $stmt->bind_param("i", $cat_id);
        $stmt->execute();
        $res_ctx = $stmt->get_result();

        if ($res_ctx->num_rows === 0) {
            // Category Context not found. Usually means empty or invalid cat.
            echo json_encode(['success' => true, 'data' => []]);
            $stmt->close();
            $m_conn->close();
            exit;
        }

        $ctx_row = $res_ctx->fetch_assoc();
        $context_id = $ctx_row['id'];
        $stmt->close();

        // 2. Fetch Assignments & Users & Role info directly
        // We join mdl_role_assignments, mdl_user, and mdl_role.
        // We filter for standard staff roles (Manager, Creator, Teacher, EditingTeacher).
        // Standard shortnames: manager, coursecreator, editingteacher, teacher.
        // We exclude 'student' to avoid massive lists if many students are enrolled at category level (rare but possible).

        $sql_users = "
            SELECT u.id, u.username, u.firstname, u.lastname, u.email, r.id as roleid, r.shortname as role_short
            FROM {$m_prefix}role_assignments ra
            JOIN {$m_prefix}user u ON ra.userid = u.id
            JOIN {$m_prefix}role r ON ra.roleid = r.id
            WHERE ra.contextid = ?
            AND r.shortname IN ('manager', 'coursecreator', 'editingteacher', 'teacher')
            ORDER BY r.sortorder ASC, u.lastname ASC
        ";

        $stmt = $m_conn->prepare($sql_users);
        $stmt->bind_param("i", $context_id);
        $stmt->execute();
        $res_users = $stmt->get_result();

        $result_users = [];
        while ($row = $res_users->fetch_assoc()) {
            $result_users[] = [
                'id' => $row['id'],
                'username' => $row['username'],
                'fullname' => $row['lastname'] . ' ' . $row['firstname'],
                'email' => $row['email'],
                'role' => $row['role_short'], // Use shortname for mapping in frontend
                'role_id' => $row['roleid']
            ];
        }

        $stmt->close();
        $m_conn->close();

        // Check for JSON errors
        $json_output = json_encode(['success' => true, 'data' => $result_users]);
        if ($json_output === false) {
            throw new Exception('JSON Encode Error: ' . json_last_error_msg());
        }
        echo $json_output;

    } else {
        throw new Exception('Invalid Action');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
