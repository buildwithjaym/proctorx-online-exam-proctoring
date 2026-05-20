<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$studentId = current_user_id();

function student_score_percent($score, $total)
{
    if ((float) $total <= 0) {
        return 0;
    }

    return round(((float) $score / (float) $total) * 100, 1);
}

function student_exam_state($exam)
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

function student_exam_state_class($state)
{
    $state = strtolower(str_replace(' ', '_', $state));

    if ($state === 'available_now') {
        return 'available';
    }

    if ($state === 'in_progress') {
        return 'progress';
    }

    if ($state === 'completed') {
        return 'completed';
    }

    if ($state === 'upcoming') {
        return 'upcoming';
    }

    if ($state === 'closed') {
        return 'closed';
    }

    return 'neutral';
}

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT e.id)
    FROM exam_students es
    INNER JOIN exams e ON es.exam_id = e.id
    WHERE es.student_id = ?
    AND e.status != 'archived'
");
$stmt->execute([$studentId]);
$totalAssigned = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT e.id)
    FROM exam_students es
    INNER JOIN exams e ON es.exam_id = e.id
    WHERE es.student_id = ?
    AND e.status = 'published'
    AND NOW() BETWEEN e.start_datetime AND e.end_datetime
    AND NOT EXISTS (
        SELECT 1 
        FROM exam_attempts ea
        WHERE ea.exam_id = e.id
        AND ea.student_id = es.student_id
        AND ea.attempt_status IN ('submitted', 'auto_submitted')
    )
");
$stmt->execute([$studentId]);
$availableNow = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT e.id)
    FROM exam_students es
    INNER JOIN exams e ON es.exam_id = e.id
    WHERE es.student_id = ?
    AND e.status = 'published'
    AND e.start_datetime > NOW()
");
$stmt->execute([$studentId]);
$upcomingExams = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM exam_attempts
    WHERE student_id = ?
    AND attempt_status IN ('submitted', 'auto_submitted')
");
$stmt->execute([$studentId]);
$completedExams = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM exam_attempts
    WHERE student_id = ?
    AND review_status IN ('flagged', 'under_review')
");
$stmt->execute([$studentId]);
$reviewItems = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(ROUND(AVG((score / total_points_at_time) * 100), 1), 0)
    FROM exam_attempts
    WHERE student_id = ?
    AND attempt_status IN ('submitted', 'auto_submitted')
    AND total_points_at_time > 0
");
$stmt->execute([$studentId]);
$averageScore = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.title,
        e.subject,
        e.duration_minutes,
        e.start_datetime,
        e.end_datetime,
        e.total_points,
        e.webcam_required,
        e.fullscreen_required,
        e.status,
        (
            SELECT COUNT(*)
            FROM questions q
            WHERE q.exam_id = e.id
        ) AS question_count,
        ea.attempt_status,
        ea.review_status,
        ea.score,
        ea.total_points_at_time,
        ea.violation_count,
        ea.submitted_at,
        ea.started_at
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
            ELSE 3
        END,
        e.start_datetime ASC
    LIMIT 6
");
$stmt->execute([$studentId, $studentId]);
$assignedExams = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT 
        e.title,
        e.subject,
        ea.score,
        ea.total_points_at_time,
        ea.attempt_status,
        ea.review_status,
        ea.violation_count,
        ea.submitted_at
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    WHERE ea.student_id = ?
    AND ea.attempt_status IN ('submitted', 'auto_submitted')
    ORDER BY ea.submitted_at DESC
    LIMIT 5
");
$stmt->execute([$studentId]);
$recentScores = $stmt->fetchAll();

