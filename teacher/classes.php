<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$teacherId = current_user_id();

function set_class_flash($type, $message)
{
    $_SESSION['class_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function class_duplicate_exists($pdo, $teacherId, $className, $section, $schoolYear, $excludeId = 0)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM classes
        WHERE teacher_id = ?
        AND LOWER(class_name) = LOWER(?)
        AND COALESCE(section, '') = ?
        AND COALESCE(school_year, '') = ?
        AND id != ?
        LIMIT 1
    ");

    $stmt->execute([
        $teacherId,
        $className,
        $section,
        $schoolYear,
        $excludeId
    ]);

    return $stmt->fetch() ? true : false;
}

$token = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($postedToken)) {
        set_class_flash('error', 'Invalid request. Please try again.');
        redirect_to('teacher/classes.php');
    }

    $action = isset($_POST['action']) ? clean_input($_POST['action']) : '';

    if ($action === 'create') {
        $className = isset($_POST['class_name']) ? clean_input($_POST['class_name']) : '';
        $section = isset($_POST['section']) ? clean_input($_POST['section']) : '';
        $schoolYear = isset($_POST['school_year']) ? clean_input($_POST['school_year']) : '';
        $status = isset($_POST['status']) ? clean_input($_POST['status']) : 'active';

        if ($className === '') {
            set_class_flash('error', 'Class name is required.');
            redirect_to('teacher/classes.php');
        }

        if (!in_array($status, ['active', 'inactive', 'archived'])) {
            set_class_flash('error', 'Invalid class status.');
            redirect_to('teacher/classes.php');
        }

        if (class_duplicate_exists($pdo, $teacherId, $className, $section, $schoolYear)) {
            set_class_flash('error', 'This class already exists.');
            redirect_to('teacher/classes.php');
        }

        $sectionValue = $section === '' ? null : $section;
        $schoolYearValue = $schoolYear === '' ? null : $schoolYear;

        $stmt = $pdo->prepare("
            INSERT INTO classes
            (teacher_id, class_name, section, school_year, status)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $teacherId,
            $className,
            $sectionValue,
            $schoolYearValue,
            $status
        ]);

        set_class_flash('success', 'Class created successfully.');
        redirect_to('teacher/classes.php');
    }

    if ($action === 'update') {
        $classId = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        $className = isset($_POST['class_name']) ? clean_input($_POST['class_name']) : '';
        $section = isset($_POST['section']) ? clean_input($_POST['section']) : '';
        $schoolYear = isset($_POST['school_year']) ? clean_input($_POST['school_year']) : '';
        $status = isset($_POST['status']) ? clean_input($_POST['status']) : 'active';

        $stmt = $pdo->prepare("
            SELECT id
            FROM classes
            WHERE id = ?
            AND teacher_id = ?
            LIMIT 1
        ");
        $stmt->execute([$classId, $teacherId]);
        $class = $stmt->fetch();

        if (!$class) {
            set_class_flash('error', 'Class not found.');
            redirect_to('teacher/classes.php');
        }

        if ($className === '') {
            set_class_flash('error', 'Class name is required.');
            redirect_to('teacher/classes.php');
        }

        if (!in_array($status, ['active', 'inactive', 'archived'])) {
            set_class_flash('error', 'Invalid class status.');
            redirect_to('teacher/classes.php');
        }

        if (class_duplicate_exists($pdo, $teacherId, $className, $section, $schoolYear, $classId)) {
            set_class_flash('error', 'Another class with the same details already exists.');
            redirect_to('teacher/classes.php');
        }

        $sectionValue = $section === '' ? null : $section;
        $schoolYearValue = $schoolYear === '' ? null : $schoolYear;

        $stmt = $pdo->prepare("
            UPDATE classes
            SET class_name = ?, section = ?, school_year = ?, status = ?
            WHERE id = ?
            AND teacher_id = ?
        ");

        $stmt->execute([
            $className,
            $sectionValue,
            $schoolYearValue,
            $status,
            $classId,
            $teacherId
        ]);

        set_class_flash('success', 'Class updated successfully.');
        redirect_to('teacher/classes.php');
    }

    if ($action === 'delete') {
        $classId = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;

        $stmt = $pdo->prepare("
            SELECT id
            FROM classes
            WHERE id = ?
            AND teacher_id = ?
            LIMIT 1
        ");
        $stmt->execute([$classId, $teacherId]);
        $class = $stmt->fetch();

        if (!$class) {
            set_class_flash('error', 'Class not found.');
            redirect_to('teacher/classes.php');
        }

        $stmt = $pdo->prepare("
            DELETE FROM classes
            WHERE id = ?
            AND teacher_id = ?
        ");
        $stmt->execute([$classId, $teacherId]);

        set_class_flash('success', 'Class deleted successfully.');
        redirect_to('teacher/classes.php');
    }

    if ($action === 'assign_students') {
        $classId = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
        $studentIds = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? $_POST['student_ids'] : [];

        $stmt = $pdo->prepare("
            SELECT id
            FROM classes
            WHERE id = ?
            AND teacher_id = ?
            LIMIT 1
        ");
        $stmt->execute([$classId, $teacherId]);
        $class = $stmt->fetch();

        if (!$class) {
            set_class_flash('error', 'Class not found.');
            redirect_to('teacher/classes.php');
        }

        $cleanStudentIds = [];

        foreach ($studentIds as $studentId) {
            $cleanStudentIds[] = (int) $studentId;
        }

        $cleanStudentIds = array_unique($cleanStudentIds);

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
            $validRows = $stmt->fetchAll();

            $validStudentIds = [];

            foreach ($validRows as $row) {
                $validStudentIds[] = (int) $row['id'];
            }
        } else {
            $validStudentIds = [];
        }

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("DELETE FROM class_students WHERE class_id = ?");
            $stmt->execute([$classId]);

            if (count($validStudentIds) > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO class_students
                    (class_id, student_id)
                    VALUES (?, ?)
                ");

                foreach ($validStudentIds as $studentId) {
                    $stmt->execute([$classId, $studentId]);
                }
            }

            $pdo->commit();

            set_class_flash('success', 'Class students updated successfully.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            set_class_flash('error', 'Unable to update class students.');
        }

        redirect_to('teacher/classes.php');
    }

    set_class_flash('error', 'Invalid action.');
    redirect_to('teacher/classes.php');
}

$flash = null;

if (isset($_SESSION['class_flash'])) {
    $flash = $_SESSION['class_flash'];
    unset($_SESSION['class_flash']);
}

$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.class_name,
        c.section,
        c.school_year,
        c.status,
        c.created_at,
        COUNT(cs.id) AS student_count
    FROM classes c
    LEFT JOIN class_students cs ON cs.class_id = c.id
    WHERE c.teacher_id = ?
    GROUP BY c.id, c.class_name, c.section, c.school_year, c.status, c.created_at
    ORDER BY c.created_at DESC
");
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll();

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
    SELECT cs.class_id, cs.student_id
    FROM class_students cs
    INNER JOIN classes c ON cs.class_id = c.id
    WHERE c.teacher_id = ?
");
$stmt->execute([$teacherId]);
$assignedRows = $stmt->fetchAll();

$assignedStudentMap = [];

foreach ($assignedRows as $row) {
    $classId = (int) $row['class_id'];

    if (!isset($assignedStudentMap[$classId])) {
        $assignedStudentMap[$classId] = [];
    }

    $assignedStudentMap[$classId][] = (int) $row['student_id'];
}

$pageTitle = 'Classes';
$panelLabel = 'Teacher Panel';
$activePage = 'classes';
$extraStyles = ['assets/css/classes.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Class Management</span>
            <h2>Manage Classes and Student Groups</h2>
        </div>

        <button type="button" class="primary-action" data-open-modal="addClassModal">
            Add Class
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
        <input type="text" id="classSearch" placeholder="Search class, section, or school year">
    </div>

    <div class="table-wrap">
        <table class="data-table" id="classesTable">
            <thead>
                <tr>
                    <th>Class Name</th>
                    <th>Section</th>
                    <th>School Year</th>
                    <th>Students</th>
                    <th>Status</th>
                    <th>Date Added</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($classes) > 0): ?>
                    <?php foreach ($classes as $class): ?>
                        <?php
                            $classId = (int) $class['id'];
                            $assignedIds = isset($assignedStudentMap[$classId]) ? $assignedStudentMap[$classId] : [];
                            $assignedJson = json_encode($assignedIds);
                        ?>
                        <tr>
                            <td><?php echo e($class['class_name']); ?></td>
                            <td><?php echo e($class['section']); ?></td>
                            <td><?php echo e($class['school_year']); ?></td>
                            <td>
                                <span class="class-count"><?php echo e($class['student_count']); ?></span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo e($class['status']); ?>">
                                    <?php echo e(ucfirst($class['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo e(date('M d, Y', strtotime($class['created_at']))); ?></td>
                            <td>
                                <div class="table-actions">
                                    <button 
                                        type="button" 
                                        class="table-btn assign"
                                        data-open-assign
                                        data-id="<?php echo e($class['id']); ?>"
                                        data-title="<?php echo e($class['class_name']); ?>"
                                        data-assigned="<?php echo e($assignedJson); ?>"
                                    >
                                        Students
                                    </button>

                                    <button 
                                        type="button" 
                                        class="table-btn edit"
                                        data-open-edit
                                        data-id="<?php echo e($class['id']); ?>"
                                        data-name="<?php echo e($class['class_name']); ?>"
                                        data-section="<?php echo e($class['section']); ?>"
                                        data-school-year="<?php echo e($class['school_year']); ?>"
                                        data-status="<?php echo e($class['status']); ?>"
                                    >
                                        Edit
                                    </button>

                                    <button 
                                        type="button" 
                                        class="table-btn danger"
                                        data-open-delete
                                        data-id="<?php echo e($class['id']); ?>"
                                        data-title="<?php echo e($class['class_name']); ?>"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-state">No classes created yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal-backdrop" id="addClassModal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <span>New Class</span>
                <h3>Add Class</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label for="add_class_name">Class Name</label>
                <input type="text" id="add_class_name" name="class_name" required>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="add_section">Section</label>
                    <input type="text" id="add_section" name="section">
                </div>

                <div class="form-group">
                    <label for="add_school_year">School Year</label>
                    <input type="text" id="add_school_year" name="school_year" placeholder="2025-2026">
                </div>
            </div>

            <div class="form-group">
                <label for="add_status">Status</label>
                <select id="add_status" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="primary-button">Create Class</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editClassModal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <span>Edit Class</span>
                <h3>Update Class</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="class_id" id="edit_class_id">

            <div class="form-group">
                <label for="edit_class_name">Class Name</label>
                <input type="text" id="edit_class_name" name="class_name" required>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="edit_section">Section</label>
                    <input type="text" id="edit_section" name="section">
                </div>

                <div class="form-group">
                    <label for="edit_school_year">School Year</label>
                    <input type="text" id="edit_school_year" name="school_year">
                </div>
            </div>

            <div class="form-group">
                <label for="edit_status">Status</label>
                <select id="edit_status" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
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
                <span>Class Students</span>
                <h3 id="assign_class_title">Manage Students</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="assign_students">
            <input type="hidden" name="class_id" id="assign_class_id">

            <?php if (count($students) > 0): ?>
                <div class="assign-toolbar">
                    <input type="text" id="studentAssignSearch" placeholder="Search students">
                    <button type="button" class="secondary-action compact-action" id="selectAllStudents">Select All</button>
                    <button type="button" class="secondary-action compact-action" id="clearAllStudents">Clear</button>
                </div>

                <div class="student-check-list" id="studentCheckList">
                    <?php foreach ($students as $student): ?>
                        <label class="student-check-item">
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
                <div class="empty-assignment">
                    No active students available. Add students first before assigning them to a class.
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="primary-button">Save Students</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="deleteClassModal">
    <div class="modal-card small-modal">
        <div class="modal-header">
            <div>
                <span>Delete Class</span>
                <h3>Confirm Delete</h3>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="class_id" id="delete_class_id">

            <p class="delete-text">
                Are you sure you want to delete <strong id="delete_class_title"></strong>?
            </p>

            <div class="modal-note danger-note">
                Student assignments under this class will also be removed.
            </div>

            <div class="form-actions">
                <button type="button" class="secondary-action" data-close-modal>Cancel</button>
                <button type="submit" class="danger-button">Delete Class</button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo e(app_url('assets/js/classes.js')); ?>"></script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>