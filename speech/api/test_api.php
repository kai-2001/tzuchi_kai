<?php
// Test API internal encoding
$url = 'http://localhost/speech/api/v1/videos.php?limit=1';
$json = file_get_contents($url);
$data = json_decode($json, true);

echo "--- RAW JSON ---\n";
echo substr($json, 0, 500) . "...\n";

echo "\n--- DECODED TITLE ---\n";
if (isset($data['data'][0]['title'])) {
    echo "Title: " . $data['data'][0]['title'] . "\n";
    echo "Hex: " . bin2hex($data['data'][0]['title']) . "\n";
} else {
    echo "Failed to decode title.\n";
}
?>