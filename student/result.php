<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$studentId = current_user_id();
$examId = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;

function result_question_label($type)
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

function result_normalize_answer($value)
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value);
    return $value;
}

function result_percent($score, $total)
{
    if ((float) $total <= 0) {
        return 0;
    }

    return round(((float) $score / (float) $total) * 100, 1);
}

function result_status_label($status)
{
    return ucwords(str_replace('_', ' ', $status));
}

$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.title,
        e.subject,
        e.description,
        e.total_points,
        e.show_result,
        ea.id AS attempt_id,
        ea.score,
        ea.total_points_at_time,
        ea.attempt_status,
        ea.review_status,
        ea.violation_count,
        ea.started_at,
        ea.submitted_at
    FROM exams e
    INNER JOIN exam_students es ON es.exam_id = e.id
    INNER JOIN exam_attempts ea ON ea.id = (
        SELECT ea2.id
        FROM exam_attempts ea2
        WHERE ea2.exam_id = e.id
        AND ea2.student_id = ?
        AND ea2.attempt_status IN ('submitted', 'auto_submitted')
        ORDER BY ea2.id DESC
        LIMIT 1
    )
    WHERE e.id = ?
    AND es.student_id = ?
    LIMIT 1
");
$stmt->execute([$studentId, $examId, $studentId]);
$result = $stmt->fetch();

if (!$result) {
    redirect_to('student/exams.php');
}

$attemptId = (int) $result['attempt_id'];

