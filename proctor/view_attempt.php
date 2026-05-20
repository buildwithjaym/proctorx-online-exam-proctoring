<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('proctor');

$proctorId = current_user_id();
$attemptId = isset($_GET['attempt_id']) ? (int) $_GET['attempt_id'] : 0;

function set_attempt_flash($type, $message)
{
    $_SESSION['attempt_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function attempt_status_label($status)
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

    return ucwords(str_replace('_', ' ', $status));
}

function attempt_percent($score, $total)
{
    if ((float) $total <= 0) {
        return 0;
    }

    return round(((float) $score / (float) $total) * 100, 1);
}

function question_type_label($type)
{
    if ($type === 'multiple_choice') {
        return 'Multiple Choice';
    }

    if ($type === 'true_false') {
        return 'True or False';
    }

    if ($type === 'identification') {
        return 'Identification';
    }

    if ($type === 'essay') {
        return 'Essay';
    }

    return 'Question';
}

function format_metadata($metadataJson)
{
    if ($metadataJson === '') {
        return 'No metadata recorded.';
    }

    $data = json_decode($metadataJson, true);

    if (!is_array($data)) {
        return $metadataJson;
    }

    $lines = [];

    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        $label = ucwords(str_replace('_', ' ', $key));
        $lines[] = $label . ': ' . $value;
    }

    if (count($lines) <= 0) {
        return 'No metadata recorded.';
    }

    return implode("\n", $lines);
}

