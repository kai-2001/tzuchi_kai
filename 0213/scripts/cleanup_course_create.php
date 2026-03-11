<?php
$file = 'c:/Apache24/htdocs/0213/templates/hospital_admin_course_create_page.php';
$content = file_get_contents($file);
$content = preg_replace('/<\/script>\s*<\/body>\s*<\/html>\s*$/is', "</script>\n</div>", $content);
file_put_contents($file, $content);
echo "Cleaned up $file\n";
