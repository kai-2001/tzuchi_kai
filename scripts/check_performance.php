<?php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/moodle/config.php');

echo "Moodle Performance Diagnostic\n";
echo "=============================\n\n";

// 1. Check event observers
echo "[1] Event Observers:\n";
$observers = $DB->get_records('events_handlers');
if ($observers) {
    echo "  Total event handlers: " . count($observers) . "\n";
    foreach ($observers as $obs) {
        if (strpos($obs->eventname, 'enrol') !== false) {
            echo "  - {$obs->eventname} → {$obs->handlerfunction}\n";
        }
    }
} else {
    echo "  No legacy event handlers (using new Events API)\n";
}

// 2. Check enrollment instances
echo "\n[2] Enrollment Methods:\n";
$enrol_instances = $DB->get_records('enrol', null, '', 'enrol, COUNT(*) as count', 0, 10);
foreach ($enrol_instances as $enrol) {
    echo "  - {$enrol->enrol}: {$enrol->count} instances\n";
}

// 3. Check database performance settings
echo "\n[3] Database Info:\n";
echo "  - DB Type: {$CFG->dbtype}\n";
echo "  - DB Host: {$CFG->dbhost}\n";

// 4. Check PHP settings
echo "\n[4] PHP Configuration:\n";
echo "  - Max execution time: " . ini_get('max_execution_time') . "s\n";
echo "  - Memory limit: " . ini_get('memory_limit') . "\n";
echo "  - PHP version: " . PHP_VERSION . "\n";

// 5. Check cache
echo "\n[5] Cache Configuration:\n";
$cacheconfig = get_config('', 'cachestore');
echo "  - Cache stores configured: " . ($cacheconfig ? 'YES' : 'NO') . "\n";

echo "\n=============================\n";
