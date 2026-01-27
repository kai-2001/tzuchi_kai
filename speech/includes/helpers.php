<?php
/**
 * Speech Portal - Helper Functions
 * Provides utility functions used across the application
 */

/**
 * Sanitize filename for safe file system operations
 */
function sanitize_filename($filename)
{
    return preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
}

/**
 * Format file size to human-readable format
 */
function format_filesize($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Recursively delete a directory and all its contents
 * 
 * @param string $dir Directory path to delete
 * @return bool Success status
 */
function deleteDirectory($dir)
{
    if (!file_exists($dir)) {
        return true;
    }
    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($dir);
}

/**
 * Parse EverCam config.js file
 * 
 * Expected format:
 * var config = {
 *     "videoFile": "media.mp4",
 *     "duration": 120000,  // milliseconds
 *     "chapters": [...]
 * };
 * 
 * @param string $config_content Content of config.js file
 * @return array|null Parsed configuration array or null on failure
 */
function parse_evercam_config($config_content)
{
    // Extract JSON from: var config = { ... };
    if (preg_match('/var\s+config\s*=\s*(\{.*\})/s', $config_content, $matches)) {
        $json_text = trim($matches[1]);

        // Remove trailing semicolon if exists
        $json_text = rtrim($json_text, ';');

        // Attempt to decode
        $config_data = json_decode($json_text, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $config_data;
        }
    }

    return null;
}

/**
 * Process EverCam ZIP file upload
 * 
 * Handles extraction, validation, and flattening of EverCam ZIP exports.
 * Returns processed file information or throws exception on failure.
 * 
 * @param string $zip_temp_path Path to temporary uploaded ZIP file
 * @param string $file_id Unique identifier for this upload
 * @return array [
 *     'content_path' => string,  // Relative path to video file
 *     'format' => 'evercam',
 *     'metadata' => array|null,  // Parsed config data
 *     'duration' => int          // Video duration in seconds
 * ]
 * @throws Exception On processing failure
 */
function process_evercam_zip($zip_temp_path, $file_id)
{
    $zip = new ZipArchive;

    if ($zip->open($zip_temp_path) !== TRUE) {
        throw new Exception("無法開啟 ZIP 檔案。");
    }

    $extract_dir = UPLOAD_DIR_VIDEOS . $file_id . '/';
    mkdir($extract_dir, 0777, true);

    // ============================================
    // Step 1: Locate config.js
    // ============================================
    $has_config = false;
    $config_path_in_zip = '';
    $zip_prefix = '';

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $zf = $zip->getNameIndex($i);
        if (basename($zf) === 'config.js') {
            $has_config = true;
            $config_path_in_zip = $zf;
            // Determine prefix (in case ZIP has nested folder)
            $dir = dirname($zf);
            $zip_prefix = ($dir === '.') ? '' : $dir . '/';
            break;
        }
    }

    if (!$has_config) {
        $zip->close();
        deleteDirectory($extract_dir);
        throw new Exception("ZIP 檔案中未找到 config.js，這可能不是標準的 EverCam 網頁匯出檔。");
    }

    // ============================================
    // Step 2: Parse config.js to find video filename
    // ============================================
    $config_content = $zip->getFromName($config_path_in_zip);
    $video_filename = '';
    $metadata = null;
    $duration = 0;

    if ($config_content) {
        $config_data = parse_evercam_config($config_content);

        if ($config_data) {
            $metadata = $config_data;

            // Extract video filename
            if (isset($config_data['videoFile'])) {
                $video_filename = $config_data['videoFile'];
            }

            // Extract duration
            if (isset($config_data['duration'])) {
                $duration = (int) ($config_data['duration'] / 1000); // ms to s
            }
        }
    }

    // ============================================
    // Step 3: Verify video file exists
    // ============================================
    if (empty($video_filename)) {
        // Fallback to media.mp4
        $search_target = $zip_prefix . 'media.mp4';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if ($zip->getNameIndex($i) === $search_target) {
                $video_filename = 'media.mp4';
                break;
            }
        }
    }

    if (empty($video_filename)) {
        $zip->close();
        deleteDirectory($extract_dir);
        throw new Exception("找不到影片檔案。ZIP 內容可能不完整。");
    }

    $video_path_in_zip = $zip_prefix . $video_filename;

    // Verify the file actually exists in ZIP
    if ($zip->locateName($video_path_in_zip) === false) {
        $zip->close();
        deleteDirectory($extract_dir);
        throw new Exception("找不到影片檔：$video_path_in_zip (config.js 指定的檔名為 $video_filename, 但 ZIP 中不存在此檔案)");
    }

    // ============================================
    // Step 4: Extract files
    // ============================================
    // Note: Uncompressed file size check can be added here if needed in the future:
    // $zip_stat = $zip->statName($video_path_in_zip);
    // $uncompressed_size = $zip_stat['size'];
    // if ($uncompressed_size > $max_size) { throw new Exception(...); }

    if (!$zip->extractTo($extract_dir, [$config_path_in_zip, $video_path_in_zip])) {
        $zip->close();
        deleteDirectory($extract_dir);

        // Check disk space
        $free_space = disk_free_space($extract_dir);
        $zip_stat = $zip->statName($video_path_in_zip);
        $required_size = $zip_stat['size'];  // Uncompressed size needed

        if ($free_space < $required_size) {
            $free_space_gb = round($free_space / 1024 / 1024 / 1024, 2);
            $required_gb = round($required_size / 1024 / 1024 / 1024, 2);
            throw new Exception(
                "解壓縮失敗：磁碟空間不足。" .
                "需要 {$required_gb}GB，但只剩 {$free_space_gb}GB。請聯繫系統管理員清理磁碟空間。"
            );
        }

        throw new Exception("解壓縮失敗：無法將檔案從 ZIP 中取出。請確認 ZIP 檔案未損壞。");
    }

    $zip->close();

    // ============================================
    // Step 5: Flatten structure if nested
    // ============================================
    if (!empty($zip_prefix)) {
        $full_config_path = $extract_dir . $config_path_in_zip;
        $full_video_path = $extract_dir . $video_path_in_zip;

        // Move files to root of extract_dir
        if (file_exists($full_config_path)) {
            rename($full_config_path, $extract_dir . 'config.js');
        }
        if (file_exists($full_video_path)) {
            rename($full_video_path, $extract_dir . $video_filename);
        }

        // Delete empty nested directory
        $first_dir = explode('/', $zip_prefix)[0];
        $nested_dir = $extract_dir . $first_dir;
        if (is_dir($nested_dir)) {
            deleteDirectory($nested_dir);
        }
    }

    // ============================================
    // Step 6: Final validation
    // ============================================
    if (!file_exists($extract_dir . 'config.js') || !file_exists($extract_dir . $video_filename)) {
        deleteDirectory($extract_dir);
        throw new Exception("檔案解壓縮後驗證失敗。");
    }

    // ============================================
    // Return processing result
    // ============================================
    return [
        'content_path' => 'uploads/videos/' . $file_id . '/' . $video_filename,
        'format' => 'evercam',
        'metadata' => $metadata ? json_encode($metadata) : null,
        'duration' => $duration
    ];
}

/**
 * Redirect with message
 */
function redirect_with_msg($url, $msg, $type = 'success')
{
    $param = ($type === 'success') ? 'msg' : 'error';
    $separator = (strpos($url, '?') !== false) ? '&' : '?';
    header("Location: {$url}{$separator}{$param}=" . urlencode($msg));
    exit;
}
