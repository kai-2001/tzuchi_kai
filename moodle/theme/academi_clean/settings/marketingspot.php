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
 * Admin settings configuration for Marketing Spot section.
 *
 * @package    theme_academi_clean
 * @copyright  2023 onwards LMSACE Dev Team (http://www.lmsace.com)
 * @author    LMSACE Dev Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

// Marketing Spot.
$temp = new admin_settingpage('theme_academi_clean_marketingspot', get_string('mspotheading', 'theme_academi_clean'));

// Marketing Spot heading.
$name = 'theme_academi_clean_mspotheading';
$heading = get_string('mspotheading', 'theme_academi_clean');
$information = '';
$setting = new admin_setting_heading($name, $heading, $information);
$temp->add($setting);

// Enable or disable option for marketing spot .
$name = 'theme_academi_clean/mspotstatus';
$title = get_string('status', 'theme_academi_clean');
$description = get_string('statusdesc', 'theme_academi_clean');
$default = NO;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$temp->add($setting);

// Marketing Spot Title.
$name = 'theme_academi_clean/mspottitle';
$title = get_string('title', 'theme_academi_clean');
$description = get_string('titledesc', 'theme_academi_clean');
$default = 'lang:aboutus';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// Marketing Spot description.
$name = 'theme_academi_clean/mspotdesc';
$title = get_string('description', 'theme_academi_clean');
$description = get_string('description_desc', 'theme_academi_clean');
$default = 'lang:description_default';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$temp->add($setting);

// Marketing Spot content.
$name = 'theme_academi_clean/mspotcontent';
$title = get_string('content', 'theme_academi_clean');
$description = get_string('content_desc', 'theme_academi_clean');
$default = get_string('mspotdesc', 'theme_academi_clean');
$setting = new admin_setting_confightmleditor($name, $title, $description, $default);
$temp->add($setting);

// Marketing Spot Media.
$name = 'theme_academi_clean/mspotmedia';
$title = get_string('media', 'theme_academi_clean');
$description = get_string('mspotmedia_desc', 'theme_academi_clean');
$default = '<img src="https://res.cloudinary.com/lmsace/image/upload/v1593602097/about-img_rztwgu.jpg">';
$setting = new admin_setting_configstoredfile($name, $title, $description, 'mspotmedia', 0,
                ['accepted_types' => 'web_image']);
$temp->add($setting);
$settings->add($temp);

