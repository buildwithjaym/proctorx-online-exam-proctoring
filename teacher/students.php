<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$teacherId = current_user_id();

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

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Student Management</span>
            <h2>Manage Student Accounts</h2>
        </div>

        <a class="primary-action" href="add_student.php">Add Student</a>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert-success">Student account created successfully.</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert-success">Student account updated successfully.</div>
    <?php endif; ?>

    <div class="table-wrap">
        <table class="data-table">
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
                                <a class="table-link" href="edit_student.php?id=<?php echo e($student['id']); ?>">Edit</a>
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

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>