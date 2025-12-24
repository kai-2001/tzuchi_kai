<?php
$urls = [
    'https://lms.tzuchi.com.tw/tzuchi/webservice/user/svr.php',
    'http://lms.tzuchi.com.tw/tzuchi/webservice/user/svr.php',
    'https://lms.tzuchi.com.tw/webservice/user/svr.php',
    'http://lms.tzuchi.com.tw/webservice/user/svr.php',
    'https://lms.tzuchi.com.tw/tzuchi/webservice/user/',
    'https://lms.tzuchi.com.tw/tzuchi/',
];

header('Content-Type: text/plain; charset=utf-8');

foreach ($urls as $url) {
    echo "Testing $url ... ";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "HTTP CODE: $code\n";
    curl_close($ch);
}
?>