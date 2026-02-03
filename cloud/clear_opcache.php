<?php
// clear_opcache.php
// Force reset of OPcache

if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "<h1>OPcache reset successfully!</h1>";
        echo "<p>All cached scripts have been invalidated.</p>";
    } else {
        echo "<h1>OPcache reset failed.</h1>";
    }
} else {
    echo "<h1>OPcache is not enabled or function not available.</h1>";
}

// Also try to clear realpath cache
clearstatcache(true);
echo "<p>Realpath cache cleared.</p>";
?>
<a href="index.php">Back to Home</a>