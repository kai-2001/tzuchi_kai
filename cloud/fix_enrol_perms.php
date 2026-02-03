<?php
define('CLI_SCRIPT', true);
require 'moodle/config.php';
global $CFG;

$conn = new mysqli($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname);
if ($conn->connect_error)
    die("DB Error");

$sid = 2; // Service ID
$func = 'enrol_manual_enrol_users';

// Check existing
$check = $conn->query("SELECT id FROM {$CFG->prefix}external_services_functions WHERE externalserviceid = $sid AND functionname = '$func'");
if ($check->num_rows > 0) {
    echo "Permission '$func' already exists.\n";
} else {
    // Insert
    $sql = "INSERT INTO {$CFG->prefix}external_services_functions (externalserviceid, functionname) VALUES ($sid, '$func')";
    if ($conn->query($sql)) {
        echo "Successfully added '$func' to Service ID $sid.\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}
