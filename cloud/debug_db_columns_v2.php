<?php
require_once 'includes/config.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

$columns = [];
$res = $conn->query("SHOW COLUMNS FROM attribute_values");
while ($row = $res->fetch_assoc()) {
    $columns[] = $row['Field'];
}

$data = null;
$res = $conn->query("SELECT * FROM attribute_values WHERE id = 39");
if ($res)
    $data = $res->fetch_assoc();

echo json_encode([
    'columns' => $columns,
    'row_39' => $data
], JSON_PRETTY_PRINT);
