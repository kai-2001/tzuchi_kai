<?php
/**
 * Shared function to trigger the background worker.
 */
require_once __DIR__ . '/config.php';

if (!function_exists('trigger_remote_worker')) {
    function trigger_remote_worker()
    {
        if (!defined('REMOTE_WORKER_URL') || !defined('WORKER_SECRET_TOKEN')) {
            return false;
        }

        $worker_url = REMOTE_WORKER_URL;
        $secret = WORKER_SECRET_TOKEN;
        $postData = ['token' => $secret];

        // Release session lock before making the request
        // This prevents deadlock if the triggered script also needs session access
        // or if the server has limited concurrency per session.
        if (session_id()) {
            session_write_close();
        }

        $ch = curl_init($worker_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);

        // Use a persistent log for upload/process triggers if needed, currently silent
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
?>