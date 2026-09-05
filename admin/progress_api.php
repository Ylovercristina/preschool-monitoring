<?php
/**
 * API Endpoint: Fetch Student Milestones
 * Preschool Monitoring System
 */

require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$studentId = (int)($_GET['student_id'] ?? 0);
if (!$studentId) {
    echo json_encode([]);
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT sm.*, m.title, m.category, m.description, u.name as teacher_name
    FROM student_milestones sm
    JOIN academic_milestones m ON sm.milestone_id = m.id
    LEFT JOIN users u ON sm.evaluated_by = u.id
    WHERE sm.student_id = ?
    ORDER BY m.category ASC, sm.rating DESC
");
$stmt->execute([$studentId]);
echo json_encode($stmt->fetchAll());
