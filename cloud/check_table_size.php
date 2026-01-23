<?php
require_once('includes/config.php');

$conn = new mysqli($db_host, $db_user, $db_pass, 'moodle');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT table_name AS `Table`, 
        round(((data_length + index_length) / 1024 / 1024), 2) `Size in MB`,
        table_rows AS `Rows`
        FROM information_schema.TABLES 
        WHERE table_schema = 'moodle' 
        AND table_name = 'mdl_logstore_standard_log'";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Table: " . $row["Table"] . "\n";
        echo "Size: " . $row["Size in MB"] . " MB\n";
        echo "Rows: " . $row["Rows"] . "\n";
    }
} else {
    echo "0 results";
}
$conn->close();
?>