$stmt = $pdo->prepare("
    SELECT id, question_text, question_type, points, position
    FROM questions
    WHERE exam_id = ?
    ORDER BY position ASC, id ASC
");
$stmt->execute([$examId]);
$questions = $stmt->fetchAll();

$questionIds = [];

foreach ($questions as $question) {
    $questionIds[] = (int) $question['id'];
}

$choiceMap = [];
$correctChoiceMap = [];

if (count($questionIds) > 0) {
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

    $stmt = $pdo->prepare("
        SELECT id, question_id, choice_text, is_correct, position
        FROM choices
        WHERE question_id IN ($placeholders)
        ORDER BY question_id ASC, position ASC
    ");
    $stmt->execute($questionIds);
    $choices = $stmt->fetchAll();

    foreach ($choices as $choice) {
        $questionId = (int) $choice['question_id'];

        if (!isset($choiceMap[$questionId])) {
            $choiceMap[$questionId] = [];
        }

        $choiceMap[$questionId][] = $choice;

        if ((int) $choice['is_correct'] === 1) {
            $correctChoiceMap[$questionId] = $choice;
        }
    }
}

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

$totalQuestions = count($questions);
$answeredCount = count($answerMap);
$essayCount = 0;
$essayPoints = 0;
$autoCheckedPoints = 0;

foreach ($questions as $question) {
    if ($question['question_type'] === 'essay') {
        $essayCount++;
        $essayPoints += (float) $question['points'];
    } else {
        $autoCheckedPoints += (float) $question['points'];
    }
}

$score = (float) $result['score'];
$totalPoints = (float) $result['total_points_at_time'];
$scorePercent = result_percent($score, $totalPoints);

$canViewDetailedResult = false;

if ((int) $result['show_result'] === 1) {
    $canViewDetailedResult = true;
}

if (in_array($result['review_status'], ['normal', 'cleared', 'under_review'])) {
    $canViewDetailedResult = true;
}

$pageTitle = 'Result';
$panelLabel = 'Student Panel';
$activePage = 'results';
$extraStyles = ['assets/css/student-result.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="result-hero">
    <div>
        <span>Exam Result</span>
        <h2><?php echo e($result['title']); ?></h2>
        <p>
            <?php echo $result['subject'] !== '' ? e($result['subject']) : 'Your submitted exam summary and answer review.'; ?>
        </p>
    </div>

    <div class="result-score-circle">
        <strong><?php echo e($scorePercent); ?>%</strong>
        <span>Current Score</span>
    </div>
</section>

<section class="dashboard-grid result-stats-grid">
    <div class="dashboard-card">
        <span>Score</span>
        <h3><?php echo e($score); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Total Points</span>
        <h3><?php echo e($totalPoints); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Answered</span>
        <h3><?php echo e($answeredCount); ?>/<?php echo e($totalQuestions); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>Violations</span>
        <h3><?php echo e($result['violation_count']); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Essay Items</span>
        <h3><?php echo e($essayCount); ?></h3>
    </div>
</section>

<section class="result-layout">
    <div class="content-card">
        <div class="section-heading">
            <div>
                <span>Submission Summary</span>
                <h2>Your exam has been submitted</h2>
            </div>
        </div>

        <div class="result-summary-grid">
            <div>
                <span>Attempt Status</span>
                <strong><?php echo e(result_status_label($result['attempt_status'])); ?></strong>
            </div>

            <div>
                <span>Review Status</span>
                <strong><?php echo e(result_status_label($result['review_status'])); ?></strong>
            </div>

            <div>
                <span>Started</span>
                <strong><?php echo e(date('M d, Y h:i A', strtotime($result['started_at']))); ?></strong>
            </div>

            <div>
                <span>Submitted</span>
                <strong><?php echo e(date('M d, Y h:i A', strtotime($result['submitted_at']))); ?></strong>
            </div>
        </div>

        <?php if ($essayCount > 0): ?>
            <div class="manual-note">
                This exam includes essay question/s worth <?php echo e($essayPoints); ?> point/s. These items require manual checking by your teacher, so your score may still be updated after review.
            </div>
        <?php endif; ?>

        <?php if ((int) $result['show_result'] !== 1): ?>
            <div class="controlled-note">
                Detailed result viewing is controlled by your teacher. You may still see your submission status and review progress here.
            </div>
        <?php endif; ?>

        <div class="score-bar-wrap">
            <div class="score-bar" style="width: <?php echo e($scorePercent); ?>%;"></div>
        </div>
    </div>

    <aside class="content-card result-side-card">
        <div class="section-heading">
            <div>
                <span>Next Step</span>
                <h2>What to do now</h2>
            </div>
        </div>

        <div class="next-step-list">
            <div>
                <strong>Wait for review</strong>
                <p>If your exam has essay items or review flags, your teacher may update your final score.</p>
            </div>

            <div>
                <strong>Check results later</strong>
                <p>Return to this page if your teacher updates your review status or score.</p>
            </div>
        </div>

        <div class="result-actions">
            <a href="exams.php" class="primary-button full-button">Back to Exams</a>
            <a href="dashboard.php" class="secondary-action full-button">Go to Dashboard</a>
        </div>
    </aside>
</section>

<?php if ($canViewDetailedResult): ?>
    <section class="content-card">
        <div class="section-heading">
            <div>
                <span>Answer Review</span>
                <h2>Your submitted answers</h2>
            </div>
        </div>

        <div class="answer-review-list">
            <?php if (count($questions) > 0): ?>
                <?php foreach ($questions as $index => $question): ?>
                    <?php
                        $questionId = (int) $question['id'];
                        $questionType = $question['question_type'];
                        $studentAnswer = isset($answerMap[$questionId]) ? $answerMap[$questionId] : null;
                        $correctChoice = isset($correctChoiceMap[$questionId]) ? $correctChoiceMap[$questionId] : null;

                        $studentAnswerText = 'No answer submitted';
                        $correctAnswerText = 'Manual checking';
                        $answerClass = 'pending';
                        $answerStatus = 'For Review';

                        if ($questionType === 'multiple_choice' || $questionType === 'true_false') {
                            if ($correctChoice) {
                                $correctAnswerText = $correctChoice['choice_text'];
                            }

                            if ($studentAnswer && $studentAnswer['choice_id']) {
                                $selectedChoiceId = (int) $studentAnswer['choice_id'];

                                if (isset($choiceMap[$questionId])) {
                                    foreach ($choiceMap[$questionId] as $choice) {
                                        if ((int) $choice['id'] === $selectedChoiceId) {
                                            $studentAnswerText = $choice['choice_text'];

                                            if ((int) $choice['is_correct'] === 1) {
                                                $answerClass = 'correct';
                                                $answerStatus = 'Correct';
                                            } else {
                                                $answerClass = 'incorrect';
                                                $answerStatus = 'Incorrect';
                                            }
                                        }
                                    }
                                }
                            } else {
                                $answerClass = 'incorrect';
                                $answerStatus = 'No Answer';
                            }
                        }

                        if ($questionType === 'identification') {
                            if ($correctChoice) {
                                $correctAnswerText = $correctChoice['choice_text'];
                            }

                            if ($studentAnswer && $studentAnswer['answer_text'] !== '') {
                                $studentAnswerText = $studentAnswer['answer_text'];

                                if ($correctChoice && result_normalize_answer($studentAnswerText) === result_normalize_answer($correctChoice['choice_text'])) {
                                    $answerClass = 'correct';
                                    $answerStatus = 'Correct';
                                } else {
                                    $answerClass = 'incorrect';
                                    $answerStatus = 'Incorrect';
                                }
                            } else {
                                $answerClass = 'incorrect';
                                $answerStatus = 'No Answer';
                            }
                        }

                        if ($questionType === 'essay') {
                            $correctAnswerText = 'No correct answer. Teacher will score manually.';

                            if ($studentAnswer && $studentAnswer['answer_text'] !== '') {
                                $studentAnswerText = $studentAnswer['answer_text'];
                            }

                            $answerClass = 'pending';
                            $answerStatus = 'Manual Checking';
                        }
                    ?>

                    <article class="answer-review-card <?php echo e($answerClass); ?>">
                        <div class="answer-review-top">
                            <div>
                                <span>Question <?php echo e($index + 1); ?> • <?php echo e(result_question_label($questionType)); ?></span>
                                <h3><?php echo e($question['question_text']); ?></h3>
                            </div>

                            <div class="answer-status-pill <?php echo e($answerClass); ?>">
                                <?php echo e($answerStatus); ?>
                            </div>
                        </div>

                        <div class="answer-detail-grid">
                            <div>
                                <span>Your Answer</span>
                                <p><?php echo e($studentAnswerText); ?></p>
                            </div>

                            <div>
                                <span>Answer Guide</span>
                                <p><?php echo e($correctAnswerText); ?></p>
                            </div>

                            <div>
                                <span>Points</span>
                                <p><?php echo e($question['points']); ?></p>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-panel">No questions found for this exam.</div>
            <?php endif; ?>
        </div>
    </section>
<?php else: ?>
    <section class="content-card">
        <div class="empty-panel">
            Detailed answer review is not available yet. Please wait for your teacher to release or finalize the result.
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>