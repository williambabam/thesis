<?php
require_once __DIR__ . '/config.php';

$super_username = 'admin';
$super_email    = 'admin@resortease.com';
$super_pass     = 'admin123'; 

try {
    $hash = password_hash($super_pass, PASSWORD_DEFAULT);
    
    // Check if exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$super_username]);
    
    if ($stmt->fetch()) {
        echo "<h3>User '$super_username' already exists!</h3>";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, username, email, password_hash, role, is_active, approval_status) 
            VALUES ('Super Administrator', ?, ?, ?, 'super_admin', 1, 'approved')
        ");
        $stmt->execute([$super_username, $super_email, $hash]);
        echo "<h3 style='color:green;'>Success! Super Admin created.</h3>";
        echo "<p>Username: <strong>$super_username</strong></p>";
        echo "<p>Password: <strong>$super_pass</strong></p>";
        echo "<br><a href='login.php'>Go to Login</a>";
        echo "<br><br><em>Please delete this file after use for security!</em>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>