<?php
define('CLI_SCRIPT', true);
$url = 'http://localhost/cloud/moodle/course/view.php?id=17';
print_r(get_headers($url));
