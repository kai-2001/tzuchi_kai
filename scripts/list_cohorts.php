<?php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/moodle/config.php');
require_once($CFG->dirroot . '/cohort/lib.php');

$cohorts = $DB->get_records('cohort');

if (empty($cohorts)) {
    echo "No cohorts found.\n";
} else {
    echo "Existing Cohorts:\n";
    foreach ($cohorts as $c) {
        $count = $DB->count_records('cohort_members', array('cohortid' => $c->id));
        echo "ID: {$c->id}, Name: {$c->name} ({$c->idnumber}) - Members: $count\n";
    }
}
