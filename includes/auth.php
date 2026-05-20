<?php

require_once __DIR__ . '/functions.php';

function login_user($username, $password)
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT id, full_name, username, password_hash, role, status
        FROM users
        WHERE username = ?
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        return [
            'success' => false,
            'message' => 'Invalid username or password.'
        ];
    }

    if ($user['status'] !== 'active') {
        return [
            'success' => false,
            'message' => 'Your account is not active.'
        ];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return [
            'success' => false,
            'message' => 'Invalid username or password.'
        ];
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    return [
        'success' => true,
        'redirect' => role_dashboard($user['role'])
    ];
}

function require_login()
{
    if (!is_logged_in()) {
        redirect_to('login.php');
    }
}

function require_role($roles)
{
    require_login();

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    $currentRole = current_user_role();

    if (!in_array($currentRole, $roles)) {
        if ($currentRole === 'teacher') {
            redirect_to('teacher/dashboard.php');
        }

        if ($currentRole === 'student') {
            redirect_to('student/dashboard.php');
        }

        if ($currentRole === 'proctor') {
            redirect_to('proctor/dashboard.php');
        }

        logout_user();
        redirect_to('login.php');
    }
}

function redirect_if_authenticated()
{
    if (!is_logged_in()) {
        return;
    }

    $role = current_user_role();

    if ($role === 'teacher') {
        redirect_to('teacher/dashboard.php');
    }

    if ($role === 'student') {
        redirect_to('student/dashboard.php');
    }

    if ($role === 'proctor') {
        redirect_to('proctor/dashboard.php');
    }

    logout_user();
    redirect_to('login.php');
}

function logout_user()
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}