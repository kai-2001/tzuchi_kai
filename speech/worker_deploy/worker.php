<?php
// worker.php - Background Video Processing Script (Live Progress Version)

function worker_log($message)
{
    echo "[LOG] $message" . PHP_EOL; // Force output to CLI
    $logFile = __DIR__ . '/transcode.log';
    $timestamp = date('Y-m-d H:i:s');
    $fp = fopen($logFile, 'a');
    if ($fp) {
        fwrite($fp, "[$timestamp] $message" . PHP_EOL);
        fclose($fp);
    }
}

// --------------------------------------------------------------------------
// 0. ROBUST LOCKING (PID FILE STRATEGY)
// --------------------------------------------------------------------------
$pidFile = __DIR__ . '/transcode.pid';
if (file_exists($pidFile)) {
    $oldPid = trim(file_get_contents($pidFile));
    // Check if process is actually running on Windows
    $output = [];
    exec("tasklist /FI \"PID eq $oldPid\"", $output);
    // If output contains the PID, it's running
    if (count($output) > 1 && strpos(implode('', $output), $oldPid) !== false) {
        // worker_log("Worker suppression: Instance $oldPid is busy.");
        exit;
    }
}
// Claim Lock
file_put_contents($pidFile, getmypid());

worker_log("Worker started (PID: " . getmypid() . ").");

// CLI Only Check
// CLI Only Check
// if (php_sapi_name() !== 'cli' && !isset($_GET['run'])) {
//     worker_log("Error: CLI access only. SAPI: " . php_sapi_name());
//     exit;
// }

require_once __DIR__ . '/includes/worker_config.php';

$conn = new mysqli(WORKER_DB_HOST, WORKER_DB_USER, WORKER_DB_PASS, WORKER_DB_NAME);
if ($conn->connect_error) {
    worker_log("DB Fail: " . $conn->connect_error);
    exit;
}
$conn->set_charset("utf8mb4");

// --------------------------------------------------------------------------
// HELPER: Run FFmpeg with Live Monitoring
// --------------------------------------------------------------------------
function run_ffmpeg_live($cmd, $conn, $id, $mode_name)
{
    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"]
        // stderr redirected to stdout via 2>&1
    ];

    $process = proc_open($cmd, $descriptors, $pipes);

    if (is_resource($process)) {
        fclose($pipes[0]); // Close stdin

        $stream = $pipes[1];
        stream_set_blocking($stream, 0); // Non-blocking read

        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }

            // Just consume the output to prevent blocking
            // We don't parse or update DB anymore to save resources
            $output = stream_get_contents($stream);

            // Optional: Keep local logging if needed, or discard
            // if ($output) { $buf .= $output; ... }

            usleep(200000); // 0.2s pause
        }

        fclose($stream);
        return proc_close($process);
    }
    return -1;
}

// --------------------------------------------------------------------------
// MAIN LOOP
// --------------------------------------------------------------------------
set_time_limit(0);
ignore_user_abort(true);
$ffmpeg_path = defined('WORKER_FFMPEG_PATH') ? WORKER_FFMPEG_PATH : 'ffmpeg';

$empty_checks = 0;
$max_startup_retries = 3;
$wait_seconds = 3;
$jobs_done = 0;

