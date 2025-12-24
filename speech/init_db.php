<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'root123';

$conn = new mysqli($db_host, $db_user, $db_pass);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create Database
$sql = "CREATE DATABASE IF NOT EXISTS speech_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully\n";
} else {
    die("Error creating database: " . $conn->error);
}

$conn->select_db('speech_db');

// Run schema
$schema = file_get_contents(__DIR__ . '/docs/schema.sql');

// Remove USE speech_db if it exists in schema to avoid conflict
$schema = preg_replace('/USE speech_db;/i', '', $schema);
$schema = preg_replace('/CREATE DATABASE IF NOT EXISTS speech_db.*;/i', '', $schema);

if ($conn->multi_query($schema)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Schema imported successfully\n";
} else {
    echo "Error importing schema: " . $conn->error . "\n";
}

$conn->close();
?>