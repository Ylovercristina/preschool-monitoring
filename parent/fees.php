<?php
/**
 * Child Fee Statement & Payment Status (Parent View)
 * Preschool Monitoring System
 * Fulfills: "Fee Management: As a parent, I want to view my child's fees
 * and payment status so that I know what's been paid or not."
 */

$pageTitle = 'Child Fees & Payment Status';
$pageSubtitle = 'Monitor tuition balances, itemized invoices, payment history and official receipts';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['parent']);

$db = getDB();
$parentId = $_SESSION['user_id'];

// Fetch Child
$childStmt = $db->prepare("SELECT s.*, c.name as class_name FROM students s LEFT JOIN classrooms c ON s.classroom_id = c.id WHERE s.parent_id = ? LIMIT 1");
$childStmt->execute([$parentId]);
$child = $childStmt->fetch();
$childId = $child['id'] ?? 0;

// Fetch Student Fees
$studentFees = [];
$totalDue = 0; $totalPaid = 0; $totalBalance = 0;

if ($childId) {
    $fStmt = $db->prepare("
        SELECT sf.*, f.title as fee_title, f.description as fee_desc, f.due_date, f.school_year,
               (sf.amount_due - sf.amount_paid) as balance
        FROM student_fees sf
        JOIN fees f ON sf.fee_id = f.id
        WHERE sf.student_id = ?
        ORDER BY sf.status ASC, f.due_date ASC
    ");
    $fStmt->execute([$childId]);
    $studentFees = $fStmt->fetchAll();

    foreach ($studentFees as $sf) {
        $totalDue += $sf['amount_due'];
        $totalPaid += $sf['amount_paid'];
    }
    $totalBalance = $totalDue - $totalPaid;
}

// Fetch Payment Receipts History
$payments = [];
if ($childId) {
    $pStmt = $db->prepare("
        SELECT p.*, f.title as fee_title, u.name as cashier_name
        FROM payment_logs p
        JOIN student_fees sf ON p.student_fee_id = sf.id
        JOIN fees f ON sf.fee_id = f.id
        LEFT JOIN users u ON p.logged_by = u.id
        WHERE p.student_id = ?
        ORDER BY p.payment_date DESC
    ");
    $pStmt->execute([$childId]);
    $payments = $pStmt->fetchAll();
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<?php if (!$child): ?>
    <div class="card" style="text-align: center; padding: 40px;">
        <p style="color: var(--text-muted);">No enrolled child linked to your account.</p>
    </div>
<?php else: ?>

    <!-- Summary KPI Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-primary">📋</div>
            <div class="stat-content">
                <div class="stat-value"><?= formatMoney($totalDue) ?></div>
                <div class="stat-label">Total Billed Fees</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-mint">✓</div>
            <div class="stat-content">
                <div class="stat-value" style="color: var(--mint);"><?= formatMoney($totalPaid) ?></div>
                <div class="stat-label">Total Amount Paid</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-rose">⏳</div>
            <div class="stat-content">
                <div class="stat-value" style="color: <?= $totalBalance > 0 ? 'var(--rose)' : 'var(--mint)' ?>;">
                    <?= formatMoney($totalBalance) ?>
                </div>
                <div class="stat-label"><?= $totalBalance > 0 ? 'Outstanding Balance' : 'Fully Settled!' ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-sky">🧾</div>
            <div class="stat-content">
                <div class="stat-value"><?= count($payments) ?></div>
                <div class="stat-label">Official Receipts Issued</div>
            </div>
        </div>
    </div>

    <!-- Itemized Fee Account Statement -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span>🎒</span> Itemized School Fee Statement: <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
            </h3>
            <button class="btn btn-secondary btn-sm" onclick="triggerPrint()">
                🖨️ Print Statement
            </button>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Particulars / Fee Item</th>
                        <th>School Year</th>
                        <th>Due Date</th>
                        <th>Amount Due</th>
                        <th>Amount Paid</th>
                        <th>Remaining Balance</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($studentFees)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">No fee statements found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($studentFees as $sf): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($sf['fee_title']) ?></strong>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); max-width: 320px;">
                                        <?= htmlspecialchars($sf['fee_desc'] ?? '') ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($sf['school_year']) ?></td>
                                <td><?= formatDate($sf['due_date']) ?></td>
                                <td><strong><?= formatMoney($sf['amount_due']) ?></strong></td>
                                <td style="color: var(--mint); font-weight: 700;"><?= formatMoney($sf['amount_paid']) ?></td>
                                <td style="color: <?= $sf['balance'] > 0 ? 'var(--rose)' : 'var(--text-muted)' ?>; font-weight: 800;">
                                    <?= formatMoney($sf['balance']) ?>
                                </td>
                                <td>
                                    <?php if ($sf['status'] === 'paid'): ?>
                                        <span class="badge badge-success">FULLY PAID</span>
                                    <?php elseif ($sf['status'] === 'partially_paid'): ?>
                                        <span class="badge badge-warning">PARTIALLY PAID</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">UNPAID</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Official Receipts History -->
    <div class="card" style="margin-top: 24px;">
        <div class="card-header">
            <h3 class="card-title">
                <span>🧾</span> Payment History & Downloadable Receipts
            </h3>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Receipt No.</th>
                        <th>Payment Date</th>
                        <th>Fee Particulars</th>
                        <th>Amount Paid</th>
                        <th>Payment Method</th>
                        <th>Reference No.</th>
                        <th>View Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">No payments logged yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><strong style="color: var(--primary);"><?= htmlspecialchars($p['receipt_no']) ?></strong></td>
                                <td><?= formatDate($p['payment_date'], 'M d, Y h:i A') ?></td>
                                <td><?= htmlspecialchars($p['fee_title']) ?></td>
                                <td style="color: var(--mint); font-weight: 800;"><?= formatMoney($p['amount']) ?></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($p['payment_method']) ?></span></td>
                                <td><small><?= htmlspecialchars($p['reference_no'] ?: 'None') ?></small></td>
                                <td>
                                    <button class="btn btn-secondary btn-sm" onclick='viewReceipt(<?= json_encode($p) ?>, <?= json_encode($child) ?>)'>
                                        👁️ View Receipt
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<!-- Modal: Printable Receipt -->
<div class="modal-overlay" id="parentReceiptModal">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Receipt</h3>
            <button class="modal-close" onclick="closeModal('parentReceiptModal')">&times;</button>
        </div>
        <div class="modal-body" id="parentReceiptContent"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('parentReceiptModal')">Close</button>
            <button type="button" class="btn btn-primary" onclick="triggerPrint()">Print Receipt</button>
        </div>
    </div>
</div>

<script>
function viewReceipt(p, c) {
    const html = `
        <div style="text-align:center; border-bottom:2px dashed #CBD5E1; padding-bottom:14px; margin-bottom:16px;">
            <h2 style="margin:0; font-size: 1.4rem;"><?= APP_NAME ?></h2>
            <div style="font-size:0.8rem; color:var(--text-muted);">Official Payment Receipt</div>
            <div style="font-size:1.15rem; font-weight:800; color:var(--primary); margin-top:6px;">${p.receipt_no}</div>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:8px;">
            <span style="color:var(--text-muted);">Pupil Name:</span>
            <strong>${c.first_name} ${c.last_name} (${c.lrn || 'N/A'})</strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:8px;">
            <span style="color:var(--text-muted);">Fee Particulars:</span>
            <strong>${p.fee_title}</strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:8px;">
            <span style="color:var(--text-muted);">Payment Mode:</span>
            <strong>${p.payment_method}</strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:8px;">
            <span style="color:var(--text-muted);">Payment Date:</span>
            <strong>${p.payment_date}</strong>
        </div>
        ${p.reference_no ? `
            <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:8px;">
                <span style="color:var(--text-muted);">Ref No:</span>
                <strong>${p.reference_no}</strong>
            </div>
        ` : ''}
        <div style="background:#F0FDF4; border: 1px solid #86EFAC; border-radius: var(--radius-sm); padding: 14px; margin: 18px 0; text-align:center;">
            <div style="font-size:0.78rem; color:#065F46; font-weight:700; text-transform:uppercase;">Amount Received</div>
            <div style="font-size:2rem; font-weight:800; color:#047857;">₱${parseFloat(p.amount).toLocaleString('en-US', {minimumFractionDigits:2})}</div>
        </div>
        <div style="font-size:0.75rem; color:var(--text-muted); text-align:center;">
            Verified and recorded by Preschool Administration.
        </div>
    `;
    document.getElementById('parentReceiptContent').innerHTML = html;
    openModal('parentReceiptModal');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