while (true) {
    // A. Pick Job
    $res = $conn->query("SELECT id, title, content_path, thumbnail_path FROM videos WHERE status = 'pending' ORDER BY id ASC LIMIT 1");

    if (!$res || $res->num_rows === 0) {
        if ($jobs_done > 0)
            break; // Finished batch
        if ($empty_checks < $max_startup_retries) {
            $empty_checks++;
            sleep($wait_seconds);
            continue;
        }
        break;
    }

    $empty_checks = 0;
    $video = $res->fetch_assoc();
    $id = $video['id'];
    $input_relative = $video['content_path'];
    $input_full = WORKER_APP_ROOT . DIRECTORY_SEPARATOR . $input_relative;
    $current_thumb = $video['thumbnail_path'];

    // B. Claim
    $conn->query("UPDATE videos SET status = 'processing', process_msg='正在轉檔中...', updated_at = NOW() WHERE id = $id AND status = 'pending'");
    if ($conn->affected_rows === 0)
        continue;

    // Log Job
    worker_log(">>> Job $id: {$video['title']}");

    if (!file_exists($input_full)) {
        worker_log("File missing: $input_relative (Root: " . WORKER_APP_ROOT . ")");
        $conn->query("UPDATE videos SET status='error', process_msg='File missing' WHERE id=$id");
        $jobs_done++;
        continue;
    }

    $path_info = pathinfo($input_full);
    $output_full = $path_info['dirname'] . '/' . $path_info['filename'] . '_processed.mp4';

    // Commands: Use -nostats -progress pipe:1 for reliable parsing
    $cmd_gpu = sprintf('"%s" -nostats -progress pipe:1 -i "%s" -c:v h264_nvenc -preset p4 -rc vbr -cq 26 -c:a aac -b:a 128k -movflags +faststart -y "%s" 2>&1', $ffmpeg_path, $input_full, $output_full);
    $cmd_cpu = sprintf('"%s" -nostats -progress pipe:1 -i "%s" -c:v libx264 -crf 28 -preset fast -c:a aac -b:a 128k -movflags +faststart -y "%s" 2>&1', $ffmpeg_path, $input_full, $output_full);

    // Run GPU
    worker_log("Starting GPU Transcode...");

    $ret = run_ffmpeg_live($cmd_gpu, $conn, $id, "GPU");

    // CPU Fallback
    if ($ret !== 0) {
        worker_log("GPU Failed ($ret). Switching to CPU...");
        worker_log("CPU Command: $cmd_cpu"); // DEBUG Log

        if (file_exists($output_full))
            @unlink($output_full);

        $ret = run_ffmpeg_live($cmd_cpu, $conn, $id, "CPU");
        worker_log("CPU Run Finished with Ret: $ret"); // DEBUG Log
    }

    // G. Finalize
    if ($ret === 0 && file_exists($output_full)) {
        sleep(1);
        $final_video_path = $input_full;

        // Replace original with processed
        if (@unlink($input_full)) {
            rename($output_full, $input_full);
            // $conn->query("UPDATE videos SET status='ready', process_msg='Done' WHERE id=$id"); 
            // Deferred update until thumbnail check
        } else {
            $new_rel = str_replace(WORKER_APP_ROOT . '/', '', $output_full);
            $new_rel = str_replace(WORKER_APP_ROOT . '\\', '', $new_rel);
            $final_video_path = $output_full;
            $conn->query("UPDATE videos SET content_path='$new_rel' WHERE id=$id");
        }

        // --- Thumbnail Extraction Logic ---
        $thumb_update_sql = "";
        // Check if thumbnail is missing or file doesn't exist
        $thumb_full_path = $current_thumb ? (WORKER_APP_ROOT . DIRECTORY_SEPARATOR . $current_thumb) : "";

        if (empty($current_thumb) || !file_exists($thumb_full_path) || strpos(basename($current_thumb), '_auto') !== false) {
            worker_log("Thumbnail missing or auto-generated. Generating from video...");

            // Define new thumb path
            // Use 'uploads/thumbnails/' structure relative to root
            $thumb_filename = "thumb_{$id}_auto.jpg";
            $thumb_rel_path = "uploads/thumbnails/" . $thumb_filename;
            $thumb_dest_path = WORKER_APP_ROOT . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "thumbnails" . DIRECTORY_SEPARATOR . $thumb_filename;

            // Ensure dir exists
            $thumb_dir = dirname($thumb_dest_path);
            if (!is_dir($thumb_dir))
                mkdir($thumb_dir, 0777, true);

            // Extract frame at 1s
            $cmd_thumb = sprintf('"%s" -y -i "%s" -ss 00:00:01.000 -vframes 1 -q:v 2 "%s" 2>&1', $ffmpeg_path, $final_video_path, $thumb_dest_path);

            // Execute
            exec($cmd_thumb, $thumb_out, $thumb_ret);

            if ($thumb_ret === 0 && file_exists($thumb_dest_path)) {
                $conn->query("UPDATE videos SET thumbnail_path='$thumb_rel_path' WHERE id=$id");
                worker_log("Thumbnail generated: $thumb_rel_path");
            } else {
                worker_log("Thumbnail generation failed.");
            }
        }

        $conn->query("UPDATE videos SET status='ready', process_msg='Done' WHERE id=$id");
        worker_log("Job $id Success.");
    } else {
        $conn->query("UPDATE videos SET status='error', process_msg='Failed' WHERE id=$id");
        worker_log("Job $id Failed.");
    }
    $jobs_done++;
    sleep(1);
}

// Cleanup
@unlink($pidFile);
$conn->close();
?>