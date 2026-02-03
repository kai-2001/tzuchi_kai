<?php
define('CLI_SCRIPT', true);
$m_db_host = 'localhost';
$m_db_user = 'root';
$m_db_pass = 'root123';
$m_db_name = 'moodle';
$m_prefix = 'mdl_';
$cid = 17;

$conn = new mysqli($m_db_host, $m_db_user, $m_db_pass, $m_db_name);
$conn->query("UPDATE {$m_prefix}enrol SET status = 0 WHERE courseid = $cid AND enrol = 'manual'");
echo "Updated status. Checking...\n";
$res = $conn->query("SELECT status FROM {$m_prefix}enrol WHERE courseid = $cid AND enrol = 'manual'");
print_r($res->fetch_assoc());
