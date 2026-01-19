<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/moodle/config.php');

// Force display of errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

$output_buffer = "";
$search = 'test';

$output_buffer .= "Searching for users like '$search'...\n";

$users = $DB->get_records_sql("SELECT * FROM {user} WHERE username LIKE ? AND deleted=0", array('%' . $search . '%'));

if (empty($users)) {
    $output_buffer .= "No users found.\n";
} else {
    foreach ($users as $user) {
        $output_buffer .= "\n================================================\n";
        $output_buffer .= "User: {$user->username} (ID: {$user->id})\n";
        $output_buffer .= "Name: {$user->firstname} {$user->lastname}\n";
        $output_buffer .= "Email: {$user->email}\n";

        // Check Site Admin
        if (is_siteadmin($user->id)) {
            $output_buffer .= "⚠️  CRITICAL: This user is a SITE ADMIN (Global Superuser)!\n";
        } else {
            $output_buffer .= "✓ Not a Site Admin.\n";
        }

        // Check Role Assignments
        $sql = "SELECT ra.id, r.shortname, r.name, c.instanceid, c.contextlevel, c.path
                FROM {role_assignments} ra
                JOIN {role} r ON r.id = ra.roleid
                JOIN {context} c ON c.id = ra.contextid
                WHERE ra.userid = ?";

        $assignments = $DB->get_records_sql($sql, array($user->id));

        if (empty($assignments)) {
            $output_buffer .= "   No roles assigned.\n";
        } else {
            foreach ($assignments as $ra) {
                $context_name = "Unknown";

                switch ($ra->contextlevel) {
                    case CONTEXT_SYSTEM:
                        $context_name = "⚠️ SYSTEM (Global Permission)";
                        break;
                    case CONTEXT_COURSECAT:
                        $cat = $DB->get_record('course_categories', array('id' => $ra->instanceid));
                        $context_name = "Category: " . ($cat ? $cat->name : 'N/A');
                        break;
                    case CONTEXT_COURSE:
                        $course = $DB->get_record('course', array('id' => $ra->instanceid));
                        $context_name = "Course: " . ($course ? $course->fullname : 'N/A');
                        break;
                    default:
                        $context_name = "Level " . $ra->contextlevel . " Instance " . $ra->instanceid;
                }

                $output_buffer .= "   [Role: {$ra->shortname}] at [$context_name]\n";
            }
        }
    }
    $output_buffer .= "\n================================================\n";
}

file_put_contents('debug_result.php', "<?php /*\n" . $output_buffer . "\n*/ ?>");
echo "Done. Written to debug_result.php\n";
