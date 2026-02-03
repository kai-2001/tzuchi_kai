<?php
// debug_moodle_context.php
// Fetch a specific category to see if we get contextid

require_once 'includes/config.php';
require_once 'includes/functions.php'; // ensure call_moodle is available

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

$cat_id = 22; // From screenshot (諮商心理)

echo "Fetching Category ID: $cat_id\n";

$params = [
    'criteria' => [['key' => 'id', 'value' => $cat_id]],
    'addsubcategories' => 0
];

$cats = call_moodle($moodle_url, $moodle_token, 'core_course_get_categories', $params);

if (!$cats) {
    echo "Error: No response or empty.\n";
} else {
    echo "Response Type: " . gettype($cats) . "\n";
    if (isset($cats['exception'])) {
        echo "Exception: " . $cats['message'] . "\n";
    } else {
        echo "Count: " . count($cats) . "\n";
        if (count($cats) > 0) {
            print_r($cats[0]);
        }
    }
}
