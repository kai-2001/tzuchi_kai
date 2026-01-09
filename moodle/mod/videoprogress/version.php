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
 * Video Progress - 影片進度追蹤模組版本資訊
 *
 * @package    mod_videoprogress
 * @copyright  2024 Tzu Chi Medical Foundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026010900;        // 版本號 (YYYYMMDDXX) - 優化進度儲存、修復 F5 恢復位置、統一按鈕 UI
$plugin->requires  = 2022041900;        // 需要 Moodle 4.0+
$plugin->component = 'mod_videoprogress'; // 完整插件名稱
$plugin->maturity  = MATURITY_STABLE;   // 穩定版本
$plugin->release   = '2.1.0';           // 重大更新：進度即時保存與 UI 優化

