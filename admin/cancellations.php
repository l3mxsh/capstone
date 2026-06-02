<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

// ── POST Handler (PRG) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($action === 'process' && $bookingId) {
        $reason          = trim($_POST['reason']           ?? '');
        $depositAmount   = (float)($_POST['deposit_amount']   ?? 0);
        $depositRetained = (float)($_POST['deposit_retained'] ?? 0);

        // Fetch booking + client info
        $stmt = $pdo->prepare("
            SELECT b.*, u.id AS uid, u.name AS client_name, u.email AS client_email,
                   p.name AS package_name
            FROM bookings b
            JOIN users u    ON b.client_id  = u.id
            JOIN packages p ON b.package_id = p.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Booking not found.'];
            header('Location: cancellations.php'); exit;
        }

        // Step 1: Cancel booking
        $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")
            ->execute([$bookingId]);

        // Step 2: Remove staff schedule
        $pdo->prepare("DELETE FROM staff_schedules WHERE booking_id=?")
            ->execute([$bookingId]);

        // Step 3: Log cancellation
        $pdo->prepare("
            INSERT INTO cancellations
                (booking_id, client_id, reason, deposit_amount, deposit_retained)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $bookingId,
            $booking['uid'],
            $reason,
            $depositAmount,
            $depositRetained
        ]);

        // Step 4: Email notification
        $to      = $booking['client_email'];
        $subject = 'Booking Cancellation — Harvy Mance Films';
        $refund  = $depositAmount - $depositRetained;
        $message = "Dear {$booking['client_name']},\n\n"
            . "Your booking for {$booking['package_name']} on {$booking['booking_date']} has been cancelled.\n\n"
            . "Reason: " . ($reason ?: 'N/A') . "\n"
            . "Deposit Paid:     ₱" . number_format($depositAmount, 2) . "\n"
            . "Amount Retained:  ₱" . number_format($depositRetained, 2) . "\n"
            . "Refundable:       ₱" . number_format(max(0, $refund), 2) . "\n\n"
            . "If you have questions, please contact us.\n\n"
            . "— Harvy Mance Films";

        $headers = "From: noreply@harvymancefilms.com\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($to, $subject, $message, $headers);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Booking #{$bookingId} cancelled and client notified."];
    }

    header('Location: cancellations.php'); exit;
}

