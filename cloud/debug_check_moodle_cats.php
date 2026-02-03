<?php
// debug_check_moodle_cats.php
require_once 'includes/config.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
// Direct Moodle DB connection for speed
define('CLI_SCRIPT', true);
require 'moodle/config.php'; // Get Moodle config
$m_conn = new mysqli($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname);
$m_conn->set_charset('utf8mb4');

echo "--- Moodle Categories (Top Levels) ---\n";
$sql = "SELECT id, name, parent FROM {$CFG->prefix}course_categories WHERE parent = 0";
$res = $m_conn->query($sql);
while ($row = $res->fetch_assoc()) {
    echo "Root: [{$row['id']}] {$row['name']}\n";
    // Get children (Level 1 - Hospitals?)
    $c_sql = "SELECT id, name FROM {$CFG->prefix}course_categories WHERE parent = {$row['id']}";
    $c_res = $m_conn->query($c_sql);
    while ($c_row = $c_res->fetch_assoc()) {
        echo "  - Hospital/Cat: [{$c_row['id']}] {$c_row['name']}\n";
        // Get children (Level 2 - Departments?)
        $d_sql = "SELECT id, name FROM {$CFG->prefix}course_categories WHERE parent = {$c_row['id']}";
        $d_res = $m_conn->query($d_sql);
        while ($d_row = $d_res->fetch_assoc()) {
            echo "    -- Dept: [{$d_row['id']}] {$d_row['name']}\n";
        }
    }
}
