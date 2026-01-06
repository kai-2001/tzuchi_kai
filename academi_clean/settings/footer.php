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
 * Admin settings configuration for footer section.
 *
 * @package    theme_academi_clean
 * @copyright  2023 onwards LMSACE Dev Team (http://www.lmsace.com)
 * @author    LMSACE Dev Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

// Footer.
$temp = new admin_settingpage('theme_academi_clean_footer', get_string('footerheading', 'theme_academi_clean'));

// Footer general block heading.
$name = 'theme_academi_clean_footergeneralheading';
$heading = get_string('footerblockgeneral', 'theme_academi_clean');
$information = '';
$setting = new admin_setting_heading($name, $heading, $information);
$temp->add($setting);

// Footer background image file setting.
$name = 'theme_academi_clean/footerbgimg';
$title = get_string('footerbgimg', 'theme_academi_clean');
$description = get_string('footerbgimgdesc', 'theme_academi_clean');
$setting = new admin_setting_configstoredfile($name, $title, $description, 'footerbgimg');
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Footer background Overlay Opacity.
$name = 'theme_academi_clean/footerbgOverlay';
$title = get_string('footerbgOverlay', 'theme_academi_clean');
$description = get_string('footerbgOverlay_desc', 'theme_academi_clean');
$opacity = [];
$opacity = array_combine(range(0, 1, 0.1 ), range(0, 1, 0.1 ));
$setting = new admin_setting_configselect($name, $title, $description, '0.4', $opacity);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Copyright.
$name = 'theme_academi_clean/copyright_footer';
$title = get_string('copyright_footer', 'theme_academi_clean');
$description = '';
$default = get_string('copyright_default', 'theme_academi_clean');
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// Footer Block 1 heading.
$name = 'theme_academi_clean_footerblock1heading';
$heading = get_string('footerblock', 'theme_academi_clean').' 1 ';
$information = '';
$setting = new admin_setting_heading($name, $heading, $information);
$temp->add($setting);

// Footer block 1 status (enable / disable) option.
$name = 'theme_academi_clean/footerb1_status';
$title = get_string('status', 'theme_academi_clean');
$description = get_string('fblock_statusdesc', 'theme_academi_clean');
$default = YES;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$temp->add($setting);

// Footer block 1 title.
$name = 'theme_academi_clean/footerbtitle1';
$title = get_string('title', 'theme_academi_clean');
$description = get_string('footerbtitledesc', 'theme_academi_clean');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// Enable and Disable footer logo.
$name = 'theme_academi_clean/footlogostatus';
$title = get_string('footerenable', 'theme_academi_clean');
$description = '';
$default = YES;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$temp->add($setting);

// Footer Logo file setting.
$name = 'theme_academi_clean/footerlogo';
$title = get_string('footerlogo', 'theme_academi_clean');
$description = get_string('footerlogodesc', 'theme_academi_clean');
$setting = new admin_setting_configstoredfile($name, $title, $description, 'footerlogo');
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Footer content.
$name = 'theme_academi_clean/footnote';
$title = get_string('footnote', 'theme_academi_clean');
$description = get_string('footnotedesc', 'theme_academi_clean');
$default = get_string('footnotedefault', 'theme_academi_clean');
$setting = new admin_setting_confightmleditor($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Footer Block 2 heading.
$name = 'theme_academi_clean_footerblock2heading';
$heading = get_string('footerblock', 'theme_academi_clean').' 2 ';
$information = '';
$setting = new admin_setting_heading($name, $heading, $information);
$temp->add($setting);

// Footer block 2 status (enable / disable) option.
$name = 'theme_academi_clean/footerb2_status';
$title = get_string('status', 'theme_academi_clean');
$description = get_string('fblock_statusdesc', 'theme_academi_clean');
$default = YES;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$temp->add($setting);

// Footer block 2 title.
$name = 'theme_academi_clean/footerbtitle2';
$title = get_string('title', 'theme_academi_clean');
$description = get_string('footerbtitledesc', 'theme_academi_clean');
$default = 'lang:footerbtitle2default';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// INFO Link.
$name = 'theme_academi_clean/infolink';
$title = get_string('infolink', 'theme_academi_clean');
$description = get_string('infolink_desc', 'theme_academi_clean');
$default = get_string('infolinkdefault', 'theme_academi_clean');
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$temp->add($setting);

// Footer Block 3 heading.
$name = 'theme_academi_clean_footerblock3heading';
$heading = get_string('footerblock', 'theme_academi_clean').' 3 ';
$information = '';
$setting = new admin_setting_heading($name, $heading, $information);
$temp->add($setting);

// Footer block 3 status ( enable / diasble) option.
$name = 'theme_academi_clean/footerb3_status';
$title = get_string('status', 'theme_academi_clean');
$description = get_string('fblock_statusdesc', 'theme_academi_clean');
$default = YES;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$temp->add($setting);

// Footer block 3 title.
$name = 'theme_academi_clean/footerbtitle3';
$title = get_string('title', 'theme_academi_clean');
$description = get_string('footerbtitledesc', 'theme_academi_clean');
$default = 'lang:footerbtitle3default';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// Address.
$name = 'theme_academi_clean/address';
$title = get_string('address', 'theme_academi_clean');
$description = '';
$default = get_string('defaultaddress', 'theme_academi_clean');
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// Email ID.
$name = 'theme_academi_clean/emailid';
$title = get_string('emailid', 'theme_academi_clean');
$description = '';
$default = get_string('defaultemailid', 'theme_academi_clean');
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// Phone number.
$name = 'theme_academi_clean/phoneno';
$title = get_string('phoneno', 'theme_academi_clean');
$description = '';
$default = get_string('defaultphoneno', 'theme_academi_clean');
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// Footer Block 4 heading.
$name = 'theme_academi_clean_footerblock4heading';
$heading = get_string('footerblock', 'theme_academi_clean').' 4 ';
$information = get_string('socialmediadesc', 'theme_academi_clean');
$setting = new admin_setting_heading($name, $heading, $information);
$temp->add($setting);

// Footer block 4 status.
$name = 'theme_academi_clean/footerb4_status';
$title = get_string('status', 'theme_academi_clean');
$description = get_string('fblock_statusdesc', 'theme_academi_clean');
$default = YES;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$temp->add($setting);

// Footer block 4 Title.
$name = 'theme_academi_clean/footerbtitle4';
$title = get_string('title', 'theme_academi_clean');
$description = get_string('footerbtitledesc', 'theme_academi_clean');
$default = 'lang:footerbtitle4default';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// Select the number of social media show on the footer.
$name = 'theme_academi_clean/numofsocialmedia';
$title = get_string('numofsocialmedia', 'theme_academi_clean');
$description = get_string('numofsocialmediadesc', 'theme_academi_clean');
$default = 4;
$choices = array_combine( range(1, 8), range(1, 8) );
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$temp->add($setting);

$numofsocialmedia = get_config('theme_academi_clean', 'numofsocialmedia');
for ($f = 1; $f <= $numofsocialmedia; $f++) {

    // Social media heading.
    $name = 'theme_academi_clean_socialmeida'.$f;
    $heading = get_string('socialmeida', 'theme_academi_clean', ['socialmedia' => $f]);
    $information = '';
    $setting = new admin_setting_heading($name, $heading, $information);
    $temp->add($setting);

    // Social media status (Enable or disable) option.
    $name = 'theme_academi_clean/socialmedia'.$f.'_status';
    $title = get_string('smediastatus', 'theme_academi_clean');
    $description = get_string('smediastatus_desc', 'theme_academi_clean');
    $default = 1;
    $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
    $temp->add($setting);

    // Social media icon.
    $name = 'theme_academi_clean/socialmedia'.$f.'_icon';
    $title = get_string('icon', 'theme_academi_clean');
    $description = get_string('socialmediaicon_desc', 'theme_academi_clean');
    $default = get_string('socialmediaicon'.$f.'_default', 'theme_academi_clean');
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $temp->add($setting);

    // Social link URL.
    $name = 'theme_academi_clean/socialmedia'.$f.'_url';
    $title = get_string('url', 'theme_academi_clean');
    $description = get_string('socialmediaurl_desc', 'theme_academi_clean');
    $default = get_string('socialmediaurl'.$f.'_default', 'theme_academi_clean');
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $temp->add($setting);

    // Social link icon color.
    $name = 'theme_academi_clean/socialmedia'.$f.'_iconcolor';
    $title = get_string('iconcolor', 'theme_academi_clean');
    $description = get_string('socialmediaiconcolor_desc', 'theme_academi_clean');
    $default = get_string('socialmediaiconcolor'.$f.'_default', 'theme_academi_clean');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $temp->add($setting);
}
$settings->add($temp);

