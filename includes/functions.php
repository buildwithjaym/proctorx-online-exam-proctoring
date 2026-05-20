<?php

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function app_url($path = '')
{
    $path = ltrim($path, '/');
    return APP_URL . '/' . $path;
}

function redirect_to($path)
{
    header("Location: " . app_url($path));
    exit;
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clean_input($value)
{
    return trim((string) $value);
}

function is_logged_in()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function current_user_id()
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
}

function current_user_role()
{
    return isset($_SESSION['role']) ? $_SESSION['role'] : '';
}

function current_user_name()
{
    return isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';
}

function role_dashboard($role)
{
    if ($role === 'teacher') {
        return 'teacher/dashboard.php';
    }

    if ($role === 'student') {
        return 'student/dashboard.php';
    }

    if ($role === 'proctor') {
        return 'proctor/dashboard.php';
    }

    return 'login.php';
}

function generate_token()
{
    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }

    return hash('sha256', uniqid(mt_rand(), true) . microtime(true));
}

function safe_hash_equals($knownString, $userString)
{
    if (function_exists('hash_equals')) {
        return hash_equals($knownString, $userString);
    }

    if (strlen($knownString) !== strlen($userString)) {
        return false;
    }

    $result = 0;
    $length = strlen($knownString);

    for ($i = 0; $i < $length; $i++) {
        $result |= ord($knownString[$i]) ^ ord($userString[$i]);
    }

    return $result === 0;
}

function csrf_token()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generate_token();
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token)
{
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }

    return safe_hash_equals($_SESSION['csrf_token'], $token);
}