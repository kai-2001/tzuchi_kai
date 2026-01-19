<?php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/moodle/config.php');
require_once($CFG->dirroot . '/cohort/lib.php');

// Define standard campuses
$campuses = [
    'cohort_taipei' => '台北院區全員',
    'cohort_chiayi' => '嘉義院區全員',
    'cohort_dalin' => '大林院區全員',
    'cohort_hualien' => '花蓮院區全員'
];

$system_context = context_system::instance();

echo "Starting Cohort Setup...\n";
echo "--------------------------------\n";

foreach ($campuses as $idnumber => $name) {
    // Check if exists
    $existing = $DB->get_record('cohort', array('idnumber' => $idnumber));

    if ($existing) {
        echo "[v] Exists: $name ($idnumber) - ID: {$existing->id}\n";
    } else {
        $cohort = new stdClass();
        $cohort->contextid = $system_context->id;
        $cohort->name = $name;
        $cohort->idnumber = $idnumber;
        $cohort->description = $name . ' - 自動化同步群組';
        $cohort->visible = 1;

        $id = cohort_add_cohort($cohort);
        echo "[+] Created: $name ($idnumber) - ID: $id\n";
    }
}

echo "--------------------------------\n";
echo "Done.\n";
