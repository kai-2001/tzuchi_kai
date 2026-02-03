<?php
define('CLI_SCRIPT', true);
require_once 'moodle/config.php'; // Defines $CFG
require_once 'includes/functions.php';
require_once 'includes/config.php'; // For token

// IDs to delete
$ids = [13, 15];

$params = ['courseids' => $ids];
$resp = call_moodle($moodle_url, $moodle_token, 'core_course_delete_courses', $params);

print_r($resp);
