<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('student');

header('Content-Type: application/json');

function json_reply($success, $message)
{
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

$studentId = current_user_id();
$token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

if (!verify_csrf_token($token)) {
    json_reply(false, 'Invalid request.');
}

$attemptId = isset($_POST['attempt_id']) ? (int) $_POST['attempt_id'] : 0;
$questionId = isset($_POST['question_id']) ? (int) $_POST['question_id'] : 0;
$questionType = isset($_POST['question_type']) ? clean_input($_POST['question_type']) : '';
$choiceId = isset($_POST['choice_id']) && $_POST['choice_id'] !== '' ? (int) $_POST['choice_id'] : null;
$answerText = isset($_POST['answer_text']) ? clean_input($_POST['answer_text']) : '';

if ($attemptId <= 0 || $questionId <= 0) {
    json_reply(false, 'Invalid answer data.');
}

$stmt = $pdo->prepare("
    SELECT 
        ea.id,
        ea.exam_id,
        ea.attempt_status
    FROM exam_attempts ea
    WHERE ea.id = ?
    AND ea.student_id = ?
    LIMIT 1
");
$stmt->execute([$attemptId, $studentId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    json_reply(false, 'Exam attempt not found.');
}

if ($attempt['attempt_status'] !== 'in_progress') {
    json_reply(false, 'This attempt is no longer active.');
}

$stmt = $pdo->prepare("
    SELECT id, question_type
    FROM questions
    WHERE id = ?
    AND exam_id = ?
    LIMIT 1
");
$stmt->execute([$questionId, $attempt['exam_id']]);
$question = $stmt->fetch();

if (!$question) {
    json_reply(false, 'Question not found.');
}

if ($question['question_type'] !== $questionType) {
    json_reply(false, 'Question type mismatch.');
}

if ($questionType === 'multiple_choice' || $questionType === 'true_false') {
    if (!$choiceId) {
        json_reply(false, 'Please select an answer.');
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM choices
        WHERE id = ?
        AND question_id = ?
        LIMIT 1
    ");
    $stmt->execute([$choiceId, $questionId]);
    $choice = $stmt->fetch();

    if (!$choice) {
        json_reply(false, 'Invalid selected answer.');
    }

    $answerText = '';
}

if ($questionType === 'identification' || $questionType === 'essay') {
    $choiceId = null;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        DELETE FROM student_answers
        WHERE attempt_id = ?
        AND question_id = ?
    ");
    $stmt->execute([$attemptId, $questionId]);

    if ($choiceId || $answerText !== '') {
        $stmt = $pdo->prepare("
            INSERT INTO student_answers
            (attempt_id, question_id, choice_id, answer_text)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $attemptId,
            $questionId,
            $choiceId,
            $answerText
        ]);
    }

    $pdo->commit();

    json_reply(true, 'Answer saved.');
} catch (PDOException $e) {
    $pdo->rollBack();
    json_reply(false, 'Unable to save answer.');
}