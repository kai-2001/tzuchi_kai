<?php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/moodle/config.php');
require_once($CFG->dirroot . '/cohort/lib.php');

// Usage: php sync_cohort.php [username] [cohort_idnumber]

$username = isset($argv[1]) ? $argv[1] : '';
$cohort_idnumber = isset($argv[2]) ? $argv[2] : '';

if (empty($username) || empty($cohort_idnumber)) {
    echo "Usage: php sync_cohort.php [username] [cohort_idnumber]\n";
    exit(1);
}

// 1. Get User
$user = $DB->get_record('user', array('username' => $username, 'deleted' => 0));
if (!$user) {
    echo "User '$username' not found.\n";
    exit(1);
}

// 2. Get Cohort
$cohort = $DB->get_record('cohort', array('idnumber' => $cohort_idnumber));
if (!$cohort) {
    echo "Cohort '$cohort_idnumber' not found.\n";
    exit(1);
}

// 3. Check membership
if ($DB->record_exists('cohort_members', array('cohortid' => $cohort->id, 'userid' => $user->id))) {
    echo "User is already in cohort.\n";
    exit(0);
}

// 4. Add member
try {
    cohort_add_member($cohort->id, $user->id);
    echo "Success: User added to cohort.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
