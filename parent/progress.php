<?php
/**
 * Child Academic Progress & Milestone Report (Parent View)
 * Preschool Monitoring System
 * Fulfills: "Monitor student academic progress: As a parent, I want to view my child's academic progress
 * so that I can monitor my child's performance in school."
 */

$pageTitle = 'Child Academic Progress & Milestones';
$pageSubtitle = 'Review developmental milestones, teacher feedback and term progress evaluations';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['parent']);

$db = getDB();
$parentId = $_SESSION['user_id'];

// Fetch Child
$childStmt = $db->prepare("
    SELECT s.*, c.name as class_name, u.name as teacher_name
    FROM students s
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    LEFT JOIN users u ON c.teacher_id = u.id
    WHERE s.parent_id = ? AND s.enrollment_status = 'enrolled'
    LIMIT 1
");
$childStmt->execute([$parentId]);
$child = $childStmt->fetch();
$childId = $child['id'] ?? 0;

// Fetch Milestones
$milestones = [];
if ($childId) {
    $mStmt = $db->prepare("
        SELECT sm.*, m.title, m.category, m.description, u.name as evaluated_by_name
        FROM student_milestones sm
        JOIN academic_milestones m ON sm.milestone_id = m.id
        LEFT JOIN users u ON sm.evaluated_by = u.id
        WHERE sm.student_id = ?
        ORDER BY m.category ASC, sm.rating DESC
    ");
    $mStmt->execute([$childId]);
    $milestones = $mStmt->fetchAll();
}

// Group milestones by domain
$domains = [];
$mastered = 0; $progressing = 0; $needsSupport = 0;
foreach ($milestones as $m) {
    $domains[$m['category']][] = $m;
    if ($m['rating'] === 'mastered') $mastered++;
    elseif ($m['rating'] === 'progressing') $progressing++;
    elseif ($m['rating'] === 'needs_support') $needsSupport++;
}
$totalEvaluated = count($milestones);

// Fetch Assessment
$assessment = null;
if ($childId) {
    $assStmt = $db->prepare("
        SELECT a.*, u.name as teacher_name 
        FROM academic_assessments a 
        LEFT JOIN users u ON a.teacher_id = u.id 
        WHERE a.student_id = ? 
        ORDER BY a.id DESC LIMIT 1
    ");
    $assStmt->execute([$childId]);
    $assessment = $assStmt->fetch();
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<?php if (!$child): ?>
    <div class="card" style="text-align: center; padding: 40px;">
        <p style="color: var(--text-muted);">No student record currently linked to your parent account.</p>
    </div>
<?php else: ?>

    <!-- Print & Header Toolbar -->
    <div class="d-flex justify-between align-center no-print" style="margin-bottom: 24px;">
        <div>
            <h2 style="margin: 0; font-size: 1.4rem;">
                🌟 Progress Report: <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
            </h2>
            <div style="font-size: 0.88rem; color: var(--text-muted);">
                Classroom: <strong><?= htmlspecialchars($child['class_name'] ?? 'Class') ?></strong> &bull; Teacher: <strong><?= htmlspecialchars($child['teacher_name'] ?? 'Faculty') ?></strong>
            </div>
        </div>
        <button class="btn btn-primary" onclick="triggerPrint()">
            🖨️ Print Official Progress Card
        </button>
    </div>

    <!-- Official Report Card Header (Printable) -->
    <div class="card" style="background: #FFFFFF; padding: 32px; border: 1px solid var(--border-color);">
        <div style="text-align: center; border-bottom: 2px solid var(--primary); padding-bottom: 16px; margin-bottom: 24px;">
            <h2 style="color: var(--primary); margin: 0 0 4px;"><?= APP_NAME ?></h2>
            <div style="font-size: 0.82rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                Early Childhood Development & Milestone Assessment Card
            </div>
            <h3 style="margin-top: 10px; font-size: 1.3rem;">
                <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?> (<?= htmlspecialchars($child['lrn'] ?? 'N/A') ?>)
            </h3>
            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px;">
                Class: <strong><?= htmlspecialchars($child['class_name'] ?? 'N/A') ?></strong> &bull; Term: <strong><?= htmlspecialchars($assessment['term'] ?? 'Term 1') ?></strong>
            </div>
        </div>

        <!-- Competency Badges Grid -->
        <div class="stat-grid" style="margin-bottom: 24px;">
            <div class="stat-card" style="padding: 14px 18px;">
                <div class="stat-content">
                    <div class="stat-value"><?= $totalEvaluated ?></div>
                    <div class="stat-label">Milestones Evaluated</div>
                </div>
            </div>
            <div class="stat-card" style="padding: 14px 18px;">
                <div class="stat-content">
                    <div class="stat-value" style="color: var(--mint);"><?= $mastered ?></div>
                    <div class="stat-label">Mastered Competencies</div>
                </div>
            </div>
            <div class="stat-card" style="padding: 14px 18px;">
                <div class="stat-content">
                    <div class="stat-value" style="color: var(--sky);"><?= $progressing ?></div>
                    <div class="stat-label">Steadily Developing</div>
                </div>
            </div>
            <div class="stat-card" style="padding: 14px 18px;">
                <div class="stat-content">
                    <div class="stat-value" style="color: <?= $needsSupport > 0 ? 'var(--rose)' : 'var(--text-muted)' ?>;"><?= $needsSupport ?></div>
                    <div class="stat-label">Practice at Home</div>
                </div>
            </div>
        </div>

        <!-- Holistic Teacher Remarks Card -->
        <?php if ($assessment): ?>
            <div style="background: #EEF2FF; border-left: 5px solid var(--primary); padding: 18px; border-radius: var(--radius-md); margin-bottom: 24px;">
                <div class="d-flex justify-between align-center" style="margin-bottom: 6px;">
                    <strong style="color: var(--primary); font-size: 1rem;">Teacher's Holistic Assessment & Summary:</strong>
                    <span class="badge badge-primary"><?= htmlspecialchars($assessment['term']) ?></span>
                </div>
                <p style="margin: 0; color: #1E1B4B; font-size: 0.95rem; line-height: 1.6;">
                    <?= nl2br(htmlspecialchars($assessment['overall_remarks'] ?: 'Child is demonstrating healthy curiosity and active engagement with classroom activities.')) ?>
                </p>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 8px;">
                    Evaluated by Teacher: <?= htmlspecialchars($assessment['teacher_name'] ?? 'Faculty') ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Grouped Developmental Domains -->
        <h3 style="font-size: 1.15rem; margin-bottom: 16px; color: var(--text-primary);">
            Developmental Domain Observations
        </h3>

        <?php if (empty($domains)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 24px;">No milestone evaluations recorded yet.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php foreach ($domains as $category => $items): ?>
                    <div style="background: var(--bg-card-subtle); border-radius: var(--radius-md); padding: 18px; border: 1px solid var(--border-color);">
                        <h4 style="margin: 0 0 12px; font-size: 1rem; color: var(--primary);">
                            ⭐ <?= htmlspecialchars($category) ?>
                        </h4>

                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach ($items as $it): 
                                $ratingBadge = $it['rating'] === 'mastered' ? 'badge-success' : ($it['rating'] === 'needs_support' ? 'badge-danger' : 'badge-info');
                            ?>
                                <div style="background: #FFFFFF; padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                    <div style="flex: 1; min-width: 200px;">
                                        <strong style="font-size: 0.92rem; color: var(--text-primary);"><?= htmlspecialchars($it['title']) ?></strong>
                                        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($it['description']) ?></div>
                                        <?php if ($it['remarks']): ?>
                                            <div style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 4px; background: #F8FAFC; padding: 4px 8px; border-radius: 4px;">
                                                <strong>Teacher Note:</strong> <?= htmlspecialchars($it['remarks']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge <?= $ratingBadge ?>" style="font-size: 0.78rem; padding: 6px 12px;">
                                        <?= strtoupper(str_replace('_', ' ', $it['rating'])) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Signature Lines for Official Report Card -->
        <div style="display: flex; justify-content: space-between; margin-top: 48px; padding-top: 20px; border-top: 1px solid var(--border-color); font-size: 0.85rem;">
            <div>
                <div>Classroom Teacher:</div>
                <div style="margin-top: 32px; font-weight: 700; border-top: 1px solid #64748B; display: inline-block; padding-top: 4px;">
                    <?= htmlspecialchars($child['teacher_name'] ?? 'Faculty') ?>
                </div>
            </div>
            <div>
                <div>Parent / Guardian:</div>
                <div style="margin-top: 32px; font-weight: 700; border-top: 1px solid #64748B; display: inline-block; padding-top: 4px;">
                    <?= htmlspecialchars($_SESSION['user']['name']) ?>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
