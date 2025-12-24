<?php
$url = 'https://lms.tzuchi.com.tw/tzuchi/webservice/user/svr.php';
$payload = '<?xml version="1.0" encoding="UTF-8"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body><login/></SOAP-ENV:Body></SOAP-ENV:Envelope>';

header('Content-Type: text/plain; charset=utf-8');

echo "Testing POST to $url ...\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in output
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: text/xml; charset=utf-8',
    'SOAPAction: "login"'
]);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP CODE: $code\n";
echo "RESPONSE:\n$response\n";
curl_close($ch);
?>