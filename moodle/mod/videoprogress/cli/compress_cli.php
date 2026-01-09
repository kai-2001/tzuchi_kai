<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CLI 腳本用於壓縮單一影片檔案
 *
 * 此腳本設計為在後台程序中執行
 * 使壓縮不會阻檔上傳請求。
 *
 * 用法：php compress_cli.php --contextid=123 --fileid=456
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/videoprogress/classes/compression_queue.php');

// 解析命令列參數
list($options, $unrecognized) = cli_get_params(
    ['contextid' => null, 'fileid' => null, 'help' => false],
    ['c' => 'contextid', 'f' => 'fileid', 'h' => 'help']
);

if ($options['help'] || empty($options['contextid']) || empty($options['fileid'])) {
    echo "Compress a video file
Usage: php compress_cli.php --contextid=123 --fileid=456

Options:
  -c, --contextid    Context ID
  -f, --fileid       File ID
  -h, --help         Show this help
";
    exit(0);
}

$contextid = (int)$options['contextid'];
$fileid = (int)$options['fileid'];

// 檢查壓縮是否已啟用
$enabled = get_config('mod_videoprogress', 'enablecompression');
if (!$enabled) {
    cli_writeln('Compression is disabled');
    exit(0);
}

// 取得 FFmpeg 路徑
$ffmpegpath = get_config('mod_videoprogress', 'ffmpegpath');
if (empty($ffmpegpath) || !file_exists($ffmpegpath)) {
    cli_writeln('FFmpeg not found at: ' . $ffmpegpath);
    exit(1);
}

// 取得 CRF 值
$crf = get_config('mod_videoprogress', 'compressioncrf');
if (empty($crf)) {
    $crf = '23';
}

// 取得檔案
$fs = get_file_storage();
$file = $fs->get_file_by_id($fileid);

if (!$file || $file->is_directory()) {
    cli_writeln('File not found or is directory');
    exit(1);
}

// 只處理影片檔案
$filename = $file->get_filename();
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$videoExtensions = ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'];

if (!in_array($ext, $videoExtensions)) {
    cli_writeln('Not a video file: ' . $filename);
    exit(0);
}

cli_writeln('Starting compression for ' . $filename);

// 標記為佇列中處理中
videoprogress_queue_processing($fileid);

// 建立暫存目錄
$tempdir = $CFG->tempdir . '/videoprogress_compress';
if (!is_dir($tempdir)) {
    mkdir($tempdir, 0777, true);
}

// 複製檔案到暫存區
$inputpath = $tempdir . '/input_' . $fileid . '.' . $ext;
$outputpath = $tempdir . '/output_' . $fileid . '.mp4';

$file->copy_content_to($inputpath);

// 檢查原始大小
$originalSize = filesize($inputpath);
cli_writeln('Original size: ' . format_bytes($originalSize));

// 50MB 門檻
$minSize = 50 * 1024 * 1024;
if ($originalSize < $minSize) {
    cli_writeln('File too small (' . format_bytes($originalSize) . ' < 50MB), skipping');
    @unlink($inputpath);
    exit(0);
}

// 建立 FFmpeg 命令（包含 CPU 保護）
// 參考 YouTube/Bilibili 等大平台的做法：
// 1. 限制 CPU 線程數 (-threads)
// 2. 低優先級執行 (nice on Linux, start /LOW on Windows)
// 3. 限制並發任務數量
// 4. 監控系統負載

$ffmpegCmd = escapeshellcmd($ffmpegpath) . ' -i ' . escapeshellarg($inputpath)
    . ' -threads 2'  // 限制最多使用 2 個 CPU 核心（YouTube 風格）
    . ' -c:v libx264 -crf ' . escapeshellarg($crf)
    . ' -preset medium'  // medium 平衡速度與壓縮率
    . ' -c:a aac -b:a 128k -movflags +faststart -y '
    . escapeshellarg($outputpath) . ' 2>&1';

// 根據作業系統使用不同的優先級控制
if (PHP_OS_FAMILY === 'Windows') {
    // Windows: 以低優先級執行
    $command = 'start /B /LOW /WAIT ' . $ffmpegCmd;
} else {
    // Linux/Unix: 使用 nice 設定低優先級 (10-19 是較低優先級)
    // nice 值 15 = 低優先級，不會搶佔其他關鍵程序
    $command = 'nice -n 15 ' . $ffmpegCmd;
}

cli_writeln('Executing with CPU protection: nice/low priority, 2 threads');
cli_writeln('Command: ' . $command);

