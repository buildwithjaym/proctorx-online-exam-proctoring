<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$error = '';
$fullName = '';
$email = '';
$username = '';
$status = 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = isset($_POST['full_name']) ? clean_input($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? clean_input($_POST['email']) : '';
    $username = isset($_POST['username']) ? clean_input($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $status = isset($_POST['status']) ? clean_input($_POST['status']) : 'active';

    if ($fullName === '' || $username === '' || $password === '' || $confirmPassword === '') {
        $error = 'Please fill in all required fields.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($status, ['active', 'inactive', 'suspended'])) {
        $error = 'Invalid account status.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $existingUsername = $stmt->fetch();

        if ($existingUsername) {
            $error = 'Username is already taken.';
        } else {
            if ($email !== '') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $existingEmail = $stmt->fetch();

                if ($existingEmail) {
                    $error = 'Email is already taken.';
                }
            }
        }

        if ($error === '') {
            $emailValue = $email === '' ? null : $email;
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users 
                (full_name, email, username, password_hash, role, status, created_by)
                VALUES (?, ?, ?, ?, 'proctor', ?, ?)
            ");

            $stmt->execute([
                $fullName,
                $emailValue,
                $username,
                $passwordHash,
                $status,
                current_user_id()
            ]);

            redirect_to('teacher/proctors.php?created=1');
        }
    }
}

$pageTitle = 'Add Proctor';
$panelLabel = 'Teacher Panel';
$activePage = 'proctors';

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="content-card narrow-card">
    <div class="section-heading">
        <div>
            <span>Proctor Management</span>
            <h2>Create Proctor Account</h2>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert-error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="form-card" autocomplete="off">
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" value="<?php echo e($fullName); ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo e($email); ?>">
        </div>

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?php echo e($username); ?>" required>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
            </select>
        </div>

        <div class="form-actions">
            <a href="proctors.php" class="secondary-action">Cancel</a>
            <button type="submit" class="primary-button">Create Proctor</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>