<?php
require_once 'includes/config.php';
require_once 'includes/auth.php'; // upload.php includes this too

echo "Conn type: " . gettype($conn) . "\n";
if ($conn instanceof mysqli) {
    echo "Conn status: Connected\n";
} else {
    echo "Conn status: Not Connected\n";
}

echo "DB_HOST defined: " . (defined('DB_HOST') ? 'Yes' : 'No') . "\n";
if (defined('DB_HOST'))
    echo "DB_HOST: " . DB_HOST . "\n";

echo "--- Content of includes/config.php ---\n";
echo file_get_contents('includes/config.php');
?>