<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$teacherId = current_user_id();

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

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Proctor Management</span>
            <h2>Manage Proctor Accounts</h2>
        </div>

        <a class="primary-action" href="add_proctor.php">Add Proctor</a>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="alert-success">Proctor account created successfully.</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert-success">Proctor account updated successfully.</div>
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
                                <a class="table-link" href="edit_proctor.php?id=<?php echo e($proctor['id']); ?>">Edit</a>
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

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>