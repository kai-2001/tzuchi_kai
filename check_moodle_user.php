<?php
$c = new mysqli('localhost', 'root', 'root123', 'moodle');
if ($c->connect_error) {
    echo "Connection failed: " . $c->connect_error;
    exit;
}
$username = 'H125518958';
$username_lc = strtolower($username);

$sql = "SELECT id, username, firstname, lastname FROM mdl_user WHERE username = '$username' OR username = '$username_lc'";
$r = $c->query($sql);

if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        echo "FOUND: " . json_encode($row) . "\n";
    }
} else {
    echo "NOT FOUND in Moodle database for $username or $username_lc\n";

    // Check total users to see if any new ones were added recently
    $r2 = $c->query("SELECT id, username, firstname, lastname FROM mdl_user ORDER BY id DESC LIMIT 5");
    echo "Latest 5 users in Moodle:\n";
    while ($row = $r2->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}
?>