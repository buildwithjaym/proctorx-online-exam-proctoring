<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$teacherId = current_user_id();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT id, full_name, email, username, status
    FROM users
    WHERE id = ?
    AND role = 'proctor'
    AND created_by = ?
    LIMIT 1
");
$stmt->execute([$id, $teacherId]);
$proctor = $stmt->fetch();

if (!$proctor) {
    redirect_to('teacher/proctors.php');
}

$error = '';
$fullName = $proctor['full_name'];
$email = $proctor['email'];
$username = $proctor['username'];
$status = $proctor['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = isset($_POST['full_name']) ? clean_input($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? clean_input($_POST['email']) : '';
    $username = isset($_POST['username']) ? clean_input($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $status = isset($_POST['status']) ? clean_input($_POST['status']) : 'active';

    if ($fullName === '' || $username === '') {
        $error = 'Please fill in all required fields.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== '' && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== '' && $password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($status, ['active', 'inactive', 'suspended'])) {
        $error = 'Invalid account status.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
        $stmt->execute([$username, $id]);
        $existingUsername = $stmt->fetch();

        if ($existingUsername) {
            $error = 'Username is already taken.';
        } else {
            if ($email !== '') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                $stmt->execute([$email, $id]);
                $existingEmail = $stmt->fetch();

                if ($existingEmail) {
                    $error = 'Email is already taken.';
                }
            }
        }

        if ($error === '') {
            $emailValue = $email === '' ? null : $email;

            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, username = ?, password_hash = ?, status = ?
                    WHERE id = ?
                    AND role = 'proctor'
                    AND created_by = ?
                ");

                $stmt->execute([
                    $fullName,
                    $emailValue,
                    $username,
                    $passwordHash,
                    $status,
                    $id,
                    $teacherId
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, username = ?, status = ?
                    WHERE id = ?
                    AND role = 'proctor'
                    AND created_by = ?
                ");

                $stmt->execute([
                    $fullName,
                    $emailValue,
                    $username,
                    $status,
                    $id,
                    $teacherId
                ]);
            }

            redirect_to('teacher/proctors.php?updated=1');
        }
    }
}

$pageTitle = 'Edit Proctor';
$panelLabel = 'Teacher Panel';
$activePage = 'proctors';

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="content-card narrow-card">
    <div class="section-heading">
        <div>
            <span>Proctor Management</span>
            <h2>Edit Proctor Account</h2>
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
                <label for="password">New Password</label>
                <input type="password" id="password" name="password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password">
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
            <button type="submit" class="primary-button">Update Proctor</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>