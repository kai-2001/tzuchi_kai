<?php
/**
 * 偵測外部網頁中的影片 URL
 *
 * 此檔案用於解析外部網頁，找出實際的影片檔案 URL
 * 可以作為 AJAX API 直接呼叫，也可以被其他檔案 require_once 來使用其中的函數
 *
 * @package    mod_videoprogress
 * @copyright  2025 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// [Note] 此檔案僅提供函數定義，供 locallib.php 使用
// 不再作為獨立的 AJAX 端點（已由 video_detector 服務取代）

/**
 * 取得影片時長（秒）
 * 使用 cURL 下載影片的部分資料來解析 MP4 metadata
 *
 * @param string $videoUrl 影片 URL
 * @return int|null 時長（秒），失敗時回傳 null
 */
function videoprogress_get_video_duration($videoUrl) {
    // 方法 1: 使用 ffprobe（如果有安裝）
    if (function_exists('shell_exec')) {
        $escapedUrl = escapeshellarg($videoUrl);
        $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $escapedUrl 2>/dev/null";
        $output = @shell_exec($cmd);
        if ($output && is_numeric(trim($output))) {
            return (int) round(floatval(trim($output)));
        }
    }
    
    // 方法 2: 下載部分檔案解析 MP4 moov atom
    // 先嘗試讀取前 1MB
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $videoUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_RANGE => '0-1048576',
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($data !== false && ($httpCode === 200 || $httpCode === 206)) {
        $duration = videoprogress_parse_mp4_duration($data);
        if ($duration !== null) {
            return $duration;
        }
    }
    
    // 前 1MB 沒找到 moov，嘗試讀取檔案尾端
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $videoUrl,
        CURLOPT_NOBODY => true,
        CURLOPT_HEADER => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    
    curl_exec($ch);
    $fileSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    
    if ($fileSize > 2097152) {
        $startByte = $fileSize - 2097152;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $videoUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_RANGE => $startByte . '-' . ($fileSize - 1),
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        
        $endData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($endData !== false && ($httpCode === 200 || $httpCode === 206)) {
            $duration = videoprogress_parse_mp4_duration($endData);
            if ($duration !== null) {
                return $duration;
            }
        }
    }
    
    return null;
}

/**
 * 從 MP4 資料解析時長
 * 使用字串搜尋方式在資料中找 moov/mvhd atom
 *
 * @param string $data MP4 檔案資料
 * @return int|null 時長（秒）
 */
function videoprogress_parse_mp4_duration($data) {
    $dataLen = strlen($data);
    
    // 直接搜尋 'moov' 字串（不依賴 atom 邊界）
    $moovPos = strpos($data, 'moov');
    if ($moovPos === false) {
        return null;
    }
    
    // 找到 moov 後，在其中搜尋 'mvhd'
    $mvhdPos = strpos($data, 'mvhd', $moovPos);
    if ($mvhdPos === false) {
        return null;
    }
    
    // mvhd 結構: [4 bytes size][4 bytes 'mvhd'][1 byte version][3 bytes flags]...
    // 對於 version 0: [4 bytes creation][4 bytes modification][4 bytes timescale][4 bytes duration]
    // 對於 version 1: [8 bytes creation][8 bytes modification][4 bytes timescale][8 bytes duration]
    
    // mvhd 的 'mvhd' 標籤位於 $mvhdPos，version 在 $mvhdPos + 4
    if ($mvhdPos + 28 >= $dataLen) {
        return null;
    }
    
    $version = ord($data[$mvhdPos + 4]);
    
    if ($version === 0) {
        // version 0: 32-bit values
        // timescale 在 offset 16 (4+1+3+4+4), duration 在 offset 20
        if ($mvhdPos + 24 >= $dataLen) {
            return null;
        }
        $timescale = unpack('N', substr($data, $mvhdPos + 16, 4))[1];
        $duration = unpack('N', substr($data, $mvhdPos + 20, 4))[1];
    } else {
        // version 1: 64-bit values
        // timescale 在 offset 24 (4+1+3+8+8), duration 在 offset 28
        if ($mvhdPos + 36 >= $dataLen) {
            return null;
        }
        $timescale = unpack('N', substr($data, $mvhdPos + 24, 4))[1];
        $durationHigh = unpack('N', substr($data, $mvhdPos + 28, 4))[1];
        $durationLow = unpack('N', substr($data, $mvhdPos + 32, 4))[1];
        $duration = ($durationHigh << 32) | $durationLow;
    }
    
    if ($timescale > 0 && $duration > 0) {
        return (int) round($duration / $timescale);
    }
    
    return null;
}
