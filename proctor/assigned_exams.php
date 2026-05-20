<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('proctor');

$proctorId = current_user_id();

function assigned_exam_status($exam)
{
    $now = time();
    $start = strtotime($exam['start_datetime']);
    $end = strtotime($exam['end_datetime']);

    if ($exam['status'] !== 'published') {
        return ucfirst($exam['status']);
    }

    if ($now < $start) {
        return 'Upcoming';
    }

    if ($now > $end) {
        return 'Closed';
    }

    return 'Open Now';
}

function assigned_exam_status_class($status)
{
    $value = strtolower(str_replace(' ', '_', $status));

    if ($value === 'open_now') {
        return 'open';
    }

    if ($value === 'upcoming') {
        return 'upcoming';
    }

    if ($value === 'closed') {
        return 'closed';
    }

    if ($value === 'draft') {
        return 'draft';
    }

    return 'neutral';
}

$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.title,
        e.subject,
        e.description,
        e.duration_minutes,
        e.start_datetime,
        e.end_datetime,
        e.total_points,
        e.webcam_required,
        e.fullscreen_required,
        e.status,
        u.full_name AS teacher_name,
        COUNT(DISTINCT es.id) AS assigned_students,
        COUNT(DISTINCT q.id) AS question_count,
        COUNT(DISTINCT ea.id) AS total_attempts,
        SUM(CASE WHEN ea.attempt_status = 'in_progress' THEN 1 ELSE 0 END) AS active_attempts,
        SUM(CASE WHEN ea.attempt_status IN ('submitted', 'auto_submitted') THEN 1 ELSE 0 END) AS submitted_attempts,
        SUM(CASE WHEN ea.review_status IN ('flagged', 'under_review') THEN 1 ELSE 0 END) AS review_attempts,
        COALESCE(SUM(ea.violation_count), 0) AS total_violations
    FROM exam_proctors ep
    INNER JOIN exams e ON ep.exam_id = e.id
    INNER JOIN users u ON e.teacher_id = u.id
    LEFT JOIN exam_students es ON es.exam_id = e.id
    LEFT JOIN questions q ON q.exam_id = e.id
    LEFT JOIN exam_attempts ea ON ea.exam_id = e.id
    WHERE ep.proctor_id = ?
    AND e.status != 'archived'
    GROUP BY 
        e.id,
        e.title,
        e.subject,
        e.description,
        e.duration_minutes,
        e.start_datetime,
        e.end_datetime,
        e.total_points,
        e.webcam_required,
        e.fullscreen_required,
        e.status,
        u.full_name
    ORDER BY 
        CASE
            WHEN e.status = 'published' AND NOW() BETWEEN e.start_datetime AND e.end_datetime THEN 1
            WHEN e.start_datetime > NOW() THEN 2
            ELSE 3
        END,
        e.start_datetime ASC
");
$stmt->execute([$proctorId]);
$exams = $stmt->fetchAll();

$totalAssigned = count($exams);
$openNow = 0;
$upcoming = 0;
$closed = 0;
$totalActive = 0;
$totalReview = 0;

foreach ($exams as $exam) {
    $status = assigned_exam_status($exam);

    if ($status === 'Open Now') {
        $openNow++;
    }

    if ($status === 'Upcoming') {
        $upcoming++;
    }

    if ($status === 'Closed') {
        $closed++;
    }

    $totalActive += (int) $exam['active_attempts'];
    $totalReview += (int) $exam['review_attempts'];
}

