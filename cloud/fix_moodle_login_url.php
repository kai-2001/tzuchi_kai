<?php
define('CLI_SCRIPT', true);
require 'moodle/config.php';
global $CFG;

$conn = new mysqli($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname);
$new_url = 'http://kai/cloud/index.php';
$stmt = $conn->prepare("UPDATE {$CFG->prefix}config SET value = ? WHERE name = 'alternateloginurl'");
$stmt->bind_param("s", $new_url);
$stmt->execute();
echo "Updated Alternate Login URL to: $new_url\n";
