<?php
/**
 * SOAP Response Debugger
 * Use this script to intercept and log the actual data structure returned by the LMS.
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Set credentials for testing
$test_user = '00'; // Please replace with a real account
$test_pass = '00'; // Please replace with the real password

header('Content-Type: text/plain; charset=utf-8');

echo "--- SOAP Response Debugging ---\n";
echo "Attempting to login as: $test_user\n\n";

try {
    // 1. Setup SOAP Client
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $client = new SoapClient(null, [
        'location' => SOAP_LOCATION,
        'uri' => SOAP_URI,
        'trace' => 1,
        'exceptions' => true,
        'stream_context' => $context
    ]);

    // 2. Call Login
    $result = $client->login($test_user, md5($test_pass));

    echo "--- Raw Result ---\n";
    var_dump($result);

    echo "\n--- Object/Array Structure ---\n";
    if (is_object($result) || is_array($result)) {
        print_r($result);
    } else {
        echo "Result is not an object/array (Type: " . gettype($result) . ", Value: " . var_export($result, true) . ")\n";
    }

    echo "\n--- Last SOAP Response (XML) ---\n";
    echo $client->__getLastResponse();

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
