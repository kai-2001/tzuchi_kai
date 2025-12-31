<?php
// worker.php - Background Video Processing Script
// Usage: Run via CLI frequently (e.g. Schedule Task every minute, or loop)
// Command: php c:\Apache24\htdocs\speech\worker.php

// Define CLI mode check
if (php_sapi_name() !== 'cli' && !isset($_GET['run'])) {
    die("Must be run from CLI or with ?run key.");
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php'; // Auth usually handles DB init or includes it

// Use global connection from included files
global $conn;

// Verify DB Connection
if (!$conn || $conn->connect_error) {
    // Fallback: Try including root config if speech config didn't provide DB
    if (file_exists(__DIR__ . '/../../includes/config.php')) {
        // require_once __DIR__ . '/../../includes/config.php'; 
        // Logic: Root config might start session, beware cli
    }
    if (!$conn) {
        die("DB Connection failed: " . ($conn ? $conn->connect_error : "Connection object not found"));
    }
}
$conn->set_charset("utf8mb4");

echo "[" . date('Y-m-d H:i:s') . "] Worker started.\n";

// 1. Fetch pending job
$sql = "SELECT * FROM videos WHERE status = 'pending' ORDER BY id ASC LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $video = $result->fetch_assoc();
    $id = $video['id'];
    $input_path = __DIR__ . '/' . $video['content_path'];

    echo "Processing Video ID: $id (" . $video['title'] . ")\n";

    // 2. Mark as processing
    $conn->query("UPDATE videos SET status = 'processing', process_msg = 'Starting transcoding...' WHERE id = $id");

    if (!file_exists($input_path)) {
        $msg = "Input file not found: $input_path";
        echo "Error: $msg\n";
        $conn->query("UPDATE videos SET status = 'error', process_msg = '$msg' WHERE id = $id");
        exit;
    }

    // 3. Prepare FFmpeg command
    // Target: Re-encode to efficient H.264
    // We will create a temp output file first
    $path_info = pathinfo($input_path);
    $output_filename = $path_info['filename'] . '_processed.mp4';
    $output_path = $path_info['dirname'] . '/' . $output_filename;

    // Command: Enforce H.264 + AAC + 128k Bitrate + Faststart
    // Fixes streaming issues and copyright/codec compatibility (Google/Edge)
    $cmd = sprintf(
        '"%s" -i "%s" -c:v libx264 -crf 28 -preset fast -c:a aac -b:a 128k -movflags +faststart -f mp4 -y "%s" 2>&1',
        FFMPEG_PATH,
        $input_path,
        $output_path
    );

    echo "Executing: $cmd\n";
    echo "--------------------------------------------------------\n";

    // Execute with real-time output using proc_open
    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ];

    // Command has 2>&1, so stderr is merged into stdout pipe [1]
    $process = proc_open($cmd, $descriptorspec, $pipes);

    $full_log = "";

    if (is_resource($process)) {
        // Close stdin immediately
        fclose($pipes[0]);

        // Read output
        while (!feof($pipes[1])) {
            $chunk = fgets($pipes[1]);
            if ($chunk) {
                echo $chunk; // Print to console
                $full_log .= $chunk; // Capture for DB log
                flush(); // Force output
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);

        $return_var = proc_close($process);
    } else {
        $return_var = -1;
        echo "Failed to launch process.\n";
    }

    echo "\n--------------------------------------------------------\n";

    // 4. Handle Result
    if ($return_var === 0 && file_exists($output_path)) {
        echo "Transcoding success.\n";

        // Windows File Lock Workaround
        sleep(1);

        // Replace logic
        $source = $output_path;
        $dest = $input_path;

        if (file_exists($dest)) {
            if (!unlink($dest)) {
                echo "Warning: Failed to delete original file (File Locked?). Keeping processed file as alternate.\n";
                // If we can't delete, we can't rename exactly.
                // Fallback: Update DB to point to new file
                $new_rel_path = str_replace(__DIR__ . '/', '', $source);
                // Correct slashes for DB
                $new_rel_path = str_replace('\\', '/', $new_rel_path);

                $conn->query("UPDATE videos SET content_path = '$new_rel_path', status = 'ready', process_msg = 'Transcoding success (Switched file due to lock)' WHERE id = $id");
                exit; // Done
            }
        }

        if (rename($source, $dest)) {
            $conn->query("UPDATE videos SET status = 'ready', process_msg = 'Transcoding completed successfully.' WHERE id = $id");
        } else {
            $conn->query("UPDATE videos SET status = 'error', process_msg = 'Validation success but Rename failed.' WHERE id = $id");
        }

    } else {
        echo "Transcoding failed.\n";
        // $log = implode("\n", array_slice($output, -10)); // Old way
        // New way: Take last 1000 chars of full log
        $log = substr($full_log, -1000);
        $safe_log = $conn->real_escape_string($log);
        $conn->query("UPDATE videos SET status = 'error', process_msg = 'FFmpeg failed:\n$safe_log' WHERE id = $id");
    }

} else {
    echo "No pending jobs.\n";
}

$conn->close();
?>