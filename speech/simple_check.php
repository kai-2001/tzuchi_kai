<?php
require_once 'includes/config.php';

echo "Campus ID\tSetting Value\n";
echo "=========\t=============\n";

$query = "SELECT campus_id, setting_value FROM system_settings WHERE setting_key='auto_compression'";
$result = $conn->query($query);

if ($result->num_rows == 0) {
    echo "沒有找到任何設定！\n";
} else {
    while ($row = $result->fetch_assoc()) {
        echo $row['campus_id'] . "\t\t" . $row['setting_value'] . "\n";
    }
}
?>