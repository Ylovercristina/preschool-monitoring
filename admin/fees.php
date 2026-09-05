<?php
/**
 * Fee Management & Payment Logging (Admin)
 * Preschool Monitoring System
 * Fulfills:
 * - "Fee Management: As an admin, I want to track the school's fee records, so that I can monitor payments and outstanding fees."
 * - "log payment: As an admin I want to be able to track the payments."
 */

$pageTitle = 'Fee Management & Payment Logs';
$pageSubtitle = 'Monitor school fee structures, track student accounts, log payments & issue receipts';

require_once dirname(__DIR__) . '/includes/auth.php';
checkAuth(['admin']);

$db = getDB();

// Handle Actions (Create Fee Structure, Assign Fee, Log Payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_fee') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $schoolYear = trim($_POST['school_year'] ?? '2026-2027');
        $dueDate = $_POST['due_date'] ?? null;

        if (empty($title) || $amount <= 0) {
            setFlash('danger', 'Fee title and a valid positive amount are required.');
        } else {
            $stmt = $db->prepare("INSERT INTO fees (title, description, amount, school_year, due_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $amount, $schoolYear, $dueDate]);
            $feeId = $db->lastInsertId();

            // Auto-assign to all enrolled students if requested
            if (!empty($_POST['auto_assign_all'])) {
                $enrolledStudents = $db->query("SELECT id FROM students WHERE enrollment_status = 'enrolled'")->fetchAll();
                $asStmt = $db->prepare("INSERT INTO student_fees (student_id, fee_id, amount_due, amount_paid, status) VALUES (?, ?, ?, 0, 'unpaid')");
                foreach ($enrolledStudents as $es) {
                    $asStmt->execute([$es['id'], $feeId, $amount]);
                }
            }

            logActivity('Fee Structure Added', "Created fee structure '{$title}' ({$amount})");
            setFlash('success', "Fee structure '{$title}' added successfully!");
            header('Location: fees.php');
            exit;
        }
    } elseif ($action === 'log_payment') {
        $studentFeeId = (int)($_POST['student_fee_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $paymentMethod = 'Cash';
        $referenceNo = trim($_POST['reference_no'] ?? '');
        $receiptNo = trim($_POST['receipt_no'] ?? ('REC-' . date('Y') . '-' . rand(100, 999)));
        $notes = trim($_POST['notes'] ?? '');

        if ($studentFeeId <= 0 || $amount <= 0) {
            setFlash('danger', 'Please select a student fee account and specify a valid payment amount.');
        } else {
            // Fetch student fee
            $sfStmt = $db->prepare("SELECT sf.*, s.parent_id, s.first_name, s.last_name, f.title as fee_title FROM student_fees sf JOIN students s ON sf.student_id = s.id JOIN fees f ON sf.fee_id = f.id WHERE sf.id = ?");
            $sfStmt->execute([$studentFeeId]);
            $sf = $sfStmt->fetch();

            if ($sf) {
                $newPaid = $sf['amount_paid'] + $amount;
                $newStatus = ($newPaid >= $sf['amount_due']) ? 'paid' : ($newPaid > 0 ? 'partially_paid' : 'unpaid');

                // Update student fee
                $upStmt = $db->prepare("UPDATE student_fees SET amount_paid = ?, status = ? WHERE id = ?");
                $upStmt->execute([$newPaid, $newStatus, $studentFeeId]);

                // Insert into payment_logs
                $pStmt = $db->prepare("INSERT INTO payment_logs (student_fee_id, student_id, amount, payment_method, reference_no, receipt_no, logged_by, notes, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))");
                $pStmt->execute([$studentFeeId, $sf['student_id'], $amount, $paymentMethod, $referenceNo, $receiptNo, $_SESSION['user_id'], $notes]);

                // Notify parent
                if ($sf['parent_id']) {
                    $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'fee_reminder', 'parent/fees.php')");
                    $notifStmt->execute([
                        $sf['parent_id'],
                        'Payment Received: ' . $receiptNo,
                        "Payment of " . formatMoney($amount) . " for {$sf['first_name']}'s {$sf['fee_title']} has been logged."
                    ]);
                }

                logActivity('Payment Logged', "Logged payment of {$amount} for {$sf['first_name']} {$sf['last_name']} (Receipt: {$receiptNo})");
                setFlash('success', "Payment of " . formatMoney($amount) . " logged successfully! Receipt {$receiptNo} generated.");
                header('Location: fees.php?tab=payments');
                exit;
            }
        }
    }
}

