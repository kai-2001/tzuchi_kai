<?php
$c = new mysqli('localhost', 'root', 'root123', 'portal_db');
if ($c->connect_error) {
    echo "Connection failed: " . $c->connect_error;
    exit;
}
$r = $c->query('SELECT username, fullname FROM users ORDER BY id DESC LIMIT 1');
if ($r) {
    $row = $r->fetch_assoc();
    echo "USERNAME:[" . $row['username'] . "] FULLNAME:[" . $row['fullname'] . "]\n";
} else {
    echo "Query failed: " . $c->error;
}
?>