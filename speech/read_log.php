<?php
$log = 'c:/Apache24/logs/error.log';
$lines = file($log);
$count = count($lines);
echo "Log lines: $count\n";
for ($i = max(0, $count - 50); $i < $count; $i++) {
    $line = $lines[$i];
    if (stripos($line, 'error') !== false || stripos($line, 'exception') !== false) {
        echo $line;
    }
}
?>