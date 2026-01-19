<?php
// scripts/test_update_real.php
// Simulate update_member.php environment

ob_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/moodle_api.php';

$username = 'teacher13'; // Use existing user
$cat_id = 7; // Assuming Taibei category exists

echo "Testing Moodle Role Update for $username...\n";

// Test Assign
echo "Assigning coursecreator...\n";
$res1 = moodle_assign_role($username, $cat_id, 'coursecreator');
print_r($res1);

// Test Unassign
echo "Unassigning coursecreator...\n";
$res2 = moodle_unassign_role($username, $cat_id, 'coursecreator');
print_r($res2);

echo "Done.\n";
$out = ob_get_clean();
echo $out;
