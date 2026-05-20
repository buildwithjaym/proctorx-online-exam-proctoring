<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$teacherId = current_user_id();

function set_exam_flash($type, $message)
{
    $_SESSION['exam_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function format_datetime_for_mysql($value)
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    return str_replace('T', ' ', $value) . ':00';
}

function format_datetime_for_input($value)
{
    if (!$value) {
        return '';
    }

    return date('Y-m-d\TH:i', strtotime($value));
}

function validate_exam_status($status)
{
    return in_array($status, ['draft', 'published', 'closed', 'archived']);
}

$token = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($postedToken)) {
        set_exam_flash('error', 'Invalid request. Please try again.');
        redirect_to('teacher/exams.php');
    }

    $action = isset($_POST['action']) ? clean_input($_POST['action']) : '';

    if ($action === 'create') {
        $title = isset($_POST['title']) ? clean_input($_POST['title']) : '';
        $subject = isset($_POST['subject']) ? clean_input($_POST['subject']) : '';
        $description = isset($_POST['description']) ? clean_input($_POST['description']) : '';
        $durationMinutes = isset($_POST['duration_minutes']) ? (int) $_POST['duration_minutes'] : 60;
        $startDatetime = isset($_POST['start_datetime']) ? format_datetime_for_mysql($_POST['start_datetime']) : '';
        $endDatetime = isset($_POST['end_datetime']) ? format_datetime_for_mysql($_POST['end_datetime']) : '';
        $randomizeQuestions = isset($_POST['randomize_questions']) ? 1 : 0;
        $showResult = isset($_POST['show_result']) ? 1 : 0;
        $webcamRequired = isset($_POST['webcam_required']) ? 1 : 0;
        $fullscreenRequired = isset($_POST['fullscreen_required']) ? 1 : 0;
        $maxAttempts = isset($_POST['max_attempts']) ? (int) $_POST['max_attempts'] : 1;
        $status = isset($_POST['status']) ? clean_input($_POST['status']) : 'draft';

        if ($title === '') {
            set_exam_flash('error', 'Exam title is required.');
            redirect_to('teacher/exams.php');
        }

        if ($durationMinutes <= 0) {
            set_exam_flash('error', 'Duration must be greater than zero.');
            redirect_to('teacher/exams.php');
        }

        if ($startDatetime === '' || $endDatetime === '') {
            set_exam_flash('error', 'Start and end schedule are required.');
            redirect_to('teacher/exams.php');
        }

        if (strtotime($endDatetime) <= strtotime($startDatetime)) {
            set_exam_flash('error', 'End schedule must be later than start schedule.');
            redirect_to('teacher/exams.php');
        }

        if ($maxAttempts <= 0) {
            $maxAttempts = 1;
        }

        if (!validate_exam_status($status)) {
            set_exam_flash('error', 'Invalid exam status.');
            redirect_to('teacher/exams.php');
        }

        $subjectValue = $subject === '' ? null : $subject;
        $descriptionValue = $description === '' ? null : $description;

        $stmt = $pdo->prepare("
            INSERT INTO exams
            (
                teacher_id,
                title,
                subject,
                description,
                duration_minutes,
                start_datetime,
                end_datetime,
                randomize_questions,
                show_result,
                webcam_required,
                fullscreen_required,
                max_attempts,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $teacherId,
            $title,
            $subjectValue,
            $descriptionValue,
            $durationMinutes,
            $startDatetime,
            $endDatetime,
            $randomizeQuestions,
            $showResult,
            $webcamRequired,
            $fullscreenRequired,
            $maxAttempts,
            $status
        ]);

        set_exam_flash('success', 'Exam created successfully.');
        redirect_to('teacher/exams.php');
    }

    if ($action === 'update') {
        $examId = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $title = isset($_POST['title']) ? clean_input($_POST['title']) : '';
        $subject = isset($_POST['subject']) ? clean_input($_POST['subject']) : '';
        $description = isset($_POST['description']) ? clean_input($_POST['description']) : '';
        $durationMinutes = isset($_POST['duration_minutes']) ? (int) $_POST['duration_minutes'] : 60;
        $startDatetime = isset($_POST['start_datetime']) ? format_datetime_for_mysql($_POST['start_datetime']) : '';
        $endDatetime = isset($_POST['end_datetime']) ? format_datetime_for_mysql($_POST['end_datetime']) : '';
        $randomizeQuestions = isset($_POST['randomize_questions']) ? 1 : 0;
        $showResult = isset($_POST['show_result']) ? 1 : 0;
        $webcamRequired = isset($_POST['webcam_required']) ? 1 : 0;
        $fullscreenRequired = isset($_POST['fullscreen_required']) ? 1 : 0;
        $maxAttempts = isset($_POST['max_attempts']) ? (int) $_POST['max_attempts'] : 1;
        $status = isset($_POST['status']) ? clean_input($_POST['status']) : 'draft';

        $stmt = $pdo->prepare("
            SELECT id
            FROM exams
            WHERE id = ?
            AND teacher_id = ?
            LIMIT 1
        ");
        $stmt->execute([$examId, $teacherId]);
        $exam = $stmt->fetch();

        if (!$exam) {
            set_exam_flash('error', 'Exam not found.');
            redirect_to('teacher/exams.php');
        }

        if ($title === '') {
            set_exam_flash('error', 'Exam title is required.');
            redirect_to('teacher/exams.php');
        }

        if ($durationMinutes <= 0) {
            set_exam_flash('error', 'Duration must be greater than zero.');
            redirect_to('teacher/exams.php');
        }

        if ($startDatetime === '' || $endDatetime === '') {
            set_exam_flash('error', 'Start and end schedule are required.');
            redirect_to('teacher/exams.php');
        }

        if (strtotime($endDatetime) <= strtotime($startDatetime)) {
            set_exam_flash('error', 'End schedule must be later than start schedule.');
            redirect_to('teacher/exams.php');
        }

        if ($maxAttempts <= 0) {
            $maxAttempts = 1;
        }

        if (!validate_exam_status($status)) {
            set_exam_flash('error', 'Invalid exam status.');
            redirect_to('teacher/exams.php');
        }

        $subjectValue = $subject === '' ? null : $subject;
        $descriptionValue = $description === '' ? null : $description;

        $stmt = $pdo->prepare("
            UPDATE exams
            SET 
                title = ?,
                subject = ?,
                description = ?,
                duration_minutes = ?,
                start_datetime = ?,
                end_datetime = ?,
                randomize_questions = ?,
                show_result = ?,
                webcam_required = ?,
                fullscreen_required = ?,
                max_attempts = ?,
                status = ?
            WHERE id = ?
            AND teacher_id = ?
        ");

        $stmt->execute([
            $title,
            $subjectValue,
            $descriptionValue,
            $durationMinutes,
            $startDatetime,
            $endDatetime,
            $randomizeQuestions,
            $showResult,
            $webcamRequired,
            $fullscreenRequired,
            $maxAttempts,
            $status,
            $examId,
            $teacherId
        ]);

        set_exam_flash('success', 'Exam updated successfully.');
        redirect_to('teacher/exams.php');
    }

    if ($action === 'delete') {
        $examId = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;

        $stmt = $pdo->prepare("
            SELECT id
            FROM exams
            WHERE id = ?
            AND teacher_id = ?
            LIMIT 1
        ");
        $stmt->execute([$examId, $teacherId]);
        $exam = $stmt->fetch();

        if (!$exam) {
            set_exam_flash('error', 'Exam not found.');
            redirect_to('teacher/exams.php');
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_attempts WHERE exam_id = ?");
        $stmt->execute([$examId]);
        $attemptCount = (int) $stmt->fetchColumn();

        if ($attemptCount > 0) {
            $stmt = $pdo->prepare("
                UPDATE exams
                SET status = 'archived'
                WHERE id = ?
                AND teacher_id = ?
            ");
            $stmt->execute([$examId, $teacherId]);

            set_exam_flash('success', 'Exam already has attempts, so it was archived instead of deleted.');
            redirect_to('teacher/exams.php');
        }

        $stmt = $pdo->prepare("
            DELETE FROM exams
            WHERE id = ?
            AND teacher_id = ?
        ");
        $stmt->execute([$examId, $teacherId]);

        set_exam_flash('success', 'Exam deleted successfully.');
        redirect_to('teacher/exams.php');
    }

    if ($action === 'assign_students') {
        $examId = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $studentIds = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? $_POST['student_ids'] : [];

        $stmt = $pdo->prepare("
            SELECT id
            FROM exams
            WHERE id = ?
            AND teacher_id = ?
            LIMIT 1
        ");
        $stmt->execute([$examId, $teacherId]);
        $exam = $stmt->fetch();

        if (!$exam) {
            set_exam_flash('error', 'Exam not found.');
            redirect_to('teacher/exams.php');
        }

        $cleanStudentIds = [];

        foreach ($studentIds as $studentId) {
            $cleanStudentIds[] = (int) $studentId;
        }

        $cleanStudentIds = array_unique($cleanStudentIds);
        $validStudentIds = [];

        if (count($cleanStudentIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($cleanStudentIds), '?'));
            $params = $cleanStudentIds;
            $params[] = $teacherId;

            $stmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE id IN ($placeholders)
                AND role = 'student'
                AND created_by = ?
                AND status = 'active'
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $validStudentIds[] = (int) $row['id'];
            }
        }

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("DELETE FROM exam_students WHERE exam_id = ?");
            $stmt->execute([$examId]);

            if (count($validStudentIds) > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO exam_students
                    (exam_id, student_id)
                    VALUES (?, ?)
                ");

                foreach ($validStudentIds as $studentId) {
                    $stmt->execute([$examId, $studentId]);
                }
            }

            $pdo->commit();
            set_exam_flash('success', 'Exam students updated successfully.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            set_exam_flash('error', 'Unable to update exam students.');
        }

        redirect_to('teacher/exams.php');
    }

    if ($action === 'assign_proctors') {
        $examId = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $proctorIds = isset($_POST['proctor_ids']) && is_array($_POST['proctor_ids']) ? $_POST['proctor_ids'] : [];

        $stmt = $pdo->prepare("
            SELECT id
            FROM exams
            WHERE id = ?
            AND teacher_id = ?
            LIMIT 1
        ");
        $stmt->execute([$examId, $teacherId]);
        $exam = $stmt->fetch();

        if (!$exam) {
            set_exam_flash('error', 'Exam not found.');
            redirect_to('teacher/exams.php');
        }

        $cleanProctorIds = [];

        foreach ($proctorIds as $proctorId) {
            $cleanProctorIds[] = (int) $proctorId;
        }

        $cleanProctorIds = array_unique($cleanProctorIds);
        $validProctorIds = [];

        if (count($cleanProctorIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($cleanProctorIds), '?'));
            $params = $cleanProctorIds;
            $params[] = $teacherId;

            $stmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE id IN ($placeholders)
                AND role = 'proctor'
                AND created_by = ?
                AND status = 'active'
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $validProctorIds[] = (int) $row['id'];
            }
        }

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("DELETE FROM exam_proctors WHERE exam_id = ?");
            $stmt->execute([$examId]);

            if (count($validProctorIds) > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO exam_proctors
                    (exam_id, proctor_id)
                    VALUES (?, ?)
                ");

                foreach ($validProctorIds as $proctorId) {
                    $stmt->execute([$examId, $proctorId]);
                }
            }

            $pdo->commit();
            set_exam_flash('success', 'Exam proctors updated successfully.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            set_exam_flash('error', 'Unable to update exam proctors.');
        }

        redirect_to('teacher/exams.php');
    }

    set_exam_flash('error', 'Invalid action.');
    redirect_to('teacher/exams.php');
}

$flash = null;

if (isset($_SESSION['exam_flash'])) {
    $flash = $_SESSION['exam_flash'];
    unset($_SESSION['exam_flash']);
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
        e.randomize_questions,
        e.show_result,
        e.webcam_required,
        e.fullscreen_required,
        e.max_attempts,
        e.status,
        e.created_at,
        COUNT(DISTINCT es.id) AS student_count,
        COUNT(DISTINCT ep.id) AS proctor_count,
        COUNT(DISTINCT q.id) AS question_count,
        COUNT(DISTINCT ea.id) AS attempt_count
    FROM exams e
    LEFT JOIN exam_students es ON es.exam_id = e.id
    LEFT JOIN exam_proctors ep ON ep.exam_id = e.id
    LEFT JOIN questions q ON q.exam_id = e.id
    LEFT JOIN exam_attempts ea ON ea.exam_id = e.id
    WHERE e.teacher_id = ?
    GROUP BY 
        e.id,
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
        e.created_at
    ORDER BY e.created_at DESC
");
$stmt->execute([$teacherId]);
$exams = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT id, full_name, username, email
    FROM users
    WHERE role = 'student'
    AND created_by = ?
    AND status = 'active'
    ORDER BY full_name ASC
");
$stmt->execute([$teacherId]);
$students = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT id, full_name, username, email
    FROM users
    WHERE role = 'proctor'
    AND created_by = ?
    AND status = 'active'
    ORDER BY full_name ASC
");
$stmt->execute([$teacherId]);
$proctors = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT es.exam_id, es.student_id
    FROM exam_students es
    INNER JOIN exams e ON es.exam_id = e.id
    WHERE e.teacher_id = ?
");
$stmt->execute([$teacherId]);
$studentAssignmentRows = $stmt->fetchAll();

$assignedStudentMap = [];

foreach ($studentAssignmentRows as $row) {
    $examId = (int) $row['exam_id'];

    if (!isset($assignedStudentMap[$examId])) {
        $assignedStudentMap[$examId] = [];
    }

    $assignedStudentMap[$examId][] = (int) $row['student_id'];
}

$stmt = $pdo->prepare("
    SELECT ep.exam_id, ep.proctor_id
    FROM exam_proctors ep
    INNER JOIN exams e ON ep.exam_id = e.id
    WHERE e.teacher_id = ?
");
$stmt->execute([$teacherId]);
$proctorAssignmentRows = $stmt->fetchAll();

$assignedProctorMap = [];

foreach ($proctorAssignmentRows as $row) {
    $examId = (int) $row['exam_id'];

    if (!isset($assignedProctorMap[$examId])) {
        $assignedProctorMap[$examId] = [];
    }

    $assignedProctorMap[$examId][] = (int) $row['proctor_id'];
}

$pageTitle = 'Exams';
$panelLabel = 'Teacher Panel';
$activePage = 'exams';
$extraStyles = ['assets/css/exams.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Exam Management</span>
            <h2>Create and Manage Exams</h2>
        </div>

        <button type="button" class="primary-action" data-open-modal="addExamModal">
            Add Exam
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
        <input type="text" id="examSearch" placeholder="Search exam title, subject, or status">
    </div>

    <div class="table-wrap">
        <table class="data-table" id="examsTable">
            <thead>
                <tr>
                    <th>Exam</th>
                    <th>Schedule</th>
                    <th>Duration</th>
                    <th>Questions</th>
                    <th>Students</th>
                    <th>Proctors</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($exams) > 0): ?>
                    <?php foreach ($exams as $exam): ?>
                        <?php
                            $examId = (int) $exam['id'];
                            $studentIds = isset($assignedStudentMap[$examId]) ? $assignedStudentMap[$examId] : [];
                            $proctorIds = isset($assignedProctorMap[$examId]) ? $assignedProctorMap[$examId] : [];
                            $studentJson = json_encode($studentIds);
                            $proctorJson = json_encode($proctorIds);
                        ?>
                        <tr>
                            <td>
                                <strong class="exam-title"><?php echo e($exam['title']); ?></strong>
                                <span class="exam-subject"><?php echo e($exam['subject']); ?></span>
                            </td>
                            <td>
                                <span><?php echo e(date('M d, Y h:i A', strtotime($exam['start_datetime']))); ?></span>
                                <small><?php echo e(date('M d, Y h:i A', strtotime($exam['end_datetime']))); ?></small>
                            </td>
                            <td><?php echo e($exam['duration_minutes']); ?> mins</td>
                            <td>
                                <span class="count-pill"><?php echo e($exam['question_count']); ?></span>
                            </td>
                            <td>
                                <span class="count-pill"><?php echo e($exam['student_count']); ?></span>
                            </td>
                            <td>
                                <span class="count-pill"><?php echo e($exam['proctor_count']); ?></span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo e($exam['status']); ?>">
                                    <?php echo e(ucfirst($exam['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button
                                        type="button"
                                        class="table-btn assign"
                                        data-open-students
                                        data-id="<?php echo e($exam['id']); ?>"
                                        data-title="<?php echo e($exam['title']); ?>"
                                        data-assigned="<?php echo e($studentJson); ?>"
                                    >
                                        Students
                                    </button>

                                    <button
                                        type="button"
                                        class="table-btn proctor"
                                        data-open-proctors
                                        data-id="<?php echo e($exam['id']); ?>"
                                        data-title="<?php echo e($exam['title']); ?>"
                                        data-assigned="<?php echo e($proctorJson); ?>"
                                    >
                                        Proctors
                                    </button>

                                    <a class="table-btn questions" href="questions.php?exam_id=<?php echo e($exam['id']); ?>">
                                        Questions
                                    </a>

                                    <button
                                        type="button"
                                        class="table-btn edit"
                                        data-open-edit
                                        data-id="<?php echo e($exam['id']); ?>"
                                        data-title="<?php echo e($exam['title']); ?>"
                                        data-subject="<?php echo e($exam['subject']); ?>"
                                        data-description="<?php echo e($exam['description']); ?>"
                                        data-duration="<?php echo e($exam['duration_minutes']); ?>"
                                        data-start="<?php echo e(format_datetime_for_input($exam['start_datetime'])); ?>"
                                        data-end="<?php echo e(format_datetime_for_input($exam['end_datetime'])); ?>"
                                        data-randomize="<?php echo e($exam['randomize_questions']); ?>"
                                        data-show-result="<?php echo e($exam['show_result']); ?>"
                                        data-webcam="<?php echo e($exam['webcam_required']); ?>"
                                        data-fullscreen="<?php echo e($exam['fullscreen_required']); ?>"
                                        data-max-attempts="<?php echo e($exam['max_attempts']); ?>"
                                        data-status="<?php echo e($exam['status']); ?>"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        type="button"
                                        class="table-btn danger"
                                        data-open-delete
                                        data-id="<?php echo e($exam['id']); ?>"
                                        data-title="<?php echo e($exam['title']); ?>"
                                        data-attempts="<?php echo e($exam['attempt_count']); ?>"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty-state">No exams created yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal-backdrop" id="addExamModal">
    <div class="modal-card large-modal">
        <div class="modal-header">
            <div>
                <span>New Exam</span>
                <h3>Add Exam</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label for="add_title">Exam Title</label>
                <input type="text" id="add_title" name="title" required>
            </div>

            <div class="form-group">
                <label for="add_subject">Subject</label>
                <input type="text" id="add_subject" name="subject">
            </div>

            <div class="form-group">
                <label for="add_description">Description</label>
                <textarea id="add_description" name="description"></textarea>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="add_start_datetime">Start Date and Time</label>
                    <input type="datetime-local" id="add_start_datetime" name="start_datetime" required>
                </div>

                <div class="form-group">
                    <label for="add_end_datetime">End Date and Time</label>
                    <input type="datetime-local" id="add_end_datetime" name="end_datetime" required>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="add_duration_minutes">Duration Minutes</label>
                    <input type="number" id="add_duration_minutes" name="duration_minutes" value="60" min="1" required>
                </div>

                <div class="form-group">
                    <label for="add_max_attempts">Max Attempts</label>
                    <input type="number" id="add_max_attempts" name="max_attempts" value="1" min="1" required>
                </div>
            </div>

            <div class="settings-grid">
                <label class="check-row">
                    <input type="checkbox" name="randomize_questions">
                    <span>Randomize questions</span>
                </label>

                <label class="check-row">
                    <input type="checkbox" name="show_result">
                    <span>Allow student result viewing</span>
                </label>

                <label class="check-row">
                    <input type="checkbox" name="webcam_required" checked>
                    <span>Require webcam</span>
                </label>

                <label class="check-row">
                    <input type="checkbox" name="fullscreen_required" checked>
                    <span>Require fullscreen</span>
                </label>
            </div>

            <div class="form-group">
                <label for="add_status">Status</label>
                <select id="add_status" name="status">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="closed">Closed</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="primary-button">Create Exam</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editExamModal">
    <div class="modal-card large-modal">
        <div class="modal-header">
            <div>
                <span>Edit Exam</span>
                <h3>Update Exam</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="exam_id" id="edit_exam_id">

            <div class="form-group">
                <label for="edit_title">Exam Title</label>
                <input type="text" id="edit_title" name="title" required>
            </div>

            <div class="form-group">
                <label for="edit_subject">Subject</label>
                <input type="text" id="edit_subject" name="subject">
            </div>

            <div class="form-group">
                <label for="edit_description">Description</label>
                <textarea id="edit_description" name="description"></textarea>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="edit_start_datetime">Start Date and Time</label>
                    <input type="datetime-local" id="edit_start_datetime" name="start_datetime" required>
                </div>

                <div class="form-group">
                    <label for="edit_end_datetime">End Date and Time</label>
                    <input type="datetime-local" id="edit_end_datetime" name="end_datetime" required>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="edit_duration_minutes">Duration Minutes</label>
                    <input type="number" id="edit_duration_minutes" name="duration_minutes" min="1" required>
                </div>

                <div class="form-group">
                    <label for="edit_max_attempts">Max Attempts</label>
                    <input type="number" id="edit_max_attempts" name="max_attempts" min="1" required>
                </div>
            </div>

            <div class="settings-grid">
                <label class="check-row">
                    <input type="checkbox" name="randomize_questions" id="edit_randomize_questions">
                    <span>Randomize questions</span>
                </label>

                <label class="check-row">
                    <input type="checkbox" name="show_result" id="edit_show_result">
                    <span>Allow student result viewing</span>
                </label>

                <label class="check-row">
                    <input type="checkbox" name="webcam_required" id="edit_webcam_required">
                    <span>Require webcam</span>
                </label>

                <label class="check-row">
                    <input type="checkbox" name="fullscreen_required" id="edit_fullscreen_required">
                    <span>Require fullscreen</span>
                </label>
            </div>

            <div class="form-group">
                <label for="edit_status">Status</label>
                <select id="edit_status" name="status">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="closed">Closed</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="primary-button">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="assignStudentsModal">
    <div class="modal-card large-modal">
        <div class="modal-header">
            <div>
                <span>Exam Students</span>
                <h3 id="assign_students_title">Assign Students</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="assign_students">
            <input type="hidden" name="exam_id" id="assign_students_exam_id">

            <?php if (count($students) > 0): ?>
                <div class="assign-toolbar">
                    <input type="text" id="studentAssignSearch" placeholder="Search students">
                    <button type="button" class="secondary-action compact-action" id="selectAllStudents">Select All</button>
                    <button type="button" class="secondary-action compact-action" id="clearAllStudents">Clear</button>
                </div>

                <div class="check-list" id="studentCheckList">
                    <?php foreach ($students as $student): ?>
                        <label class="check-item">
                            <input
                                type="checkbox"
                                name="student_ids[]"
                                value="<?php echo e($student['id']); ?>"
                                data-student-checkbox
                            >
                            <span>
                                <strong><?php echo e($student['full_name']); ?></strong>
                                <small><?php echo e($student['username']); ?><?php echo $student['email'] !== '' ? ' • ' . e($student['email']) : ''; ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-assignment">No active students available.</div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="primary-button">Save Students</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="assignProctorsModal">
    <div class="modal-card large-modal">
        <div class="modal-header">
            <div>
                <span>Exam Proctors</span>
                <h3 id="assign_proctors_title">Assign Proctors</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="assign_proctors">
            <input type="hidden" name="exam_id" id="assign_proctors_exam_id">

            <?php if (count($proctors) > 0): ?>
                <div class="assign-toolbar">
                    <input type="text" id="proctorAssignSearch" placeholder="Search proctors">
                    <button type="button" class="secondary-action compact-action" id="selectAllProctors">Select All</button>
                    <button type="button" class="secondary-action compact-action" id="clearAllProctors">Clear</button>
                </div>

                <div class="check-list" id="proctorCheckList">
                    <?php foreach ($proctors as $proctor): ?>
                        <label class="check-item">
                            <input
                                type="checkbox"
                                name="proctor_ids[]"
                                value="<?php echo e($proctor['id']); ?>"
                                data-proctor-checkbox
                            >
                            <span>
                                <strong><?php echo e($proctor['full_name']); ?></strong>
                                <small><?php echo e($proctor['username']); ?><?php echo $proctor['email'] !== '' ? ' • ' . e($proctor['email']) : ''; ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-assignment">No active proctors available.</div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="primary-button">Save Proctors</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="deleteExamModal">
    <div class="modal-card small-modal">
        <div class="modal-header">
            <div>
                <span>Delete Exam</span>
                <h3>Confirm Action</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="exam_id" id="delete_exam_id">

            <p class="delete-text">
                Are you sure you want to delete <strong id="delete_exam_title"></strong>?
            </p>

            <div class="modal-note danger-note" id="delete_exam_note">
                If this exam already has attempts, it will be archived instead of deleted.
            </div>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="danger-button">Continue</button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo e(app_url('assets/js/exams.js')); ?>"></script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>