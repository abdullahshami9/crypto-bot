<?php
require_once '../includes/db.php';

$username = 'admin';
$email = 'admin@cryptointel.com';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
$role = 'admin';

try {
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $email, $hash, $role]);
    echo "Admin user created successfully.\nEmail: $email\nPassword: $password";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
