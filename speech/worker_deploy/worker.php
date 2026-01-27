<?php
// worker.php - Background Video Processing Script (Security Hardened Version)

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

require_once __DIR__ . '/includes/worker_config.php';

$conn = new mysqli(WORKER_DB_HOST, WORKER_DB_USER, WORKER_DB_PASS, WORKER_DB_NAME);
if ($conn->connect_error) {
    worker_log("DB Fail: " . $conn->connect_error);
    exit;
}
$conn->set_charset("utf8mb4");

// --------------------------------------------------------------------------
// HELPER: Validate Path Security
// --------------------------------------------------------------------------
function validate_path($relative_path, $base_root)
{
    $full_path = $base_root . DIRECTORY_SEPARATOR . $relative_path;
    $real_path = realpath($full_path);

    // Check if path exists and is within allowed directory
    if ($real_path === false || strpos($real_path, $base_root) !== 0) {
        return false;
    }

    return $real_path;
}

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
    // A. Pick Job (Using Prepared Statement)
    $stmt = $conn->prepare("SELECT id, title, content_path, thumbnail_path FROM videos WHERE status = 'pending' ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();

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
    $id = (int) $video['id']; // Ensure integer
    $input_relative = $video['content_path'];
    $current_thumb = $video['thumbnail_path'];

    // B. Validate Path Security
    $input_full = validate_path($input_relative, WORKER_APP_ROOT);
    if ($input_full === false) {
        worker_log("Invalid or unsafe path: $input_relative");
        $stmt_err = $conn->prepare("UPDATE videos SET status=?, process_msg=? WHERE id=?");
        $status = 'error';
        $msg = 'Invalid file path';
        $stmt_err->bind_param("ssi", $status, $msg, $id);
        $stmt_err->execute();
        $jobs_done++;
        continue;
    }

    // C. Claim Job (Using Prepared Statement)
    $stmt_claim = $conn->prepare("UPDATE videos SET status=?, process_msg=?, updated_at=NOW() WHERE id=? AND status='pending'");
    $status_proc = 'processing';
    $msg_proc = '正在轉檔中...';
    $stmt_claim->bind_param("ssi", $status_proc, $msg_proc, $id);
    $stmt_claim->execute();

    if ($stmt_claim->affected_rows === 0)
        continue;

    // Log Job
    worker_log(">>> Job $id: {$video['title']}");

    if (!file_exists($input_full)) {
        worker_log("File missing: $input_relative (Root: " . WORKER_APP_ROOT . ")");
        $stmt_err = $conn->prepare("UPDATE videos SET status=?, process_msg=? WHERE id=?");
        $status = 'error';
        $msg = 'File missing';
        $stmt_err->bind_param("ssi", $status, $msg, $id);
        $stmt_err->execute();
        $jobs_done++;
        continue;
    }

    $path_info = pathinfo($input_full);
    $output_full = $path_info['dirname'] . '/' . $path_info['filename'] . '_processed.mp4';

    // D. Properly escape shell arguments
    $ffmpeg_esc = escapeshellarg($ffmpeg_path);
    $input_esc = escapeshellarg($input_full);
    $output_esc = escapeshellarg($output_full);

    // Commands: Use -nostats -progress pipe:1 for reliable parsing
    $cmd_gpu = sprintf('%s -nostats -progress pipe:1 -i %s -c:v h264_nvenc -preset p4 -rc vbr -cq 26 -c:a aac -b:a 128k -movflags +faststart -y %s 2>&1', $ffmpeg_esc, $input_esc, $output_esc);
    $cmd_cpu = sprintf('%s -nostats -progress pipe:1 -i %s -c:v libx264 -crf 28 -preset fast -c:a aac -b:a 128k -movflags +faststart -y %s 2>&1', $ffmpeg_esc, $input_esc, $output_esc);

    // E. Run GPU
    worker_log("Starting GPU Transcode...");

    $ret = run_ffmpeg_live($cmd_gpu, $conn, $id, "GPU");

    // F. CPU Fallback
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
        } else {
            $new_rel = str_replace(WORKER_APP_ROOT . '/', '', $output_full);
            $new_rel = str_replace(WORKER_APP_ROOT . '\\', '', $new_rel);
            $final_video_path = $output_full;

            $stmt_update = $conn->prepare("UPDATE videos SET content_path=? WHERE id=?");
            $stmt_update->bind_param("si", $new_rel, $id);
            $stmt_update->execute();
        }

        // --- Thumbnail Extraction Logic ---
        $thumb_update_sql = "";
        // Check if thumbnail is missing or file doesn't exist
        $thumb_full_path = $current_thumb ? (WORKER_APP_ROOT . DIRECTORY_SEPARATOR . $current_thumb) : "";

        if (empty($current_thumb) || !file_exists($thumb_full_path) || strpos(basename($current_thumb), '_auto') !== false) {
            worker_log("Thumbnail missing or auto-generated. Generating from video...");

            // Define new thumb path
            $thumb_filename = "thumb_{$id}_auto.jpg";
            $thumb_rel_path = "uploads/thumbnails/" . $thumb_filename;
            $thumb_dest_path = WORKER_APP_ROOT . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "thumbnails" . DIRECTORY_SEPARATOR . $thumb_filename;

            // Ensure dir exists
            $thumb_dir = dirname($thumb_dest_path);
            if (!is_dir($thumb_dir))
                mkdir($thumb_dir, 0777, true);

            // Extract frame at 1s (with proper escaping)
            $thumb_dest_esc = escapeshellarg($thumb_dest_path);
            $final_video_esc = escapeshellarg($final_video_path);
            $cmd_thumb = sprintf('%s -y -i %s -ss 00:00:01.000 -vframes 1 -q:v 2 %s 2>&1', $ffmpeg_esc, $final_video_esc, $thumb_dest_esc);

            // Execute
            exec($cmd_thumb, $thumb_out, $thumb_ret);

            if ($thumb_ret === 0 && file_exists($thumb_dest_path)) {
                $stmt_thumb = $conn->prepare("UPDATE videos SET thumbnail_path=? WHERE id=?");
                $stmt_thumb->bind_param("si", $thumb_rel_path, $id);
                $stmt_thumb->execute();
                worker_log("Thumbnail generated: $thumb_rel_path");
            } else {
                worker_log("Thumbnail generation failed.");
            }
        }

        $stmt_done = $conn->prepare("UPDATE videos SET status=?, process_msg=? WHERE id=?");
        $status_ready = 'ready';
        $msg_done = 'Done';
        $stmt_done->bind_param("ssi", $status_ready, $msg_done, $id);
        $stmt_done->execute();
        worker_log("Job $id Success.");
    } else {
        $stmt_fail = $conn->prepare("UPDATE videos SET status=?, process_msg=? WHERE id=?");
        $status_err = 'error';
        $msg_fail = 'Failed';
        $stmt_fail->bind_param("ssi", $status_err, $msg_fail, $id);
        $stmt_fail->execute();
        worker_log("Job $id Failed.");
    }
    $jobs_done++;
    sleep(1);
}

// Cleanup
@unlink($pidFile);
$conn->close();
?>