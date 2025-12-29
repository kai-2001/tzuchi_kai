<?php
require_once 'includes/config.php';
$id = 9;
$res = $conn->query("SELECT content_path FROM videos WHERE id = $id");
$video = $res->fetch_assoc();
$dir = dirname($video['content_path']);
$config_path = __DIR__ . '/' . $dir . '/config.js';

if (file_exists($config_path)) {
    $config_content = file_get_contents($config_path);
    if (preg_match('/var\s+config\s*=\s*(\{.*\})/s', $config_content, $matches)) {
        $json_text = trim($matches[1]);
        $json_text = rtrim($json_text, ';');
        $config_data = json_decode($json_text, true);
        if ($config_data && isset($config_data['index'])) {
            $metadata = json_encode($config_data['index']);
            $duration = (int) ($config_data['duration'] / 1000);

            $stmt = $conn->prepare("UPDATE videos SET format = 'evercam', metadata = ?, duration = ? WHERE id = ?");
            $stmt->bind_param("sii", $metadata, $duration, $id);
            $stmt->execute();
            echo "Repair successful for ID $id\n";
        } else {
            echo "Failed to parse config.js JSON\n";
        }
    } else {
        echo "Regex mismatch on config.js\n";
    }
} else {
    echo "config.js not found at $config_path\n";
}
