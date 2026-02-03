<?php
// debug_test_contextid.php
// Test fetching assignments using the URL-derived contextid (132)

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Force define call_moodle if missing (sanity check)
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

$context_id = 132; // From User Screenshot

echo "Testing assignments for Context ID: $context_id\n";

$params = [
    'contextid' => $context_id
];

$assignments = call_moodle($moodle_url, $moodle_token, 'core_role_get_role_assignments', $params);

if (!$assignments) {
    echo "Error: No response or empty.\n";
} else {
    if (isset($assignments['exception'])) {
        echo "Exception: " . $assignments['message'] . "\n";
    } else {
        echo "Success! Count: " . count($assignments) . "\n";
        print_r($assignments);
    }
}
