<?php

require_once __DIR__ . '/includes/auth.php';

redirect_if_authenticated();

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($token)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = isset($_POST['username']) ? clean_input($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if ($username === '' || $password === '') {
            $error = 'Please enter your username and password.';
        } else {
            $result = login_user($username, $password);

            if ($result['success']) {
                redirect_to($result['redirect']);
            }

            $error = $result['message'];
        }
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | ProctorX</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="login-body">

<div class="login-page">
    <form class="login-card" method="POST" action="login.php" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">

        <div class="login-brand">
            <div class="brand-mark">PX</div>
            <h1>ProctorX</h1>
            <p>Distributed Online Exam Proctoring System</p>
        </div>

        <div class="login-header">
            <h2>Sign in</h2>
            <p>Enter your account credentials to continue.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert-error">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="username">Username</label>
            <input 
                type="text" 
                id="username" 
                name="username" 
                value="<?php echo e($username); ?>" 
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="password-wrap">
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                >
                <button type="button" id="togglePassword">Show</button>
            </div>
        </div>

        <button type="submit" class="login-btn" id="loginBtn">
            Login
        </button>
    </form>
</div>

<script src="assets/js/auth.js"></script>
</body>
</html>