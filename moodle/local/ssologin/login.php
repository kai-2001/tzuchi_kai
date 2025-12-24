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
 * Handles SSO login requests.
 *
 * @package    local_ssologin
 * @copyright  2025 Richard Guedes  - Instituto de Defesa Cibernética (IDCiber)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/authlib.php');
require_once(__DIR__ . '/locallib.php');

// 注意：不能用 require_login()，因為這是讓未登入用戶登入的頁面

$secret = get_config('local_ssologin', 'secretkey');
$tokenexpire = get_config('local_ssologin', 'tokenexpire');

$encdata = required_param('data', PARAM_RAW);
$signature = required_param('sig', PARAM_ALPHANUMEXT);

$data = local_ssologin_decrypt($encdata, $secret);
$payload = json_decode($data, true);

if (!local_ssologin_verify_token($data, $signature, $secret)) {
    throw new moodle_exception('invalidtoken', 'local_ssologin');
}

if (time() - $payload['timestamp'] > $tokenexpire) {
    throw new moodle_exception('invalidtoken', 'local_ssologin');
}

$username = $payload['username'];

if ($user = $DB->get_record('user', ['username' => $username, 'deleted' => 0])) {
    complete_user_login($user);
    local_ssologin_log_attempt('success', $user->id, $username);

    // Check for wantsurl
    $wantsurl = optional_param('wantsurl', '', PARAM_URL);
    if (!empty($wantsurl)) {
        redirect(new moodle_url($wantsurl));
    } else {
        redirect(new moodle_url('/'));
    }
} else {
    local_ssologin_log_attempt('fail', 0, $username);
    throw new moodle_exception('loginfailure', 'local_ssologin', '', $username);
}
