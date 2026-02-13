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
 * Event class for SSO login attempts.
 *
 * @package    local_ssologin
 * @copyright  2025 Richard Guedes  - Instituto de Defesa Cibernética (IDCiber)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ssologin\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered for SSO login attempts.
 *
 * @package    local_ssologin
 */
class sso_login_attempted extends \core\event\base
{

    /**
     * Initialize event data.
     *
     * @return void
     */
    protected function init()
    {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        // 不設定 objecttable，因為此事件不關聯特定資料表
    }

    /**
     * Get the name of the event.
     *
     * @return string The event name.
     */
    public static function get_name()
    {
        return get_string('eventssologinattempted', 'local_ssologin');
    }

    /**
     * Get the description of the event.
     *
     * @return string The event description.
     */
    public function get_description()
    {
        $username = $this->other['username'] ?? 'unknown';
        $status = $this->other['status'] ?? 'unknown';
        return "SSO login attempt for user '{$username}' with status '{$status}'.";
    }

    /**
     * Get the URL related to the event.
     *
     * @return \moodle_url The event URL.
     */
    public function get_url()
    {
        return new \moodle_url('/local/ssologin/login.php');
    }

    /**
     * Custom validation.
     *
     * @return void
     */
    protected function validate_data()
    {
        parent::validate_data();
        if (!isset($this->other['username'])) {
            throw new \coding_exception('The \'username\' value must be set in other.');
        }
        if (!isset($this->other['status'])) {
            throw new \coding_exception('The \'status\' value must be set in other.');
        }
    }

    /**
     * Get other mapping for the event.
     *
     * @return array False as no mapping is required.
     */
    public static function get_other_mapping()
    {
        return [];
    }
}
