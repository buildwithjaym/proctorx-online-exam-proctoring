<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$teacherId = current_user_id();

function result_percent($score, $total)
{
    if ((float) $total <= 0) {
        return 0;
    }

    return round(((float) $score / (float) $total) * 100, 1);
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
        u.full_name,
        u.username,
        (
            SELECT COUNT(*)
            FROM questions q
            WHERE q.exam_id = e.id
        ) AS question_count,
        (
            SELECT COUNT(*)
            FROM questions q
            WHERE q.exam_id = e.id
            AND q.question_type = 'essay'
        ) AS essay_count
    FROM exam_attempts ea
    INNER JOIN exams e ON ea.exam_id = e.id
    INNER JOIN users u ON ea.student_id = u.id
    WHERE e.teacher_id = ?
    AND ea.attempt_status IN ('submitted', 'auto_submitted')
    ORDER BY ea.submitted_at DESC
");
$stmt->execute([$teacherId]);
$attempts = $stmt->fetchAll();

$totalSubmitted = count($attempts);
$underReview = 0;
$flagged = 0;
$cleared = 0;
$invalidated = 0;

foreach ($attempts as $attempt) {
    if ($attempt['review_status'] === 'under_review') {
        $underReview++;
    }

    if ($attempt['review_status'] === 'flagged') {
        $flagged++;
    }

    if ($attempt['review_status'] === 'cleared' || $attempt['review_status'] === 'normal') {
        $cleared++;
    }

    if ($attempt['review_status'] === 'invalidated') {
        $invalidated++;
    }
}

$pageTitle = 'Results';
$panelLabel = 'Teacher Panel';
$activePage = 'results';
$extraStyles = ['assets/css/teacher-results.css'];

require_once __DIR__ . '/../includes/dashboard_header.php';
?>

<section class="results-hero">
    <div>
        <span>Exam Results</span>
        <h2>Review submitted exams and finalize scores</h2>
        <p>Check student submissions, review essay answers, monitor flagged attempts, and finalize exam results.</p>
    </div>
</section>

<section class="dashboard-grid teacher-result-stats">
    <div class="dashboard-card">
        <span>Submitted Attempts</span>
        <h3><?php echo e($totalSubmitted); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>Under Review</span>
        <h3><?php echo e($underReview); ?></h3>
    </div>

    <div class="dashboard-card warning">
        <span>Flagged</span>
        <h3><?php echo e($flagged); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Cleared / Normal</span>
        <h3><?php echo e($cleared); ?></h3>
    </div>

    <div class="dashboard-card">
        <span>Invalidated</span>
        <h3><?php echo e($invalidated); ?></h3>
    </div>
</section>

<section class="content-card">
    <div class="section-heading">
        <div>
            <span>Submitted Exams</span>
            <h2>Student Attempt Records</h2>
        </div>
    </div>

    <div class="result-tools">
        <input type="text" id="resultSearch" placeholder="Search student, exam, subject, or status">

        <select id="resultFilter">
            <option value="all">All Results</option>
            <option value="under_review">Under Review</option>
            <option value="flagged">Flagged</option>
            <option value="normal">Normal</option>
            <option value="cleared">Cleared</option>
            <option value="invalidated">Invalidated</option>
        </select>
    </div>

    <div class="table-wrap">
        <table class="data-table" id="resultsTable">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Exam</th>
                    <th>Score</th>
                    <th>Questions</th>
                    <th>Essay</th>
                    <th>Violations</th>
                    <th>Review</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($attempts) > 0): ?>
                    <?php foreach ($attempts as $attempt): ?>
                        <?php $percent = result_percent($attempt['score'], $attempt['total_points_at_time']); ?>

                        <tr data-review-status="<?php echo e($attempt['review_status']); ?>">
                            <td>
                                <strong><?php echo e($attempt['full_name']); ?></strong>
                                <small><?php echo e($attempt['username']); ?></small>
                            </td>

                            <td>
                                <strong><?php echo e($attempt['title']); ?></strong>
                                <small><?php echo e($attempt['subject']); ?></small>
                            </td>

                            <td>
                                <strong><?php echo e($percent); ?>%</strong>
                                <small><?php echo e($attempt['score']); ?> / <?php echo e($attempt['total_points_at_time']); ?></small>
                            </td>

                            <td><?php echo e($attempt['question_count']); ?></td>
                            <td><?php echo e($attempt['essay_count']); ?></td>
                            <td><?php echo e($attempt['violation_count']); ?></td>

                            <td>
                                <span class="status-badge <?php echo e($attempt['review_status']); ?>">
                                    <?php echo e(ucwords(str_replace('_', ' ', $attempt['review_status']))); ?>
                                </span>
                            </td>

                            <td>
                                <?php echo e(date('M d, Y h:i A', strtotime($attempt['submitted_at']))); ?>
                            </td>

                            <td>
                                <a class="table-btn review" href="review_attempt.php?attempt_id=<?php echo e($attempt['attempt_id']); ?>">
                                    Review
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="empty-state">No submitted exam attempts yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var searchInput = document.getElementById("resultSearch");
    var filterInput = document.getElementById("resultFilter");
    var table = document.getElementById("resultsTable");

    function filterResults() {
        if (!table) {
            return;
        }

        var searchValue = searchInput ? searchInput.value.toLowerCase() : "";
        var filterValue = filterInput ? filterInput.value : "all";
        var rows = table.querySelectorAll("tbody tr");

        for (var i = 0; i < rows.length; i++) {
            var text = rows[i].textContent.toLowerCase();
            var status = rows[i].getAttribute("data-review-status");
            var matchesSearch = text.indexOf(searchValue) > -1;
            var matchesFilter = filterValue === "all" || status === filterValue;

            if (matchesSearch && matchesFilter) {
                rows[i].style.display = "";
            } else {
                rows[i].style.display = "none";
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener("keyup", filterResults);
    }

    if (filterInput) {
        filterInput.addEventListener("change", filterResults);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/dashboard_footer.php'; ?>