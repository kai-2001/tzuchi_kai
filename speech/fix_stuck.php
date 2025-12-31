<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

echo "Checking for stuck jobs...\n";

// Find processing videos
$result = $conn->query("SELECT * FROM videos WHERE status = 'processing'");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $path = __DIR__ . '/' . $row['content_path'];
        $processed_path = str_replace('.mp4', '_processed.mp4', $path);

        echo "Checking ID $id: $path... ";

        if (file_exists($processed_path)) {
            echo "Found processed file! Finishing job.\n";
            // Attempt switch
            $new_rel = str_replace('.mp4', '_processed.mp4', $row['content_path']);
            // Update DB to point to processed file (easiest fix for stuck state)
            $conn->query("UPDATE videos SET content_path = '$new_rel', status = 'ready', process_msg = 'Recovered from stuck state.' WHERE id = $id");
            echo "Updated DB to use processed file.\n";

            // Try cleanup old file
            @unlink($path);
        } else {
            echo "No processed file found. Resetting to pending.\n";
            $conn->query("UPDATE videos SET status = 'pending', process_msg = 'Reset from stuck state.' WHERE id = $id");
        }
    }
} else {
    echo "No stuck videos found.\n";
}
echo "Done.\n";
?>