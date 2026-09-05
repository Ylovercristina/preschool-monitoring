<?php
/**
 * Report Generation (Students & Fee Reports)
 * Preschool Monitoring System
 * Fulfills: "report generation (students, fee): As an admin, I want to manage parent and teacher accounts,
 * system activity logs, report generation (students, fee)"
 */

$pageTitle = 'Preschool Reports Generator';
$pageSubtitle = 'Generate, preview, print and export comprehensive official student enrollment and fee collection reports';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['admin']);

$db = getDB();

$reportType = $_GET['type'] ?? 'students'; // 'students' or 'fees'

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if ($reportType === 'students') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=preschool_students_report_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['LRN', 'First Name', 'Last Name', 'Gender', 'DOB', 'Classroom', 'Parent / Guardian', 'Parent Contact', 'Enrollment Status']);

        $rows = $db->query("
            SELECT s.lrn, s.first_name, s.last_name, s.gender, s.dob, c.name as class_name, u.name as parent_name, u.phone as parent_phone, s.enrollment_status
            FROM students s
            LEFT JOIN classrooms c ON s.classroom_id = c.id
            LEFT JOIN users u ON s.parent_id = u.id
            ORDER BY s.last_name ASC
        ")->fetchAll();

        foreach ($rows as $r) {
            fputcsv($output, [
                $r['lrn'], $r['first_name'], $r['last_name'], $r['gender'], $r['dob'],
                $r['class_name'] ?? 'Unassigned', $r['parent_name'] ?? 'None', $r['parent_phone'] ?? '', $r['enrollment_status']
            ]);
        }
        fclose($output);
        exit;
    } elseif ($reportType === 'fees') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=preschool_fees_report_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Student LRN', 'Student Name', 'Classroom', 'Fee Particulars', 'Amount Due', 'Amount Paid', 'Remaining Balance', 'Status']);

        $rows = $db->query("
            SELECT s.lrn, (s.first_name || ' ' || s.last_name) as student_name, c.name as class_name, f.title as fee_title,
                   sf.amount_due, sf.amount_paid, (sf.amount_due - sf.amount_paid) as balance, sf.status
            FROM student_fees sf
            JOIN students s ON sf.student_id = s.id
            JOIN fees f ON sf.fee_id = f.id
            LEFT JOIN classrooms c ON s.classroom_id = c.id
            ORDER BY s.last_name ASC
        ")->fetchAll();

        foreach ($rows as $r) {
            fputcsv($output, [
                $r['lrn'], $r['student_name'], $r['class_name'] ?? 'Unassigned', $r['fee_title'],
                $r['amount_due'], $r['amount_paid'], $r['balance'], strtoupper($r['status'])
            ]);
        }
        fclose($output);
        exit;
    }
}

