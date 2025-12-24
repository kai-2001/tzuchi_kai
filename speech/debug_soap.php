<?php
require_once 'includes/config.php';

$username = 'test_user'; // Dummy
$password = 'test_pass'; // Dummy

header('Content-Type: text/plain; charset=utf-8');

try {
    echo "Testing SOAP Connection to: " . SOAP_LOCATION . "\n";
    echo "URI: " . SOAP_URI . "\n\n";

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ],
        'http' => [
            'timeout' => 5
        ]
    ]);

    $client = new SoapClient(null, [
        'location' => SOAP_LOCATION,
        'uri' => SOAP_URI,
        'trace' => 1,
        'exceptions' => true,
        'stream_context' => $context
    ]);

    echo "Attempting __soapCall('login')...\n";
    try {
        $result = $client->__soapCall('login', [$username, $password]);
        echo "Result: ";
        var_dump($result);
    } catch (Exception $e) {
        echo "Method 'login' failed: " . $e->getMessage() . "\n";
    }

    echo "\nAttempting direct call \$client->login()...\n";
    try {
        $result = $client->login($username, $password);
        echo "Result: ";
        var_dump($result);
    } catch (Exception $e) {
        echo "Direct call failed: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "SOAP Initialization failed: " . $e->getMessage() . "\n";
}
?>