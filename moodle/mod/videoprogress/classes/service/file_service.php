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
 * File Service - 檔案服務
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoprogress\service;

defined('MOODLE_INTERNAL') || die();

/**
 * 檔案服務類別
 */
class file_service {

    /**
     * 儲存上傳的影片檔案
     *
     * @param \stdClass $data 活動資料
     * @param \moodleform|null $mform 表單物件
     * @return void
     */
    public static function save_video_file(\stdClass $data, $mform = null): void {
        global $DB;

        if ($data->videotype !== 'upload') {
            return;
        }

        $context = \context_module::instance($data->coursemodule);

        // 儲存檔案
        $options = ['subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 1, 'accepted_types' => ['video/*', '.zip']];
        file_save_draft_area_files(
            $data->videofile,
            $context->id,
            'mod_videoprogress',
            'video',
            0,
            $options
        );

        // 檢查是否為 ZIP
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_videoprogress', 'video', 0, 'filename', false);
        
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if ($ext === 'zip') {
                $result = zip_service::process_package($context->id, $file);
                if ($result !== true) {
                    \core\notification::error($result);
                } else {
                    // 解壓縮成功，刪除原始 ZIP 以節省空間
                    $fs->delete_area_files($context->id, 'mod_videoprogress', 'video');
                }
                break;
            }
        }
    }

    /**
     * 提供檔案下載（pluginfile 回調）
     *
     * @param \stdClass $course 課程
     * @param \stdClass $cm 課程模組
     * @param \context $context 上下文
     * @param string $filearea 檔案區域
     * @param array $args 參數
     * @param bool $forcedownload 強制下載
     * @param array $options 選項
     * @return bool
     */
    public static function serve_file($course, $cm, $context, string $filearea, array $args, bool $forcedownload, array $options = []): bool {
        if ($context->contextlevel != CONTEXT_MODULE) {
            return false;
        }

        require_login($course, true, $cm);
        require_capability('mod/videoprogress:view', $context);

        $fs = get_file_storage();
        
        $itemid = (int)array_shift($args);
        $filename = array_pop($args);
        
        if (!$args) {
            $filepath = '/';
        } else {
            $filepath = '/' . implode('/', $args) . '/';
        }
        
        $file = $fs->get_file($context->id, 'mod_videoprogress', $filearea, $itemid, $filepath, $filename);

        if (!$file || $file->is_directory()) {
            return false;
        }

        // ZIP 套件需要額外的安全 headers
        if ($filearea === 'package') {
            self::send_package_headers($file);
        }

        send_stored_file($file, 86400, 0, $forcedownload, $options);
        return true;
    }

    /**
     * 發送 package 安全 headers
     */
    private static function send_package_headers(\stored_file $file): void {
        $mime = $file->get_mimetype();
        
        // HTML/JavaScript 需要限制
        if (strpos($mime, 'html') !== false || strpos($mime, 'javascript') !== false) {
            header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob:; " .
                   "img-src 'self' data: blob: https:; " .
                   "media-src 'self' data: blob: https:; " .
                   "connect-src 'none'");
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
        }
    }

    /**
     * 取得影片檔案 URL
     *
     * @param int $contextid 上下文 ID
     * @return string|null URL 或 null
     */
    public static function get_video_url(int $contextid): ?string {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'mod_videoprogress', 'video', 0, 'filename', false);

        if (empty($files)) {
            return null;
        }

        $file = reset($files);
        return \moodle_url::make_pluginfile_url(
            $contextid, 'mod_videoprogress', 'video', 0,
            $file->get_filepath(), $file->get_filename()
        )->out();
    }
}
