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
 * Video Detector Service - 影片偵測服務
 * 
 * 負責偵測各種影片來源類型（Evercam、HTML5、YouTube 等）
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoprogress\service;

defined('MOODLE_INTERNAL') || die();

/**
 * 影片偵測服務類別
 */
class video_detector {

    /** @var string 外部 URL */
    private $externalUrl;
    
    /** @var string 基礎 URL */
    private $baseUrl;
    
    /** @var array 偵測結果 */
    private $result = [
        'is_evercam' => false,
        'use_html5' => false,
        'video_url' => null,
        'duration' => null,
        'chapters' => null,
        'config' => null,
    ];

    /**
     * 建構函數
     * 
     * @param string $externalUrl 外部 URL
     */
    public function __construct(string $externalUrl) {
        $this->externalUrl = $externalUrl;
        $this->baseUrl = $this->calculate_base_url($externalUrl);
    }

    /**
     * 計算基礎 URL
     * 
     * @param string $url 輸入 URL
     * @return string 基礎 URL
     */
    private function calculate_base_url(string $url): string {
        $baseUrl = $url;
        if (preg_match('/index\.html$/i', $url)) {
            $baseUrl = preg_replace('/index\.html$/i', '', $url);
        }
        if (!str_ends_with($baseUrl, '/')) {
            $baseUrl .= '/';
        }
        return $baseUrl;
    }

    /**
     * 執行影片偵測
     * 
     * @return array 偵測結果
     */
    public function detect(): array {
        // 1. 嘗試偵測 Evercam (config.js)
        if ($this->detect_evercam()) {
            return $this->result;
        }

        // 2. 嘗試偵測標準 media.mp4
        if ($this->detect_standard_mp4()) {
            return $this->result;
        }

        // 3. 嘗試通用影片偵測
        if ($this->detect_generic_video()) {
            return $this->result;
        }

        return $this->result;
    }

    /**
     * 偵測 Evercam 格式
     * 
     * @return bool 是否為 Evercam
     */
    private function detect_evercam(): bool {
        $configUrl = $this->baseUrl . 'config.js';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $configUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        $configContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$configContent) {
            return false;
        }

        // 解析 config.js
        if (preg_match('/var\s+config\s*=\s*(\{.+\})/s', $configContent, $matches)) {
            $configJson = json_decode($matches[1], true);
            if ($configJson && isset($configJson['src'][0]['src'])) {
                $videoFilename = $configJson['src'][0]['src'];
                $this->result['is_evercam'] = true;
                $this->result['video_url'] = $this->baseUrl . $videoFilename;
                $this->result['config'] = $configJson;
                
                // 提取章節資訊
                if (isset($configJson['index'])) {
                    $this->result['chapters'] = $configJson['index'];
                }
                
                // 提取時長
                if (isset($configJson['duration'])) {
                    $this->result['duration'] = intval($configJson['duration']);
                }
                
                return true;
            }
        }

        return false;
    }

    /**
     * 偵測標準 media.mp4
     * 
     * @return bool 是否找到
     */
    private function detect_standard_mp4(): bool {
        $guessedMp4 = $this->baseUrl . 'media.mp4';
        
        if ($this->check_url_exists($guessedMp4)) {
            $this->result['is_evercam'] = true;
            $this->result['video_url'] = $guessedMp4;
            return true;
        }

        return false;
    }

    /**
     * 通用影片偵測
     * 
     * @return bool 是否找到
     */
    private function detect_generic_video(): bool {
        $detection = videoprogress_detect_external_video($this->externalUrl);
        
        if ($detection && !empty($detection['videourl'])) {
            $this->result['use_html5'] = true;
            $this->result['video_url'] = $detection['videourl'];
            $this->result['duration'] = $detection['duration'] ?? null;
            return true;
        }

        return false;
    }

    /**
     * 檢查 URL 是否存在
     * 
     * @param string $url URL
     * @return bool 是否存在
     */
    private function check_url_exists(string $url): bool {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200 || $httpCode === 206);
    }

    /**
     * 取得偵測結果
     * 
     * @return array 結果
     */
    public function get_result(): array {
        return $this->result;
    }

    /**
     * 是否為 Evercam
     * 
     * @return bool
     */
    public function is_evercam(): bool {
        return $this->result['is_evercam'];
    }

    /**
     * 是否使用 HTML5 Video
     * 
     * @return bool
     */
    public function use_html5(): bool {
        return $this->result['use_html5'] || $this->result['is_evercam'];
    }

    /**
     * 取得影片 URL
     * 
     * @return string|null
     */
    public function get_video_url(): ?string {
        return $this->result['video_url'];
    }

    /**
     * 取得章節資訊
     * 
     * @return array|null
     */
    public function get_chapters(): ?array {
        return $this->result['chapters'];
    }

    /**
     * 取得基礎 URL
     * 
     * @return string
     */
    public function get_base_url(): string {
        return $this->baseUrl;
    }
}
