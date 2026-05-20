<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('student');

header('Content-Type: application/json');

function json_response($success, $message)
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
    json_response(false, 'Invalid request.');
}

$attemptId = isset($_POST['attempt_id']) ? (int) $_POST['attempt_id'] : 0;
$eventType = isset($_POST['event_type']) ? clean_input($_POST['event_type']) : '';
$metadata = isset($_POST['metadata']) ? $_POST['metadata'] : '{}';

$eventRules = [
    'tab_switch' => [
        'severity' => 'high',
        'description' => 'Student switched away from the exam tab.'
    ],
    'window_blur' => [
        'severity' => 'medium',
        'description' => 'Exam window lost focus.'
    ],
    'fullscreen_exit' => [
        'severity' => 'high',
        'description' => 'Student exited fullscreen mode.'
    ],
    'copy_attempt' => [
        'severity' => 'medium',
        'description' => 'Student attempted to copy content during the exam.'
    ],
    'paste_attempt' => [
        'severity' => 'high',
        'description' => 'Student attempted to paste content during the exam.'
    ],
    'right_click' => [
        'severity' => 'low',
        'description' => 'Student used right click during the exam.'
    ],
    'inactivity' => [
        'severity' => 'medium',
        'description' => 'Student was inactive for an extended period.'
    ]
];

if ($attemptId <= 0 || !isset($eventRules[$eventType])) {
    json_response(false, 'Invalid proctoring event.');
}

$stmt = $pdo->prepare("
    SELECT id, exam_id, attempt_status
    FROM exam_attempts
    WHERE id = ?
    AND student_id = ?
    LIMIT 1
");
$stmt->execute([$attemptId, $studentId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    json_response(false, 'Attempt not found.');
}

if ($attempt['attempt_status'] !== 'in_progress') {
    json_response(false, 'Attempt is no longer active.');
}

$decodedMetadata = json_decode($metadata, true);

if (!is_array($decodedMetadata)) {
    $decodedMetadata = [];
}

$decodedMetadata['ip_address'] = $_SERVER['REMOTE_ADDR'];
$decodedMetadata['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 250) : '';

$metadataJson = json_encode($decodedMetadata);

$stmt = $pdo->prepare("
    SELECT id
    FROM proctor_logs
    WHERE attempt_id = ?
    AND event_type = ?
    AND created_at >= DATE_SUB(NOW(), INTERVAL 8 SECOND)
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$attemptId, $eventType]);
$recentDuplicate = $stmt->fetch();

if ($recentDuplicate) {
    json_response(true, 'Event already logged recently.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO proctor_logs
        (attempt_id, event_type, severity, event_description, metadata_json)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $attemptId,
        $eventType,
        $eventRules[$eventType]['severity'],
        $eventRules[$eventType]['description'],
        $metadataJson
    ]);

    $stmt = $pdo->prepare("
        UPDATE exam_attempts
        SET 
            violation_count = violation_count + 1,
            review_status = CASE 
                WHEN review_status = 'normal' THEN 'under_review'
                ELSE review_status
            END,
            last_activity_at = NOW()
        WHERE id = ?
        AND student_id = ?
        AND attempt_status = 'in_progress'
    ");
    $stmt->execute([$attemptId, $studentId]);

    $pdo->commit();

    json_response(true, 'Proctoring event logged.');
} catch (PDOException $e) {
    $pdo->rollBack();
    json_response(false, 'Unable to log proctoring event.');
}