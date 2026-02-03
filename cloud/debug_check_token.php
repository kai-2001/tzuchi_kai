<?php
define('CLI_SCRIPT', true);
require 'moodle/config.php';
global $CFG;

$token = '758a2fbbc57ae5ef9f1724d462cbe7e1';

$conn = new mysqli($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname);
if ($conn->connect_error)
    die("DB Connection Error");

// 1. Get Service ID
$sql = "SELECT externalserviceid FROM {$CFG->prefix}external_tokens WHERE token = '$token'";
$res = $conn->query($sql);
if ($res->num_rows === 0) {
    die("Token not found in DB\n");
}
$row = $res->fetch_assoc();
$sid = $row['externalserviceid'];
echo "Token belongs to Service ID: $sid\n";

// 2. Check Function
$sql2 = "SELECT * FROM {$CFG->prefix}external_services_functions 
         WHERE externalserviceid = $sid 
         AND functionname = 'core_course_create_courses'";
$res2 = $conn->query($sql2);

if ($res2->num_rows > 0) {
    echo "Function 'core_course_create_courses' IS enabled.\n";
} else {
    echo "Function 'core_course_create_courses' is NOT enabled.\n";
}
