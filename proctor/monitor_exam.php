<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('proctor');

$proctorId = current_user_id();
$examId = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;

function set_monitor_flash($type, $message)
{
    $_SESSION['monitor_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function monitor_exam_status($exam)
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

function monitor_status_class($status)
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

    if ($value === 'in_progress') {
        return 'progress';
    }

    if ($value === 'submitted' || $value === 'auto_submitted') {
        return 'submitted';
    }

    return 'neutral';
}

function monitor_attempt_label($status)
{
    if ($status === 'in_progress') {
        return 'In Progress';
    }

    if ($status === 'submitted') {
        return 'Submitted';
    }

    if ($status === 'auto_submitted') {
        return 'Auto Submitted';
    }

    return 'Not Started';
}

function monitor_percent($score, $total)
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
        e.status,
        u.full_name AS teacher_name,
        (
            SELECT COUNT(*)
            FROM questions q
            WHERE q.exam_id = e.id
        ) AS question_count
    FROM exam_proctors ep
    INNER JOIN exams e ON ep.exam_id = e.id
    INNER JOIN users u ON e.teacher_id = u.id
    WHERE ep.proctor_id = ?
    AND e.id = ?
    AND e.status != 'archived'
    LIMIT 1
");
$stmt->execute([$proctorId, $examId]);
$exam = $stmt->fetch();

if (!$exam) {
    redirect_to('proctor/assigned_exams.php');
}

$token = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $action = isset($_POST['action']) ? clean_input($_POST['action']) : '';
    $attemptId = isset($_POST['attempt_id']) ? (int) $_POST['attempt_id'] : 0;

    if (!verify_csrf_token($postedToken)) {
        set_monitor_flash('error', 'Invalid request. Please try again.');
        redirect_to('proctor/monitor_exam.php?exam_id=' . $examId);
    }

    $stmt = $pdo->prepare("
        SELECT ea.id
        FROM exam_attempts ea
        INNER JOIN exams e ON ea.exam_id = e.id
        INNER JOIN exam_proctors ep ON ep.exam_id = e.id
        WHERE ea.id = ?
        AND e.id = ?
        AND ep.proctor_id = ?
        LIMIT 1
    ");
    $stmt->execute([$attemptId, $examId, $proctorId]);
    $attemptCheck = $stmt->fetch();

    if (!$attemptCheck) {
        set_monitor_flash('error', 'Attempt not found.');
        redirect_to('proctor/monitor_exam.php?exam_id=' . $examId);
    }

    if ($action === 'flag_attempt') {
        $stmt = $pdo->prepare("
            UPDATE exam_attempts
            SET review_status = 'flagged'
            WHERE id = ?
            AND exam_id = ?
        ");
        $stmt->execute([$attemptId, $examId]);

        set_monitor_flash('success', 'Attempt has been flagged for teacher review.');
        redirect_to('proctor/monitor_exam.php?exam_id=' . $examId);
    }

    if ($action === 'mark_under_review') {
        $stmt = $pdo->prepare("
            UPDATE exam_attempts
            SET review_status = 'under_review'
            WHERE id = ?
            AND exam_id = ?
        ");
        $stmt->execute([$attemptId, $examId]);

        set_monitor_flash('success', 'Attempt has been marked under review.');
        redirect_to('proctor/monitor_exam.php?exam_id=' . $examId);
    }

    if ($action === 'clear_attempt') {
        $stmt = $pdo->prepare("
            UPDATE exam_attempts
            SET review_status = 'cleared'
            WHERE id = ?
            AND exam_id = ?
        ");
        $stmt->execute([$attemptId, $examId]);

        set_monitor_flash('success', 'Attempt has been cleared.');
        redirect_to('proctor/monitor_exam.php?exam_id=' . $examId);
    }

    set_monitor_flash('error', 'Invalid action.');
    redirect_to('proctor/monitor_exam.php?exam_id=' . $examId);
}

$flash = null;

if (isset($_SESSION['monitor_flash'])) {
    $flash = $_SESSION['monitor_flash'];
    unset($_SESSION['monitor_flash']);
}

$stmt = $pdo->prepare("
    SELECT 
        u.id AS student_id,
        u.full_name,
        u.username,
        u.email,
        u.status AS student_status,
        ea.id AS attempt_id,
        ea.attempt_status,
        ea.review_status,
        ea.score,
        ea.total_points_at_time,
        ea.violation_count,
        ea.started_at,
        ea.submitted_at,
        ea.last_activity_at,
        (
            SELECT COUNT(*)
            FROM student_answers sa
            WHERE sa.attempt_id = ea.id
        ) AS answered_count,
        (
            SELECT COUNT(*)
            FROM proctor_logs pl
            WHERE pl.attempt_id = ea.id
        ) AS log_count
    FROM exam_students es
    INNER JOIN users u ON es.student_id = u.id
    LEFT JOIN exam_attempts ea ON ea.id = (
        SELECT ea2.id
        FROM exam_attempts ea2
        WHERE ea2.exam_id = es.exam_id
        AND ea2.student_id = es.student_id
        ORDER BY ea2.id DESC
        LIMIT 1
    )
    WHERE es.exam_id = ?
    ORDER BY 
        CASE
            WHEN ea.attempt_status = 'in_progress' THEN 1
            WHEN ea.attempt_status IN ('submitted', 'auto_submitted') THEN 2
            ELSE 3
        END,
        u.full_name ASC
");
$stmt->execute([$examId]);
$students = $stmt->fetchAll();

$totalStudents = count($students);
$notStartedCount = 0;
$activeCount = 0;
$submittedCount = 0;
$needsReviewCount = 0;
$totalViolations = 0;

foreach ($students as $student) {
    if (!$student['attempt_status']) {
        $notStartedCount++;
    }

    if ($student['attempt_status'] === 'in_progress') {
        $activeCount++;
    }

    if ($student['attempt_status'] === 'submitted' || $student['attempt_status'] === 'auto_submitted') {
        $submittedCount++;
    }

    if ($student['review_status'] === 'flagged' || $student['review_status'] === 'under_review') {
        $needsReviewCount++;
    }

    $totalViolations += (int) $student['violation_count'];
}

$stmt = $pdo->prepare("
    SELECT 
        pl.id,
        pl.event_type,
        pl.severity,
        pl.event_description,
        pl.metadata_json,
        pl.created_at,
        ea.id AS attempt_id,
        u.full_name,
        u.username
    FROM proctor_logs pl
    INNER JOIN exam_attempts ea ON pl.attempt_id = ea.id
    INNER JOIN users u ON ea.student_id = u.id
    WHERE ea.exam_id = ?
    ORDER BY pl.created_at DESC
    LIMIT 15
");
$stmt->execute([$examId]);
$recentLogs = $stmt->fetchAll();

$examStatus = monitor_exam_status($exam);
$examStatusClass = monitor_status_class($examStatus);

$pageTitle = 'Monitor Exam';
$panelLabel = 'Proctor Panel';
$activePage = 'monitoring';
$extraStyles = ['assets/css/proctor-monitor.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="monitor-hero">
    <div>
        <span>Live Monitoring</span>
        <h2><?php echo e($exam['title']); ?></h2>
        <p>
            <?php echo $exam['subject'] !== '' ? e($exam['subject']) : 'Monitor student attempts and suspicious activity.'; ?>
            • Teacher: <?php echo e($exam['teacher_name']); ?>
        </p>
    </div>

    <div class="monitor-status <?php echo e($examStatusClass); ?>">
        <?php echo e($examStatus); ?>
    </div>
</section>

<?php if ($flash): ?>
    <?php if ($flash['type'] === 'success'): ?>
        <div class="alert-success"><?php echo e($flash['message']); ?></div>
    <?php else: ?>
        <div class="alert-error"><?php echo e($flash['message']); ?></div>
    <?php endif; ?>
<?php endif; ?>

<section class="dashboard-grid monitor-stats-grid">
    <div class="dashboard-card">
        <span>Assigned Students</span>
        <h3><?php echo e($totalStudents); ?></h3>
    </div>

    <div class="dashboard-card success-card">
        <span>Active Now</span>
        <h3><?php echo e($activeCount); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Submitted</span>
        <h3><?php echo e($submittedCount); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Not Started</span>
        <h3><?php echo e($notStartedCount); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>Needs Review</span>
        <h3><?php echo e($needsReviewCount); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>Total Violations</span>
        <h3><?php echo e($totalViolations); ?></h3>
    </div>
</section>

<section class="monitor-layout">
    <div class="content-card">
        <div class="section-heading">
            <div>
                <span>Student Attempts</span>
                <h2>Monitor student progress</h2>
            </div>

            <a class="secondary-action" href="assigned_exams.php">Back to Assigned Exams</a>
        </div>

        <div class="monitor-tools">
            <input type="text" id="monitorSearch" placeholder="Search student, username, status, or review">

            <select id="monitorFilter">
                <option value="all">All Students</option>
                <option value="in_progress">In Progress</option>
                <option value="submitted">Submitted</option>
                <option value="not_started">Not Started</option>
                <option value="needs_review">Needs Review</option>
                <option value="violations">Has Violations</option>
            </select>

            <button type="button" id="refreshMonitorBtn">Refresh</button>
        </div>

        <div class="student-monitor-list" id="studentMonitorList">
            <?php if (count($students) > 0): ?>
                <?php foreach ($students as $student): ?>
                    <?php
                        $attemptStatus = $student['attempt_status'] ? $student['attempt_status'] : 'not_started';
                        $attemptLabel = monitor_attempt_label($attemptStatus);
                        $attemptClass = monitor_status_class($attemptStatus);
                        $reviewStatus = $student['review_status'] ? $student['review_status'] : 'none';
                        $percent = monitor_percent($student['score'], $student['total_points_at_time']);

                        $filterStatus = $attemptStatus;

                        if ($reviewStatus === 'flagged' || $reviewStatus === 'under_review') {
                            $filterStatus .= ' needs_review';
                        }

                        if ((int) $student['violation_count'] > 0) {
                            $filterStatus .= ' violations';
                        }
                    ?>

                    <article class="student-monitor-card" data-monitor-status="<?php echo e($filterStatus); ?>">
                        <div class="student-monitor-top">
                            <div>
                                <span class="attempt-state <?php echo e($attemptClass); ?>">
                                    <?php echo e($attemptLabel); ?>
                                </span>

                                <h3><?php echo e($student['full_name']); ?></h3>

                                <p>
                                    <?php echo e($student['username']); ?>
                                    <?php if ($student['email'] !== ''): ?>
                                        • <?php echo e($student['email']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div class="violation-box">
                                <strong><?php echo e((int) $student['violation_count']); ?></strong>
                                <span>Violations</span>
                            </div>
                        </div>

                        <div class="monitor-meta-grid">
                            <div>
                                <span>Review Status</span>
                                <strong><?php echo e(ucwords(str_replace('_', ' ', $reviewStatus))); ?></strong>
                            </div>

                            <div>
                                <span>Answered</span>
                                <strong><?php echo e((int) $student['answered_count']); ?> / <?php echo e($exam['question_count']); ?></strong>
                            </div>

                            <div>
                                <span>Logs</span>
                                <strong><?php echo e((int) $student['log_count']); ?></strong>
                            </div>

                            <div>
                                <span>Score</span>
                                <strong>
                                    <?php if ($attemptStatus === 'submitted' || $attemptStatus === 'auto_submitted'): ?>
                                        <?php echo e($percent); ?>%
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </strong>
                            </div>

                            <div>
                                <span>Started</span>
                                <strong>
                                    <?php echo $student['started_at'] ? e(date('M d, Y h:i A', strtotime($student['started_at']))) : 'Not started'; ?>
                                </strong>
                            </div>

                            <div>
                                <span>Last Activity</span>
                                <strong>
                                    <?php echo $student['last_activity_at'] ? e(date('M d, Y h:i A', strtotime($student['last_activity_at']))) : 'No activity'; ?>
                                </strong>
                            </div>
                        </div>

                        <div class="monitor-actions">
                            <?php if ($student['attempt_id']): ?>
                                <a class="table-btn review" href="view_attempt.php?attempt_id=<?php echo e($student['attempt_id']); ?>">
                                    View Activity
                                </a>

                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
                                    <input type="hidden" name="attempt_id" value="<?php echo e($student['attempt_id']); ?>">
                                    <input type="hidden" name="action" value="mark_under_review">
                                    <button type="submit" class="monitor-btn review-btn">Under Review</button>
                                </form>

                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
                                    <input type="hidden" name="attempt_id" value="<?php echo e($student['attempt_id']); ?>">
                                    <input type="hidden" name="action" value="flag_attempt">
                                    <button type="submit" class="monitor-btn danger-btn">Flag</button>
                                </form>

                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
                                    <input type="hidden" name="attempt_id" value="<?php echo e($student['attempt_id']); ?>">
                                    <input type="hidden" name="action" value="clear_attempt">
                                    <button type="submit" class="monitor-btn clear-btn">Clear</button>
                                </form>
                            <?php else: ?>
                                <span class="locked-monitor-action">No attempt yet</span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-panel">
                    No students assigned to this exam yet.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="content-card monitor-side-card">
        <div class="section-heading">
            <div>
                <span>Recent Events</span>
                <h2>Latest proctoring logs</h2>
            </div>
        </div>

        <div class="monitor-log-list">
            <?php if (count($recentLogs) > 0): ?>
                <?php foreach ($recentLogs as $log): ?>
                    <div class="monitor-log-card">
                        <span class="severity-pill <?php echo e($log['severity']); ?>">
                            <?php echo e(ucfirst($log['severity'])); ?>
                        </span>

                        <h3><?php echo e($log['event_description']); ?></h3>

                        <p><?php echo e($log['full_name']); ?> • <?php echo e($log['username']); ?></p>

                        <small><?php echo e(date('M d, Y h:i A', strtotime($log['created_at']))); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-panel">
                    No proctoring logs for this exam yet.
                </div>
            <?php endif; ?>
        </div>
    </aside>
</section>

<script src="<?php echo e(app_url('assets/js/proctor-monitor.js?v=1')); ?>"></script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>