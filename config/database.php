<?php

date_default_timezone_set('Asia/Manila');

define('APP_URL', '/proctorx');

$host = "localhost";
$dbname = "proctorx_db";
$db_username = "root";
$db_password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $db_username,
        $db_password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("SET time_zone = '+08:00'");

} catch (PDOException $e) {
    die("Database connection failed.");
}