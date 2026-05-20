<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$teacherId = current_user_id();

function generate_student_username($fullName, $pdo)
{
    $base = strtolower(trim($fullName));
    $base = preg_replace('/[^a-z0-9]+/', '.', $base);
    $base = trim($base, '.');

    if ($base === '') {
        $base = 'student';
    }

    $base = substr($base, 0, 30);

    do {
        $username = $base . mt_rand(1000, 9999);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $existing = $stmt->fetch();
    } while ($existing);

    return $username;
}

function generate_temp_password($length = 8)
{
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $password = '';
    $max = strlen($characters) - 1;

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[mt_rand(0, $max)];
    }

    return $password;
}

function set_student_flash($type, $message, $username = '', $password = '')
{
    $_SESSION['student_flash'] = [
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
        set_student_flash('error', 'Invalid request. Please try again.');
        redirect_to('teacher/students.php');
    }

    $action = isset($_POST['action']) ? clean_input($_POST['action']) : '';

    if ($action === 'create') {
        $fullName = isset($_POST['full_name']) ? clean_input($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? clean_input($_POST['email']) : '';
        $status = isset($_POST['status']) ? clean_input($_POST['status']) : 'active';

        if ($fullName === '') {
            set_student_flash('error', 'Student full name is required.');
            redirect_to('teacher/students.php');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_student_flash('error', 'Please enter a valid email address.');
            redirect_to('teacher/students.php');
        }

        if (!in_array($status, ['active', 'inactive', 'suspended'])) {
            set_student_flash('error', 'Invalid account status.');
            redirect_to('teacher/students.php');
        }

        if ($email !== '') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $existingEmail = $stmt->fetch();

            if ($existingEmail) {
                set_student_flash('error', 'Email is already taken.');
                redirect_to('teacher/students.php');
            }
        }

        $username = generate_student_username($fullName, $pdo);
        $tempPassword = generate_temp_password(8);
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $emailValue = $email === '' ? null : $email;

        $stmt = $pdo->prepare("
            INSERT INTO users
            (full_name, email, username, password_hash, role, status, created_by)
            VALUES (?, ?, ?, ?, 'student', ?, ?)
        ");

        $stmt->execute([
            $fullName,
            $emailValue,
            $username,
            $passwordHash,
            $status,
            $teacherId
        ]);

        set_student_flash('success', 'Student account created successfully. Share these login credentials with the student.', $username, $tempPassword);
        redirect_to('teacher/students.php');
    }

    if ($action === 'update') {
        $studentId = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        $fullName = isset($_POST['full_name']) ? clean_input($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? clean_input($_POST['email']) : '';
        $status = isset($_POST['status']) ? clean_input($_POST['status']) : 'active';
        $resetPassword = isset($_POST['reset_password']) ? clean_input($_POST['reset_password']) : '';

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = ?
            AND role = 'student'
            AND created_by = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId, $teacherId]);
        $student = $stmt->fetch();

        if (!$student) {
            set_student_flash('error', 'Student account not found.');
            redirect_to('teacher/students.php');
        }

        if ($fullName === '') {
            set_student_flash('error', 'Student full name is required.');
            redirect_to('teacher/students.php');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_student_flash('error', 'Please enter a valid email address.');
            redirect_to('teacher/students.php');
        }

        if (!in_array($status, ['active', 'inactive', 'suspended'])) {
            set_student_flash('error', 'Invalid account status.');
            redirect_to('teacher/students.php');
        }

        if ($email !== '') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->execute([$email, $studentId]);
            $existingEmail = $stmt->fetch();

            if ($existingEmail) {
                set_student_flash('error', 'Email is already taken.');
                redirect_to('teacher/students.php');
            }
        }

        $emailValue = $email === '' ? null : $email;

        if ($resetPassword === 'yes') {
            $tempPassword = generate_temp_password(8);
            $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                UPDATE users
                SET full_name = ?, email = ?, password_hash = ?, status = ?
                WHERE id = ?
                AND role = 'student'
                AND created_by = ?
            ");

            $stmt->execute([
                $fullName,
                $emailValue,
                $passwordHash,
                $status,
                $studentId,
                $teacherId
            ]);

            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$studentId]);
            $updatedStudent = $stmt->fetch();

            set_student_flash('success', 'Student account updated successfully. A new temporary password was generated.', $updatedStudent['username'], $tempPassword);
            redirect_to('teacher/students.php');
        }

        $stmt = $pdo->prepare("
            UPDATE users
            SET full_name = ?, email = ?, status = ?
            WHERE id = ?
            AND role = 'student'
            AND created_by = ?
        ");

        $stmt->execute([
            $fullName,
            $emailValue,
            $status,
            $studentId,
            $teacherId
        ]);

        set_student_flash('success', 'Student account updated successfully.');
        redirect_to('teacher/students.php');
    }

    if ($action === 'delete') {
        $studentId = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = ?
            AND role = 'student'
            AND created_by = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId, $teacherId]);
        $student = $stmt->fetch();

        if (!$student) {
            set_student_flash('error', 'Student account not found.');
            redirect_to('teacher/students.php');
        }

        try {
            $stmt = $pdo->prepare("
                DELETE FROM users
                WHERE id = ?
                AND role = 'student'
                AND created_by = ?
            ");
            $stmt->execute([$studentId, $teacherId]);

            set_student_flash('success', 'Student account deleted successfully.');
        } catch (PDOException $e) {
            set_student_flash('error', 'This student already has linked records. Set the account to inactive instead.');
        }

        redirect_to('teacher/students.php');
    }

    set_student_flash('error', 'Invalid action.');
    redirect_to('teacher/students.php');
}

