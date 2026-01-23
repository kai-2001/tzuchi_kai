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
 * ZIP Service - ZIP 套件服務
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoprogress\service;

defined('MOODLE_INTERNAL') || die();

/**
 * ZIP 套件服務類別
 */
class zip_service {

    /** @var array 允許的副檔名 */
    const ALLOWED_EXTENSIONS = [
        'html', 'htm', 'css', 'js', 'json',
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico',
        'mp4', 'webm', 'mp3', 'wav', 'ogg',
        'woff', 'woff2', 'ttf', 'eot',
        'txt', 'xml', 'vtt', 'srt'
    ];

    /** @var int 最大檔案數 */
    const MAX_FILES = 500;

    /** @var int 最大解壓後大小（500MB） */
    const MAX_UNCOMPRESSED_SIZE = 524288000;

    /**
     * 處理 ZIP 套件
     *
     * @param int $contextid 上下文 ID
     * @param \stored_file $zipfile ZIP 檔案
     * @return bool|string true 成功，失敗則返回錯誤訊息
     */
    public static function process_package(int $contextid, \stored_file $zipfile) {
        // 驗證
        $validation = self::validate($zipfile);
        if ($validation !== true) {
            return $validation;
        }

        // 解壓縮到暫存目錄
        $tempdir = make_temp_directory('videoprogress_zip');
        $packer = get_file_packer('application/zip');
        $result = $packer->extract_to_pathname($zipfile, $tempdir);

        if (!$result) {
            return 'ZIP 解壓縮失敗';
        }

        // 儲存到 Moodle 檔案系統
        $fs = get_file_storage();
        
        // 清除舊的 package 檔案
        $fs->delete_area_files($contextid, 'mod_videoprogress', 'package');

        // 遞迴儲存檔案
        self::save_directory($fs, $contextid, $tempdir, '/');

        // 清理暫存
        fulldelete($tempdir);

        // [優化] 解壓縮後清理非必要檔案
        // 只保留影片、設定檔(資料)與系統識別用的 HTML
        // 嚴格模式：JS 只保留 config 開頭的設定檔，其他如 jquery 都是垃圾
        $keep_extensions = ['mp4', 'webm', 'm4v', 'html', 'htm'];
        
        $extracted_files = $fs->get_area_files($contextid, 'mod_videoprogress', 'package', 0, 'sortorder', false);
        
        foreach ($extracted_files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $filename = $file->get_filename();
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // 特殊規則：JS 檔案只保留 config*.js
            if ($ext === 'js') {
                if (strpos($filename, 'config') !== 0) {
                    $file->delete(); // 刪除 jquery.js, player.js 等無用腳本
                    continue;
                }
            } elseif (!in_array($ext, $keep_extensions)) {
                // 其他非允許的副檔名 (css, png, etc) 全部刪除
                $file->delete();
            }
        }
        
        // 再次驗證 index.html 存在（支援巢狀目錄）
        $indexfile = self::find_index_file($contextid);
        if (!$indexfile) {
            return '解壓後找不到 index.html';
        }

        return true;
    }

    /**
     * 驗證 ZIP 檔案
     *
     * @param \stored_file $zipfile ZIP 檔案
     * @return bool|string true 有效，否則返回錯誤訊息
     */
    private static function validate(\stored_file $zipfile) {
        $packer = get_file_packer('application/zip');
        $files = $zipfile->list_files($packer);

        if (!$files) {
            return 'ZIP 檔案無效或損壞';
        }

        // 檢查檔案數量
        if (count($files) > self::MAX_FILES) {
            return '超過最大檔案數量限制 (' . self::MAX_FILES . ')';
        }

        // 檢查每個檔案
        $totalSize = 0;
        $hasIndex = false;

        foreach ($files as $file) {
            // 路徑安全檢查
            if (strpos($file->pathname, '..') !== false) {
                return '偵測到路徑穿越攻擊';
            }

            // 檢查 index.html（用 explode 取代 basename，避免 Windows 下 / 分隔符問題）
            $parts = explode('/', rtrim($file->pathname ?? '', '/'));
            $filename = end($parts);
            if (strtolower($filename) === 'index.html') {
                $hasIndex = true;
            }

            // 檢查副檔名
            if (!$file->is_directory) {
                $ext = strtolower(pathinfo($file->pathname, PATHINFO_EXTENSION));
                if (!in_array($ext, self::ALLOWED_EXTENSIONS)) {
                    return "不允許的檔案類型: .{$ext}";
                }
                $totalSize += $file->size;
            }
        }

        // 檢查總大小
        if ($totalSize > self::MAX_UNCOMPRESSED_SIZE) {
            return '超過最大解壓縮大小限制';
        }

        // 檢查 index.html
        if (!$hasIndex) {
            return 'ZIP 套件必須包含 index.html';
        }

        return true;
    }

