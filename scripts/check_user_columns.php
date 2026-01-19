<?php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/moodle/config.php');

$columns = $DB->get_columns('user');
foreach ($columns as $col) {
    echo $col->name . "\n";
}
