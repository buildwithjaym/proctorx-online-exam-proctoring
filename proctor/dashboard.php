<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('proctor');

$proctorId = current_user_id();

function proctor_exam_status($exam)
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

function proctor_status_class($status)
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

    if ($value === 'published') {
        return 'open';
    }

    return 'neutral';
}

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT e.id)
    FROM exam_proctors ep
    INNER JOIN exams e ON ep.exam_id = e.id
    WHERE ep.proctor_id = ?
    AND e.status != 'archived'
");
$stmt->execute([$proctorId]);
$totalAssignedExams = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT e.id)
    FROM exam_proctors ep
    INNER JOIN exams e ON ep.exam_id = e.id
    WHERE ep.proctor_id = ?
    AND e.status = 'published'
    AND NOW() BETWEEN e.start_datetime AND e.end_datetime
");
$stmt->execute([$proctorId]);
$openNowExams = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    INNER JOIN exam_proctors ep ON ep.exam_id = e.id
    WHERE ep.proctor_id = ?
    AND ea.attempt_status = 'in_progress'
");
$stmt->execute([$proctorId]);
$activeAttempts = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    INNER JOIN exam_proctors ep ON ep.exam_id = e.id
    WHERE ep.proctor_id = ?
    AND ea.attempt_status IN ('submitted', 'auto_submitted')
");
$stmt->execute([$proctorId]);
$submittedAttempts = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    INNER JOIN exam_proctors ep ON ep.exam_id = e.id
    WHERE ep.proctor_id = ?
    AND ea.review_status IN ('flagged', 'under_review')
");
$stmt->execute([$proctorId]);
$reviewAttempts = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.title,
        e.subject,
        e.duration_minutes,
        e.start_datetime,
        e.end_datetime,
        e.webcam_required,
        e.fullscreen_required,
        e.status,
        COUNT(DISTINCT es.id) AS assigned_students,
        COUNT(DISTINCT ea.id) AS attempt_count,
        SUM(CASE WHEN ea.attempt_status = 'in_progress' THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN ea.review_status IN ('flagged', 'under_review') THEN 1 ELSE 0 END) AS review_count
    FROM exam_proctors ep
    INNER JOIN exams e ON ep.exam_id = e.id
    LEFT JOIN exam_students es ON es.exam_id = e.id
    LEFT JOIN exam_attempts ea ON ea.exam_id = e.id
    WHERE ep.proctor_id = ?
    AND e.status != 'archived'
    GROUP BY 
        e.id,
        e.title,
        e.subject,
        e.duration_minutes,
        e.start_datetime,
        e.end_datetime,
        e.webcam_required,
        e.fullscreen_required,
        e.status
    ORDER BY 
        CASE
            WHEN e.status = 'published' AND NOW() BETWEEN e.start_datetime AND e.end_datetime THEN 1
            WHEN e.start_datetime > NOW() THEN 2
            ELSE 3
        END,
        e.start_datetime ASC
    LIMIT 5
");
$stmt->execute([$proctorId]);
$assignedExams = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT 
        pl.id,
        pl.event_type,
        pl.severity,
        pl.event_description,
        pl.created_at,
        ea.id AS attempt_id,
        ea.violation_count,
        e.title AS exam_title,
        u.full_name AS student_name,
        u.username
    FROM proctor_logs pl
    INNER JOIN exam_attempts ea ON pl.attempt_id = ea.id
    INNER JOIN exams e ON ea.exam_id = e.id
    INNER JOIN exam_proctors ep ON ep.exam_id = e.id
    INNER JOIN users u ON ea.student_id = u.id
    WHERE ep.proctor_id = ?
    ORDER BY pl.created_at DESC
    LIMIT 8
");
$stmt->execute([$proctorId]);
$recentLogs = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT 
        ea.id AS attempt_id,
        ea.started_at,
        ea.violation_count,
        ea.review_status,
        e.title AS exam_title,
        e.subject,
        u.full_name AS student_name,
        u.username
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    INNER JOIN exam_proctors ep ON ep.exam_id = e.id
    INNER JOIN users u ON ea.student_id = u.id
    WHERE ep.proctor_id = ?
    AND ea.attempt_status = 'in_progress'
    ORDER BY ea.started_at DESC
    LIMIT 6
");
$stmt->execute([$proctorId]);
$liveAttempts = $stmt->fetchAll();

