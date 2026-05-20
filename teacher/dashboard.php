<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$teacherId = current_user_id();

function dashboard_percent($value, $total)
{
    if ((int) $total <= 0) {
        return 0;
    }

    return round(((int) $value / (int) $total) * 100);
}

function dashboard_max_value($values)
{
    $max = 1;

    foreach ($values as $value) {
        if ((int) $value > $max) {
            $max = (int) $value;
        }
    }

    return $max;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND created_by = ? AND status = 'active'");
$stmt->execute([$teacherId]);
$totalStudents = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'proctor' AND created_by = ? AND status = 'active'");
$stmt->execute([$teacherId]);
$totalProctors = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE teacher_id = ? AND status = 'active'");
$stmt->execute([$teacherId]);
$totalClasses = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE teacher_id = ? AND status IN ('draft', 'published')");
$stmt->execute([$teacherId]);
$totalExams = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    WHERE e.teacher_id = ?
    AND ea.review_status IN ('flagged', 'under_review')
");
$stmt->execute([$teacherId]);
$flaggedAttempts = (int) $stmt->fetchColumn();

$examStatusLabels = [
    'draft' => 'Draft',
    'published' => 'Published',
    'closed' => 'Closed',
    'archived' => 'Archived'
];

$examStatusCounts = [
    'draft' => 0,
    'published' => 0,
    'closed' => 0,
    'archived' => 0
];

$stmt = $pdo->prepare("
    SELECT status, COUNT(*) AS total
    FROM exams
    WHERE teacher_id = ?
    GROUP BY status
");
$stmt->execute([$teacherId]);
$examStatusRows = $stmt->fetchAll();

foreach ($examStatusRows as $row) {
    if (isset($examStatusCounts[$row['status']])) {
        $examStatusCounts[$row['status']] = (int) $row['total'];
    }
}

$maxExamStatus = dashboard_max_value($examStatusCounts);

$reviewStatusLabels = [
    'normal' => 'Normal',
    'flagged' => 'Flagged',
    'under_review' => 'Under Review',
    'cleared' => 'Cleared',
    'invalidated' => 'Invalidated'
];

$reviewStatusCounts = [
    'normal' => 0,
    'flagged' => 0,
    'under_review' => 0,
    'cleared' => 0,
    'invalidated' => 0
];

$stmt = $pdo->prepare("
    SELECT ea.review_status, COUNT(*) AS total
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    WHERE e.teacher_id = ?
    GROUP BY ea.review_status
");
$stmt->execute([$teacherId]);
$reviewStatusRows = $stmt->fetchAll();

foreach ($reviewStatusRows as $row) {
    if (isset($reviewStatusCounts[$row['review_status']])) {
        $reviewStatusCounts[$row['review_status']] = (int) $row['total'];
    }
}

$maxReviewStatus = dashboard_max_value($reviewStatusCounts);

$dailySubmissions = [];
$dailyLabels = [];

for ($i = 6; $i >= 0; $i--) {
    $date = new DateTime();
    $date->sub(new DateInterval('P' . $i . 'D'));
    $key = $date->format('Y-m-d');

    $dailySubmissions[$key] = 0;
    $dailyLabels[$key] = $date->format('M d');
}

$stmt = $pdo->prepare("
    SELECT DATE(ea.submitted_at) AS submit_date, COUNT(*) AS total
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    WHERE e.teacher_id = ?
    AND ea.submitted_at IS NOT NULL
    AND ea.submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(ea.submitted_at)
");
$stmt->execute([$teacherId]);
$dailyRows = $stmt->fetchAll();

foreach ($dailyRows as $row) {
    if (isset($dailySubmissions[$row['submit_date']])) {
        $dailySubmissions[$row['submit_date']] = (int) $row['total'];
    }
}

$maxDailySubmissions = dashboard_max_value($dailySubmissions);

$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.title,
        COUNT(ea.id) AS attempt_count,
        COALESCE(SUM(ea.violation_count), 0) AS total_violations
    FROM exams e
    LEFT JOIN exam_attempts ea ON ea.exam_id = e.id
    WHERE e.teacher_id = ?
    GROUP BY e.id, e.title
    HAVING total_violations > 0
    ORDER BY total_violations DESC
    LIMIT 5
");
$stmt->execute([$teacherId]);
$violationExams = $stmt->fetchAll();

$maxViolations = 1;

foreach ($violationExams as $exam) {
    if ((int) $exam['total_violations'] > $maxViolations) {
        $maxViolations = (int) $exam['total_violations'];
    }
}

$stmt = $pdo->prepare("
    SELECT 
        e.title,
        u.full_name,
        ea.score,
        ea.total_points_at_time,
        ea.attempt_status,
        ea.review_status,
        ea.violation_count,
        ea.submitted_at,
        ea.created_at
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    INNER JOIN users u ON ea.student_id = u.id
    WHERE e.teacher_id = ?
    ORDER BY ea.created_at DESC
    LIMIT 6
");
$stmt->execute([$teacherId]);
$recentAttempts = $stmt->fetchAll();

$pageTitle = 'Teacher Dashboard';
$panelLabel = 'Teacher Panel';
$activePage = 'dashboard';
$extraStyles = ['assets/css/teacher-dashboard.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="dashboard-grid">
    <div class="dashboard-card">
        <span>Total Students</span>
        <h3><?php echo e($totalStudents); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Total Proctors</span>
        <h3><?php echo e($totalProctors); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Total Classes</span>
        <h3><?php echo e($totalClasses); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Total Exams</span>
        <h3><?php echo e($totalExams); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>Flagged Attempts</span>
        <h3><?php echo e($flaggedAttempts); ?></h3>
    </div>
</section>

<section class="teacher-dashboard-layout">
    <div class="content-card chart-card">
        <div class="section-heading">
            <div>
                <span>Exam Overview</span>
                <h2>Exam Status Distribution</h2>
            </div>
        </div>

        <div class="bar-chart">
            <?php foreach ($examStatusCounts as $status => $count): ?>
                <?php $percent = dashboard_percent($count, $maxExamStatus); ?>
                <div class="bar-row">
                    <div class="bar-label"><?php echo e($examStatusLabels[$status]); ?></div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width: <?php echo e($percent); ?>%;"></div>
                    </div>
                    <div class="bar-value"><?php echo e($count); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="content-card chart-card">
        <div class="section-heading">
            <div>
                <span>Proctoring Review</span>
                <h2>Attempt Review Status</h2>
            </div>
        </div>

        <div class="bar-chart">
            <?php foreach ($reviewStatusCounts as $status => $count): ?>
                <?php $percent = dashboard_percent($count, $maxReviewStatus); ?>
                <div class="bar-row">
                    <div class="bar-label"><?php echo e($reviewStatusLabels[$status]); ?></div>
                    <div class="bar-track">
                        <div class="bar-fill gold" style="width: <?php echo e($percent); ?>%;"></div>
                    </div>
                    <div class="bar-value"><?php echo e($count); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="teacher-dashboard-layout">
    <div class="content-card chart-card wide-card">
        <div class="section-heading">
            <div>
                <span>Submission Activity</span>
                <h2>Submissions in the Last 7 Days</h2>
            </div>
        </div>

        <div class="daily-chart">
            <?php foreach ($dailySubmissions as $date => $count): ?>
                <?php $height = dashboard_percent($count, $maxDailySubmissions); ?>
                <div class="daily-item">
                    <div class="daily-bar-wrap">
                        <div class="daily-bar" style="height: <?php echo e($height); ?>%;"></div>
                    </div>
                    <strong><?php echo e($count); ?></strong>
                    <span><?php echo e($dailyLabels[$date]); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="content-card chart-card">
        <div class="section-heading">
            <div>
                <span>Risk Monitor</span>
                <h2>Top Exams by Violations</h2>
            </div>
        </div>

        <?php if (count($violationExams) > 0): ?>
            <div class="violation-list">
                <?php foreach ($violationExams as $exam): ?>
                    <?php $percent = dashboard_percent($exam['total_violations'], $maxViolations); ?>
                    <div class="violation-item">
                        <div class="violation-top">
                            <strong><?php echo e($exam['title']); ?></strong>
                            <span><?php echo e($exam['total_violations']); ?> logs</span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill danger" style="width: <?php echo e($percent); ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-chart">No violation records yet.</div>
        <?php endif; ?>
    </div>
</section>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Recent Activity</span>
            <h2>Latest Exam Attempts</h2>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Exam</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Review</th>
                    <th>Violations</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recentAttempts) > 0): ?>
                    <?php foreach ($recentAttempts as $attempt): ?>
                        <tr>
                            <td><?php echo e($attempt['full_name']); ?></td>
                            <td><?php echo e($attempt['title']); ?></td>
                            <td>
                                <?php echo e($attempt['score']); ?> / <?php echo e($attempt['total_points_at_time']); ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo e($attempt['attempt_status']); ?>">
                                    <?php echo e(ucwords(str_replace('_', ' ', $attempt['attempt_status']))); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo e($attempt['review_status']); ?>">
                                    <?php echo e(ucwords(str_replace('_', ' ', $attempt['review_status']))); ?>
                                </span>
                            </td>
                            <td><?php echo e($attempt['violation_count']); ?></td>
                            <td>
                                <?php if ($attempt['submitted_at']): ?>
                                    <?php echo e(date('M d, Y h:i A', strtotime($attempt['submitted_at']))); ?>
                                <?php else: ?>
                                    Not submitted
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-state">No exam attempts yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Next Step</span>
            <h2>Build your exam environment</h2>
        </div>
    </div>

    <div class="quick-actions">
        <a href="students.php">Manage Students</a>
        <a href="proctors.php">Manage Proctors</a>
        <a href="classes.php">Manage Classes</a>
        <a href="exams.php">Create Exam</a>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>