<?php
$c = new mysqli('localhost', 'root', 'root123', 'portal_db');
if ($c->connect_error)
    die($c->connect_error);
$stmt = $c->prepare("DELETE FROM users WHERE username = ?");
$user = 'H125518958';
$stmt->bind_param("s", $user);
if ($stmt->execute()) {
    echo "DELETED user $user from portal_db\n";
} else {
    echo "DELETE failed: " . $c->error . "\n";
}
?>