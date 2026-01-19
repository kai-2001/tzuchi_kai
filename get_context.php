<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/moodle/config.php');

// Force display of errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $cat_id = 7; // [測試] 台北慈濟醫院
    $context = context_coursecat::instance($cat_id);

    echo "CONTEXT_ID::" . $context->id . "::END\n";
    echo "ASSIGN_URL::" . $CFG->wwwroot . "/admin/roles/assign.php?contextid=" . $context->id . "::END\n";
} catch (Exception $e) {
    echo "ERROR::" . $e->getMessage() . "::END\n";
}
