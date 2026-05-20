<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$teacherId = current_user_id();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND created_by = ? AND status = 'active'");
$stmt->execute([$teacherId]);
$totalStudents = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'proctor' AND created_by = ? AND status = 'active'");
$stmt->execute([$teacherId]);
$totalProctors = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE teacher_id = ? AND status = 'active'");
$stmt->execute([$teacherId]);
$totalClasses = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE teacher_id = ? AND status IN ('draft', 'published')");
$stmt->execute([$teacherId]);
$totalExams = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    WHERE e.teacher_id = ?
    AND ea.review_status IN ('flagged', 'under_review')
");
$stmt->execute([$teacherId]);
$flaggedAttempts = $stmt->fetchColumn();

$pageTitle = 'Teacher Dashboard';
$panelLabel = 'Teacher Panel';
$activePage = 'dashboard';

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="dashboard-grid">
    <div class="dashboard-card">
        <span>Total Students</span>
        <h3><?php echo e($totalStudents); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Total Proctors</span>
        <h3><?php echo e($totalProctors); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Total Classes</span>
        <h3><?php echo e($totalClasses); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Total Exams</span>
        <h3><?php echo e($totalExams); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>Flagged Attempts</span>
        <h3><?php echo e($flaggedAttempts); ?></h3>
    </div>
</section>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Next Step</span>
            <h2>Build your exam environment</h2>
        </div>
    </div>

    <div class="quick-actions">
        <a href="add_student.php">Add Student</a>
        <a href="add_proctor.php">Add Proctor</a>
        <a href="classes.php">Manage Classes</a>
        <a href="exams.php">Create Exam</a>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>