// 1. Overall Financial Metrics
$totalInvoiced = $db->query("SELECT COALESCE(SUM(amount_due), 0) FROM student_fees")->fetchColumn();
$totalCollected = $db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM student_fees")->fetchColumn();
$totalBalance = $totalInvoiced - $totalCollected;
$paymentsCount = $db->query("SELECT COUNT(*) FROM payment_logs")->fetchColumn();

// 2. Student Fees Breakdown
$studentFees = $db->query("
    SELECT sf.*, s.first_name, s.last_name, s.lrn, c.name as class_name, f.title as fee_title, f.due_date
    FROM student_fees sf
    JOIN students s ON sf.student_id = s.id
    JOIN fees f ON sf.fee_id = f.id
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    WHERE s.enrollment_status = 'enrolled'
    ORDER BY sf.status ASC, s.last_name ASC
")->fetchAll();

// 3. Payment Logs History
$payments = $db->query("
    SELECT p.*, s.first_name, s.last_name, s.lrn, f.title as fee_title, u.name as logged_by_name
    FROM payment_logs p
    JOIN students s ON p.student_id = s.id
    JOIN student_fees sf ON p.student_fee_id = sf.id
    JOIN fees f ON sf.fee_id = f.id
    LEFT JOIN users u ON p.logged_by = u.id
    ORDER BY p.payment_date DESC
")->fetchAll();

// 4. Fee Structures
$feeStructures = $db->query("SELECT * FROM fees ORDER BY id DESC")->fetchAll();

$activeTab = $_GET['tab'] ?? 'accounts';

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Financial Statistics Grid -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-primary">📋</div>
        <div class="stat-content">
            <div class="stat-value"><?= formatMoney($totalInvoiced) ?></div>
            <div class="stat-label">Total Invoiced Fees</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-mint">💵</div>
        <div class="stat-content">
            <div class="stat-value" style="color: var(--mint);"><?= formatMoney($totalCollected) ?></div>
            <div class="stat-label">Total Payments Collected</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-rose">⏳</div>
        <div class="stat-content">
            <div class="stat-value" style="color: var(--rose);"><?= formatMoney($totalBalance) ?></div>
            <div class="stat-label">Total Outstanding Balance</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-sky">🧾</div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($paymentsCount) ?></div>
            <div class="stat-label">Recorded Transactions</div>
        </div>
    </div>
</div>

<!-- Header Controls -->
<div class="d-flex justify-between align-center" style="margin-bottom: 20px; flex-wrap: wrap; gap: 14px;">
    <!-- Navigation Tabs -->
    <div class="d-flex gap-2" style="background: #E2E8F0; padding: 4px; border-radius: var(--radius-md);">
        <a href="fees.php?tab=accounts" class="btn btn-sm <?= $activeTab === 'accounts' ? 'btn-primary' : 'btn-secondary' ?>">
            Student Accounts
        </a>
        <a href="fees.php?tab=payments" class="btn btn-sm <?= $activeTab === 'payments' ? 'btn-primary' : 'btn-secondary' ?>">
            Payment History & Receipts
        </a>
        <a href="fees.php?tab=structures" class="btn btn-sm <?= $activeTab === 'structures' ? 'btn-primary' : 'btn-secondary' ?>">
            Fee Categories
        </a>
    </div>

    <!-- Action Buttons -->
    <div class="d-flex gap-2">
        <button class="btn btn-mint" onclick="openLogPaymentModal()">
            <span>💳</span> Log Payment
        </button>
        <button class="btn btn-primary" onclick="openModal('addFeeModal')">
            <span>+</span> Create Fee Item
        </button>
    </div>
</div>

<?php if ($activeTab === 'accounts'): ?>
    <!-- Student Fee Accounts Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span>🎒</span> Student Fee Balances & Payment Status
                <span class="badge badge-primary"><?= count($studentFees) ?> Invoices</span>
            </h3>
            <input type="text" class="form-control" data-table-search="feeAccountsTable" placeholder="🔍 Search student or fee..." style="max-width: 280px;">
        </div>
        <div class="table-responsive">
            <table class="table" id="feeAccountsTable">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Classroom</th>
                        <th>Fee Item</th>
                        <th>Amount Due</th>
                        <th>Amount Paid</th>
                        <th>Remaining Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentFees as $sf): 
                        $balance = $sf['amount_due'] - $sf['amount_paid'];
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($sf['first_name'] . ' ' . $sf['last_name']) ?></strong><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($sf['lrn']) ?></small>
                            </td>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($sf['class_name'] ?? 'Unassigned') ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($sf['fee_title']) ?></strong>
                                <?php if ($sf['due_date']): ?>
                                    <br><small style="color: var(--text-muted);">Due: <?= formatDate($sf['due_date']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= formatMoney($sf['amount_due']) ?></td>
                            <td style="color: var(--mint); font-weight: 600;"><?= formatMoney($sf['amount_paid']) ?></td>
                            <td style="color: <?= $balance > 0 ? 'var(--rose)' : 'var(--text-muted)' ?>; font-weight: 700;">
                                <?= formatMoney($balance) ?>
                            </td>
                            <td>
                                <?php if ($sf['status'] === 'paid'): ?>
                                    <span class="badge badge-success">PAID</span>
                                <?php elseif ($sf['status'] === 'partially_paid'): ?>
                                    <span class="badge badge-warning">PARTIAL</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">UNPAID</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($balance > 0): ?>
                                    <button class="btn btn-mint btn-sm" onclick='prefillPayment(<?= json_encode($sf) ?>, <?= $balance ?>)'>
                                        Log Payment
                                    </button>
                                <?php else: ?>
                                    <span class="badge badge-success">Settled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($activeTab === 'payments'): ?>
    <!-- Payment Logs & Receipts Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span>🧾</span> Official Payment Logs & Receipts
                <span class="badge badge-primary"><?= count($payments) ?> Records</span>
            </h3>
            <input type="text" class="form-control" data-table-search="paymentsTable" placeholder="🔍 Search receipt, student or ref..." style="max-width: 280px;">
        </div>
        <div class="table-responsive">
            <table class="table" id="paymentsTable">
                <thead>
                    <tr>
                        <th>Receipt No.</th>
                        <th>Student Name</th>
                        <th>Fee Particulars</th>
                        <th>Amount Paid</th>
                        <th>Payment Mode</th>
                        <th>Ref Number</th>
                        <th>Date & Time</th>
                        <th>Recorded By</th>
                        <th>Print</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><strong style="color: var(--primary);"><?= htmlspecialchars($p['receipt_no']) ?></strong></td>
                            <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                            <td><?= htmlspecialchars($p['fee_title']) ?></td>
                            <td style="color: var(--mint); font-weight: 700;"><?= formatMoney($p['amount']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($p['payment_method']) ?></span></td>
                            <td><small><?= htmlspecialchars($p['reference_no'] ?: 'None') ?></small></td>
                            <td><?= formatDate($p['payment_date'], 'M d, Y h:i A') ?></td>
                            <td><small><?= htmlspecialchars($p['logged_by_name'] ?? 'Admin') ?></small></td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick='printReceipt(<?= json_encode($p) ?>)'>
                                    🖨️ Receipt
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($activeTab === 'structures'): ?>
    <!-- Fee Structures Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📋 Configured School Fee Items</h3>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fee Title</th>
                        <th>Description</th>
                        <th>Standard Amount</th>
                        <th>School Year</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feeStructures as $f): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($f['title']) ?></strong></td>
                            <td><?= htmlspecialchars($f['description']) ?></td>
                            <td style="font-weight: 700; color: var(--primary);"><?= formatMoney($f['amount']) ?></td>
                            <td><?= htmlspecialchars($f['school_year']) ?></td>
                            <td><?= formatDate($f['due_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Modal: Log Payment -->
<div class="modal-overlay" id="logPaymentModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">💳 Log Fee Payment</h3>
            <button class="modal-close" onclick="closeModal('logPaymentModal')">&times;</button>
        </div>
        <form method="POST" action="fees.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="log_payment">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="student_fee_id">Select Student & Fee Account *</label>
                    <select name="student_fee_id" id="student_fee_id" class="form-select" required onchange="onFeeAccountSelect(this)">
                        <option value="">-- Choose Account --</option>
                        <?php foreach ($studentFees as $sf): 
                            $bal = $sf['amount_due'] - $sf['amount_paid'];
                            if ($bal > 0):
                        ?>
                            <option value="<?= $sf['id'] ?>" data-balance="<?= $bal ?>">
                                <?= htmlspecialchars($sf['first_name'] . ' ' . $sf['last_name']) ?> &bull; <?= htmlspecialchars($sf['fee_title']) ?> (Bal: <?= formatMoney($bal) ?>)
                            </option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" for="amount">Payment Amount (₱) *</label>
                        <input type="number" step="0.01" name="amount" id="paymentAmount" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="payment_method">Payment Mode *</label>
                        <input type="text" class="form-control" value="Cash" readonly>
                        <input type="hidden" name="payment_method" value="Cash">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" for="receipt_no">Official Receipt No.</label>
                        <input type="text" name="receipt_no" id="receipt_no" class="form-control" value="REC-<?= date('Y') ?>-<?= rand(100, 999) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reference_no">Ref / Transaction No.</label>
                        <input type="text" name="reference_no" id="reference_no" class="form-control" placeholder="Optional for Cash">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="notes">Payment Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="e.g. Paid in cash at admin office counter"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('logPaymentModal')">Cancel</button>
                <button type="submit" class="btn btn-mint">Record Payment & Issue Receipt</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Create Fee Item -->
<div class="modal-overlay" id="addFeeModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Create School Fee Item</h3>
            <button class="modal-close" onclick="closeModal('addFeeModal')">&times;</button>
        </div>
        <form method="POST" action="fees.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="create_fee">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="fee_title">Fee Title *</label>
                    <input type="text" name="title" id="fee_title" class="form-control" placeholder="e.g. 2nd Term Tuition Fee" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="fee_desc">Description</label>
                    <textarea name="description" id="fee_desc" class="form-control" rows="2" placeholder="Detailed explanation of what this fee covers"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" for="fee_amount">Standard Amount (₱) *</label>
                        <input type="number" step="0.01" name="amount" id="fee_amount" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fee_due">Due Date</label>
                        <input type="date" name="due_date" id="fee_due" class="form-control">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem;">
                        <input type="checkbox" name="auto_assign_all" value="1" checked>
                        <span>Automatically create fee invoice for all enrolled students</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addFeeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Fee Structure</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Printable Official Receipt -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <h3 class="modal-title">🧾 Official Payment Receipt</h3>
            <button class="modal-close" onclick="closeModal('receiptModal')">&times;</button>
        </div>
        <div class="modal-body" id="receiptContent" style="padding: 24px; font-family: inherit;">
            <!-- Rendered via JS -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('receiptModal')">Close</button>
            <button type="button" class="btn btn-primary" onclick="triggerPrint()">Print Official Receipt</button>
        </div>
    </div>
</div>

<script>
function openLogPaymentModal() {
    openModal('logPaymentModal');
}

function prefillPayment(sf, balance) {
    const sel = document.getElementById('student_fee_id');
    sel.value = sf.id;
    document.getElementById('paymentAmount').value = balance;
    openModal('logPaymentModal');
}

function onFeeAccountSelect(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.balance) {
        document.getElementById('paymentAmount').value = opt.dataset.balance;
    }
}

function printReceipt(p) {
    const html = `
        <div style="text-align:center; margin-bottom:16px; border-bottom: 2px dashed #CBD5E1; padding-bottom: 14px;">
            <h2 style="margin:0; font-size: 1.4rem;"><?= APP_NAME ?></h2>
            <div style="font-size:0.8rem; color:var(--text-muted);">Official Payment Receipt &bull; Non-VAT</div>
            <div style="font-size:1.1rem; font-weight:800; color:var(--primary); margin-top:6px;">${p.receipt_no}</div>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:8px;">
            <span style="color:var(--text-muted);">Date & Time:</span>
            <strong>${p.payment_date}</strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:8px;">
            <span style="color:var(--text-muted);">Student Name:</span>
            <strong>${p.first_name} ${p.last_name} (${p.lrn || 'N/A'})</strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:8px;">
            <span style="color:var(--text-muted);">Particulars:</span>
            <strong>${p.fee_title}</strong>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:8px;">
            <span style="color:var(--text-muted);">Payment Mode:</span>
            <strong>${p.payment_method}</strong>
        </div>
        ${p.reference_no ? `
            <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:8px;">
                <span style="color:var(--text-muted);">Transaction Ref:</span>
                <strong>${p.reference_no}</strong>
            </div>
        ` : ''}
        <div style="background:#F0FDF4; border: 1px solid #86EFAC; border-radius: var(--radius-sm); padding: 14px; margin: 18px 0; text-align:center;">
            <div style="font-size:0.8rem; color:#065F46; font-weight:700; text-transform:uppercase;">Amount Paid</div>
            <div style="font-size:2rem; font-weight:800; color:#047857;">₱${parseFloat(p.amount).toLocaleString('en-US', {minimumFractionDigits:2})}</div>
        </div>
        <div style="font-size:0.78rem; color:var(--text-muted); text-align:center; border-top: 1px dashed #CBD5E1; padding-top:12px;">
            Processed by: ${p.logged_by_name || 'Admin'} &bull; Thank you for your payment!
        </div>
    `;
    document.getElementById('receiptContent').innerHTML = html;
    openModal('receiptModal');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
