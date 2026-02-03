<?php
// api/course/create.php
// Creates a Moodle course and sets Portal Enrollment Rules

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit;
}

try {
    // 1. Check Permissions
    $is_hospital_admin = isset($_SESSION['is_hospital_admin']) ? $_SESSION['is_hospital_admin'] : false;
    $is_coursecreator = isset($_SESSION['is_coursecreator']) ? $_SESSION['is_coursecreator'] : false;

    if (!$is_hospital_admin && !$is_coursecreator) {
        throw new Exception('Permission denied');
    }

    // 2. Get Input
    $raw_input = file_get_contents('php://input');
    // DEBUG: Log input
    file_put_contents('../../debug_course_create.log', date('Y-m-d H:i:s') . " Raw Input: " . $raw_input . "\n", FILE_APPEND);

    $input = json_decode($raw_input, true);
    if (!$input) {
        file_put_contents('../../debug_course_create.log', "JSON Decode Error: " . json_last_error_msg() . "\n", FILE_APPEND);
        throw new Exception('Invalid JSON input');
    }

    $fullname = trim($input['fullname'] ?? '');
    $shortname = trim($input['shortname'] ?? '');
    $category_id = intval($input['category'] ?? 0);
    $package_id = intval($input['package_id'] ?? 0);
    $package_required = !empty($input['package_required']);
    $rules_data = $input['rules'] ?? null;

    if (empty($fullname) || empty($shortname) || $category_id <= 0) {
        throw new Exception('Missing required fields (Fullname, Shortname, Category)');
    }

    // 3. Connect to Moodle DB
    // AVOID including moodle/config.php because it triggers lib/setup.php and pollution
    // Manually define Moodle DB creds (matched from cloud/moodle/config.php)
    $m_db_host = 'localhost';
    $m_db_user = 'root';
    $m_db_pass = 'root123';
    $m_db_name = 'moodle';
    $m_prefix = 'mdl_';

    $m_conn = new mysqli($m_db_host, $m_db_user, $m_db_pass, $m_db_name);
    if ($m_conn->connect_error) {
        throw new Exception("Moodle DB Connection Error");
    }

    $role_shortname = 'editingteacher';

    $sql_role = "SELECT id FROM {$m_prefix}role WHERE shortname = ?";
    $stmt = $m_conn->prepare($sql_role);
    $stmt->bind_param("s", $role_shortname);
    $stmt->execute();
    $stmt->bind_result($role_id);
    $stmt->fetch();
    $stmt->close();
    $m_conn->close();

    if (!$role_id) {
        throw new Exception("Role '{$role_shortname}' not found in Moodle");
    }

    // 4. Create Course via Moodle API
    $course_params = [
        'courses' => [
            [
                'fullname' => $fullname,
                'shortname' => $shortname,
                'categoryid' => $category_id,
                'format' => 'topics' // Default format
            ]
        ]
    ];

    $resp = call_moodle($moodle_url, $moodle_token, 'core_course_create_courses', $course_params);

    // DEBUG: Log Moodle Response
    file_put_contents('../../debug_course_create.log', date('Y-m-d H:i:s') . " Moodle Response: " . print_r($resp, true) . "\n", FILE_APPEND);

    if (isset($resp['exception'])) {
        throw new Exception("Moodle Error: " . $resp['message']);
    }

    // Response is array of created courses: [{id: 123, shortname: ...}]
    if (empty($resp[0]['id'])) {
        throw new Exception("Failed to create course (No ID returned)");
    }

    $moodle_course_id = $resp[0]['id'];

    // 4b. URGENT FIX: Enable 'manual' enrolment method for this course
    // Retry loop to handle race condition where enrol instances aren't created instantly by Moodle
    $max_retries = 5;
    $retry_count = 0;
    $updated = false;

    // Re-conn to Moodle DB
    $m_conn = new mysqli($m_db_host, $m_db_user, $m_db_pass, $m_db_name);

    if ($m_conn->connect_error) {
        file_put_contents('../../debug_course_create.log', "Re-conn failed: " . $m_conn->connect_error . "\n", FILE_APPEND);
    } else {
        while ($retry_count < $max_retries) {
            // Try to set status=0. 
            // We only care if the row exists. If status is already 0, affected_rows might be 0, but that's fine.
            // Let's check if the row exists first.
            $check_res = $m_conn->query("SELECT id, status FROM {$m_prefix}enrol WHERE courseid = $moodle_course_id AND enrol = 'manual'");
            if ($check_res && $check_res->num_rows > 0) {
                // Row exists, verify/update status
                $row = $check_res->fetch_assoc();
                if ($row['status'] != 0) {
                    $m_conn->query("UPDATE {$m_prefix}enrol SET status = 0 WHERE id = " . $row['id']);
                }
                $updated = true;
                break;
            }

            // Wait 1s and retry
            sleep(1);
            $retry_count++;
        }

        if (!$updated) {
            file_put_contents('../../debug_course_create.log', "Warning: Could not enable manual enrolment for Course $moodle_course_id after retries.\n", FILE_APPEND);
        }
        $m_conn->close();
    }

    // 5. Enroll Creator as Teacher/Manager
    // We need the Moodle User ID of the current user.
    // Assuming $_SESSION['moodle_user_id'] exists? Or 'user_id' in session?
    // Session usually has 'username'. We need to resolve to Moodle ID.
    // Wait, Moodle ID might be in session? GET SSO URL uses it.
    // check get_sso_url.php -> it uses $_SESSION['username'] to look up user.

    // We need to look up Moodle User ID by username (which we have in session)
    // Re-connect to Moodle DB or use Attribute Helper?
    // Easier: We just closed Moodle DB. Re-open is cheap?
    // Or use Moodle API `core_user_get_users_by_field`.

    $user_params = [
        'field' => 'username',
        'values' => [$_SESSION['username']]
    ];
    $user_resp = call_moodle($moodle_url, $moodle_token, 'core_user_get_users_by_field', $user_params);

    if (empty($user_resp[0]['id'])) {
        // Fallback: If verification fails, we can't enroll. But course is created.
        // Log warning?
    } else {
        $moodle_user_id = $user_resp[0]['id'];

        $enrol_params = [
            'enrolments' => [
                [
                    'roleid' => $role_id,
                    'userid' => $moodle_user_id,
                    'courseid' => $moodle_course_id
                ]
            ]
        ];
        call_moodle($moodle_url, $moodle_token, 'enrol_manual_enrol_users', $enrol_params);
    }

    // 6. Save Enrollment Rules (Portal DB)
    // Connect to Portal DB
    $portal_conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $portal_conn->set_charset('utf8mb4');

    /* 
       Table `course_rules`:
       id, moodle_course_id, rule_type (open/dept/custom), rules_json, is_active

       We assume `rules_data` from frontend matches needed structure.
       Frontend sends:
       {
           open_hospital: true/false,
           depts: [id, id],
           conditions: [{attr: 'role', value: 'doctor'}]
       }
    */

    $rule_type = 'custom'; // Default
    $rules_json = json_encode($rules_data);

    $stmt = $portal_conn->prepare("INSERT INTO course_rules (moodle_course_id, rule_type, rules_json) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $moodle_course_id, $rule_type, $rules_json);
    $stmt->execute();
    $stmt->close();

    // 7. Add to Learning Path (Package) if selected
    if ($package_id > 0) {
        $is_req = $package_required ? 1 : 0;
        // Determine ordering: get max order + 1
        $stmt = $portal_conn->prepare("SELECT MAX(display_order) FROM learning_path_courses WHERE path_id = ?");
        $stmt->bind_param("i", $package_id);
        $stmt->execute();
        $stmt->bind_result($max_order);
        $stmt->fetch();
        $stmt->close();

        $new_order = $max_order + 1;

        $stmt = $portal_conn->prepare("INSERT INTO learning_path_courses (path_id, moodle_course_id, display_order, is_required) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiii", $package_id, $moodle_course_id, $new_order, $is_req);
        $stmt->execute();
        $stmt->close();
    }

    $portal_conn->close();

    // 8. Success
    echo json_encode([
        'success' => true,
        'course_id' => $moodle_course_id,
        'redirect_url' => $moodle_url . '/course/view.php?id=' . $moodle_course_id
    ]);

} catch (Exception $e) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