$flash = null;

if (isset($_SESSION['student_flash'])) {
    $flash = $_SESSION['student_flash'];
    unset($_SESSION['student_flash']);
}

$stmt = $pdo->prepare("
    SELECT id, full_name, email, username, status, created_at
    FROM users
    WHERE role = 'student'
    AND created_by = ?
    ORDER BY created_at DESC
");
$stmt->execute([$teacherId]);
$students = $stmt->fetchAll();

$pageTitle = 'Students';
$panelLabel = 'Teacher Panel';
$activePage = 'students';
$extraStyles = ['assets/css/students.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Student Management</span>
            <h2>Manage Student Accounts</h2>
        </div>

        <button type="button" class="primary-action" data-open-modal="addStudentModal">
            Add Student
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
        <input type="text" id="studentSearch" placeholder="Search student name, username, or email">
    </div>

    <div class="table-wrap">
        <table class="data-table" id="studentsTable">
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
                <?php if (count($students) > 0): ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo e($student['full_name']); ?></td>
                            <td><?php echo e($student['username']); ?></td>
                            <td><?php echo e($student['email']); ?></td>
                            <td>
                                <span class="status-badge <?php echo e($student['status']); ?>">
                                    <?php echo e(ucfirst($student['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo e(date('M d, Y', strtotime($student['created_at']))); ?></td>
                            <td>
                                <div class="table-actions">
                                    <button 
                                        type="button" 
                                        class="table-btn edit"
                                        data-open-edit
                                        data-id="<?php echo e($student['id']); ?>"
                                        data-name="<?php echo e($student['full_name']); ?>"
                                        data-email="<?php echo e($student['email']); ?>"
                                        data-username="<?php echo e($student['username']); ?>"
                                        data-status="<?php echo e($student['status']); ?>"
                                    >
                                        Edit
                                    </button>

                                    <button 
                                        type="button" 
                                        class="table-btn danger"
                                        data-open-delete
                                        data-id="<?php echo e($student['id']); ?>"
                                        data-name="<?php echo e($student['full_name']); ?>"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-state">No students added yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal-backdrop" id="addStudentModal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <span>New Student</span>
                <h3>Add Student Account</h3>
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
                <button type="submit" class="primary-button">Create Student</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editStudentModal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <span>Edit Student</span>
                <h3>Update Student Account</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="student_id" id="edit_student_id">

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

<div class="modal-backdrop" id="deleteStudentModal">
    <div class="modal-card small-modal">
        <div class="modal-header">
            <div>
                <span>Delete Student</span>
                <h3>Confirm Delete</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="student_id" id="delete_student_id">

            <p class="delete-text">
                Are you sure you want to delete <strong id="delete_student_name"></strong>?
            </p>

            <div class="modal-note danger-note">
                If this student already has class, exam, or result records, delete may be blocked to protect system history.
            </div>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="danger-button">Delete Student</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var openButtons = document.querySelectorAll("[data-open-modal]");
    var closeButtons = document.querySelectorAll("[data-close-modal]");
    var editButtons = document.querySelectorAll("[data-open-edit]");
    var deleteButtons = document.querySelectorAll("[data-open-delete]");
    var searchInput = document.getElementById("studentSearch");
    var table = document.getElementById("studentsTable");

    function openModal(id) {
        var modal = document.getElementById(id);

        if (modal) {
            modal.classList.add("show");
            document.body.classList.add("modal-open");
        }
    }

    function closeModals() {
        var modals = document.querySelectorAll(".modal-backdrop");

        for (var i = 0; i < modals.length; i++) {
            modals[i].classList.remove("show");
        }

        document.body.classList.remove("modal-open");
    }

    for (var i = 0; i < openButtons.length; i++) {
        openButtons[i].addEventListener("click", function () {
            openModal(this.getAttribute("data-open-modal"));
        });
    }

    for (var j = 0; j < closeButtons.length; j++) {
        closeButtons[j].addEventListener("click", function () {
            closeModals();
        });
    }

    for (var k = 0; k < editButtons.length; k++) {
        editButtons[k].addEventListener("click", function () {
            document.getElementById("edit_student_id").value = this.getAttribute("data-id");
            document.getElementById("edit_full_name").value = this.getAttribute("data-name");
            document.getElementById("edit_email").value = this.getAttribute("data-email");
            document.getElementById("edit_username").value = this.getAttribute("data-username");
            document.getElementById("edit_status").value = this.getAttribute("data-status");
            openModal("editStudentModal");
        });
    }

    for (var l = 0; l < deleteButtons.length; l++) {
        deleteButtons[l].addEventListener("click", function () {
            document.getElementById("delete_student_id").value = this.getAttribute("data-id");
            document.getElementById("delete_student_name").textContent = this.getAttribute("data-name");
            openModal("deleteStudentModal");
        });
    }

    var modalBackdrops = document.querySelectorAll(".modal-backdrop");

    for (var m = 0; m < modalBackdrops.length; m++) {
        modalBackdrops[m].addEventListener("click", function (event) {
            if (event.target === this) {
                closeModals();
            }
        });
    }

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeModals();
        }
    });

    if (searchInput && table) {
        searchInput.addEventListener("keyup", function () {
            var filter = searchInput.value.toLowerCase();
            var rows = table.querySelectorAll("tbody tr");

            for (var n = 0; n < rows.length; n++) {
                var rowText = rows[n].textContent.toLowerCase();

                if (rowText.indexOf(filter) > -1) {
                    rows[n].style.display = "";
                } else {
                    rows[n].style.display = "none";
                }
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>