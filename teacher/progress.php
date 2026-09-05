<?php
/**
 * Student Academic Milestones & Assessment Management
 * Preschool Monitoring System
 * Fulfills: "update student academic progress: As a teacher, I want to record and update my students
 * milestones, activities, and assessments so that I can monitor their progress."
 */

$pageTitle = 'Milestones & Academic Assessments';
$pageSubtitle = 'Record, evaluate and update student developmental milestones, activities and term progress reports';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['teacher']);

$db = getDB();
$teacherId = $_SESSION['user_id'];

// Get assigned classroom
$classStmt = $db->prepare("SELECT * FROM classrooms WHERE teacher_id = ? LIMIT 1");
$classStmt->execute([$teacherId]);
$classroom = $classStmt->fetch();
$classroomId = $classroom['id'] ?? 0;

// Fetch Students in this class
$students = $db->query("
    SELECT s.*, u.name as parent_name 
    FROM students s 
    LEFT JOIN users u ON s.parent_id = u.id
    WHERE s.classroom_id = $classroomId AND s.enrollment_status = 'enrolled'
    ORDER BY s.last_name ASC
")->fetchAll();

$selectedStudentId = (int)($_GET['student_id'] ?? ($students[0]['id'] ?? 0));

// Handle Milestone & Assessment Update Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $studentId = (int)$_POST['student_id'];
    $ratings = $_POST['milestones'] ?? [];
    $term = trim($_POST['term'] ?? 'Term 1 - Midterm');
    $overallRemarks = trim($_POST['overall_remarks'] ?? '');
    $needsIntervention = !empty($_POST['needs_intervention']) ? 1 : 0;

    // 1. Update individual milestones
    foreach ($ratings as $milestoneId => $data) {
        $milestoneId = (int)$milestoneId;
        $rating = $data['rating'] ?? 'progressing';
        $remarks = trim($data['remarks'] ?? '');

        $check = $db->prepare("SELECT id FROM student_milestones WHERE student_id = ? AND milestone_id = ?");
        $check->execute([$studentId, $milestoneId]);
        $existing = $check->fetch();

        if ($existing) {
            $up = $db->prepare("UPDATE student_milestones SET rating = ?, remarks = ?, evaluated_by = ?, updated_at = datetime('now') WHERE id = ?");
            $up->execute([$rating, $remarks, $teacherId, $existing['id']]);
        } else {
            $ins = $db->prepare("INSERT INTO student_milestones (student_id, milestone_id, rating, remarks, evaluated_by) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$studentId, $milestoneId, $rating, $remarks, $teacherId]);
        }
    }

    // 2. Update or insert term assessment
    $assCheck = $db->prepare("SELECT id FROM academic_assessments WHERE student_id = ? AND term = ?");
    $assCheck->execute([$studentId, $term]);
    $existingAss = $assCheck->fetch();

    if ($existingAss) {
        $aUp = $db->prepare("UPDATE academic_assessments SET overall_remarks = ?, needs_intervention = ?, teacher_id = ? WHERE id = ?");
        $aUp->execute([$overallRemarks, $needsIntervention, $teacherId, $existingAss['id']]);
    } else {
        $aIns = $db->prepare("INSERT INTO academic_assessments (student_id, term, overall_remarks, needs_intervention, teacher_id) VALUES (?, ?, ?, ?, ?)");
        $aIns->execute([$studentId, $term, $overallRemarks, $needsIntervention, $teacherId]);
    }

    // Notify parent
    $stInfo = $db->prepare("SELECT first_name, last_name, parent_id FROM students WHERE id = ?");
    $stInfo->execute([$studentId]);
    $stData = $stInfo->fetch();
    if ($stData && $stData['parent_id']) {
        $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'milestone', 'parent/progress.php')");
        $notifStmt->execute([
            $stData['parent_id'],
            "New Milestone Evaluation: {$stData['first_name']}",
            "Teacher Sarah updated {$stData['first_name']}'s developmental milestones and academic assessment."
        ]);
    }

    logActivity('Milestones Evaluated', "Teacher updated milestones and assessment for student ID {$studentId} ({$stData['first_name']})");
    setFlash('success', "Developmental milestones and term assessment for {$stData['first_name']} successfully saved!");
    header("Location: progress.php?student_id={$studentId}");
    exit;
}