$pageTitle = 'Assigned Exams';
$panelLabel = 'Proctor Panel';
$activePage = 'assigned_exams';
$extraStyles = ['assets/css/proctor-assigned-exams.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="assigned-hero">
    <div>
        <span>Assigned Exams</span>
        <h2>Exams assigned for your monitoring</h2>
        <p>View schedules, check live attempts, and open monitoring for exams assigned by teachers.</p>
    </div>
</section>

<section class="dashboard-grid assigned-stats-grid">
    <div class="dashboard-card">
        <span>Total Assigned</span>
        <h3><?php echo e($totalAssigned); ?></h3>
    </div>

    <div class="dashboard-card success-card">
        <span>Open Now</span>
        <h3><?php echo e($openNow); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Upcoming</span>
        <h3><?php echo e($upcoming); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Active Attempts</span>
        <h3><?php echo e($totalActive); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>Needs Review</span>
        <h3><?php echo e($totalReview); ?></h3>
    </div>
</section>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Exam Monitoring</span>
            <h2>Assigned exam list</h2>
        </div>
    </div>

    <div class="assigned-tools">
        <input type="text" id="assignedExamSearch" placeholder="Search exam, subject, teacher, or status">

        <select id="assignedExamFilter">
            <option value="all">All Exams</option>
            <option value="open">Open Now</option>
            <option value="upcoming">Upcoming</option>
            <option value="closed">Closed</option>
            <option value="review">Needs Review</option>
            <option value="active">Has Active Attempts</option>
        </select>
    </div>

    <div class="assigned-exam-list" id="assignedExamList">
        <?php if (count($exams) > 0): ?>
            <?php foreach ($exams as $exam): ?>
                <?php
                    $status = assigned_exam_status($exam);
                    $statusClass = assigned_exam_status_class($status);
                    $filterStatus = $statusClass;

                    if ((int) $exam['review_attempts'] > 0) {
                        $filterStatus .= ' review';
                    }

                    if ((int) $exam['active_attempts'] > 0) {
                        $filterStatus .= ' active';
                    }
                ?>

                <article class="assigned-exam-card" data-status="<?php echo e($filterStatus); ?>">
                    <div class="assigned-exam-top">
                        <div>
                            <span class="assigned-status <?php echo e($statusClass); ?>">
                                <?php echo e($status); ?>
                            </span>

                            <h3><?php echo e($exam['title']); ?></h3>

                            <p>
                                <?php echo $exam['subject'] !== '' ? e($exam['subject']) : 'No subject provided'; ?>
                                • Teacher: <?php echo e($exam['teacher_name']); ?>
                            </p>
                        </div>

                        <div class="assigned-live-box">
                            <strong><?php echo e((int) $exam['active_attempts']); ?></strong>
                            <span>Live</span>
                        </div>
                    </div>

                    <?php if ($exam['description'] !== ''): ?>
                        <p class="assigned-description">
                            <?php echo e($exam['description']); ?>
                        </p>
                    <?php endif; ?>

                    <div class="assigned-meta-grid">
                        <div>
                            <span>Starts</span>
                            <strong><?php echo e(date('M d, Y h:i A', strtotime($exam['start_datetime']))); ?></strong>
                        </div>

                        <div>
                            <span>Ends</span>
                            <strong><?php echo e(date('M d, Y h:i A', strtotime($exam['end_datetime']))); ?></strong>
                        </div>

                        <div>
                            <span>Duration</span>
                            <strong><?php echo e($exam['duration_minutes']); ?> mins</strong>
                        </div>

                        <div>
                            <span>Students</span>
                            <strong><?php echo e($exam['assigned_students']); ?></strong>
                        </div>

                        <div>
                            <span>Questions</span>
                            <strong><?php echo e($exam['question_count']); ?></strong>
                        </div>

                        <div>
                            <span>Total Attempts</span>
                            <strong><?php echo e($exam['total_attempts']); ?></strong>
                        </div>

                        <div>
                            <span>Submitted</span>
                            <strong><?php echo e((int) $exam['submitted_attempts']); ?></strong>
                        </div>

                        <div>
                            <span>Needs Review</span>
                            <strong><?php echo e((int) $exam['review_attempts']); ?></strong>
                        </div>

                        <div>
                            <span>Violations</span>
                            <strong><?php echo e((int) $exam['total_violations']); ?></strong>
                        </div>

                        <div>
                            <span>Setup</span>
                            <strong>
                                <?php if ((int) $exam['webcam_required'] === 1 && (int) $exam['fullscreen_required'] === 1): ?>
                                    Webcam + Fullscreen
                                <?php elseif ((int) $exam['webcam_required'] === 1): ?>
                                    Webcam
                                <?php elseif ((int) $exam['fullscreen_required'] === 1): ?>
                                    Fullscreen
                                <?php else: ?>
                                    Standard
                                <?php endif; ?>
                            </strong>
                        </div>
                    </div>

                    <div class="assigned-actions">
                        <a class="primary-button" href="monitor_exam.php?exam_id=<?php echo e($exam['id']); ?>">
                            Monitor Exam
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-panel">
                No assigned exams yet. Once a teacher assigns you as proctor, exams will appear here.
            </div>
        <?php endif; ?>
    </div>
</section>

<script src="<?php echo e(app_url('assets/js/proctor-assigned-exams.js?v=1')); ?>"></script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>