$pageTitle = 'Student Dashboard';
$panelLabel = 'Student Panel';
$activePage = 'dashboard';
$extraStyles = ['assets/css/student-dashboard.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="student-hero">
    <div>
        <span>Welcome back</span>
        <h2><?php echo e(current_user_name()); ?></h2>
        <p>Track your assigned exams, monitor your progress, and review your latest scores in one place.</p>
    </div>

    <div class="score-circle">
        <strong><?php echo e($averageScore); ?>%</strong>
        <span>Average Score</span>
    </div>
</section>

<section class="dashboard-grid student-stats-grid">
    <div class="dashboard-card">
        <span>Assigned Exams</span>
        <h3><?php echo e($totalAssigned); ?></h3>
    </div>

    <div class="dashboard-card success-card">
        <span>Available Now</span>
        <h3><?php echo e($availableNow); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Upcoming Exams</span>
        <h3><?php echo e($upcomingExams); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Completed</span>
        <h3><?php echo e($completedExams); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>For Review</span>
        <h3><?php echo e($reviewItems); ?></h3>
    </div>
</section>

<section class="student-dashboard-layout">
    <div class="content-card">
        <div class="section-heading">
            <div>
                <span>Exam Progress</span>
                <h2>Your Assigned Exams</h2>
            </div>

            <a class="secondary-action" href="exams.php">View All Exams</a>
        </div>

        <div class="student-exam-list">
            <?php if (count($assignedExams) > 0): ?>
                <?php foreach ($assignedExams as $exam): ?>
                    <?php
                        $state = student_exam_state($exam);
                        $stateClass = student_exam_state_class($state);
                        $scorePercent = student_score_percent($exam['score'], $exam['total_points_at_time']);
                    ?>
                    <div class="student-exam-card">
                        <div class="exam-card-top">
                            <div>
                                <span class="exam-state <?php echo e($stateClass); ?>">
                                    <?php echo e($state); ?>
                                </span>
                                <h3><?php echo e($exam['title']); ?></h3>
                                <p><?php echo e($exam['subject']); ?></p>
                            </div>

                            <div class="exam-score-mini">
                                <?php if ($exam['attempt_status'] === 'submitted' || $exam['attempt_status'] === 'auto_submitted'): ?>
                                    <strong><?php echo e($scorePercent); ?>%</strong>
                                    <span>Score</span>
                                <?php else: ?>
                                    <strong><?php echo e($exam['question_count']); ?></strong>
                                    <span>Questions</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="exam-meta-grid">
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
                                <span>Proctoring</span>
                                <strong>
                                    <?php echo (int) $exam['webcam_required'] === 1 ? 'Webcam' : 'Standard'; ?>
                                </strong>
                            </div>
                        </div>

                        <?php if ($exam['attempt_status'] === 'submitted' || $exam['attempt_status'] === 'auto_submitted'): ?>
                            <div class="score-bar-wrap">
                                <div class="score-bar" style="width: <?php echo e($scorePercent); ?>%;"></div>
                            </div>
                        <?php endif; ?>

                        <div class="exam-card-actions">
                            <?php if ($state === 'Available Now' || $state === 'In Progress'): ?>
                                <a class="primary-button" href="exam_instructions.php?exam_id=<?php echo e($exam['id']); ?>">
                                    Continue
                                </a>
                            <?php elseif ($state === 'Completed'): ?>
                                <a class="secondary-action" href="result.php?exam_id=<?php echo e($exam['id']); ?>">
                                    View Result
                                </a>
                            <?php else: ?>
                                <span class="muted-action"><?php echo e($state); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-panel">
                    No assigned exams yet. Once your teacher assigns an exam, it will appear here.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="content-card">
        <div class="section-heading">
            <div>
                <span>Performance</span>
                <h2>Recent Scores</h2>
            </div>
        </div>

        <div class="recent-score-list">
            <?php if (count($recentScores) > 0): ?>
                <?php foreach ($recentScores as $score): ?>
                    <?php $percent = student_score_percent($score['score'], $score['total_points_at_time']); ?>
                    <div class="score-item">
                        <div class="score-item-header">
                            <div>
                                <h3><?php echo e($score['title']); ?></h3>
                                <span><?php echo e($score['subject']); ?></span>
                            </div>

                            <strong><?php echo e($percent); ?>%</strong>
                        </div>

                        <div class="score-details">
                            <span>
                                Score: <?php echo e($score['score']); ?> / <?php echo e($score['total_points_at_time']); ?>
                            </span>
                            <span>
                                Violations: <?php echo e($score['violation_count']); ?>
                            </span>
                        </div>

                        <div class="score-bar-wrap">
                            <div class="score-bar" style="width: <?php echo e($percent); ?>%;"></div>
                        </div>

                        <div class="review-line">
                            <span class="status-badge <?php echo e($score['review_status']); ?>">
                                <?php echo e(ucwords(str_replace('_', ' ', $score['review_status']))); ?>
                            </span>

                            <small>
                                <?php echo e(date('M d, Y h:i A', strtotime($score['submitted_at']))); ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-panel">
                    No submitted exams yet. Your scores will appear here after you complete an exam.
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Quick Guide</span>
            <h2>How to stay prepared</h2>
        </div>
    </div>

    <div class="student-guide-grid">
        <div>
            <strong>Check your schedule</strong>
            <p>Review the start and end time before opening an exam.</p>
        </div>

        <div>
            <strong>Prepare your device</strong>
            <p>Use a stable browser, charged device, and working internet connection.</p>
        </div>

        <div>
            <strong>Follow exam rules</strong>
            <p>Stay on the exam page and avoid actions that may trigger proctoring logs.</p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>