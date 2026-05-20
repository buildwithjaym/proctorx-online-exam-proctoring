<?php

require_once __DIR__ . '/includes/functions.php';

$username = 'teacher';
$password = 'teacher123';
$full_name = 'Default Teacher';
$email = 'teacher@proctorx.local';
$role = 'teacher';
$status = 'active';

$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$existingUser = $stmt->fetch();

if ($existingUser) {
    echo "Teacher account already exists.";
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users 
    (full_name, email, username, password_hash, role, status)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $full_name,
    $email,
    $username,
    $passwordHash,
    $role,
    $status
]);

echo "Teacher account created successfully.<br>";
echo "Username: teacher<br>";
echo "Password: teacher123<br>";
echo "Delete seed_teacher.php after this.";