// 檢查系統負載（Linux only）- 如果負載太高就等待
if (PHP_OS_FAMILY !== 'Windows') {
    $loadAvg = sys_getloadavg();
    $cpuCount = (int) shell_exec('nproc') ?: 4;
    $loadThreshold = $cpuCount * 0.8;  // 80% 負載門檻
    
    $waitCount = 0;
    while ($loadAvg[0] > $loadThreshold && $waitCount < 10) {
        cli_writeln('System load high (' . round($loadAvg[0], 2) . ' > ' . $loadThreshold . '), waiting 30s...');
        sleep(30);
        $loadAvg = sys_getloadavg();
        $waitCount++;
    }
    
    if ($waitCount >= 10) {
        cli_writeln('System load still high after 5 minutes, proceeding anyway');
    }
}

$output = [];
$returnCode = 0;
exec($command, $output, $returnCode);

if ($returnCode !== 0) {
    cli_writeln('FFmpeg failed with code ' . $returnCode);
    cli_writeln('Output: ' . implode("\n", array_slice($output, -10)));
    @unlink($inputpath);
    @unlink($outputpath);
    videoprogress_queue_failed($fileid, 'FFmpeg failed with code ' . $returnCode);
    exit(1);
}

// 檢查輸出檔案
if (!file_exists($outputpath)) {
    cli_writeln('Output file not created');
    @unlink($inputpath);
    videoprogress_queue_failed($fileid, 'Output file not created');
    exit(1);
}

$compressedSize = filesize($outputpath);
cli_writeln('Compressed size: ' . format_bytes($compressedSize));

// 只有較小時才取代
if ($compressedSize >= $originalSize) {
    cli_writeln('Compressed file is not smaller, keeping original');
    @unlink($inputpath);
    @unlink($outputpath);
    exit(0);
}

// 計算節省
$saved = $originalSize - $compressedSize;
$percent = round(($saved / $originalSize) * 100, 1);
cli_writeln('Saved ' . format_bytes($saved) . ' (' . $percent . '%)');

// 取代原始檔案
try {
    $filerecord = [
        'contextid' => $file->get_contextid(),
        'component' => $file->get_component(),
        'filearea' => $file->get_filearea(),
        'itemid' => $file->get_itemid(),
        'filepath' => $file->get_filepath(),
        'filename' => pathinfo($filename, PATHINFO_FILENAME) . '.mp4',
    ];

    // 刪除原始檔案
    $file->delete();

    // 儲存壓縮後檔案
    $newfile = $fs->create_file_from_pathname($filerecord, $outputpath);

    // 記錄壓縮結果
    log_compression($contextid, $newfile->get_id(), $filename, $originalSize, $compressedSize, $crf);
    
    // 標記佇列完成
    videoprogress_queue_complete($fileid);

    cli_writeln('Successfully replaced with compressed version');

} catch (Exception $e) {
    cli_writeln('Error replacing file: ' . $e->getMessage());
    videoprogress_queue_failed($fileid, $e->getMessage());
}

// 清理暫存檔
@unlink($inputpath);
@unlink($outputpath);

cli_writeln('Compression completed');
exit(0);

/**
 * 格式化位元組為人類可讀格式
 */
function format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * 記錄壓縮結果到資料庫
 */
function log_compression($contextid, $fileid, $filename, $originalSize, $compressedSize, $crf) {
    global $DB;

    // 確保資料表存在
    $dbman = $DB->get_manager();
    $table = new xmldb_table('videoprogress_compression_log');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('original_size', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('compressed_size', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('saved_size', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('saved_percent', XMLDB_TYPE_NUMBER, '5', null, XMLDB_NOTNULL, null, null, 2);
        $table->add_field('crf', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('contextid_idx', XMLDB_INDEX_NOTUNIQUE, ['contextid']);

        $dbman->create_table($table);
        cli_writeln('Created compression_log table');
    }

    $savedSize = $originalSize - $compressedSize;
    $savedPercent = round(($savedSize / $originalSize) * 100, 2);

    $record = new stdClass();
    $record->contextid = $contextid;
    $record->fileid = $fileid;
    $record->filename = $filename;
    $record->original_size = $originalSize;
    $record->compressed_size = $compressedSize;
    $record->saved_size = $savedSize;
    $record->saved_percent = $savedPercent;
    $record->crf = $crf;
    $record->timecreated = time();

    $DB->insert_record('videoprogress_compression_log', $record);
    cli_writeln('Logged compression result');
}
