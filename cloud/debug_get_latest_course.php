<?php
define('CLI_SCRIPT', true);
// Use manual credentials to avoid include mess
$m_db_host = 'localhost';
$m_db_user = 'root';
$m_db_pass = 'root123';
$m_db_name = 'moodle';
$m_prefix = 'mdl_';

$conn = new mysqli($m_db_host, $m_db_user, $m_db_pass, $m_db_name);
if ($conn->connect_error)
    die("Connect failed: " . $conn->connect_error);

$res = $conn->query("SELECT id, fullname, shortname FROM {$m_prefix}course ORDER BY id DESC LIMIT 1");
if ($row = $res->fetch_assoc()) {
    print_r($row);

    // Check enrolment methods for this course
    $cid = $row['id'];
    echo "\nEnrolment Methods for Course $cid:\n";
    $res2 = $conn->query("SELECT id, enrol, status FROM {$m_prefix}enrol WHERE courseid = $cid");
    while ($row2 = $res2->fetch_assoc()) {
        print_r($row2);
    }
} else {
    echo "No courses found.";
}