$pageTitle = 'Proctor Dashboard';
$panelLabel = 'Proctor Panel';
$activePage = 'dashboard';
$extraStyles = ['assets/css/proctor-dashboard.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="proctor-hero">
    <div>
        <span>Proctor Dashboard</span>
        <h2>Monitor assigned exams and student activity</h2>
        <p>Track live attempts, review suspicious events, and respond quickly when an exam needs attention.</p>
    </div>
</section>

<section class="dashboard-grid proctor-stats-grid">
    <div class="dashboard-card">
        <span>Assigned Exams</span>
        <h3><?php echo e($totalAssignedExams); ?></h3>
    </div>

    <div class="dashboard-card success-card">
        <span>Open Now</span>
        <h3><?php echo e($openNowExams); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Active Attempts</span>
        <h3><?php echo e($activeAttempts); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Submitted</span>
        <h3><?php echo e($submittedAttempts); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>Needs Review</span>
        <h3><?php echo e($reviewAttempts); ?></h3>
    </div>
</section>

<section class="proctor-dashboard-layout">
    <div class="content-card">
        <div class="section-heading">
            <div>
                <span>Assigned Exams</span>
                <h2>Exams you are assigned to monitor</h2>
            </div>

            <a class="secondary-action" href="assigned_exams.php">View All</a>
        </div>

        <div class="proctor-exam-list">
            <?php if (count($assignedExams) > 0): ?>
                <?php foreach ($assignedExams as $exam): ?>
                    <?php
                        $statusLabel = proctor_exam_status($exam);
                        $statusClass = proctor_status_class($statusLabel);
                    ?>

                    <article class="proctor-exam-card">
                        <div class="proctor-exam-top">
                            <div>
                                <span class="proctor-status <?php echo e($statusClass); ?>">
                                    <?php echo e($statusLabel); ?>
                                </span>

                                <h3><?php echo e($exam['title']); ?></h3>

                                <p>
                                    <?php echo $exam['subject'] !== '' ? e($exam['subject']) : 'No subject provided'; ?>
                                </p>
                            </div>

                            <div class="exam-live-box">
                                <strong><?php echo e((int) $exam['active_count']); ?></strong>
                                <span>Live</span>
                            </div>
                        </div>

                        <div class="proctor-meta-grid">
                            <div>
                                <span>Starts</span>
                                <strong><?php echo e(date('M d, Y h:i A', strtotime($exam['start_datetime']))); ?></strong>
                            </div>

                            <div>
                                <span>Ends</span>
                                <strong><?php echo e(date('M d, Y h:i A', strtotime($exam['end_datetime']))); ?></strong>
                            </div>

                            <div>
                                <span>Students</span>
                                <strong><?php echo e($exam['assigned_students']); ?></strong>
                            </div>

                            <div>
                                <span>Attempts</span>
                                <strong><?php echo e($exam['attempt_count']); ?></strong>
                            </div>

                            <div>
                                <span>Review</span>
                                <strong><?php echo e((int) $exam['review_count']); ?></strong>
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

                        <div class="proctor-card-actions">
                            <a class="primary-button" href="monitor_exam.php?exam_id=<?php echo e($exam['id']); ?>">
                                Monitor
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-panel">
                    No exams assigned yet. Once a teacher assigns you as proctor, exams will appear here.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="content-card">
        <div class="section-heading">
            <div>
                <span>Live Attempts</span>
                <h2>Students currently taking exams</h2>
            </div>
        </div>

        <div class="live-attempt-list">
            <?php if (count($liveAttempts) > 0): ?>
                <?php foreach ($liveAttempts as $attempt): ?>
                    <div class="live-attempt-card">
                        <div>
                            <h3><?php echo e($attempt['student_name']); ?></h3>
                            <span><?php echo e($attempt['username']); ?></span>
                        </div>

                        <p><?php echo e($attempt['exam_title']); ?></p>

                        <div class="live-attempt-footer">
                            <span class="status-badge <?php echo e($attempt['review_status']); ?>">
                                <?php echo e(ucwords(str_replace('_', ' ', $attempt['review_status']))); ?>
                            </span>

                            <strong><?php echo e($attempt['violation_count']); ?> violations</strong>
                        </div>

                        <a href="view_attempt.php?attempt_id=<?php echo e($attempt['attempt_id']); ?>">
                            View Activity
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-panel">
                    No active student attempts right now.
                </div>
            <?php endif; ?>
        </div>
    </aside>
</section>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Recent Proctoring Events</span>
            <h2>Latest activity logs</h2>
        </div>
    </div>

    <div class="proctor-log-list">
        <?php if (count($recentLogs) > 0): ?>
            <?php foreach ($recentLogs as $log): ?>
                <div class="proctor-log-item">
                    <div>
                        <span class="severity-pill <?php echo e($log['severity']); ?>">
                            <?php echo e(ucfirst($log['severity'])); ?>
                        </span>

                        <h3><?php echo e($log['event_description']); ?></h3>

                        <p>
                            <?php echo e($log['student_name']); ?> • <?php echo e($log['exam_title']); ?>
                        </p>
                    </div>

                    <div class="log-time">
                        <?php echo e(date('M d, Y h:i A', strtotime($log['created_at']))); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-panel">
                No proctoring logs recorded yet.
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>