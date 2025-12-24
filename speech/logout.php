<?php
require_once 'includes/config.php';
session_unset();
session_destroy();

// Redirect back to homepage
header("Location: index.php");
exit;
?>