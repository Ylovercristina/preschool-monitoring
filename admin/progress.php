<?php
/**
 * Academic Progress & Support Radar (Admin)
 * Preschool Monitoring System
 * Fulfills: "Monitor student academic progress: As an admin, I want to monitor and review students'
 * academic progress, so that I can evaluate their overall performance and identify students who may need support."
 */

$pageTitle = 'Academic Progress Monitoring';
$pageSubtitle = 'Review student developmental milestones, performance analytics & identify students requiring intervention';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['admin']);

$db = getDB();

// 1. Overall Metrics
$totalEvaluations = $db->query("SELECT COUNT(*) FROM student_milestones")->fetchColumn();
$masteredCount = $db->query("SELECT COUNT(*) FROM student_milestones WHERE rating = 'mastered'")->fetchColumn();
$progressingCount = $db->query("SELECT COUNT(*) FROM student_milestones WHERE rating = 'progressing'")->fetchColumn();
$needsSupportCount = $db->query("SELECT COUNT(*) FROM student_milestones WHERE rating = 'needs_support'")->fetchColumn();

// 2. Students Identified for Intervention
$interventionStudents = $db->query("
    SELECT s.id, s.first_name, s.last_name, s.lrn, c.name as class_name,
           a.term, a.overall_remarks, a.needs_intervention, u.name as teacher_name,
           (SELECT COUNT(*) FROM student_milestones sm WHERE sm.student_id = s.id AND sm.rating = 'needs_support') as support_milestones_count
    FROM students s
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    LEFT JOIN academic_assessments a ON a.student_id = s.id
    LEFT JOIN users u ON a.teacher_id = u.id
    WHERE a.needs_intervention = 1 OR (SELECT COUNT(*) FROM student_milestones sm WHERE sm.student_id = s.id AND sm.rating = 'needs_support') > 0
    GROUP BY s.id
")->fetchAll();

// 3. Domain Performance Breakdown
$domainBreakdown = $db->query("
    SELECT m.category,
           COUNT(sm.id) as total_evals,
           SUM(CASE WHEN sm.rating = 'mastered' THEN 1 ELSE 0 END) as mastered,
           SUM(CASE WHEN sm.rating = 'progressing' THEN 1 ELSE 0 END) as progressing,
           SUM(CASE WHEN sm.rating = 'needs_support' THEN 1 ELSE 0 END) as needs_support
    FROM academic_milestones m
    LEFT JOIN student_milestones sm ON sm.milestone_id = m.id
    GROUP BY m.category
")->fetchAll();

// 4. All Students with Milestone Progress Summary
$studentsProgress = $db->query("
    SELECT s.id, s.first_name, s.last_name, s.lrn, c.name as class_name,
           COUNT(sm.id) as evaluated_count,
           SUM(CASE WHEN sm.rating = 'mastered' THEN 1 ELSE 0 END) as mastered_count,
           SUM(CASE WHEN sm.rating = 'progressing' THEN 1 ELSE 0 END) as progressing_count,
           SUM(CASE WHEN sm.rating = 'needs_support' THEN 1 ELSE 0 END) as support_count
    FROM students s
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    LEFT JOIN student_milestones sm ON sm.student_id = s.id
    WHERE s.enrollment_status = 'enrolled'
    GROUP BY s.id
    ORDER BY support_count DESC, s.last_name ASC
")->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Metric Cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-primary">🌟</div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($totalEvaluations) ?></div>
            <div class="stat-label">Total Milestone Evaluations</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-mint">🏆</div>
        <div class="stat-content">
            <div class="stat-value" style="color: var(--mint);"><?= number_format($masteredCount) ?></div>
            <div class="stat-label">Milestones Mastered</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-sky">🚀</div>
        <div class="stat-content">
            <div class="stat-value" style="color: var(--sky);"><?= number_format($progressingCount) ?></div>
            <div class="stat-label">Steadily Progressing</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-rose">
            ⚠️
        </div>
        <div class="stat-content">
            <div class="stat-value" style="color: var(--rose);"><?= number_format(count($interventionStudents)) ?></div>
            <div class="stat-label">Students Needing Support</div>
        </div>
    </div>
</div>

<!-- Special Alert Section: Students Requiring Academic Support -->
<div class="card" style="border: 2px solid #FECDD3; background: #FFF5F6;">
    <div class="card-header" style="border-bottom-color: #FEE2E2;">
        <h3 class="card-title" style="color: #9F1239;">
            <span>🚨</span> Early Intervention & Support Watchlist
            <span class="badge badge-danger"><?= count($interventionStudents) ?> Flagged</span>
        </h3>
        <small style="color: #881337;">Identified via teacher assessments & developmental milestone evaluations</small>
    </div>
    <div class="table-responsive">
        <table class="table" style="background: #FFFFFF; border-radius: var(--radius-md);">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Classroom</th>
                    <th>Teacher Remarks / Specific Needs</th>
                    <th>Support Milestones</th>
                    <th>Assigned Faculty</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($interventionStudents)): ?>
                    <tr><td colspan="6" style="text-align:center; padding: 24px; color: var(--text-muted);">All students are performing on track! No active interventions needed.</td></tr>
                <?php else: ?>
                    <?php foreach ($interventionStudents as $st): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($st['lrn']) ?></small>
                            </td>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($st['class_name']) ?></span></td>
                            <td>
                                <div style="color: #9F1239; font-weight: 500; font-size: 0.88rem;">
                                    <?= htmlspecialchars($st['overall_remarks'] ?? 'Flagged for specific developmental milestone support.') ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-danger">
                                    <?= $st['support_milestones_count'] ?> Area(s)
                                </span>
                            </td>
                            <td><?= htmlspecialchars($st['teacher_name'] ?? 'Faculty Assigned') ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="viewStudentMilestones(<?= $st['id'] ?>, '<?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name'], ENT_QUOTES) ?>')">
                                    Inspect Milestones &rarr;
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Developmental Domain Performance Overview -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">📚 Developmental Domain Competency Breakdown</h3>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px;">
        <?php foreach ($domainBreakdown as $dom): 
            $tot = max(1, (int)$dom['total_evals']);
            $pctMastered = round(($dom['mastered'] / $tot) * 100);
            $pctProgressing = round(($dom['progressing'] / $tot) * 100);
            $pctSupport = round(($dom['needs_support'] / $tot) * 100);
        ?>
            <div style="background: var(--bg-card-subtle); padding: 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <div class="d-flex justify-between align-center" style="margin-bottom: 8px;">
                    <strong style="color: var(--text-primary); font-size: 0.95rem;"><?= htmlspecialchars($dom['category']) ?></strong>
                    <span class="badge badge-primary"><?= (int)$dom['total_evals'] ?> Evals</span>
                </div>
                <!-- Progress Bar Stack -->
                <div style="height: 12px; background: #E2E8F0; border-radius: 6px; overflow: hidden; display: flex; margin-bottom: 12px;">
                    <div style="width: <?= $pctMastered ?>%; background: var(--mint);" title="Mastered: <?= $pctMastered ?>%"></div>
                    <div style="width: <?= $pctProgressing ?>%; background: var(--sky);" title="Progressing: <?= $pctProgressing ?>%"></div>
                    <div style="width: <?= $pctSupport ?>%; background: var(--rose);" title="Needs Support: <?= $pctSupport ?>%"></div>
                </div>
                <div class="d-flex justify-between" style="font-size: 0.78rem;">
                    <span style="color: var(--mint-dark); font-weight: 700;">🏆 <?= $dom['mastered'] ?> Mastered</span>
                    <span style="color: var(--sky); font-weight: 700;">🚀 <?= $dom['progressing'] ?> In Progress</span>
                    <span style="color: var(--rose); font-weight: 700;">⚠️ <?= $dom['needs_support'] ?> Support</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- All Students Comprehensive Progress Roster -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">👶 Student Performance & Milestone Evaluation Roster</h3>
        <input type="text" class="form-control" data-table-search="progressTable" placeholder="🔍 Search student..." style="max-width: 280px;">
    </div>
    <div class="table-responsive">
        <table class="table" id="progressTable">
            <thead>
                <tr>
                    <th>Student Name & LRN</th>
                    <th>Classroom</th>
                    <th>Evaluated Milestones</th>
                    <th>Mastered</th>
                    <th>Progressing</th>
                    <th>Needs Support</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($studentsProgress as $sp): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($sp['first_name'] . ' ' . $sp['last_name']) ?></strong><br>
                            <small style="color: var(--text-muted);"><?= htmlspecialchars($sp['lrn'] ?? 'No LRN') ?></small>
                        </td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($sp['class_name'] ?? 'Unassigned') ?></span></td>
                        <td><strong><?= (int)$sp['evaluated_count'] ?></strong> milestones</td>
                        <td><span class="badge badge-success"><?= (int)$sp['mastered_count'] ?> Mastered</span></td>
                        <td><span class="badge badge-info"><?= (int)$sp['progressing_count'] ?> Progressing</span></td>
                        <td>
                            <?php if ($sp['support_count'] > 0): ?>
                                <span class="badge badge-danger"><?= (int)$sp['support_count'] ?> Needs Support</span>
                            <?php else: ?>
                                <span class="badge badge-neutral">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-secondary btn-sm" onclick="viewStudentMilestones(<?= $sp['id'] ?>, '<?= htmlspecialchars($sp['first_name'] . ' ' . $sp['last_name'], ENT_QUOTES) ?>')">
                                Details &rarr;
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Student Milestones Drilldown -->
<div class="modal-overlay" id="milestonesDetailModal">
    <div class="modal-box" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title" id="milestoneStudentTitle">Student Milestone Evaluation</h3>
            <button class="modal-close" onclick="closeModal('milestonesDetailModal')">&times;</button>
        </div>
        <div class="modal-body" id="milestonesDetailContent">
            Loading milestones...
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('milestonesDetailModal')">Close</button>
            <button type="button" class="btn btn-primary" onclick="triggerPrint()">Print Report Card</button>
        </div>
    </div>
</div>

<script>
function viewStudentMilestones(studentId, studentName) {
    document.getElementById('milestoneStudentTitle').innerText = 'Milestones & Progress: ' + studentName;
    document.getElementById('milestonesDetailContent').innerHTML = '<p style="text-align:center; padding:20px;">Fetching academic milestones...</p>';
    openModal('milestonesDetailModal');

    fetch('progress_api.php?student_id=' + studentId)
        .then(res => res.json())
        .then(data => {
            if (!data || data.length === 0) {
                document.getElementById('milestonesDetailContent').innerHTML = '<p style="color:var(--text-muted); text-align:center; padding:20px;">No milestone evaluations recorded yet for this student.</p>';
                return;
            }

            let html = '<div style="display:flex; flex-direction:column; gap:12px;">';
            data.forEach(m => {
                let badgeClass = m.rating === 'mastered' ? 'badge-success' : (m.rating === 'needs_support' ? 'badge-danger' : 'badge-info');
                html += `
                    <div style="background:var(--bg-card-subtle); padding:14px; border-radius:var(--radius-md); border-left: 4px solid ${m.rating === 'needs_support' ? 'var(--rose)' : (m.rating === 'mastered' ? 'var(--mint)' : 'var(--sky)')};">
                        <div class="d-flex justify-between align-center" style="margin-bottom:4px;">
                            <strong>${m.title}</strong>
                            <span class="badge ${badgeClass}">${m.rating.toUpperCase().replace('_', ' ')}</span>
                        </div>
                        <div style="font-size:0.78rem; color:var(--text-muted); margin-bottom:6px;">
                            Domain: <strong>${m.category}</strong> &bull; Evaluated on ${m.updated_at}
                        </div>
                        <div style="font-size:0.86rem; color:var(--text-secondary); background:#fff; padding:8px; border-radius:6px;">
                            <strong>Teacher Notes:</strong> ${m.remarks || 'Standard progress demonstrated.'}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            document.getElementById('milestonesDetailContent').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('milestonesDetailContent').innerHTML = '<p style="color:var(--rose);">Error loading academic milestones.</p>';
        });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