// Data Queries for Display
if ($reportType === 'students') {
    $studentsReport = $db->query("
        SELECT s.*, c.name as class_name, u.name as parent_name, u.phone as parent_phone,
               (SELECT COUNT(*) FROM student_milestones sm WHERE sm.student_id = s.id AND sm.rating = 'mastered') as mastered_milestones,
               (SELECT COUNT(*) FROM student_milestones sm WHERE sm.student_id = s.id AND sm.rating = 'needs_support') as support_milestones,
               (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id AND a.status = 'present') as days_present,
               (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id) as total_days_recorded
        FROM students s
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        LEFT JOIN users u ON s.parent_id = u.id
        WHERE s.enrollment_status = 'enrolled'
        ORDER BY s.last_name ASC
    ")->fetchAll();
} else {
    $feesReport = $db->query("
        SELECT sf.*, s.first_name, s.last_name, s.lrn, c.name as class_name, f.title as fee_title, f.due_date,
               (sf.amount_due - sf.amount_paid) as balance
        FROM student_fees sf
        JOIN students s ON sf.student_id = s.id
        JOIN fees f ON sf.fee_id = f.id
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        ORDER BY sf.status ASC, s.last_name ASC
    ")->fetchAll();

    $totDue = array_sum(array_column($feesReport, 'amount_due'));
    $totPaid = array_sum(array_column($feesReport, 'amount_paid'));
    $totBal = $totDue - $totPaid;
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Report Selector & Export Toolbar (Hidden on Print) -->
<div class="d-flex justify-between align-center no-print" style="margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <!-- Tabs -->
    <div class="d-flex gap-2" style="background: #E2E8F0; padding: 4px; border-radius: var(--radius-md);">
        <a href="reports.php?type=students" class="btn btn-sm <?= $reportType === 'students' ? 'btn-primary' : 'btn-secondary' ?>">
            👶 Student Enrollment & Academic Report
        </a>
        <a href="reports.php?type=fees" class="btn btn-sm <?= $reportType === 'fees' ? 'btn-primary' : 'btn-secondary' ?>">
            💰 School Fee Collection & Balances Report
        </a>
    </div>

    <!-- Export / Print Buttons -->
    <div class="d-flex gap-2">
        <a href="reports.php?type=<?= $reportType ?>&export=csv" class="btn btn-mint btn-sm">
            📥 Export to CSV / Excel
        </a>
        <button class="btn btn-secondary btn-sm" onclick="triggerPrint()">
            🖨️ Print Official Report
        </button>
    </div>
</div>

<!-- Report Document Sheet -->
<div class="card" style="padding: 32px; background: #FFFFFF; border: 1px solid var(--border-color);">
    <!-- Formal Report Letterhead -->
    <div style="text-align: center; border-bottom: 2px solid var(--primary); padding-bottom: 18px; margin-bottom: 24px;">
        <h2 style="font-size: 1.6rem; color: var(--primary); margin-bottom: 4px;"><?= APP_NAME ?></h2>
        <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
            Early Childhood Learning & Safety Administration &bull; Academic Year 2026-2027
        </div>
        <h3 style="font-size: 1.25rem; margin-top: 10px; color: var(--text-primary);">
            <?= $reportType === 'students' ? 'OFFICIAL STUDENT ENROLLMENT & ACADEMIC PROGRESS REPORT' : 'OFFICIAL SCHOOL FEE COLLECTION & OUTSTANDING BALANCES REPORT' ?>
        </h3>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
            Generated on: <strong><?= date('F d, Y - h:i A') ?></strong> &bull; Generated by: <strong>Administrator</strong>
        </div>
    </div>

    <?php if ($reportType === 'students'): ?>
        <!-- Student Report Summary -->
        <div class="stat-grid no-print" style="margin-bottom: 20px;">
            <div class="stat-card" style="padding: 14px 18px;">
                <div class="stat-content">
                    <div class="stat-value" style="font-size: 1.4rem;"><?= count($studentsReport) ?></div>
                    <div class="stat-label">Total Enrolled Pupils</div>
                </div>
            </div>
            <div class="stat-card" style="padding: 14px 18px;">
                <div class="stat-content">
                    <div class="stat-value" style="font-size: 1.4rem; color: var(--mint);">
                        <?= array_sum(array_column($studentsReport, 'mastered_milestones')) ?>
                    </div>
                    <div class="stat-label">Total Milestones Mastered</div>
                </div>
            </div>
            <div class="stat-card" style="padding: 14px 18px;">
                <div class="stat-content">
                    <div class="stat-value" style="font-size: 1.4rem; color: var(--rose);">
                        <?= array_sum(array_column($studentsReport, 'support_milestones')) ?>
                    </div>
                    <div class="stat-label">Developmental Areas Needing Support</div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Learner Ref No. (LRN)</th>
                        <th>Student Full Name</th>
                        <th>Gender</th>
                        <th>Classroom</th>
                        <th>Parent / Guardian</th>
                        <th>Attendance</th>
                        <th>Mastered</th>
                        <th>Support Needed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentsReport as $st): 
                        $attPct = ($st['total_days_recorded'] > 0) ? round(($st['days_present'] / $st['total_days_recorded']) * 100) : 100;
                    ?>
                        <tr>
                            <td><code><?= htmlspecialchars($st['lrn'] ?? 'N/A') ?></code></td>
                            <td><strong><?= htmlspecialchars($st['last_name'] . ', ' . $st['first_name']) ?></strong></td>
                            <td><?= htmlspecialchars($st['gender']) ?></td>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($st['class_name'] ?? 'Unassigned') ?></span></td>
                            <td>
                                <?= htmlspecialchars($st['parent_name'] ?? 'None Linked') ?><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($st['parent_phone'] ?? '') ?></small>
                            </td>
                            <td><strong><?= $attPct ?>%</strong> (<?= $st['days_present'] ?>/<?= $st['total_days_recorded'] ?> days)</td>
                            <td><span class="badge badge-success"><?= $st['mastered_milestones'] ?></span></td>
                            <td>
                                <?php if ($st['support_milestones'] > 0): ?>
                                    <span class="badge badge-danger"><?= $st['support_milestones'] ?> Alert</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">None</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'fees'): ?>
        <!-- Fee Report Summary -->
        <div class="stat-grid" style="margin-bottom: 20px;">
            <div class="stat-card" style="padding: 14px 18px;">
                <div class="stat-content">
                    <div class="stat-value" style="font-size: 1.4rem;"><?= formatMoney($totDue) ?></div>
                    <div class="stat-label">Total Invoiced Fees</div>
                </div>
            </div>
            <div class="stat-card" style="padding: 14px 18px;">
                <div class="stat-content">
                    <div class="stat-value" style="font-size: 1.4rem; color: var(--mint);"><?= formatMoney($totPaid) ?></div>
                    <div class="stat-label">Total Payments Received</div>
                </div>
            </div>
            <div class="stat-card" style="padding: 14px 18px;">
                <div class="stat-content">
                    <div class="stat-value" style="font-size: 1.4rem; color: var(--rose);"><?= formatMoney($totBal) ?></div>
                    <div class="stat-label">Outstanding Receivables</div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student Name & LRN</th>
                        <th>Classroom</th>
                        <th>Particulars / Fee Item</th>
                        <th>Amount Due</th>
                        <th>Amount Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feesReport as $fr): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($fr['last_name'] . ', ' . $fr['first_name']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($fr['lrn']) ?></small>
                            </td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($fr['class_name'] ?? 'Unassigned') ?></span></td>
                            <td><?= htmlspecialchars($fr['fee_title']) ?></td>
                            <td><?= formatMoney($fr['amount_due']) ?></td>
                            <td style="color: var(--mint); font-weight: 700;"><?= formatMoney($fr['amount_paid']) ?></td>
                            <td style="color: <?= $fr['balance'] > 0 ? 'var(--rose)' : 'var(--text-muted)' ?>; font-weight: 700;">
                                <?= formatMoney($fr['balance']) ?>
                            </td>
                            <td>
                                <?php if ($fr['status'] === 'paid'): ?>
                                    <span class="badge badge-success">PAID</span>
                                <?php elseif ($fr['status'] === 'partially_paid'): ?>
                                    <span class="badge badge-warning">PARTIAL</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">UNPAID</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Sign-off Block for Formal Printing -->
    <div style="display: flex; justify-content: space-between; margin-top: 48px; padding-top: 24px; border-top: 1px solid var(--border-color); font-size: 0.88rem;">
        <div>
            <div>Prepared by:</div>
            <div style="margin-top: 32px; font-weight: 700; border-top: 1px solid #94A3B8; display: inline-block; padding-top: 4px;">
                Preschool Administrator
            </div>
        </div>
        <div>
            <div>Certified Correct:</div>
            <div style="margin-top: 32px; font-weight: 700; border-top: 1px solid #94A3B8; display: inline-block; padding-top: 4px;">
                School Principal / Directress
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