    /**
     * 遞迴儲存目錄
     */
    private static function save_directory(\file_storage $fs, int $contextid, string $basepath, string $filepath): void {
        $fullpath = rtrim($basepath, '/') . $filepath;
        $handle = opendir($fullpath);

        if (!$handle) {
            return;
        }

        while (($item = readdir($handle)) !== false) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itempath = $fullpath . $item;
            $storedpath = $filepath;

            if (is_dir($itempath)) {
                // 遞迴處理子目錄
                self::save_directory($fs, $contextid, $basepath, $filepath . $item . '/');
            } else {
                // 儲存檔案
                $fileinfo = [
                    'contextid' => $contextid,
                    'component' => 'mod_videoprogress',
                    'filearea' => 'package',
                    'itemid' => 0,
                    'filepath' => $storedpath,
                    'filename' => $item
                ];
                $fs->create_file_from_pathname($fileinfo, $itempath);
            }
        }

        closedir($handle);
    }

    /**
     * 檢查是否為 ZIP 套件模式
     *
     * @param int $contextid 上下文 ID
     * @return bool
     */
    public static function is_package(int $contextid): bool {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'mod_videoprogress', 'package', 0, '', false);
        return !empty($files);
    }

    /**
     * 取得套件 index.html URL
     *
     * @param int $contextid 上下文 ID
     * @return string|null
     */
    public static function get_index_url(int $contextid): ?string {
        $file = self::find_index_file($contextid);

        if (!$file) {
            return null;
        }

        return \moodle_url::make_pluginfile_url(
            $contextid, 'mod_videoprogress', 'package', 0, $file->get_filepath(), $file->get_filename()
        )->out();
    }

    /**
     * 取得套件基礎 URL
     *
     * @param int $contextid 上下文 ID
     * @return string|null
     */
    public static function get_base_url(int $contextid): ?string {
        $file = self::find_index_file($contextid);
        $filepath = $file ? $file->get_filepath() : '/';
        
        return \moodle_url::make_pluginfile_url(
            $contextid, 'mod_videoprogress', 'package', 0, $filepath, ''
        )->out();
    }

    /**
     * 讀取 config.js 內容
     *
     * @param int $contextid 上下文 ID
     * @return array|null 設定陣列或 null
     */
    public static function get_config(int $contextid): ?array {
        $fs = get_file_storage();
        
        // 先找到 index.html 的位置，config.js 應該在同一個目錄下
        $indexFile = self::find_index_file($contextid);
        $filepath = $indexFile ? $indexFile->get_filepath() : '/';
        
        $file = $fs->get_file($contextid, 'mod_videoprogress', 'package', 0, $filepath, 'config.js');

        if (!$file) {
            return null;
        }

        $content = $file->get_content();
        if (preg_match('/var\s+config\s*=\s*(\{.+\})/s', $content, $matches)) {
            return json_decode($matches[1], true);
        }

        return null;
    }

    /**
     * 尋找 index.html 檔案（支援巢狀目錄）
     *
     * @param int $contextid 上下文 ID
     * @return \stored_file|null
     */
    public static function find_index_file(int $contextid): ?\stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'mod_videoprogress', 'package', 0, 'filepath, filename', false);

        foreach ($files as $file) {
            if ($file->get_filename() === 'index.html') {
                return $file;
            }
        }

        return null;
    }
}
