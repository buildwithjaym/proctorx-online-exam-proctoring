<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$studentId = current_user_id();
$token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

if (!verify_csrf_token($token)) {
    redirect_to('student/exams.php');
}

$attemptId = isset($_POST['attempt_id']) ? (int) $_POST['attempt_id'] : 0;
$autoSubmit = isset($_POST['auto_submit']) ? clean_input($_POST['auto_submit']) : '0';
$choiceAnswers = isset($_POST['choice_answers']) && is_array($_POST['choice_answers']) ? $_POST['choice_answers'] : [];
$textAnswers = isset($_POST['text_answers']) && is_array($_POST['text_answers']) ? $_POST['text_answers'] : [];
$questionIds = isset($_POST['question_ids']) && is_array($_POST['question_ids']) ? $_POST['question_ids'] : [];

$stmt = $pdo->prepare("
    SELECT 
        ea.id,
        ea.exam_id,
        ea.student_id,
        ea.attempt_status,
        ea.total_points_at_time,
        e.title,
        e.end_datetime
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

$examId = (int) $attempt['exam_id'];

if ($attempt['attempt_status'] === 'submitted' || $attempt['attempt_status'] === 'auto_submitted') {
    redirect_to('student/result.php?exam_id=' . $examId);
}

if ($attempt['attempt_status'] !== 'in_progress') {
    redirect_to('student/exam_instructions.php?exam_id=' . $examId);
}

$stmt = $pdo->prepare("
    SELECT id, question_text, question_type, points
    FROM questions
    WHERE exam_id = ?
    ORDER BY position ASC, id ASC
");
$stmt->execute([$examId]);
$questions = $stmt->fetchAll();

$questionMap = [];
$questionIdList = [];
$hasEssay = false;

foreach ($questions as $question) {
    $qid = (int) $question['id'];
    $questionMap[$qid] = $question;
    $questionIdList[] = $qid;

    if ($question['question_type'] === 'essay') {
        $hasEssay = true;
    }
}

$choiceMap = [];

if (count($questionIdList) > 0) {
    $placeholders = implode(',', array_fill(0, count($questionIdList), '?'));

    $stmt = $pdo->prepare("
        SELECT id, question_id, choice_text, is_correct
        FROM choices
        WHERE question_id IN ($placeholders)
    ");
    $stmt->execute($questionIdList);
    $choices = $stmt->fetchAll();

    foreach ($choices as $choice) {
        $choiceId = (int) $choice['id'];
        $choiceMap[$choiceId] = $choice;
    }
}

function normalize_answer_text($value)
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value);
    return $value;
}

$score = 0;

try {
    $pdo->beginTransaction();

    foreach ($questionMap as $qid => $question) {
        $questionType = $question['question_type'];
        $choiceId = null;
        $answerText = '';

        if ($questionType === 'multiple_choice' || $questionType === 'true_false') {
            if (isset($choiceAnswers[$qid]) && $choiceAnswers[$qid] !== '') {
                $selectedChoiceId = (int) $choiceAnswers[$qid];

                if (isset($choiceMap[$selectedChoiceId]) && (int) $choiceMap[$selectedChoiceId]['question_id'] === $qid) {
                    $choiceId = $selectedChoiceId;

                    if ((int) $choiceMap[$selectedChoiceId]['is_correct'] === 1) {
                        $score += (float) $question['points'];
                    }
                }
            }
        }

        if ($questionType === 'identification') {
            if (isset($textAnswers[$qid])) {
                $answerText = clean_input($textAnswers[$qid]);
            }

            $correctAnswer = '';

            foreach ($choiceMap as $choice) {
                if ((int) $choice['question_id'] === $qid && (int) $choice['is_correct'] === 1) {
                    $correctAnswer = $choice['choice_text'];
                    break;
                }
            }

            if ($answerText !== '' && normalize_answer_text($answerText) === normalize_answer_text($correctAnswer)) {
                $score += (float) $question['points'];
            }
        }

        if ($questionType === 'essay') {
            if (isset($textAnswers[$qid])) {
                $answerText = clean_input($textAnswers[$qid]);
            }
        }

        $stmt = $pdo->prepare("
            DELETE FROM student_answers
            WHERE attempt_id = ?
            AND question_id = ?
        ");
        $stmt->execute([$attemptId, $qid]);

        if ($choiceId || $answerText !== '') {
            $stmt = $pdo->prepare("
                INSERT INTO student_answers
                (attempt_id, question_id, choice_id, answer_text)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $attemptId,
                $qid,
                $choiceId,
                $answerText
            ]);
        }
    }

    $attemptStatus = $autoSubmit === '1' ? 'auto_submitted' : 'submitted';
    $reviewStatus = $hasEssay ? 'under_review' : 'normal';

    $stmt = $pdo->prepare("
        UPDATE exam_attempts
        SET 
            attempt_status = ?,
            review_status = ?,
            score = ?,
            submitted_at = NOW()
        WHERE id = ?
        AND student_id = ?
    ");
    $stmt->execute([
        $attemptStatus,
        $reviewStatus,
        $score,
        $attemptId,
        $studentId
    ]);

    $pdo->commit();

    redirect_to('student/result.php?exam_id=' . $examId);
} catch (PDOException $e) {
    $pdo->rollBack();
    redirect_to('student/take_exam.php?attempt_id=' . $attemptId);
}