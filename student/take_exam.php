<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$studentId = current_user_id();
$attemptId = isset($_GET['attempt_id']) ? (int) $_GET['attempt_id'] : 0;

function take_exam_question_label($type)
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

function take_exam_strip_choice_label($choiceText)
{
    if (preg_match('/^[A-D]\.\s(.+)$/', $choiceText, $matches)) {
        return $choiceText;
    }

    return $choiceText;
}

$stmt = $pdo->prepare("
    SELECT 
        ea.id AS attempt_id,
        ea.exam_id,
        ea.student_id,
        ea.attempt_status,
        ea.started_at,
        ea.total_points_at_time,
        e.title,
        e.subject,
        e.description,
        e.duration_minutes,
        e.start_datetime,
        e.end_datetime,
        e.randomize_questions,
        e.webcam_required,
        e.fullscreen_required,
        e.status
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    WHERE ea.id = ?
    AND ea.student_id = ?
    LIMIT 1
");
$stmt->execute([$attemptId, $studentId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    redirect_to('student/exams.php');
}

if ($attempt['attempt_status'] === 'submitted' || $attempt['attempt_status'] === 'auto_submitted') {
    redirect_to('student/result.php?exam_id=' . $attempt['exam_id']);
}

if ($attempt['attempt_status'] !== 'in_progress') {
    redirect_to('student/exam_instructions.php?exam_id=' . $attempt['exam_id']);
}

if ($attempt['status'] !== 'published') {
    redirect_to('student/exam_instructions.php?exam_id=' . $attempt['exam_id']);
}

$examId = (int) $attempt['exam_id'];

$startedAt = strtotime($attempt['started_at']);
$durationEnd = $startedAt + ((int) $attempt['duration_minutes'] * 60);
$scheduleEnd = strtotime($attempt['end_datetime']);
$finalEnd = min($durationEnd, $scheduleEnd);
$remainingSeconds = $finalEnd - time();

if ($remainingSeconds < 0) {
    $remainingSeconds = 0;
}

if ((int) $attempt['randomize_questions'] === 1) {
    $stmt = $pdo->prepare("
        SELECT id, question_text, question_type, points, position
        FROM questions
        WHERE exam_id = ?
        ORDER BY MD5(CONCAT(id, ?))
    ");
    $stmt->execute([$examId, $attemptId]);
} else {
    $stmt = $pdo->prepare("
        SELECT id, question_text, question_type, points, position
        FROM questions
        WHERE exam_id = ?
        ORDER BY position ASC, id ASC
    ");
    $stmt->execute([$examId]);
}

$questions = $stmt->fetchAll();

if (count($questions) <= 0) {
    redirect_to('student/exam_instructions.php?exam_id=' . $examId);
}

$questionIds = [];

foreach ($questions as $question) {
    $questionIds[] = (int) $question['id'];
}

$choiceMap = [];

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
    }
}

$stmt = $pdo->prepare("
    SELECT question_id, choice_id, answer_text
    FROM student_answers
    WHERE attempt_id = ?
");
$stmt->execute([$attemptId]);
$savedAnswers = $stmt->fetchAll();

$savedAnswerMap = [];

foreach ($savedAnswers as $answer) {
    $savedAnswerMap[(int) $answer['question_id']] = $answer;
}

$token = csrf_token();

$pageTitle = 'Take Exam';
$panelLabel = 'Student Panel';
$activePage = 'exams';
$extraStyles = ['assets/css/take-exam.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section 
    class="take-exam-shell" 
    data-save-url="<?php echo e(app_url('actions/save_answer.php')); ?>" 
    data-csrf="<?php echo e($token); ?>" 
    data-attempt-id="<?php echo e($attemptId); ?>"
>
    <div class="exam-header-card">
        <div>
            <span>Exam in Progress</span>
            <h2><?php echo e($attempt['title']); ?></h2>
            <p>
                <?php echo $attempt['subject'] !== '' ? e($attempt['subject']) : 'Answer each question carefully before submitting.'; ?>
            </p>
        </div>

        <div class="timer-card">
            <span>Time Remaining</span>
            <strong id="examTimer" data-remaining="<?php echo e($remainingSeconds); ?>">--:--</strong>
        </div>
    </div>

    <form method="POST" action="<?php echo e(app_url('actions/submit_exam.php')); ?>" id="examForm">
        <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
        <input type="hidden" name="attempt_id" value="<?php echo e($attemptId); ?>">
        <input type="hidden" name="auto_submit" id="autoSubmit" value="0">

        <div class="exam-layout">
            <main class="question-list">
                <?php foreach ($questions as $index => $question): ?>
                    <?php
                        $questionId = (int) $question['id'];
                        $savedChoiceId = '';
                        $savedText = '';

                        if (isset($savedAnswerMap[$questionId])) {
                            $savedChoiceId = $savedAnswerMap[$questionId]['choice_id'];
                            $savedText = $savedAnswerMap[$questionId]['answer_text'];
                        }

                        $questionType = $question['question_type'];
                        $typeLabel = take_exam_question_label($questionType);
                    ?>

                    <article class="question-card" id="question-<?php echo e($questionId); ?>" data-question-card>
                        <input type="hidden" name="question_ids[]" value="<?php echo e($questionId); ?>">

                        <div class="question-top">
                            <div>
                                <span class="question-number">
                                    Question <?php echo e($index + 1); ?> of <?php echo e(count($questions)); ?>
                                </span>

                                <h3><?php echo e($question['question_text']); ?></h3>
                            </div>

                            <div class="question-meta">
                                <span><?php echo e($typeLabel); ?></span>
                                <strong><?php echo e($question['points']); ?> pts</strong>
                            </div>
                        </div>

                        <?php if ($questionType === 'multiple_choice' || $questionType === 'true_false'): ?>
                            <div class="choice-list">
                                <?php if (isset($choiceMap[$questionId])): ?>
                                    <?php foreach ($choiceMap[$questionId] as $choice): ?>
                                        <label class="answer-choice">
                                            <input 
                                                type="radio" 
                                                name="choice_answers[<?php echo e($questionId); ?>]" 
                                                value="<?php echo e($choice['id']); ?>"
                                                data-answer-input
                                                data-question-id="<?php echo e($questionId); ?>"
                                                data-question-type="<?php echo e($questionType); ?>"
                                                <?php echo (string) $savedChoiceId === (string) $choice['id'] ? 'checked' : ''; ?>
                                            >
                                            <span><?php echo e(take_exam_strip_choice_label($choice['choice_text'])); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($questionType === 'identification'): ?>
                            <div class="text-answer-box">
                                <label>Your Answer</label>
                                <input 
                                    type="text" 
                                    name="text_answers[<?php echo e($questionId); ?>]" 
                                    value="<?php echo e($savedText); ?>"
                                    placeholder="Type your answer here"
                                    data-answer-input
                                    data-question-id="<?php echo e($questionId); ?>"
                                    data-question-type="identification"
                                >
                            </div>
                        <?php endif; ?>

                        <?php if ($questionType === 'essay'): ?>
                            <div class="text-answer-box">
                                <label>Your Essay Response</label>
                                <textarea 
                                    name="text_answers[<?php echo e($questionId); ?>]" 
                                    placeholder="Write your answer here. Your teacher will read and score this manually."
                                    data-answer-input
                                    data-question-id="<?php echo e($questionId); ?>"
                                    data-question-type="essay"
                                    data-word-counter="word-count-<?php echo e($questionId); ?>"
                                ><?php echo e($savedText); ?></textarea>

                                <div class="word-count-line">
                                    <span id="word-count-<?php echo e($questionId); ?>">0 words</span>
                                    <small>This answer will be checked manually by your teacher.</small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="save-status" id="save-status-<?php echo e($questionId); ?>">
                            <?php echo isset($savedAnswerMap[$questionId]) ? 'Answer saved' : 'Answer not saved yet'; ?>
                        </div>
                    </article>
                <?php endforeach; ?>

                <div class="question-step-actions">
                    <button type="button" class="secondary-action" id="prevQuestionBtn">
                        Back
                    </button>

                    <div class="question-step-indicator" id="questionStepIndicator">
                        Question 1 of <?php echo e(count($questions)); ?>
                    </div>

                    <button type="button" class="primary-button" id="nextQuestionBtn">
                        Next
                    </button>
                </div>
            </main>

            <aside class="exam-side-panel">
                <div class="content-card exam-progress-card">
                    <span>Exam Progress</span>
                    <h3><?php echo e(count($questions)); ?> Questions</h3>

                    <div class="question-nav" id="questionNav">
                        <?php foreach ($questions as $index => $question): ?>
                            <a 
                                href="#question-<?php echo e($question['id']); ?>"
                                data-question-jump="<?php echo e($index); ?>"
                            >
                                <?php echo e($index + 1); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="exam-reminders">
                        <strong>Reminders</strong>
                        <p>Focus on one question at a time. Your answers are saved while you work.</p>
                    </div>

                    <button type="submit" class="primary-button submit-exam-button" id="submitExamButton">
                        Submit Exam
                    </button>

                    <a href="exams.php" class="secondary-action back-link">
                        Back to Exams
                    </a>
                </div>
            </aside>
        </div>
    </form>
</section>

<script src="<?php echo e(app_url('assets/js/take-exam.js?v=2')); ?>"></script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>