<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$teacherId = current_user_id();

function generate_proctor_username($fullName, $pdo)
{
    $base = strtolower(trim($fullName));
    $base = preg_replace('/[^a-z0-9]+/', '.', $base);
    $base = trim($base, '.');

    if ($base === '') {
        $base = 'proctor';
    }

    $base = 'proctor.' . substr($base, 0, 24);

    do {
        $username = $base . mt_rand(1000, 9999);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $existing = $stmt->fetch();
    } while ($existing);

    return $username;
}

function generate_proctor_password($length = 8)
{
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $password = '';
    $max = strlen($characters) - 1;

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[mt_rand(0, $max)];
    }

    return $password;
}

function set_proctor_flash($type, $message, $username = '', $password = '')
{
    $_SESSION['proctor_flash'] = [
        'type' => $type,
        'message' => $message,
        'username' => $username,
        'password' => $password
    ];
}

$token = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($postedToken)) {
        set_proctor_flash('error', 'Invalid request. Please try again.');
        redirect_to('teacher/proctors.php');
    }

    $action = isset($_POST['action']) ? clean_input($_POST['action']) : '';

    if ($action === 'create') {
        $fullName = isset($_POST['full_name']) ? clean_input($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? clean_input($_POST['email']) : '';
        $status = isset($_POST['status']) ? clean_input($_POST['status']) : 'active';

        if ($fullName === '') {
            set_proctor_flash('error', 'Proctor full name is required.');
            redirect_to('teacher/proctors.php');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_proctor_flash('error', 'Please enter a valid email address.');
            redirect_to('teacher/proctors.php');
        }

        if (!in_array($status, ['active', 'inactive', 'suspended'])) {
            set_proctor_flash('error', 'Invalid account status.');
            redirect_to('teacher/proctors.php');
        }

        if ($email !== '') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $existingEmail = $stmt->fetch();

            if ($existingEmail) {
                set_proctor_flash('error', 'Email is already taken.');
                redirect_to('teacher/proctors.php');
            }
        }

        $username = generate_proctor_username($fullName, $pdo);
        $tempPassword = generate_proctor_password(8);
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $emailValue = $email === '' ? null : $email;

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
            $teacherId
        ]);

        set_proctor_flash('success', 'Proctor account created successfully. Share these login credentials with the proctor.', $username, $tempPassword);
        redirect_to('teacher/proctors.php');
    }

    if ($action === 'update') {
        $proctorId = isset($_POST['proctor_id']) ? (int) $_POST['proctor_id'] : 0;
        $fullName = isset($_POST['full_name']) ? clean_input($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? clean_input($_POST['email']) : '';
        $status = isset($_POST['status']) ? clean_input($_POST['status']) : 'active';
        $resetPassword = isset($_POST['reset_password']) ? clean_input($_POST['reset_password']) : '';

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = ?
            AND role = 'proctor'
            AND created_by = ?
            LIMIT 1
        ");
        $stmt->execute([$proctorId, $teacherId]);
        $proctor = $stmt->fetch();

        if (!$proctor) {
            set_proctor_flash('error', 'Proctor account not found.');
            redirect_to('teacher/proctors.php');
        }

        if ($fullName === '') {
            set_proctor_flash('error', 'Proctor full name is required.');
            redirect_to('teacher/proctors.php');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_proctor_flash('error', 'Please enter a valid email address.');
            redirect_to('teacher/proctors.php');
        }

        if (!in_array($status, ['active', 'inactive', 'suspended'])) {
            set_proctor_flash('error', 'Invalid account status.');
            redirect_to('teacher/proctors.php');
        }

        if ($email !== '') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->execute([$email, $proctorId]);
            $existingEmail = $stmt->fetch();

            if ($existingEmail) {
                set_proctor_flash('error', 'Email is already taken.');
                redirect_to('teacher/proctors.php');
            }
        }

        $emailValue = $email === '' ? null : $email;

        if ($resetPassword === 'yes') {
            $tempPassword = generate_proctor_password(8);
            $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                UPDATE users
                SET full_name = ?, email = ?, password_hash = ?, status = ?
                WHERE id = ?
                AND role = 'proctor'
                AND created_by = ?
            ");

            $stmt->execute([
                $fullName,
                $emailValue,
                $passwordHash,
                $status,
                $proctorId,
                $teacherId
            ]);

            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$proctorId]);
            $updatedProctor = $stmt->fetch();

            set_proctor_flash('success', 'Proctor account updated successfully. A new temporary password was generated.', $updatedProctor['username'], $tempPassword);
            redirect_to('teacher/proctors.php');
        }

        $stmt = $pdo->prepare("
            UPDATE users
            SET full_name = ?, email = ?, status = ?
            WHERE id = ?
            AND role = 'proctor'
            AND created_by = ?
        ");

        $stmt->execute([
            $fullName,
            $emailValue,
            $status,
            $proctorId,
            $teacherId
        ]);

        set_proctor_flash('success', 'Proctor account updated successfully.');
        redirect_to('teacher/proctors.php');
    }

    if ($action === 'delete') {
        $proctorId = isset($_POST['proctor_id']) ? (int) $_POST['proctor_id'] : 0;

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = ?
            AND role = 'proctor'
            AND created_by = ?
            LIMIT 1
        ");
        $stmt->execute([$proctorId, $teacherId]);
        $proctor = $stmt->fetch();

        if (!$proctor) {
            set_proctor_flash('error', 'Proctor account not found.');
            redirect_to('teacher/proctors.php');
        }

        try {
            $stmt = $pdo->prepare("
                DELETE FROM users
                WHERE id = ?
                AND role = 'proctor'
                AND created_by = ?
            ");
            $stmt->execute([$proctorId, $teacherId]);

            set_proctor_flash('success', 'Proctor account deleted successfully.');
        } catch (PDOException $e) {
            set_proctor_flash('error', 'This proctor already has linked records. Set the account to inactive instead.');
        }

        redirect_to('teacher/proctors.php');
    }

    set_proctor_flash('error', 'Invalid action.');
    redirect_to('teacher/proctors.php');
}

