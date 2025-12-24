<?php
require_once 'includes/config.php';

if (isset($_GET['user']) && isset($_GET['pass'])) {
    $username = $_GET['user'];
    $password = password_hash($_GET['pass'], PASSWORD_DEFAULT);

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        // Update
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $password, $user['id']);
        $stmt->execute();
        echo "Successfully updated password for $username.";
    } else {
        // Create as admin/test
        $role = ($username === 'admin') ? 'manager' : 'member';
        $stmt = $conn->prepare("INSERT INTO users (username, password, role, display_name) VALUES (?, ?, ?, ?)");
        $display_name = ucfirst($username);
        $stmt->bind_param("ssss", $username, $password, $role, $display_name);
        $stmt->execute();
        echo "Successfully created user $username with the provided password.";
    }
} else {
    echo "Usage: set_local_password.php?user=USERNAME&pass=PASSWORD";
}
?>