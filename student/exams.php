<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$studentId = current_user_id();

function exam_status_label($exam)
{
    $now = time();
    $start = strtotime($exam['start_datetime']);
    $end = strtotime($exam['end_datetime']);

    if ($exam['attempt_status'] === 'submitted' || $exam['attempt_status'] === 'auto_submitted') {
        return 'Completed';
    }

    if ($exam['attempt_status'] === 'in_progress') {
        return 'In Progress';
    }

    if ($exam['status'] !== 'published') {
        return ucfirst($exam['status']);
    }

    if ($now < $start) {
        return 'Upcoming';
    }

    if ($now > $end) {
        return 'Closed';
    }

    return 'Available Now';
}

function exam_status_class($label)
{
    $value = strtolower(str_replace(' ', '_', $label));

    if ($value === 'available_now') {
        return 'available';
    }

    if ($value === 'in_progress') {
        return 'progress';
    }

    if ($value === 'completed') {
        return 'completed';
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

function score_percent($score, $total)
{
    if ((float) $total <= 0) {
        return 0;
    }

    return round(((float) $score / (float) $total) * 100, 1);
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
        e.max_attempts,
        e.status,
        (
            SELECT COUNT(*)
            FROM questions q
            WHERE q.exam_id = e.id
        ) AS question_count,
        (
            SELECT COUNT(*)
            FROM exam_attempts ea_count
            WHERE ea_count.exam_id = e.id
            AND ea_count.student_id = ?
        ) AS attempt_count,
        ea.id AS attempt_id,
        ea.attempt_status,
        ea.review_status,
        ea.score,
        ea.total_points_at_time,
        ea.violation_count,
        ea.started_at,
        ea.submitted_at
    FROM exam_students es
    INNER JOIN exams e ON es.exam_id = e.id
    LEFT JOIN exam_attempts ea ON ea.id = (
        SELECT ea2.id
        FROM exam_attempts ea2
        WHERE ea2.exam_id = e.id
        AND ea2.student_id = ?
        ORDER BY ea2.id DESC
        LIMIT 1
    )
    WHERE es.student_id = ?
    AND e.status != 'archived'
    ORDER BY 
        CASE
            WHEN e.status = 'published' AND NOW() BETWEEN e.start_datetime AND e.end_datetime THEN 1
            WHEN e.start_datetime > NOW() THEN 2
            WHEN ea.attempt_status IN ('submitted', 'auto_submitted') THEN 3
            ELSE 4
        END,
        e.start_datetime ASC
");
$stmt->execute([$studentId, $studentId, $studentId]);
$exams = $stmt->fetchAll();

$totalAssigned = count($exams);
$availableCount = 0;
$upcomingCount = 0;
$completedCount = 0;
$closedCount = 0;

foreach ($exams as $exam) {
    $label = exam_status_label($exam);

    if ($label === 'Available Now' || $label === 'In Progress') {
        $availableCount++;
    }

    if ($label === 'Upcoming') {
        $upcomingCount++;
    }

    if ($label === 'Completed') {
        $completedCount++;
    }

    if ($label === 'Closed') {
        $closedCount++;
    }
}

$pageTitle = 'Assigned Exams';
$panelLabel = 'Student Panel';
$activePage = 'exams';
$extraStyles = ['assets/css/student-exams.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="student-exams-hero">
    <div>
        <span>Assigned Exams</span>
        <h2>Your exam schedule and progress</h2>
        <p>View exams assigned by your teacher, check availability, and continue when an exam is ready.</p>
    </div>
</section>

<section class="dashboard-grid student-exam-stats">
    <div class="dashboard-card">
        <span>Total Assigned</span>
        <h3><?php echo e($totalAssigned); ?></h3>
    </div>

    <div class="dashboard-card success-card">
        <span>Available / In Progress</span>
        <h3><?php echo e($availableCount); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Upcoming</span>
        <h3><?php echo e($upcomingCount); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Completed</span>
        <h3><?php echo e($completedCount); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>Closed</span>
        <h3><?php echo e($closedCount); ?></h3>
    </div>
</section>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Exam List</span>
            <h2>Monitor your assigned exams</h2>
        </div>
    </div>

    <div class="exam-tools">
        <input type="text" id="examSearch" placeholder="Search exam title, subject, or status">

        <select id="examFilter">
            <option value="all">All Exams</option>
            <option value="available">Available / In Progress</option>
            <option value="upcoming">Upcoming</option>
            <option value="completed">Completed</option>
            <option value="closed">Closed</option>
        </select>
    </div>

    <div class="student-exam-list" id="studentExamList">
        <?php if (count($exams) > 0): ?>
            <?php foreach ($exams as $exam): ?>
                <?php
                    $label = exam_status_label($exam);
                    $labelClass = exam_status_class($label);
                    $percent = score_percent($exam['score'], $exam['total_points_at_time']);
                    $canOpen = $label === 'Available Now' || $label === 'In Progress';
                    $isCompleted = $label === 'Completed';
                    $attemptCount = (int) $exam['attempt_count'];
                    $maxAttempts = (int) $exam['max_attempts'];
                ?>

                <article class="student-exam-card" data-status="<?php echo e($labelClass); ?>">
                    <div class="exam-main-row">
                        <div class="exam-title-block">
                            <span class="exam-state <?php echo e($labelClass); ?>">
                                <?php echo e($label); ?>
                            </span>

                            <h3><?php echo e($exam['title']); ?></h3>

                            <p>
                                <?php echo $exam['subject'] !== '' ? e($exam['subject']) : 'No subject provided'; ?>
                            </p>
                        </div>

                        <div class="exam-points-box">
                            <?php if ($isCompleted): ?>
                                <strong><?php echo e($percent); ?>%</strong>
                                <span>Score</span>
                            <?php else: ?>
                                <strong><?php echo e($exam['question_count']); ?></strong>
                                <span>Questions</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($exam['description'] !== ''): ?>
                        <p class="exam-description">
                            <?php echo e($exam['description']); ?>
                        </p>
                    <?php endif; ?>

                    <div class="exam-info-grid">
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
                            <span>Total Points</span>
                            <strong><?php echo e($exam['total_points']); ?></strong>
                        </div>

                        <div>
                            <span>Attempts</span>
                            <strong><?php echo e($attemptCount); ?> / <?php echo e($maxAttempts); ?></strong>
                        </div>

                        <div>
                            <span>Proctoring</span>
                            <strong>
                                <?php if ((int) $exam['webcam_required'] === 1 && (int) $exam['fullscreen_required'] === 1): ?>
                                    Webcam + Fullscreen
                                <?php elseif ((int) $exam['webcam_required'] === 1): ?>
                                    Webcam Required
                                <?php elseif ((int) $exam['fullscreen_required'] === 1): ?>
                                    Fullscreen Required
                                <?php else: ?>
                                    Standard
                                <?php endif; ?>
                            </strong>
                        </div>
                    </div>

                    <?php if ($isCompleted): ?>
                        <div class="score-bar-wrap">
                            <div class="score-bar" style="width: <?php echo e($percent); ?>%;"></div>
                        </div>

                        <div class="review-row">
                            <span class="status-badge <?php echo e($exam['review_status']); ?>">
                                <?php echo e(ucwords(str_replace('_', ' ', $exam['review_status']))); ?>
                            </span>

                            <small>
                                Submitted: <?php echo e(date('M d, Y h:i A', strtotime($exam['submitted_at']))); ?>
                            </small>
                        </div>
                    <?php endif; ?>

                    <div class="exam-action-row">
                        <?php if ($canOpen): ?>
                            <a class="primary-button" href="exam_instructions.php?exam_id=<?php echo e($exam['id']); ?>">
                                <?php echo $label === 'In Progress' ? 'Continue Exam' : 'Open Instructions'; ?>
                            </a>
                        <?php elseif ($isCompleted): ?>
                            <a class="secondary-action" href="result.php?exam_id=<?php echo e($exam['id']); ?>">
                                View Result
                            </a>
                        <?php elseif ($label === 'Upcoming'): ?>
                            <span class="locked-action">Not yet available</span>
                        <?php else: ?>
                            <span class="locked-action">Unavailable</span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-panel">
                No exams assigned yet. Once your teacher assigns an exam, it will appear here.
            </div>
        <?php endif; ?>
    </div>
</section>

<script src="<?php echo e(app_url('assets/js/student-exams.js?v=1')); ?>"></script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>