<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$teacherId = current_user_id();
$attemptId = isset($_GET['attempt_id']) ? (int) $_GET['attempt_id'] : 0;

function review_question_label($type)
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

function review_normalize_answer($value)
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value);
    return $value;
}

function review_percent($score, $total)
{
    if ((float) $total <= 0) {
        return 0;
    }

    return round(((float) $score / (float) $total) * 100, 1);
}

function review_word_count($value)
{
    $value = trim($value);

    if ($value === '') {
        return 0;
    }

    $words = preg_split('/\s+/', $value);
    return count($words);
}

function set_review_flash($type, $message)
{
    $_SESSION['review_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

$stmt = $pdo->prepare("
    SELECT 
        ea.id AS attempt_id,
        ea.exam_id,
        ea.student_id,
        ea.score,
        ea.total_points_at_time,
        ea.attempt_status,
        ea.review_status,
        ea.violation_count,
        ea.started_at,
        ea.submitted_at,
        e.title,
        e.subject,
        e.description,
        u.full_name,
        u.username,
        u.email
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    INNER JOIN users u ON ea.student_id = u.id
    WHERE ea.id = ?
    AND e.teacher_id = ?
    AND ea.attempt_status IN ('submitted', 'auto_submitted')
    LIMIT 1
");
$stmt->execute([$attemptId, $teacherId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    redirect_to('teacher/results.php');
}

$examId = (int) $attempt['exam_id'];

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
        $qid = (int) $choice['question_id'];

        if (!isset($choiceMap[$qid])) {
            $choiceMap[$qid] = [];
        }

        $choiceMap[$qid][] = $choice;

        if ((int) $choice['is_correct'] === 1) {
            $correctChoiceMap[$qid] = $choice;
        }
    }
}

$stmt = $pdo->prepare("
    SELECT id, question_id, choice_id, answer_text, points_awarded, teacher_feedback, checked_at
    FROM student_answers
    WHERE attempt_id = ?
");
$stmt->execute([$attemptId]);
$answerRows = $stmt->fetchAll();

$answerMap = [];

foreach ($answerRows as $answer) {
    $answerMap[(int) $answer['question_id']] = $answer;
}

$token = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $action = isset($_POST['action']) ? clean_input($_POST['action']) : '';

    if (!verify_csrf_token($postedToken)) {
        set_review_flash('error', 'Invalid request. Please try again.');
        redirect_to('teacher/review_attempt.php?attempt_id=' . $attemptId);
    }

    if ($action === 'save_review') {
        $manualScores = isset($_POST['manual_scores']) && is_array($_POST['manual_scores']) ? $_POST['manual_scores'] : [];
        $feedbacks = isset($_POST['feedbacks']) && is_array($_POST['feedbacks']) ? $_POST['feedbacks'] : [];
        $reviewStatus = isset($_POST['review_status']) ? clean_input($_POST['review_status']) : 'cleared';

        if (!in_array($reviewStatus, ['normal', 'flagged', 'under_review', 'cleared', 'invalidated'])) {
            $reviewStatus = 'cleared';
        }

        $autoScore = 0;
        $manualScore = 0;

        try {
            $pdo->beginTransaction();

            foreach ($questions as $question) {
                $qid = (int) $question['id'];
                $questionType = $question['question_type'];
                $points = (float) $question['points'];
                $answer = isset($answerMap[$qid]) ? $answerMap[$qid] : null;

                if ($questionType === 'multiple_choice' || $questionType === 'true_false') {
                    if ($answer && $answer['choice_id']) {
                        $selectedChoiceId = (int) $answer['choice_id'];

                        if (isset($choiceMap[$qid])) {
                            foreach ($choiceMap[$qid] as $choice) {
                                if ((int) $choice['id'] === $selectedChoiceId && (int) $choice['is_correct'] === 1) {
                                    $autoScore += $points;
                                }
                            }
                        }
                    }
                }

                if ($questionType === 'identification') {
                    $studentAnswer = $answer ? $answer['answer_text'] : '';
                    $correctAnswer = isset($correctChoiceMap[$qid]) ? $correctChoiceMap[$qid]['choice_text'] : '';

                    if ($studentAnswer !== '' && review_normalize_answer($studentAnswer) === review_normalize_answer($correctAnswer)) {
                        $autoScore += $points;
                    }
                }

                if ($questionType === 'essay') {
                    $scoreValue = isset($manualScores[$qid]) ? (float) $manualScores[$qid] : 0;
                    $feedbackValue = isset($feedbacks[$qid]) ? clean_input($feedbacks[$qid]) : '';

                    if ($scoreValue < 0) {
                        $scoreValue = 0;
                    }

                    if ($scoreValue > $points) {
                        $scoreValue = $points;
                    }

                    $manualScore += $scoreValue;

                    if ($answer) {
                        $stmt = $pdo->prepare("
                            UPDATE student_answers
                            SET points_awarded = ?, teacher_feedback = ?, checked_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $scoreValue,
                            $feedbackValue,
                            $answer['id']
                        ]);
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO student_answers
                            (attempt_id, question_id, choice_id, answer_text, is_correct, points_awarded, teacher_feedback, checked_at, answered_at)
                            VALUES (?, ?, NULL, '', 0, ?, ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $attemptId,
                            $qid,
                            $scoreValue,
                            $feedbackValue
                        ]);
                    }
                }
            }

            $finalScore = $autoScore + $manualScore;

            if ($reviewStatus === 'invalidated') {
                $finalScore = 0;
            }

            $stmt = $pdo->prepare("
                UPDATE exam_attempts
                SET score = ?, review_status = ?
                WHERE id = ?
                AND exam_id = ?
            ");
            $stmt->execute([
                $finalScore,
                $reviewStatus,
                $attemptId,
                $examId
            ]);

            $pdo->commit();

            set_review_flash('success', 'Attempt review saved successfully.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            set_review_flash('error', 'Unable to save review. Please check if teacher_feedback and checked_at columns exist in student_answers.');
        }

        redirect_to('teacher/review_attempt.php?attempt_id=' . $attemptId);
    }

    set_review_flash('error', 'Invalid action.');
    redirect_to('teacher/review_attempt.php?attempt_id=' . $attemptId);
}

$flash = null;

if (isset($_SESSION['review_flash'])) {
    $flash = $_SESSION['review_flash'];
    unset($_SESSION['review_flash']);
}

$totalQuestions = count($questions);
$essayCount = 0;
$autoCheckedCount = 0;

foreach ($questions as $question) {
    if ($question['question_type'] === 'essay') {
        $essayCount++;
    } else {
        $autoCheckedCount++;
    }
}

$currentPercent = review_percent($attempt['score'], $attempt['total_points_at_time']);

$pageTitle = 'Review Attempt';
$panelLabel = 'Teacher Panel';
$activePage = 'results';
$extraStyles = ['assets/css/teacher-results.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="review-hero">
    <div>
        <span>Attempt Review</span>
        <h2><?php echo e($attempt['title']); ?></h2>
        <p><?php echo e($attempt['full_name']); ?> • <?php echo e($attempt['subject']); ?></p>
    </div>

    <div class="review-score-box">
        <strong><?php echo e($currentPercent); ?>%</strong>
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

<section class="dashboard-grid teacher-result-stats">
    <div class="dashboard-card">
        <span>Total Questions</span>
        <h3><?php echo e($totalQuestions); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Auto-Checked</span>
        <h3><?php echo e($autoCheckedCount); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>Essay Items</span>
        <h3><?php echo e($essayCount); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>Violations</span>
        <h3><?php echo e($attempt['violation_count']); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Review Status</span>
        <h3><?php echo e(ucwords(str_replace('_', ' ', $attempt['review_status']))); ?></h3>
    </div>
</section>

<section class="review-layout">
    <div class="content-card">
        <div class="section-heading">
            <div>
                <span>Student Submission</span>
                <h2>Check answers and score essays manually</h2>
            </div>
        </div>

        <form method="POST" class="review-form">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="save_review">

            <div class="review-question-list">
                <?php foreach ($questions as $index => $question): ?>
                    <?php
                        $qid = (int) $question['id'];
                        $questionType = $question['question_type'];
                        $answer = isset($answerMap[$qid]) ? $answerMap[$qid] : null;
                        $studentAnswerText = 'No answer submitted';
                        $correctAnswerText = 'Manual checking';
                        $answerClass = 'pending';
                        $answerStatus = 'For Review';
                        $wordCount = 0;

                        if ($questionType === 'multiple_choice' || $questionType === 'true_false') {
                            $correctAnswerText = isset($correctChoiceMap[$qid]) ? $correctChoiceMap[$qid]['choice_text'] : 'No answer guide';

                            if ($answer && $answer['choice_id']) {
                                $selectedChoiceId = (int) $answer['choice_id'];

                                if (isset($choiceMap[$qid])) {
                                    foreach ($choiceMap[$qid] as $choice) {
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
                            $correctAnswerText = isset($correctChoiceMap[$qid]) ? $correctChoiceMap[$qid]['choice_text'] : 'No answer guide';

                            if ($answer && $answer['answer_text'] !== '') {
                                $studentAnswerText = $answer['answer_text'];

                                if (review_normalize_answer($studentAnswerText) === review_normalize_answer($correctAnswerText)) {
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
                            $correctAnswerText = 'No correct answer. Teacher manual checking required.';
                            $answerClass = 'pending';
                            $answerStatus = 'Manual Check';

                            if ($answer && $answer['answer_text'] !== '') {
                                $studentAnswerText = $answer['answer_text'];
                                $wordCount = review_word_count($studentAnswerText);
                            }
                        }

                        $manualValue = '';
                        $feedbackValue = '';

                        if ($answer) {
                            $manualValue = $answer['points_awarded'];
                            $feedbackValue = $answer['teacher_feedback'];
                        }
                    ?>

                    <article class="review-question-card <?php echo e($answerClass); ?>">
                        <div class="review-question-top">
                            <div>
                                <span>Question <?php echo e($index + 1); ?> • <?php echo e(review_question_label($questionType)); ?></span>
                                <h3><?php echo e($question['question_text']); ?></h3>
                            </div>

                            <div class="answer-status-pill <?php echo e($answerClass); ?>">
                                <?php echo e($answerStatus); ?>
                            </div>
                        </div>

                        <div class="review-answer-grid">
                            <div>
                                <span>Student Answer</span>
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

                        <?php if ($questionType === 'essay'): ?>
                            <div class="essay-review-note">
                                <strong>Essay Word Count:</strong> <?php echo e($wordCount); ?> words
                            </div>

                            <div class="manual-score-box">
                                <div class="form-group">
                                    <label for="manual_score_<?php echo e($qid); ?>">Manual Score</label>
                                    <input 
                                        type="number" 
                                        id="manual_score_<?php echo e($qid); ?>" 
                                        name="manual_scores[<?php echo e($qid); ?>]" 
                                        min="0" 
                                        max="<?php echo e($question['points']); ?>" 
                                        step="0.01" 
                                        value="<?php echo e($manualValue); ?>"
                                        placeholder="0"
                                    >
                                    <small>Maximum: <?php echo e($question['points']); ?> points</small>
                                </div>

                                <div class="form-group">
                                    <label for="feedback_<?php echo e($qid); ?>">Teacher Feedback</label>
                                    <textarea 
                                        id="feedback_<?php echo e($qid); ?>" 
                                        name="feedbacks[<?php echo e($qid); ?>]" 
                                        placeholder="Optional feedback for this essay answer"
                                    ><?php echo e($feedbackValue); ?></textarea>
                                </div>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="content-card review-final-card">
                <div class="section-heading">
                    <div>
                        <span>Finalize Review</span>
                        <h2>Set final status and save score</h2>
                    </div>
                </div>

                <div class="form-group">
                    <label for="review_status">Final Review Status</label>
                    <select id="review_status" name="review_status">
                        <option value="normal" <?php echo $attempt['review_status'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                        <option value="under_review" <?php echo $attempt['review_status'] === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                        <option value="flagged" <?php echo $attempt['review_status'] === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                        <option value="cleared" <?php echo $attempt['review_status'] === 'cleared' ? 'selected' : ''; ?>>Cleared</option>
                        <option value="invalidated" <?php echo $attempt['review_status'] === 'invalidated' ? 'selected' : ''; ?>>Invalidated</option>
                    </select>
                </div>

                <div class="review-final-warning">
                    Saving this review will recompute the final score from auto-checked answers plus essay manual scores. If you mark the attempt as invalidated, the final score will become 0.
                </div>

                <div class="form-actions">
                    <a href="results.php" class="secondary-action">Back to Results</a>
                    <button type="submit" class="primary-button">Save Review</button>
                </div>
            </div>
        </form>
    </div>

    <aside class="content-card review-side-card">
        <div class="section-heading">
            <div>
                <span>Attempt Summary</span>
                <h2>Review Details</h2>
            </div>
        </div>

        <div class="review-summary-list">
            <div>
                <span>Student</span>
                <strong><?php echo e($attempt['full_name']); ?></strong>
            </div>

            <div>
                <span>Username</span>
                <strong><?php echo e($attempt['username']); ?></strong>
            </div>

            <div>
                <span>Email</span>
                <strong><?php echo $attempt['email'] !== '' ? e($attempt['email']) : 'No email'; ?></strong>
            </div>

            <div>
                <span>Started</span>
                <strong><?php echo e(date('M d, Y h:i A', strtotime($attempt['started_at']))); ?></strong>
            </div>

            <div>
                <span>Submitted</span>
                <strong><?php echo e(date('M d, Y h:i A', strtotime($attempt['submitted_at']))); ?></strong>
            </div>

            <div>
                <span>Attempt Status</span>
                <strong><?php echo e(ucwords(str_replace('_', ' ', $attempt['attempt_status']))); ?></strong>
            </div>

            <div>
                <span>Current Score</span>
                <strong><?php echo e($attempt['score']); ?> / <?php echo e($attempt['total_points_at_time']); ?></strong>
            </div>

            <div>
                <span>Violations</span>
                <strong><?php echo e($attempt['violation_count']); ?></strong>
            </div>
        </div>
    </aside>
</section>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>