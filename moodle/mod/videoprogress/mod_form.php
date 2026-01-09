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
 * Video Progress 活動編輯表單
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_videoprogress_mod_form extends moodleform_mod {

    /**
     * 定義表單元素
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // ==========================================
        // 一般設定
        // ==========================================
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // 活動名稱
        $mform->addElement('text', 'name', get_string('name', 'videoprogress'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // 標準說明欄位
        $this->standard_intro_elements();

        // ==========================================
        // 影片設定
        // ==========================================
        $mform->addElement('header', 'videosettings', get_string('videotype', 'videoprogress'));

        // 影片來源類型
        $videotypes = array(
            'youtube' => get_string('videotype_youtube', 'videoprogress'),
            'upload' => get_string('videotype_upload', 'videoprogress'),
            'external' => get_string('videotype_external', 'videoprogress'),
        );
        $mform->addElement('select', 'videotype', get_string('videotype', 'videoprogress'), $videotypes);
        $mform->addHelpButton('videotype', 'videotype', 'videoprogress');
        $mform->setDefault('videotype', 'youtube');

        // YouTube 網址
        $mform->addElement('text', 'videourl', get_string('videourl', 'videoprogress'), array('size' => '80'));
        $mform->setType('videourl', PARAM_URL);
        $mform->addHelpButton('videourl', 'videourl', 'videoprogress');
        $mform->hideIf('videourl', 'videotype', 'neq', 'youtube');

        // 外部網址
        $mform->addElement('text', 'externalurl', get_string('externalurl', 'videoprogress'), array('size' => '80'));
        $mform->setType('externalurl', PARAM_URL);
        $mform->addHelpButton('externalurl', 'externalurl', 'videoprogress');
        $mform->hideIf('externalurl', 'videotype', 'neq', 'external');

        // 影片檔案上傳
        $mform->addElement('filemanager', 'videofile', get_string('videofile', 'videoprogress'), null,
            array(
                'subdirs' => 0,
                'maxfiles' => 1,
                'accepted_types' => array('video', '.zip'),
            )
        );
        $mform->addHelpButton('videofile', 'videofile', 'videoprogress');
        $mform->hideIf('videofile', 'videotype', 'neq', 'upload');
        
        // Evercam ZIP 套件說明
        $mform->addElement('static', 'upload_zip_note', '', 
            '<div class="alert alert-info mt-2" style="padding: 10px 15px;">' .
            get_string('upload_zip_note', 'videoprogress') .
            '</div>'
        );
        $mform->hideIf('upload_zip_note', 'videotype', 'neq', 'upload');

        // 影片長度（隱藏欄位，由系統自動偵測）
        $mform->addElement('hidden', 'videoduration', 0);
        $mform->setType('videoduration', PARAM_INT);

        // ==========================================
        // 完成設定
        // ==========================================
        $mform->addElement('header', 'completionsettings', get_string('completionpercent', 'videoprogress'));

        // 完成門檻百分比（現在也適用於外部網址模式）
        $percentages = array(0 => '0%');
        for ($i = 10; $i <= 100; $i += 10) {
            $percentages[$i] = $i . '%';
        }
        $mform->addElement('select', 'completionpercent', get_string('completionpercent', 'videoprogress'), $percentages);
        $mform->addHelpButton('completionpercent', 'completionpercent', 'videoprogress');
        $mform->setDefault('completionpercent', 0);
        
        // 完成門檻說明
        $mform->addElement('static', 'completionpercent_note', '', 
            '<small style="color: #000;">' . get_string('completionpercent_note', 'videoprogress') . '</small>'
        );

        // 外部網址專用：最少停留秒數
        $mform->addElement('text', 'externalmintime', get_string('externalmintime', 'videoprogress'), array('size' => '10'));
        $mform->setType('externalmintime', PARAM_INT);
        $mform->addHelpButton('externalmintime', 'externalmintime', 'videoprogress');
        $mform->setDefault('externalmintime', 60);
        $mform->hideIf('externalmintime', 'videotype', 'neq', 'external');

        // 外部網址偵測說明
        $mform->addElement('static', 'external_detection_note', '', 
            '<div class="alert alert-info">' .
            get_string('external_detection_note', 'videoprogress') .
            '</div>'
        );
        $mform->hideIf('external_detection_note', 'videotype', 'neq', 'external');

        // 專注模式（切換分頁時暫停）- 現在也適用於外部網址
        $mform->addElement('advcheckbox', 'requirefocus', get_string('requirefocus', 'videoprogress'));
        $mform->addHelpButton('requirefocus', 'requirefocus', 'videoprogress');
        $mform->setDefault('requirefocus', 0);
        // 不再隱藏 - 外部網址如果偵測成功也會有專注模式

        // ==========================================
        // 標準課程模組元素
        // ==========================================
        $this->standard_coursemodule_elements();

        // ==========================================
        // 動作按鈕
        // ==========================================
        $this->add_action_buttons();
    }

    /**
     * 表單驗證
     *
     * @param array $data 表單資料
     * @param array $files 上傳的檔案
     * @return array 錯誤訊息
     */
    public function validation($data, $files) {
        global $USER;
        
        $errors = parent::validation($data, $files);

        // YouTube 必須填網址
        if ($data['videotype'] === 'youtube' && empty($data['videourl'])) {
            $errors['videourl'] = get_string('required');
        }

        // YouTube 網址格式驗證
        if ($data['videotype'] === 'youtube' && !empty($data['videourl'])) {
            if (!preg_match('/(?:youtube\.com|youtu\.be)/', $data['videourl'])) {
                $errors['videourl'] = get_string('error:invalidurl', 'videoprogress');
            }
        }

        // 外部網址必須填
        if ($data['videotype'] === 'external' && empty($data['externalurl'])) {
            $errors['externalurl'] = get_string('required');
        }

        // 上傳模式：檢查 ZIP 檔案是否包含 index.html
        if ($data['videotype'] === 'upload' && !empty($data['videofile'])) {
            $fs = get_file_storage();
            $usercontext = context_user::instance($USER->id);
            
            // 從 draft area 取得上傳的檔案
            $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $data['videofile'], 'id', false);
            $draftfile = reset($draftfiles);
            
            if ($draftfile && strtolower(pathinfo($draftfile->get_filename(), PATHINFO_EXTENSION)) === 'zip') {
                // 檢查 ZIP 內容
                $packer = get_file_packer('application/zip');
                $zipcontents = $draftfile->list_files($packer);
                
                $hasIndexHtml = false;
                if ($zipcontents) {
                    foreach ($zipcontents as $file) {
                        $pathname = $file->pathname ?? '';
                        // 用 explode 取代 basename()，避免 Windows 下 / 分隔符問題
                        $parts = explode('/', rtrim($pathname, '/'));
                        $filename = end($parts);
                        if (strtolower($filename) === 'index.html') {
                            $hasIndexHtml = true;
                            break;
                        }
                    }
                }
                
                if (!$hasIndexHtml) {
                    $errors['videofile'] = 'ZIP 檔案必須包含 index.html 檔案。請確認您的 ZIP 套件結構正確。';
                }
            }
        }

        return $errors;
    }

    /**
     * 載入現有資料到表單
     *
     * @param array $defaultvalues 預設值
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        // 處理已上傳的影片檔案
        if ($this->current->instance) {
            $draftitemid = file_get_submitted_draft_itemid('videofile');
            file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mod_videoprogress',
                'video',
                0,
                array('subdirs' => 0, 'maxfiles' => 1)
            );
            $defaultvalues['videofile'] = $draftitemid;
        }
    }

    /**
     * 表單定義完成後的處理
     * 隱藏標準的活動完成區塊，因為 videoprogress 有自己的完成邏輯
     */
    public function definition_after_data() {
        parent::definition_after_data();

        $mform = $this->_form;

        // 隱藏活動完成區塊（因為我們有自己的完成門檻設定）
        if ($mform->elementExists('activitycompletionheader')) {
            $mform->removeElement('activitycompletionheader');
        }
        if ($mform->elementExists('completion')) {
            $mform->removeElement('completion');
        }
        if ($mform->elementExists('completionview')) {
            $mform->removeElement('completionview');
        }
        if ($mform->elementExists('completionexpected')) {
            $mform->removeElement('completionexpected');
        }
        // Moodle 4.x 的完成元素
        if ($mform->elementExists('completionunlocked')) {
            $mform->removeElement('completionunlocked');
        }
    }

    /**
     * 新增自訂完成規則
     * 這個方法讓 Moodle 知道我們有自訂的完成條件
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        
        // 新增一個隱藏的完成規則元素（永遠為 1）
        $mform->addElement('hidden', 'completionenabled', 1);
        $mform->setType('completionenabled', PARAM_INT);
        
        return array('completionenabled');
    }

    /**
     * 檢查完成規則是否啟用
     */
    public function completion_rule_enabled($data) {
        // completionenabled 永遠是 1，所以規則永遠啟用
        return !empty($data['completionenabled']);
    }
}
