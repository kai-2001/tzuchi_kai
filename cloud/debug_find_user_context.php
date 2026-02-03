<?php
// debug_find_user_context.php
// Find user '北 學生33' and check their assignments

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Force define call_moodle if missing
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

echo "Searching for user '北 學生33'...\n";
$params = [
    'criteria' => [['key' => 'fullname', 'value' => '北 學生33']] // 'fullname' might not be valid key for core_user_get_users, strict matching?
    // core_user_get_users criteria: id, lastname, firstname, idnumber, username, email, auth
    // fullname is NOT a criteria.
    // Try 'username' if we can guess it.
    // Screenshot link says: "北 學生33". Usually username is English?
    // Let's try key='email' with '%' (wildcard)? No.
    // 'core_user_get_users' doesn't support fuzzy search well.
    // Use 'core_user_get_users_by_field'? field='fullname'? Not supported.
    // Use 'core_user_search_users'? (query)
];

// Better: core_user_search_users
$search_params = ['query' => '北 學生33'];
$users = call_moodle($moodle_url, $moodle_token, 'core_user_search_users', $search_params);

if (!$users || empty($users['users'])) {
    echo "User not found via search '北 學生33'. Trying simple search 'student'...\n";
    $search_params = ['query' => 'student'];
    $users = call_moodle($moodle_url, $moodle_token, 'core_user_search_users', $search_params);
}

if (!empty($users['users'])) {
    $target_user = $users['users'][0];
    echo "Found User: " . $target_user['fullname'] . " (ID: " . $target_user['id'] . ")\n";

    // Check Assignments
    echo "Fetching assignments for User ID: " . $target_user['id'] . "\n";
    // core_role_get_user_role_assignments(userid)
    // Actually standard API: core_role_get_user_role_assignments does not exist?
    // Usually: core_user_get_user_role_assignments (deprecated?)
    // Let's use: core_role_get_role_assignments with userid filter? NO, invalid param.
    // Let's try: core_webservice_get_site_info (returns user ID, not assignments).

    // Actually, core_role_get_user_roles (contextid, userid)? No.
    // core_user_get_course_user_profiles?

    // Wait, Moodle API is tricky.
    // Let's use `core_enrol_get_users_courses` to see courses? No, this is category assignment.

    // Let's try `core_role_get_user_assignment`? No.
    // Let's try the suspected `core_role_get_role_assignments` BUT passing `userid`.
    // Documentation says `userid` is optional.

    $assign_params = ['userid' => $target_user['id']];
    $assigns = call_moodle($moodle_url, $moodle_token, 'core_role_get_role_assignments', $assign_params);

    if (isset($assigns['exception'])) {
        echo "Assignments Error: " . $assigns['message'] . "\n";
    } else {
        echo "Assignments Found: " . count($assigns) . "\n";
        print_r($assigns);

        // Find Context 132
        foreach ($assigns as $a) {
            if ($a['contextid'] == 132) {
                echo "MATCH! Context 132 found. Role ID: " . $a['roleid'] . "\n";
            }
        }
    }

} else {
    echo "User not found.\n";
    if (isset($users['exception']))
        echo "Search Exception: " . $users['message'];
}