$stmt = $pdo->prepare("
    SELECT 
        ea.id AS attempt_id,
        ea.exam_id,
        ea.student_id,
        ea.attempt_status,
        ea.review_status,
        ea.score,
        ea.total_points_at_time,
        ea.violation_count,
        ea.started_at,
        ea.submitted_at,
        ea.last_activity_at,
        e.title,
        e.subject,
        e.description,
        e.duration_minutes,
        e.start_datetime,
        e.end_datetime,
        e.webcam_required,
        e.fullscreen_required,
        e.status AS exam_status,
        teacher.full_name AS teacher_name,
        student.full_name AS student_name,
        student.username AS student_username,
        student.email AS student_email,
        student.status AS student_status
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    INNER JOIN exam_proctors ep ON ep.exam_id = e.id
    INNER JOIN users teacher ON e.teacher_id = teacher.id
    INNER JOIN users student ON ea.student_id = student.id
    WHERE ea.id = ?
    AND ep.proctor_id = ?
    LIMIT 1
");
$stmt->execute([$attemptId, $proctorId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    redirect_to('proctor/assigned_exams.php');
}

$examId = (int) $attempt['exam_id'];
$token = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $action = isset($_POST['action']) ? clean_input($_POST['action']) : '';

    if (!verify_csrf_token($postedToken)) {
        set_attempt_flash('error', 'Invalid request. Please try again.');
        redirect_to('proctor/view_attempt.php?attempt_id=' . $attemptId);
    }

    if ($action === 'flag_attempt') {
        $stmt = $pdo->prepare("
            UPDATE exam_attempts
            SET review_status = 'flagged'
            WHERE id = ?
        ");
        $stmt->execute([$attemptId]);

        set_attempt_flash('success', 'Attempt has been flagged for teacher review.');
        redirect_to('proctor/view_attempt.php?attempt_id=' . $attemptId);
    }

    if ($action === 'mark_under_review') {
        $stmt = $pdo->prepare("
            UPDATE exam_attempts
            SET review_status = 'under_review'
            WHERE id = ?
        ");
        $stmt->execute([$attemptId]);

        set_attempt_flash('success', 'Attempt has been marked under review.');
        redirect_to('proctor/view_attempt.php?attempt_id=' . $attemptId);
    }

    if ($action === 'clear_attempt') {
        $stmt = $pdo->prepare("
            UPDATE exam_attempts
            SET review_status = 'cleared'
            WHERE id = ?
        ");
        $stmt->execute([$attemptId]);

        set_attempt_flash('success', 'Attempt has been cleared.');
        redirect_to('proctor/view_attempt.php?attempt_id=' . $attemptId);
    }

    set_attempt_flash('error', 'Invalid action.');
    redirect_to('proctor/view_attempt.php?attempt_id=' . $attemptId);
}

$flash = null;

if (isset($_SESSION['attempt_flash'])) {
    $flash = $_SESSION['attempt_flash'];
    unset($_SESSION['attempt_flash']);
}

$stmt = $pdo->prepare("
    SELECT id, question_text, question_type, points, position
    FROM questions
    WHERE exam_id = ?
    ORDER BY position ASC, id ASC
");
$stmt->execute([$examId]);
$questions = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT question_id, choice_id, answer_text
    FROM student_answers
    WHERE attempt_id = ?
");
$stmt->execute([$attemptId]);
$answers = $stmt->fetchAll();

$answerMap = [];

foreach ($answers as $answer) {
    $answerMap[(int) $answer['question_id']] = $answer;
}

$stmt = $pdo->prepare("
    SELECT 
        id,
        event_type,
        severity,
        event_description,
        metadata_json,
        created_at
    FROM proctor_logs
    WHERE attempt_id = ?
    ORDER BY created_at DESC, id DESC
");
$stmt->execute([$attemptId]);
$logs = $stmt->fetchAll();

$totalQuestions = count($questions);
$answeredCount = count($answerMap);
$unansweredCount = $totalQuestions - $answeredCount;

if ($unansweredCount < 0) {
    $unansweredCount = 0;
}

$highCount = 0;
$mediumCount = 0;
$lowCount = 0;

foreach ($logs as $log) {
    if ($log['severity'] === 'high') {
        $highCount++;
    }

    if ($log['severity'] === 'medium') {
        $mediumCount++;
    }

    if ($log['severity'] === 'low') {
        $lowCount++;
    }
}

$scorePercent = attempt_percent($attempt['score'], $attempt['total_points_at_time']);

$pageTitle = 'View Attempt';
$panelLabel = 'Proctor Panel';
$activePage = 'monitoring';
$extraStyles = ['assets/css/proctor-view-attempt.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="attempt-hero">
    <div>
        <span>Attempt Activity</span>
        <h2><?php echo e($attempt['student_name']); ?></h2>
        <p>
            <?php echo e($attempt['title']); ?>
            <?php if ($attempt['subject'] !== ''): ?>
                • <?php echo e($attempt['subject']); ?>
            <?php endif; ?>
        </p>
    </div>

    <div class="attempt-score-box">
        <strong><?php echo e($scorePercent); ?>%</strong>
        <span><?php echo e($attempt['score']); ?> / <?php echo e($attempt['total_points_at_time']); ?></span>
    </div>
</section>

<?php if ($flash): ?>
    <?php if ($flash['type'] === 'success'): ?>
        <div class="alert-success"><?php echo e($flash['message']); ?></div>
    <?php else: ?>
        <div class="alert-error"><?php echo e($flash['message']); ?></div>
    <?php endif; ?>
<?php endif; ?>

<section class="dashboard-grid attempt-stats-grid">
    <div class="dashboard-card">
        <span>Attempt Status</span>
        <h3><?php echo e(attempt_status_label($attempt['attempt_status'])); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>Violations</span>
        <h3><?php echo e($attempt['violation_count']); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Answered</span>
        <h3><?php echo e($answeredCount); ?>/<?php echo e($totalQuestions); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>High Severity</span>
        <h3><?php echo e($highCount); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Review Status</span>
        <h3><?php echo e(ucwords(str_replace('_', ' ', $attempt['review_status']))); ?></h3>
    </div>
</section>

<section class="attempt-layout">
    <div class="content-card">
        <div class="section-heading">
            <div>
                <span>Activity Timeline</span>
                <h2>Proctoring events recorded for this attempt</h2>
            </div>

            <a class="secondary-action" href="monitor_exam.php?exam_id=<?php echo e($examId); ?>">
                Back to Monitor
            </a>
        </div>

        <div class="attempt-tools">
            <input type="text" id="attemptLogSearch" placeholder="Search event type, severity, or description">

            <select id="attemptLogFilter">
                <option value="all">All Events</option>
                <option value="high">High Severity</option>
                <option value="medium">Medium Severity</option>
                <option value="low">Low Severity</option>
            </select>

            <button type="button" id="refreshAttemptBtn">Refresh</button>
        </div>

        <div class="attempt-log-list" id="attemptLogList">
            <?php if (count($logs) > 0): ?>
                <?php foreach ($logs as $log): ?>
                    <article class="attempt-log-card" data-severity="<?php echo e($log['severity']); ?>">
                        <div class="attempt-log-top">
                            <div>
                                <span class="severity-pill <?php echo e($log['severity']); ?>">
                                    <?php echo e(ucfirst($log['severity'])); ?>
                                </span>

                                <h3><?php echo e($log['event_description']); ?></h3>

                                <p>
                                    Event: <?php echo e(ucwords(str_replace('_', ' ', $log['event_type']))); ?>
                                </p>
                            </div>

                            <div class="event-time">
                                <?php echo e(date('M d, Y h:i A', strtotime($log['created_at']))); ?>
                            </div>
                        </div>

                        <pre><?php echo e(format_metadata($log['metadata_json'])); ?></pre>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-panel">
                    No proctoring events recorded for this attempt yet.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="content-card attempt-side-card">
        <div class="section-heading">
            <div>
                <span>Attempt Summary</span>
                <h2>Student and exam details</h2>
            </div>
        </div>

        <div class="attempt-summary-list">
            <div>
                <span>Student</span>
                <strong><?php echo e($attempt['student_name']); ?></strong>
            </div>

            <div>
                <span>Username</span>
                <strong><?php echo e($attempt['student_username']); ?></strong>
            </div>

            <div>
                <span>Email</span>
                <strong><?php echo $attempt['student_email'] !== '' ? e($attempt['student_email']) : 'No email'; ?></strong>
            </div>

            <div>
                <span>Teacher</span>
                <strong><?php echo e($attempt['teacher_name']); ?></strong>
            </div>

            <div>
                <span>Started</span>
                <strong><?php echo $attempt['started_at'] ? e(date('M d, Y h:i A', strtotime($attempt['started_at']))) : 'Not started'; ?></strong>
            </div>

            <div>
                <span>Submitted</span>
                <strong><?php echo $attempt['submitted_at'] ? e(date('M d, Y h:i A', strtotime($attempt['submitted_at']))) : 'Not submitted'; ?></strong>
            </div>

            <div>
                <span>Last Activity</span>
                <strong><?php echo $attempt['last_activity_at'] ? e(date('M d, Y h:i A', strtotime($attempt['last_activity_at']))) : 'No activity'; ?></strong>
            </div>

            <div>
                <span>Proctoring Setup</span>
                <strong>
                    <?php if ((int) $attempt['webcam_required'] === 1 && (int) $attempt['fullscreen_required'] === 1): ?>
                        Webcam + Fullscreen
                    <?php elseif ((int) $attempt['webcam_required'] === 1): ?>
                        Webcam
                    <?php elseif ((int) $attempt['fullscreen_required'] === 1): ?>
                        Fullscreen
                    <?php else: ?>
                        Standard
                    <?php endif; ?>
                </strong>
            </div>
        </div>

        <div class="attempt-action-panel">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
                <input type="hidden" name="action" value="mark_under_review">
                <button type="submit" class="attempt-btn review-btn">Mark Under Review</button>
            </form>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
                <input type="hidden" name="action" value="flag_attempt">
                <button type="submit" class="attempt-btn danger-btn">Flag Attempt</button>
            </form>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
                <input type="hidden" name="action" value="clear_attempt">
                <button type="submit" class="attempt-btn clear-btn">Clear Attempt</button>
            </form>
        </div>
    </aside>
</section>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Answer Progress</span>
            <h2>Question completion overview</h2>
        </div>
    </div>

    <div class="answer-progress-grid">
        <?php if (count($questions) > 0): ?>
            <?php foreach ($questions as $index => $question): ?>
                <?php
                    $questionId = (int) $question['id'];
                    $isAnswered = isset($answerMap[$questionId]);
                ?>

                <div class="answer-progress-card <?php echo $isAnswered ? 'answered' : 'unanswered'; ?>">
                    <span>
                        Question <?php echo e($index + 1); ?> • <?php echo e(question_type_label($question['question_type'])); ?>
                    </span>

                    <h3><?php echo e($question['points']); ?> pts</h3>

                    <p><?php echo $isAnswered ? 'Answered' : 'Not answered yet'; ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-panel">No questions found for this exam.</div>
        <?php endif; ?>
    </div>
</section>

<script src="<?php echo e(app_url('assets/js/proctor-view-attempt.js?v=1')); ?>"></script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>