<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$studentId = current_user_id();
$examId = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;

function set_exam_instruction_flash($type, $message)
{
    $_SESSION['exam_instruction_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function instruction_status_label($exam)
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

function instruction_status_class($label)
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

    return 'neutral';
}

$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.teacher_id,
        e.title,
        e.subject,
        e.description,
        e.duration_minutes,
        e.start_datetime,
        e.end_datetime,
        e.total_points,
        e.randomize_questions,
        e.show_result,
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
    AND e.id = ?
    AND e.status != 'archived'
    LIMIT 1
");
$stmt->execute([$studentId, $studentId, $studentId, $examId]);
$exam = $stmt->fetch();

if (!$exam) {
    redirect_to('student/exams.php');
}

$token = csrf_token();
$statusLabel = instruction_status_label($exam);
$statusClass = instruction_status_class($statusLabel);
$attemptCount = (int) $exam['attempt_count'];
$maxAttempts = (int) $exam['max_attempts'];
$questionCount = (int) $exam['question_count'];
$totalPoints = (float) $exam['total_points'];

$canStart = false;
$canContinue = false;
$blockReason = '';

if ($statusLabel === 'Available Now') {
    $canStart = true;
}

if ($statusLabel === 'In Progress') {
    $canContinue = true;
}

if ($questionCount <= 0) {
    $canStart = false;
    $canContinue = false;
    $blockReason = 'This exam is not ready yet because no questions have been added.';
}

if ($attemptCount >= $maxAttempts && !$canContinue && $statusLabel !== 'Completed') {
    $canStart = false;
    $blockReason = 'You have reached the maximum number of attempts for this exam.';
}

if ($statusLabel === 'Upcoming') {
    $blockReason = 'This exam is not yet available. Please come back during the scheduled time.';
}

if ($statusLabel === 'Closed') {
    $blockReason = 'This exam is already closed.';
}

if ($statusLabel === 'Completed') {
    $blockReason = 'You have already submitted this exam.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $action = isset($_POST['action']) ? clean_input($_POST['action']) : '';

    if (!verify_csrf_token($postedToken)) {
        set_exam_instruction_flash('error', 'Invalid request. Please try again.');
        redirect_to('student/exam_instructions.php?exam_id=' . $examId);
    }

    if ($action === 'start_exam') {
        $stmt = $pdo->prepare("
            SELECT id
            FROM exam_attempts
            WHERE exam_id = ?
            AND student_id = ?
            AND attempt_status = 'in_progress'
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$examId, $studentId]);
        $activeAttempt = $stmt->fetch();

        if ($activeAttempt) {
            redirect_to('student/take_exam.php?attempt_id=' . $activeAttempt['id']);
        }

        $freshStatus = instruction_status_label($exam);

        if ($freshStatus !== 'Available Now') {
            set_exam_instruction_flash('error', 'This exam is not available right now.');
            redirect_to('student/exam_instructions.php?exam_id=' . $examId);
        }

        if ($questionCount <= 0) {
            set_exam_instruction_flash('error', 'This exam has no questions yet.');
            redirect_to('student/exam_instructions.php?exam_id=' . $examId);
        }

        if ($attemptCount >= $maxAttempts) {
            set_exam_instruction_flash('error', 'You have reached the maximum number of attempts for this exam.');
            redirect_to('student/exam_instructions.php?exam_id=' . $examId);
        }

        $stmt = $pdo->prepare("
            INSERT INTO exam_attempts
            (
                exam_id,
                student_id,
                attempt_status,
                review_status,
                score,
                total_points_at_time,
                violation_count,
                started_at
            )
            VALUES (?, ?, 'in_progress', 'normal', 0, ?, 0, NOW())
        ");

        $stmt->execute([
            $examId,
            $studentId,
            $totalPoints
        ]);

        $attemptId = (int) $pdo->lastInsertId();

        redirect_to('student/take_exam.php?attempt_id=' . $attemptId);
    }

    if ($action === 'continue_exam') {
        if ((int) $exam['attempt_id'] > 0 && $exam['attempt_status'] === 'in_progress') {
            redirect_to('student/take_exam.php?attempt_id=' . $exam['attempt_id']);
        }

        set_exam_instruction_flash('error', 'No active exam attempt was found.');
        redirect_to('student/exam_instructions.php?exam_id=' . $examId);
    }
}

$flash = null;

if (isset($_SESSION['exam_instruction_flash'])) {
    $flash = $_SESSION['exam_instruction_flash'];
    unset($_SESSION['exam_instruction_flash']);
}

$pageTitle = 'Exam Instructions';
$panelLabel = 'Student Panel';
$activePage = 'exams';
$extraStyles = ['assets/css/exam-instructions.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="instruction-hero">
    <div>
        <span>Before You Begin</span>
        <h2><?php echo e($exam['title']); ?></h2>
        <p>
            <?php echo $exam['subject'] !== '' ? e($exam['subject']) : 'Review the exam details carefully before starting.'; ?>
        </p>
    </div>

    <div class="instruction-status <?php echo e($statusClass); ?>">
        <?php echo e($statusLabel); ?>
    </div>
</section>

<?php if ($flash): ?>
    <?php if ($flash['type'] === 'success'): ?>
        <div class="alert-success"><?php echo e($flash['message']); ?></div>
    <?php else: ?>
        <div class="alert-error"><?php echo e($flash['message']); ?></div>
    <?php endif; ?>
<?php endif; ?>

<section class="instruction-layout">
    <div class="content-card instruction-main-card">
        <div class="section-heading">
            <div>
                <span>Exam Details</span>
                <h2>Check your schedule and requirements</h2>
            </div>
        </div>

        <?php if ($exam['description'] !== ''): ?>
            <div class="instruction-description">
                <?php echo e($exam['description']); ?>
            </div>
        <?php endif; ?>

        <div class="instruction-info-grid">
            <div>
                <span>Start Time</span>
                <strong><?php echo e(date('M d, Y h:i A', strtotime($exam['start_datetime']))); ?></strong>
            </div>

            <div>
                <span>End Time</span>
                <strong><?php echo e(date('M d, Y h:i A', strtotime($exam['end_datetime']))); ?></strong>
            </div>

            <div>
                <span>Duration</span>
                <strong><?php echo e($exam['duration_minutes']); ?> minutes</strong>
            </div>

            <div>
                <span>Questions</span>
                <strong><?php echo e($questionCount); ?></strong>
            </div>

            <div>
                <span>Total Points</span>
                <strong><?php echo e($exam['total_points']); ?></strong>
            </div>

            <div>
                <span>Attempts</span>
                <strong><?php echo e($attemptCount); ?> / <?php echo e($maxAttempts); ?></strong>
            </div>
        </div>

        <div class="rules-card">
            <span>Exam Rules</span>

            <ul>
                <li>Read each question carefully before answering.</li>
                <li>Do not refresh, close, or leave the exam page while taking the exam.</li>
                <li>Make sure your device has enough battery and a stable internet connection.</li>
                <li>Answers may be saved during the exam, but final submission is required.</li>
                <li>Essay answers will be checked manually by your teacher.</li>
            </ul>
        </div>
    </div>

    <aside class="content-card instruction-side-card">
        <div class="side-block">
            <span>Proctoring Setup</span>

            <div class="requirement-list">
                <div>
                    <strong>Webcam</strong>
                    <span><?php echo (int) $exam['webcam_required'] === 1 ? 'Required' : 'Not Required'; ?></span>
                </div>

                <div>
                    <strong>Fullscreen</strong>
                    <span><?php echo (int) $exam['fullscreen_required'] === 1 ? 'Required' : 'Not Required'; ?></span>
                </div>

                <div>
                    <strong>Question Order</strong>
                    <span><?php echo (int) $exam['randomize_questions'] === 1 ? 'Randomized' : 'Fixed'; ?></span>
                </div>

                <div>
                    <strong>Result Viewing</strong>
                    <span><?php echo (int) $exam['show_result'] === 1 ? 'Allowed' : 'Teacher Controlled'; ?></span>
                </div>
            </div>
        </div>

        <?php if ($blockReason !== ''): ?>
            <div class="blocked-note">
                <?php echo e($blockReason); ?>
            </div>
        <?php endif; ?>

        <div class="instruction-actions">
            <?php if ($canContinue): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
                    <input type="hidden" name="action" value="continue_exam">
                    <button type="submit" class="primary-button full-button">Continue Exam</button>
                </form>
            <?php elseif ($canStart): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
                    <input type="hidden" name="action" value="start_exam">
                    <button type="submit" class="primary-button full-button">Start Exam</button>
                </form>
            <?php elseif ($statusLabel === 'Completed'): ?>
                <a href="result.php?exam_id=<?php echo e($examId); ?>" class="primary-button full-button">View Result</a>
            <?php else: ?>
                <button type="button" class="disabled-button" disabled>Exam Unavailable</button>
            <?php endif; ?>

            <a href="exams.php" class="secondary-action full-button">Back to Assigned Exams</a>
        </div>
    </aside>
</section>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>