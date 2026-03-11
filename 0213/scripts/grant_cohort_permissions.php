<?php
define('CLI_SCRIPT', true);
require_once(dirname(__DIR__) . '/moodle/config.php');

echo "Granting Cohort Sync Permissions to Teachers...\n";
echo "================================================\n\n";

// Find the teacherplus/coursecreator role
$roles = ['editingteacher', 'coursecreator'];
$capabilities = [
    'enrol/cohort:config',
    'enrol/cohort:unenrol'
];

foreach ($roles as $rolename) {
    $role = $DB->get_record('role', ['shortname' => $rolename]);

    if (!$role) {
        echo "[-] Role '{$rolename}' not found, skipping.\n";
        continue;
    }

    echo "[*] Processing role: {$role->name} ({$rolename})\n";

    foreach ($capabilities as $capability) {
        // Check if capability exists
        $cap_exists = $DB->record_exists('capabilities', ['name' => $capability]);

        if (!$cap_exists) {
            echo "    [!] Capability '{$capability}' does not exist in this Moodle version.\n";
            continue;
        }

        // Get system context
        $context = context_system::instance();

        // Assign capability
        assign_capability($capability, CAP_ALLOW, $role->id, $context->id, true);
        echo "    [+] Granted: {$capability}\n";
    }
}

echo "\n================================================\n";
echo "Done! Teachers can now use Cohort sync in their courses.\n";
echo "\nNext steps:\n";
echo "1. Create a new course as a teacher\n";
echo "2. Go to: Course → Participants → Enrolment methods\n";
echo "3. Add 'Cohort sync' and select a campus cohort\n";