// ── Summary stats for this month ───────────────────────────────
$stats = $pdo->query("
    SELECT COUNT(*) AS total, COALESCE(SUM(deposit_retained), 0) AS retained
    FROM cancellations
    WHERE MONTH(cancelled_at) = MONTH(CURDATE()) AND YEAR(cancelled_at) = YEAR(CURDATE())
")->fetch();

// ── Cancellation log ───────────────────────────────────────────
$logs = $pdo->query("
    SELECT c.id, c.reason, c.deposit_amount, c.deposit_retained, c.cancelled_at,
           u.name AS client, u.email AS client_email,
           p.name AS package, b.booking_date, b.id AS booking_id
    FROM cancellations c
    JOIN bookings b ON c.booking_id = b.id
    JOIN users u    ON c.client_id  = u.id
    JOIN packages p ON b.package_id = p.id
    ORDER BY c.cancelled_at DESC
")->fetchAll();

// ── Pending/approved bookings eligible for cancellation ────────
$eligible = $pdo->query("
    SELECT b.id, b.booking_date, b.event_type,
           u.name AS client, u.email AS client_email,
           p.name AS package, b.status
    FROM bookings b
    JOIN users u    ON b.client_id  = u.id
    JOIN packages p ON b.package_id = p.id
    WHERE b.status IN ('pending','approved','rescheduled')
    ORDER BY b.booking_date ASC
")->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle  = 'Cancellations — Harvy Mance Films';
$activePage = 'cancellations';
require_once '../includes/admin_head.php';
?>
</head>
<body>

<?php require_once '../includes/sidebar.php'; ?>

<div id="main-wrapper">

    <!-- Topbar -->
    <div id="topbar">
        <div class="welcome">Welcome back, <span><?= htmlspecialchars($_SESSION['name']) ?></span></div>
        <input type="search" class="search-input" placeholder="&#128269; Search...">
    </div>

    <div class="p-4">

        <!-- Flash -->
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ── Summary Cards ──────────────────────────────── -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-white">
                    <div class="icon-box" style="background:#fdecea;">
                        <i class="bi bi-x-circle" style="color:#dc3545;"></i>
                    </div>
                    <div>
                        <div class="count"><?= (int)$stats['total'] ?></div>
                        <div class="label">Cancellations This Month</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-white">
                    <div class="icon-box" style="background:#fff3cd;">
                        <i class="bi bi-cash-coin" style="color:var(--gold);"></i>
                    </div>
                    <div>
                        <div class="count" style="font-size:1.4rem;">
                            ₱<?= number_format((float)$stats['retained'], 2) ?>
                        </div>
                        <div class="label">Deposits Retained This Month</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Process Cancellation Panel ────────────────── -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
                <h6 class="mb-0 fw-semibold">Process a Cancellation</h6>
                <span class="badge bg-secondary" style="font-size:.75rem;">
                    <?= count($eligible) ?> active booking(s)
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Package</th>
                                <th>Status</th>
                                <th style="width:110px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($eligible)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No active bookings.</td></tr>
                        <?php else: ?>
                            <?php foreach ($eligible as $e): ?>
                            <tr>
                                <td><?= htmlspecialchars($e['booking_date']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($e['client']) ?></div>
                                    <div class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($e['client_email']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($e['package']) ?></td>
                                <td>
                                    <?php
                                    $sc = ['pending'=>'warning','approved'=>'success','rescheduled'=>'info'];
                                    ?>
                                    <span class="badge bg-<?= $sc[$e['status']] ?? 'secondary' ?>">
                                        <?= ucfirst($e['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-2"
                                            onclick="openCancelModal(<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── Cancellation Log ───────────────────────────── -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="mb-0 fw-semibold">Cancellation Log</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Client</th>
                                <th>Package</th>
                                <th>Booking Date</th>
                                <th>Reason</th>
                                <th>Deposit Paid</th>
                                <th>Retained</th>
                                <th>Refundable</th>
                                <th>Cancelled At</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No cancellations recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log):
                                $refundable = max(0, (float)$log['deposit_amount'] - (float)$log['deposit_retained']);
                            ?>
                            <tr>
                                <td><?= (int)$log['id'] ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($log['client']) ?></div>
                                    <div class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($log['client_email']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($log['package']) ?></td>
                                <td><?= htmlspecialchars($log['booking_date']) ?></td>
                                <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= htmlspecialchars($log['reason'] ?: '—') ?>
                                </td>
                                <td>₱<?= number_format((float)$log['deposit_amount'], 2) ?></td>
                                <td class="text-danger fw-semibold">
                                    ₱<?= number_format((float)$log['deposit_retained'], 2) ?>
                                </td>
                                <td class="text-success fw-semibold">
                                    ₱<?= number_format($refundable, 2) ?>
                                </td>
                                <td><?= date('M d, Y g:i A', strtotime($log['cancelled_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main-wrapper -->

<!-- ── Process Cancellation Modal ──────────────────────────── -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="action"     value="process">
            <input type="hidden" name="booking_id" id="cancelBookingId">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold text-danger">Process Cancellation</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    <!-- Booking summary -->
                    <div class="bg-light rounded p-3 mb-3" style="font-size:.85rem;">
                        <div class="row g-1">
                            <div class="col-5 text-muted">Client</div>
                            <div class="col-7 fw-semibold" id="cClient"></div>
                            <div class="col-5 text-muted">Package</div>
                            <div class="col-7" id="cPackage"></div>
                            <div class="col-5 text-muted">Booking Date</div>
                            <div class="col-7" id="cDate"></div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Reason for Cancellation</label>
                            <textarea class="form-control" name="reason" rows="3"
                                      placeholder="Optional reason..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Deposit Amount Paid (₱)</label>
                            <input type="number" class="form-control" name="deposit_amount"
                                   id="depositAmount" min="0" step="0.01" value="0"
                                   oninput="calcRefund()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Amount to Retain (₱)</label>
                            <input type="number" class="form-control" name="deposit_retained"
                                   id="depositRetained" min="0" step="0.01" value="0"
                                   oninput="calcRefund()">
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between bg-light rounded px-3 py-2"
                                 style="font-size:.875rem;">
                                <span class="text-muted">Refundable to Client</span>
                                <span class="fw-semibold text-success" id="refundDisplay">₱0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Back</button>
                    <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm('Confirm cancellation? This cannot be undone.')">
                        Confirm Cancellation
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openCancelModal(b) {
        document.getElementById('cancelBookingId').value = b.id;
        document.getElementById('cClient').textContent   = b.client;
        document.getElementById('cPackage').textContent  = b.package;
        document.getElementById('cDate').textContent     = b.booking_date;
        document.getElementById('depositAmount').value   = '0';
        document.getElementById('depositRetained').value = '0';
        document.getElementById('refundDisplay').textContent = '₱0.00';
        new bootstrap.Modal(document.getElementById('cancelModal')).show();
    }

    function calcRefund() {
        const paid     = parseFloat(document.getElementById('depositAmount').value)   || 0;
        const retained = parseFloat(document.getElementById('depositRetained').value) || 0;
        const refund   = Math.max(0, paid - retained);
        document.getElementById('refundDisplay').textContent =
            '₱' + refund.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
</script>
</body>
</html>
