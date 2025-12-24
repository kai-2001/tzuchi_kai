<?php
require_once 'includes/config.php';

$username = 'test_user';
$password = 'test_pass';

header('Content-Type: text/plain; charset=utf-8');

function debug_soap_call()
{
    global $username, $password;

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    try {
        echo "Attempting SOAP connection...\n";
        $client = new SoapClient(null, [
            'location' => SOAP_LOCATION,
            'uri' => SOAP_URI,
            'trace' => 1,
            'exceptions' => true,
            'stream_context' => $context
        ]);

        echo "Calling login method...\n";
        $result = $client->login($username, md5($password));
        echo "Result received: " . var_export($result, true) . "\n";

    } catch (SoapFault $e) {
        echo "SOAP Fault Exception:\n";
        echo "Code: " . $e->faultcode . "\n";
        echo "String: " . $e->faultstring . "\n";
        if (isset($client)) {
            echo "\n--- Last Request ---\n";
            echo $client->__getLastRequest() . "\n";
            echo "\n--- Last Response ---\n";
            echo $client->__getLastResponse() . "\n";
        }
    } catch (Exception $e) {
        echo "General Exception: " . $e->getMessage() . "\n";
        echo "Trace:\n" . $e->getTraceAsString() . "\n";
    }
}

debug_soap_call();
?>