$flash = null;

if (isset($_SESSION['proctor_flash'])) {
    $flash = $_SESSION['proctor_flash'];
    unset($_SESSION['proctor_flash']);
}

$stmt = $pdo->prepare("
    SELECT id, full_name, email, username, status, created_at
    FROM users
    WHERE role = 'proctor'
    AND created_by = ?
    ORDER BY created_at DESC
");
$stmt->execute([$teacherId]);
$proctors = $stmt->fetchAll();

$pageTitle = 'Proctors';
$panelLabel = 'Teacher Panel';
$activePage = 'proctors';
$extraStyles = ['assets/css/proctors.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Proctor Management</span>
            <h2>Manage Proctor Accounts</h2>
        </div>

        <button type="button" class="primary-action" data-open-modal="addProctorModal">
            Add Proctor
        </button>
    </div>

    <?php if ($flash): ?>
        <?php if ($flash['type'] === 'success'): ?>
            <div class="alert-success"><?php echo e($flash['message']); ?></div>
        <?php else: ?>
            <div class="alert-error"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>

        <?php if ($flash['username'] !== '' && $flash['password'] !== ''): ?>
            <div class="credential-card">
                <div>
                    <span>Generated Username</span>
                    <strong><?php echo e($flash['username']); ?></strong>
                </div>
                <div>
                    <span>Temporary Password</span>
                    <strong><?php echo e($flash['password']); ?></strong>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="table-toolbar">
        <input type="text" id="proctorSearch" placeholder="Search proctor name, username, or email">
    </div>

    <div class="table-wrap">
        <table class="data-table" id="proctorsTable">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Date Added</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($proctors) > 0): ?>
                    <?php foreach ($proctors as $proctor): ?>
                        <tr>
                            <td><?php echo e($proctor['full_name']); ?></td>
                            <td><?php echo e($proctor['username']); ?></td>
                            <td><?php echo e($proctor['email']); ?></td>
                            <td>
                                <span class="status-badge <?php echo e($proctor['status']); ?>">
                                    <?php echo e(ucfirst($proctor['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo e(date('M d, Y', strtotime($proctor['created_at']))); ?></td>
                            <td>
                                <div class="table-actions">
                                    <button 
                                        type="button" 
                                        class="table-btn edit"
                                        data-open-edit
                                        data-id="<?php echo e($proctor['id']); ?>"
                                        data-name="<?php echo e($proctor['full_name']); ?>"
                                        data-email="<?php echo e($proctor['email']); ?>"
                                        data-username="<?php echo e($proctor['username']); ?>"
                                        data-status="<?php echo e($proctor['status']); ?>"
                                    >
                                        Edit
                                    </button>

                                    <button 
                                        type="button" 
                                        class="table-btn danger"
                                        data-open-delete
                                        data-id="<?php echo e($proctor['id']); ?>"
                                        data-name="<?php echo e($proctor['full_name']); ?>"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-state">No proctors added yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal-backdrop" id="addProctorModal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <span>New Proctor</span>
                <h3>Add Proctor Account</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label for="add_full_name">Full Name</label>
                <input type="text" id="add_full_name" name="full_name" required>
            </div>

            <div class="form-group">
                <label for="add_email">Email Address</label>
                <input type="email" id="add_email" name="email">
            </div>

            <div class="form-group">
                <label for="add_status">Status</label>
                <select id="add_status" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>

            <div class="modal-note">
                Username and temporary password will be generated automatically after saving.
            </div>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="primary-button">Create Proctor</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editProctorModal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <span>Edit Proctor</span>
                <h3>Update Proctor Account</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="proctor_id" id="edit_proctor_id">

            <div class="form-group">
                <label for="edit_full_name">Full Name</label>
                <input type="text" id="edit_full_name" name="full_name" required>
            </div>

            <div class="form-group">
                <label for="edit_email">Email Address</label>
                <input type="email" id="edit_email" name="email">
            </div>

            <div class="form-group">
                <label for="edit_username">Username</label>
                <input type="text" id="edit_username" disabled>
            </div>

            <div class="form-group">
                <label for="edit_status">Status</label>
                <select id="edit_status" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>

            <label class="check-row">
                <input type="checkbox" name="reset_password" value="yes">
                <span>Generate new temporary password</span>
            </label>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="primary-button">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="deleteProctorModal">
    <div class="modal-card small-modal">
        <div class="modal-header">
            <div>
                <span>Delete Proctor</span>
                <h3>Confirm Delete</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="proctor_id" id="delete_proctor_id">

            <p class="delete-text">
                Are you sure you want to delete <strong id="delete_proctor_name"></strong>?
            </p>

            <div class="modal-note danger-note">
                If this proctor is already assigned to exams or reports, delete may be blocked to protect system history.
            </div>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="danger-button">Delete Proctor</button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo e(app_url('assets/js/proctors.js')); ?>"></script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>