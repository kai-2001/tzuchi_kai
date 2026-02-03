<?php
define('CLI_SCRIPT', true);
$m_db_host = 'localhost';
$m_db_user = 'root';
$m_db_pass = 'root123';
$m_db_name = 'moodle';
$m_prefix = 'mdl_';

$conn = new mysqli($m_db_host, $m_db_user, $m_db_pass, $m_db_name);
if ($conn->connect_error)
    die("Connect failed");

// Find courses created in last 2 hours (assuming user started testing recently)
// And check enrollment count.
// Moodle created time is timestamp.
$since = time() - 7200;

$sql = "SELECT c.id, c.fullname, c.shortname, c.timecreated,
        (SELECT COUNT(*) FROM {$m_prefix}user_enrolments ue 
         JOIN {$m_prefix}enrol e ON e.id = ue.enrolid 
         WHERE e.courseid = c.id) as user_count
        FROM {$m_prefix}course c
        WHERE c.timecreated > $since
        ORDER BY c.id DESC";

$res = $conn->query($sql);

echo "Found Potential Ghost Courses:\n";
while ($row = $res->fetch_assoc()) {
    echo "ID:{$row['id']}|Users:{$row['user_count']}|Name:{$row['fullname']}\n";
}
