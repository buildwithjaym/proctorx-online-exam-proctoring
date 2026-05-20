<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$teacherId = current_user_id();
$examId = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;

function set_question_flash($type, $message)
{
    $_SESSION['question_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function valid_question_type($questionType)
{
    return in_array($questionType, ['multiple_choice', 'true_false', 'identification', 'essay']);
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

function recalculate_exam_total_points($pdo, $examId)
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(points), 0)
        FROM questions
        WHERE exam_id = ?
    ");
    $stmt->execute([$examId]);
    $totalPoints = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        UPDATE exams
        SET total_points = ?
        WHERE id = ?
    ");
    $stmt->execute([$totalPoints, $examId]);
}

function collect_multiple_choices()
{
    $choices = [];
    $labels = ['A', 'B', 'C', 'D'];

    for ($i = 1; $i <= 4; $i++) {
        $key = 'choice_text_' . $i;
        $value = isset($_POST[$key]) ? clean_input($_POST[$key]) : '';

        if ($value !== '') {
            $choices[$i] = [
                'label' => $labels[$i - 1],
                'text' => $value
            ];
        }
    }

    return $choices;
}

function strip_choice_label($choiceText)
{
    if (preg_match('/^[A-D]\.\s(.+)$/', $choiceText, $matches)) {
        return $matches[1];
    }

    return $choiceText;
}

function save_question_choices($pdo, $questionId, $questionType)
{
    if ($questionType === 'multiple_choice') {
        $choices = collect_multiple_choices();
        $correctChoice = isset($_POST['correct_choice']) ? (int) $_POST['correct_choice'] : 0;

        if (count($choices) < 2) {
            throw new Exception('Please provide at least two choices for this multiple choice question.');
        }

        if (!isset($choices[$correctChoice])) {
            throw new Exception('Please select the correct answer for this multiple choice question.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO choices
            (question_id, choice_text, is_correct, position)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($choices as $position => $choice) {
            $isCorrect = $position === $correctChoice ? 1 : 0;
            $choiceText = $choice['label'] . '. ' . $choice['text'];

            $stmt->execute([
                $questionId,
                $choiceText,
                $isCorrect,
                $position
            ]);
        }
    }

    if ($questionType === 'true_false') {
        $correctTf = isset($_POST['correct_tf']) ? clean_input($_POST['correct_tf']) : '';

        if (!in_array($correctTf, ['true', 'false'])) {
            throw new Exception('Please choose the correct True or False answer.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO choices
            (question_id, choice_text, is_correct, position)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $questionId,
            'True',
            $correctTf === 'true' ? 1 : 0,
            1
        ]);

        $stmt->execute([
            $questionId,
            'False',
            $correctTf === 'false' ? 1 : 0,
            2
        ]);
    }

    if ($questionType === 'identification') {
        $correctIdentification = isset($_POST['correct_identification']) ? clean_input($_POST['correct_identification']) : '';

        if ($correctIdentification === '') {
            throw new Exception('Please enter the correct answer for this identification question.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO choices
            (question_id, choice_text, is_correct, position)
            VALUES (?, ?, 1, 1)
        ");

        $stmt->execute([
            $questionId,
            $correctIdentification
        ]);
    }

    if ($questionType === 'essay') {
        return;
    }
}

$stmt = $pdo->prepare("
    SELECT id, title, subject, total_points, status
    FROM exams
    WHERE id = ?
    AND teacher_id = ?
    LIMIT 1
");
$stmt->execute([$examId, $teacherId]);
$exam = $stmt->fetch();

if (!$exam) {
    redirect_to('teacher/exams.php');
}

$token = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($postedToken)) {
        set_question_flash('error', 'Invalid request. Please try again.');
        redirect_to('teacher/questions.php?exam_id=' . $examId);
    }

    $action = isset($_POST['action']) ? clean_input($_POST['action']) : '';

    if ($action === 'create') {
        $questionText = isset($_POST['question_text']) ? clean_input($_POST['question_text']) : '';
        $questionType = isset($_POST['question_type']) ? clean_input($_POST['question_type']) : 'multiple_choice';
        $points = isset($_POST['points']) ? (float) $_POST['points'] : 1;

        if ($questionText === '') {
            set_question_flash('error', 'Please write the question before saving.');
            redirect_to('teacher/questions.php?exam_id=' . $examId);
        }

        if (!valid_question_type($questionType)) {
            set_question_flash('error', 'Please select a valid question type.');
            redirect_to('teacher/questions.php?exam_id=' . $examId);
        }

        if ($points <= 0) {
            set_question_flash('error', 'Points must be greater than zero.');
            redirect_to('teacher/questions.php?exam_id=' . $examId);
        }

        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(position), 0) + 1
            FROM questions
            WHERE exam_id = ?
        ");
        $stmt->execute([$examId]);
        $position = (int) $stmt->fetchColumn();

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO questions
                (exam_id, question_text, question_type, points, position)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $examId,
                $questionText,
                $questionType,
                $points,
                $position
            ]);

            $questionId = (int) $pdo->lastInsertId();

            save_question_choices($pdo, $questionId, $questionType);
            recalculate_exam_total_points($pdo, $examId);

            $pdo->commit();

            set_question_flash('success', question_type_label($questionType) . ' question added successfully.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_question_flash('error', $e->getMessage());
        }

        redirect_to('teacher/questions.php?exam_id=' . $examId);
    }

    if ($action === 'update') {
        $questionId = isset($_POST['question_id']) ? (int) $_POST['question_id'] : 0;
        $questionText = isset($_POST['question_text']) ? clean_input($_POST['question_text']) : '';
        $questionType = isset($_POST['question_type']) ? clean_input($_POST['question_type']) : 'multiple_choice';
        $points = isset($_POST['points']) ? (float) $_POST['points'] : 1;

        $stmt = $pdo->prepare("
            SELECT q.id
            FROM questions q
            INNER JOIN exams e ON q.exam_id = e.id
            WHERE q.id = ?
            AND q.exam_id = ?
            AND e.teacher_id = ?
            LIMIT 1
        ");
        $stmt->execute([$questionId, $examId, $teacherId]);
        $question = $stmt->fetch();

        if (!$question) {
            set_question_flash('error', 'Question not found.');
            redirect_to('teacher/questions.php?exam_id=' . $examId);
        }

        if ($questionText === '') {
            set_question_flash('error', 'Please write the question before saving.');
            redirect_to('teacher/questions.php?exam_id=' . $examId);
        }

        if (!valid_question_type($questionType)) {
            set_question_flash('error', 'Please select a valid question type.');
            redirect_to('teacher/questions.php?exam_id=' . $examId);
        }

        if ($points <= 0) {
            set_question_flash('error', 'Points must be greater than zero.');
            redirect_to('teacher/questions.php?exam_id=' . $examId);
        }

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                UPDATE questions
                SET question_text = ?, question_type = ?, points = ?
                WHERE id = ?
                AND exam_id = ?
            ");
            $stmt->execute([
                $questionText,
                $questionType,
                $points,
                $questionId,
                $examId
            ]);

            $stmt = $pdo->prepare("DELETE FROM choices WHERE question_id = ?");
            $stmt->execute([$questionId]);

            save_question_choices($pdo, $questionId, $questionType);
            recalculate_exam_total_points($pdo, $examId);

            $pdo->commit();

            set_question_flash('success', question_type_label($questionType) . ' question updated successfully.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_question_flash('error', $e->getMessage());
        }

        redirect_to('teacher/questions.php?exam_id=' . $examId);
    }

    if ($action === 'delete') {
        $questionId = isset($_POST['question_id']) ? (int) $_POST['question_id'] : 0;

        $stmt = $pdo->prepare("
            SELECT q.id
            FROM questions q
            INNER JOIN exams e ON q.exam_id = e.id
            WHERE q.id = ?
            AND q.exam_id = ?
            AND e.teacher_id = ?
            LIMIT 1
        ");
        $stmt->execute([$questionId, $examId, $teacherId]);
        $question = $stmt->fetch();

        if (!$question) {
            set_question_flash('error', 'Question not found.');
            redirect_to('teacher/questions.php?exam_id=' . $examId);
        }

        try {
            $stmt = $pdo->prepare("
                DELETE FROM questions
                WHERE id = ?
                AND exam_id = ?
            ");
            $stmt->execute([$questionId, $examId]);

            recalculate_exam_total_points($pdo, $examId);

            set_question_flash('success', 'Question deleted successfully.');
        } catch (PDOException $e) {
            set_question_flash('error', 'This question already has linked student answers and cannot be deleted safely.');
        }

        redirect_to('teacher/questions.php?exam_id=' . $examId);
    }

    set_question_flash('error', 'Invalid action.');
    redirect_to('teacher/questions.php?exam_id=' . $examId);
}

$flash = null;

if (isset($_SESSION['question_flash'])) {
    $flash = $_SESSION['question_flash'];
    unset($_SESSION['question_flash']);
}

$stmt = $pdo->prepare("
    SELECT id, question_text, question_type, points, position, created_at
    FROM questions
    WHERE exam_id = ?
    ORDER BY position ASC, id ASC
");
$stmt->execute([$examId]);
$questions = $stmt->fetchAll();

$choiceMap = [];

if (count($questions) > 0) {
    $questionIds = [];

    foreach ($questions as $question) {
        $questionIds[] = (int) $question['id'];
    }

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

$totalQuestions = count($questions);

$stmt = $pdo->prepare("SELECT COALESCE(SUM(points), 0) FROM questions WHERE exam_id = ?");
$stmt->execute([$examId]);
$totalPoints = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ? AND question_type = 'multiple_choice'");
$stmt->execute([$examId]);
$totalMultipleChoice = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ? AND question_type = 'true_false'");
$stmt->execute([$examId]);
$totalTrueFalse = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ? AND question_type = 'identification'");
$stmt->execute([$examId]);
$totalIdentification = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE exam_id = ? AND question_type = 'essay'");
$stmt->execute([$examId]);
$totalEssay = (int) $stmt->fetchColumn();

$pageTitle = 'Questions';
$panelLabel = 'Teacher Panel';
$activePage = 'exams';
$extraStyles = ['assets/css/questions.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="exam-summary-card">
    <div>
        <span>Exam Builder</span>
        <h2><?php echo e($exam['title']); ?></h2>
        <p><?php echo e($exam['subject']); ?></p>
    </div>

    <a href="exams.php" class="secondary-action">Back to Exams</a>
</section>

<section class="dashboard-grid question-stats-grid">
    <div class="dashboard-card">
        <span>Total Questions</span>
        <h3><?php echo e($totalQuestions); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Total Points</span>
        <h3><?php echo e($totalPoints); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Multiple Choice</span>
        <h3><?php echo e($totalMultipleChoice); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>True or False</span>
        <h3><?php echo e($totalTrueFalse); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Identification</span>
        <h3><?php echo e($totalIdentification); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Essay</span>
        <h3><?php echo e($totalEssay); ?></h3>
    </div>
</section>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Question Bank</span>
            <h2>Build clear, fair, and easy-to-answer exam questions</h2>
        </div>

        <button type="button" class="primary-action" data-open-modal="addQuestionModal">
            Add Question
        </button>
    </div>

    <?php if ($flash): ?>
        <?php if ($flash['type'] === 'success'): ?>
            <div class="alert-success"><?php echo e($flash['message']); ?></div>
        <?php else: ?>
            <div class="alert-error"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="table-toolbar">
        <input type="text" id="questionSearch" placeholder="Search by question text, type, or answer">
    </div>

    <div class="table-wrap">
        <table class="data-table" id="questionsTable">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Question</th>
                    <th>Type</th>
                    <th>Points</th>
                    <th>Answer Guide</th>
                    <th>Options</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($questions) > 0): ?>
                    <?php foreach ($questions as $index => $question): ?>
                        <?php
                            $questionId = (int) $question['id'];
                            $questionChoices = isset($choiceMap[$questionId]) ? $choiceMap[$questionId] : [];
                            $answerGuide = '';
                            $optionTexts = [];
                            $choiceData = [];

                            foreach ($questionChoices as $choice) {
                                $optionTexts[] = $choice['choice_text'];

                                if ((int) $choice['is_correct'] === 1) {
                                    $answerGuide = $choice['choice_text'];
                                }

                                $choiceData[] = [
                                    'text' => $choice['choice_text'],
                                    'raw_text' => strip_choice_label($choice['choice_text']),
                                    'is_correct' => (int) $choice['is_correct'],
                                    'position' => (int) $choice['position']
                                ];
                            }

                            if ($question['question_type'] === 'essay') {
                                $answerGuide = 'Manual checking';
                                $optionTexts = ['No correct answer required. Teacher will read and score the response.'];
                            }

                            $choiceJson = json_encode($choiceData);
                        ?>
                        <tr>
                            <td><?php echo e($index + 1); ?></td>
                            <td>
                                <strong class="question-text"><?php echo e($question['question_text']); ?></strong>
                            </td>
                            <td>
                                <span class="type-badge <?php echo e($question['question_type']); ?>">
                                    <?php echo e(question_type_label($question['question_type'])); ?>
                                </span>
                            </td>
                            <td><?php echo e($question['points']); ?></td>
                            <td><?php echo e($answerGuide); ?></td>
                            <td>
                                <span class="choice-preview"><?php echo e(implode(' • ', $optionTexts)); ?></span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button
                                        type="button"
                                        class="table-btn edit"
                                        data-open-edit
                                        data-id="<?php echo e($question['id']); ?>"
                                        data-text="<?php echo e($question['question_text']); ?>"
                                        data-type="<?php echo e($question['question_type']); ?>"
                                        data-points="<?php echo e($question['points']); ?>"
                                        data-choices="<?php echo e($choiceJson); ?>"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        type="button"
                                        class="table-btn danger"
                                        data-open-delete
                                        data-id="<?php echo e($question['id']); ?>"
                                        data-text="<?php echo e($question['question_text']); ?>"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-state">No questions added yet. Start by adding your first exam question.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal-backdrop" id="addQuestionModal">
    <div class="modal-card large-modal">
        <div class="modal-header">
            <div>
                <span>Create Question</span>
                <h3>Add a new exam question</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label for="add_question_text">Question</label>
                <textarea id="add_question_text" name="question_text" placeholder="Write the question exactly as the student should see it." required></textarea>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="add_question_type">Question Type</label>
                    <select id="add_question_type" name="question_type" data-question-type="add">
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True or False</option>
                        <option value="identification">Identification</option>
                        <option value="essay">Essay</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="add_points">Points</label>
                    <input type="number" id="add_points" name="points" value="1" min="0.01" step="0.01" required>
                </div>
            </div>

            <div id="add_multiple_choice_box" class="choice-box">
                <span class="choice-box-title">Multiple Choice Options</span>
                <p class="question-note">Add up to four choices. Select the correct answer using A, B, C, or D.</p>

                <?php
                    $labels = ['A', 'B', 'C', 'D'];
                    for ($i = 1; $i <= 4; $i++):
                ?>
                    <div class="choice-row abcd-row">
                        <label class="choice-letter"><?php echo e($labels[$i - 1]); ?></label>
                        <input type="text" name="choice_text_<?php echo e($i); ?>" placeholder="Option <?php echo e($labels[$i - 1]); ?>">
                        <label class="correct-radio">
                            <input type="radio" name="correct_choice" value="<?php echo e($i); ?>" <?php echo $i === 1 ? 'checked' : ''; ?>>
                            Correct
                        </label>
                    </div>
                <?php endfor; ?>
            </div>

            <div id="add_true_false_box" class="choice-box hidden">
                <span class="choice-box-title">True or False Answer</span>
                <p class="question-note">Choose the correct answer for this statement.</p>

                <label class="check-row">
                    <input type="radio" name="correct_tf" value="true" checked>
                    <span>True</span>
                </label>

                <label class="check-row">
                    <input type="radio" name="correct_tf" value="false">
                    <span>False</span>
                </label>
            </div>

            <div id="add_identification_box" class="choice-box hidden">
                <span class="choice-box-title">Identification Answer</span>
                <p class="question-note">Students will type one answer. Provide the expected correct answer here.</p>

                <div class="form-group no-margin">
                    <label for="add_correct_identification">Correct Answer</label>
                    <input type="text" id="add_correct_identification" name="correct_identification" placeholder="Example: Photosynthesis">
                </div>
            </div>

            <div id="add_essay_box" class="choice-box hidden">
                <span class="choice-box-title">Essay Response</span>
                <p class="question-note">No correct answer is required. Students will write their response freely, and the teacher will read and score it manually.</p>
            </div>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="primary-button">Save Question</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editQuestionModal">
    <div class="modal-card large-modal">
        <div class="modal-header">
            <div>
                <span>Edit Question</span>
                <h3>Update exam question</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="question_id" id="edit_question_id">

            <div class="form-group">
                <label for="edit_question_text">Question</label>
                <textarea id="edit_question_text" name="question_text" required></textarea>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="edit_question_type">Question Type</label>
                    <select id="edit_question_type" name="question_type" data-question-type="edit">
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True or False</option>
                        <option value="identification">Identification</option>
                        <option value="essay">Essay</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_points">Points</label>
                    <input type="number" id="edit_points" name="points" min="0.01" step="0.01" required>
                </div>
            </div>

            <div id="edit_multiple_choice_box" class="choice-box">
                <span class="choice-box-title">Multiple Choice Options</span>
                <p class="question-note">Edit the choices and mark the correct answer.</p>

                <?php
                    $labels = ['A', 'B', 'C', 'D'];
                    for ($i = 1; $i <= 4; $i++):
                ?>
                    <div class="choice-row abcd-row">
                        <label class="choice-letter"><?php echo e($labels[$i - 1]); ?></label>
                        <input type="text" name="choice_text_<?php echo e($i); ?>" id="edit_choice_text_<?php echo e($i); ?>" placeholder="Option <?php echo e($labels[$i - 1]); ?>">
                        <label class="correct-radio">
                            <input type="radio" name="correct_choice" value="<?php echo e($i); ?>" id="edit_correct_choice_<?php echo e($i); ?>">
                            Correct
                        </label>
                    </div>
                <?php endfor; ?>
            </div>

            <div id="edit_true_false_box" class="choice-box hidden">
                <span class="choice-box-title">True or False Answer</span>
                <p class="question-note">Choose the correct answer for this statement.</p>

                <label class="check-row">
                    <input type="radio" name="correct_tf" value="true" id="edit_correct_tf_true">
                    <span>True</span>
                </label>

                <label class="check-row">
                    <input type="radio" name="correct_tf" value="false" id="edit_correct_tf_false">
                    <span>False</span>
                </label>
            </div>

            <div id="edit_identification_box" class="choice-box hidden">
                <span class="choice-box-title">Identification Answer</span>
                <p class="question-note">Students will type one answer. Provide the expected correct answer here.</p>

                <div class="form-group no-margin">
                    <label for="edit_correct_identification">Correct Answer</label>
                    <input type="text" id="edit_correct_identification" name="correct_identification" placeholder="Expected answer">
                </div>
            </div>

            <div id="edit_essay_box" class="choice-box hidden">
                <span class="choice-box-title">Essay Response</span>
                <p class="question-note">No correct answer is required. Students will write their response freely, and the teacher will read and score it manually.</p>
            </div>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="primary-button">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="deleteQuestionModal">
    <div class="modal-card small-modal">
        <div class="modal-header">
            <div>
                <span>Delete Question</span>
                <h3>Confirm Delete</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="question_id" id="delete_question_id">

            <p class="delete-text">
                Are you sure you want to delete this question?
            </p>

            <div class="modal-note danger-note">
                This will also remove the answer guide or choices connected to this question.
            </div>

            <div class="delete-question-preview" id="delete_question_text"></div>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="danger-button">Delete Question</button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo e(app_url('assets/js/questions.js?V-3')); ?>"></script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>