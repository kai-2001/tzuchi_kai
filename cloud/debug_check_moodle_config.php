<?php
define('CLI_SCRIPT', true);
require 'moodle/config.php';
global $CFG;

$conn = new mysqli($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname);
$res = $conn->query("SELECT name, value FROM {$CFG->prefix}config WHERE name = 'alternateloginurl'");
if ($row = $res->fetch_assoc()) {
    echo "Alternate Login URL: " . $row['value'] . "\n";
} else {
    echo "Alternate Login URL is NOT set.\n";
}