// Fetch selected student details
$selectedStudent = null;
foreach ($students as $st) {
    if ($st['id'] === $selectedStudentId) {
        $selectedStudent = $st;
        break;
    }
}

// Fetch all available milestone definitions grouped by domain
$milestoneDefinitions = $db->query("SELECT * FROM academic_milestones ORDER BY category ASC, id ASC")->fetchAll();
$domains = [];
foreach ($milestoneDefinitions as $m) {
    $domains[$m['category']][] = $m;
}

// Fetch current ratings for selected student
$studentRatings = [];
if ($selectedStudentId) {
    $rStmt = $db->prepare("SELECT * FROM student_milestones WHERE student_id = ?");
    $rStmt->execute([$selectedStudentId]);
    while ($row = $rStmt->fetch()) {
        $studentRatings[$row['milestone_id']] = $row;
    }
}

// Fetch current assessment for selected student
$currentAssessment = null;
if ($selectedStudentId) {
    $assStmt = $db->prepare("SELECT * FROM academic_assessments WHERE student_id = ? ORDER BY id DESC LIMIT 1");
    $assStmt->execute([$selectedStudentId]);
    $currentAssessment = $assStmt->fetch();
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Pupil Selector Ribbon -->
<div class="card" style="padding: 16px 24px; margin-bottom: 24px;">
    <div class="d-flex justify-between align-center" style="flex-wrap: wrap; gap: 16px;">
        <div class="d-flex align-center gap-3">
            <label for="studentSelect" style="font-weight: 700; font-size: 0.95rem;">Select Pupil to Evaluate:</label>
            <select id="studentSelect" class="form-select" style="max-width: 280px;" onchange="location.href='progress.php?student_id=' + this.value;">
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $s['id'] === $selectedStudentId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?> (<?= htmlspecialchars($s['lrn']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($selectedStudent): ?>
            <div class="d-flex align-center gap-2">
                <span style="font-size: 0.88rem; color: var(--text-muted);">Parent: <strong><?= htmlspecialchars($selectedStudent['parent_name'] ?? 'Not Linked') ?></strong></span>
                <span class="badge badge-primary">DOB: <?= formatDate($selectedStudent['dob']) ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$selectedStudent): ?>
    <div class="card" style="text-align:center; padding: 40px;">
        <p style="color: var(--text-muted);">Please select an enrolled student to begin evaluation.</p>
    </div>
<?php else: ?>
    <form method="POST" action="progress.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="student_id" value="<?= $selectedStudent['id'] ?>">

        <!-- Term Overall Assessment & Intervention Flag Card -->
        <div class="card" style="background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFC 100%); border-left: 5px solid var(--primary);">
            <div class="card-header">
                <h3 class="card-title">
                    <span>📝</span> Term Progress Report & Academic Intervention Watch
                </h3>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
                <div>
                    <div class="form-group">
                        <label class="form-label" for="term">Grading Period / Term</label>
                        <select name="term" id="term" class="form-select">
                            <option value="Term 1 - Midterm" <?= ($currentAssessment['term'] ?? '') === 'Term 1 - Midterm' ? 'selected' : '' ?>>Term 1 - Midterm</option>
                            <option value="Term 1 - Finals" <?= ($currentAssessment['term'] ?? '') === 'Term 1 - Finals' ? 'selected' : '' ?>>Term 1 - Finals</option>
                            <option value="Term 2 - Midterm" <?= ($currentAssessment['term'] ?? '') === 'Term 2 - Midterm' ? 'selected' : '' ?>>Term 2 - Midterm</option>
                            <option value="Term 2 - Finals" <?= ($currentAssessment['term'] ?? '') === 'Term 2 - Finals' ? 'selected' : '' ?>>Term 2 - Finals</option>
                        </select>
                    </div>

                    <div style="background: #FFF1F2; border: 1px solid #FECDD3; border-radius: var(--radius-md); padding: 14px; margin-top: 14px;">
                        <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="needs_intervention" value="1" style="margin-top: 3px;" <?= (!empty($currentAssessment['needs_intervention'])) ? 'checked' : '' ?>>
                            <div>
                                <strong style="color: var(--rose-dark); font-size: 0.92rem;">Flag: Requires Early Intervention</strong>
                                <p style="font-size: 0.78rem; color: #881337; margin-top: 2px;">
                                    Highlights this pupil on the Admin Academic Support Radar for targeted learning assistance.
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <div class="form-group">
                        <label class="form-label" for="overall_remarks">Teacher's Holistic Assessment & Feedback</label>
                        <textarea name="overall_remarks" id="overall_remarks" class="form-control" rows="4" placeholder="Write overall remarks on cognitive curiosity, social adaptation, and areas to practice at home..."><?= htmlspecialchars($currentAssessment['overall_remarks'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Developmental Domain Milestone Cards -->
        <?php foreach ($domains as $categoryName => $milestones): ?>
            <div class="card">
                <div class="card-header" style="background: var(--bg-card-subtle); margin: -24px -24px 20px -24px; padding: 16px 24px;">
                    <h3 class="card-title" style="font-size: 1.05rem;">
                        <span>⭐</span> <?= htmlspecialchars($categoryName) ?>
                    </h3>
                </div>

                <div style="display: flex; flex-direction: column; gap: 18px;">
                    <?php foreach ($milestones as $m): 
                        $curRating = $studentRatings[$m['id']]['rating'] ?? 'progressing';
                        $curRemarks = $studentRatings[$m['id']]['remarks'] ?? '';
                    ?>
                        <div style="padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: #FFFFFF; display: grid; grid-template-columns: 2fr 1.5fr 2fr; gap: 16px; align-items: center;">
                            <div>
                                <strong style="font-size: 0.95rem; color: var(--text-primary);"><?= htmlspecialchars($m['title']) ?></strong>
                                <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($m['description']) ?></p>
                            </div>

                            <!-- Rating Radios -->
                            <div>
                                <div class="d-flex gap-2" style="flex-wrap: wrap;">
                                    <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 0.82rem; font-weight: 700;">
                                        <input type="radio" name="milestones[<?= $m['id'] ?>][rating]" value="mastered" <?= $curRating === 'mastered' ? 'checked' : '' ?>>
                                        <span class="badge badge-success" style="padding: 4px 8px;">Mastered</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 0.82rem; font-weight: 700;">
                                        <input type="radio" name="milestones[<?= $m['id'] ?>][rating]" value="progressing" <?= $curRating === 'progressing' ? 'checked' : '' ?>>
                                        <span class="badge badge-info" style="padding: 4px 8px;">In Progress</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 0.82rem; font-weight: 700;">
                                        <input type="radio" name="milestones[<?= $m['id'] ?>][rating]" value="needs_support" <?= $curRating === 'needs_support' ? 'checked' : '' ?>>
                                        <span class="badge badge-danger" style="padding: 4px 8px;">Needs Support</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Teacher Observation Notes -->
                            <div>
                                <input type="text" name="milestones[<?= $m['id'] ?>][remarks]" class="form-control" value="<?= htmlspecialchars($curRemarks) ?>" placeholder="Specific observation notes / achievements..." style="font-size: 0.85rem;">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Floating Save Bar -->
        <div class="card" style="position: sticky; bottom: 20px; z-index: 30; box-shadow: var(--shadow-xl); display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; background: rgba(255,255,255,0.98); backdrop-filter: blur(8px);">
            <div>
                <strong>Evaluating:</strong> <?= htmlspecialchars($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?>
                <span style="color: var(--text-muted); font-size: 0.85rem; margin-left: 8px;">Parent will receive notification upon save.</span>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">
                💾 Save Milestone Evaluations
            </button>
        </div>
    </form>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
