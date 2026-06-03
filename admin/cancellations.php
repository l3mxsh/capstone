<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action         = $_POST['action']      ?? '';
    $cancellationId = (int)($_POST['cancellation_id'] ?? 0);

    // ── APPROVE cancellation request ──────────────────────────
    if ($action === 'approve' && $cancellationId) {
        $depositAmount   = (float)($_POST['deposit_amount']   ?? 0);
        $depositRetained = (float)($_POST['deposit_retained'] ?? 0);

        // Fetch cancellation + booking info
        $stmt = $pdo->prepare("
            SELECT c.booking_id, c.client_id,
                   u.name AS client_name, u.email AS client_email,
                   p.name AS package_name, b.booking_date
            FROM cancellations c
            JOIN bookings b ON c.booking_id = b.id
            JOIN users u    ON c.client_id  = u.id
            JOIN packages p ON b.package_id = p.id
            WHERE c.id = ? AND c.cancellation_status = 'pending_approval'
        ");
        $stmt->execute([$cancellationId]);
        $row = $stmt->fetch();

        if ($row) {
            // 1. Update cancellation record
            $pdo->prepare("
                UPDATE cancellations
                SET cancellation_status = 'approved',
                    deposit_amount      = ?,
                    deposit_retained    = ?,
                    cancelled_at        = NOW()
                WHERE id = ?
            ")->execute([$depositAmount, $depositRetained, $cancellationId]);

            // 2. Cancel the booking
            $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")
                ->execute([$row['booking_id']]);

            // 3. Remove staff schedule
            $pdo->prepare("DELETE FROM staff_schedules WHERE booking_id = ?")
                ->execute([$row['booking_id']]);

            // 4. Email client
            $refund  = max(0, $depositAmount - $depositRetained);
            $subject = 'Cancellation Approved — Harvy Mance Films';
            $message = "Dear {$row['client_name']},\n\n"
                . "Your cancellation request for {$row['package_name']} on {$row['booking_date']} has been approved.\n\n"
                . "Deposit Paid:    ₱" . number_format($depositAmount, 2) . "\n"
                . "Amount Retained: ₱" . number_format($depositRetained, 2) . "\n"
                . "Refundable:      ₱" . number_format($refund, 2) . "\n\n"
                . "Thank you for choosing Harvy Mance Films.\n\n— Harvy Mance Films";
            @mail($row['client_email'], $subject, $message,
                "From: noreply@harvymancefilms.com\r\nContent-Type: text/plain; charset=UTF-8");

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Cancellation approved and client notified.'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Cancellation request not found or already processed.'];
        }

    // ── REJECT cancellation request ───────────────────────────
    } elseif ($action === 'reject' && $cancellationId) {
        $rejectReason = trim($_POST['reject_reason'] ?? '');

        $stmt = $pdo->prepare("
            SELECT c.client_id, u.name AS client_name, u.email AS client_email,
                   p.name AS package_name, b.booking_date
            FROM cancellations c
            JOIN bookings b ON c.booking_id = b.id
            JOIN users u    ON c.client_id  = u.id
            JOIN packages p ON b.package_id = p.id
            WHERE c.id = ? AND c.cancellation_status = 'pending_approval'
        ");
        $stmt->execute([$cancellationId]);
        $row = $stmt->fetch();

        if ($row) {
            $pdo->prepare("UPDATE cancellations SET cancellation_status = 'rejected', reject_reason = ? WHERE id = ?")
                ->execute([$rejectReason, $cancellationId]);

            // Email client
            $subject = 'Cancellation Request Rejected — Harvy Mance Films';
            $message = "Dear {$row['client_name']},\n\n"
                . "Your cancellation request for {$row['package_name']} on {$row['booking_date']} has been reviewed.\n\n"
                . "Unfortunately, your request has been rejected.\n"
                . ($rejectReason ? "Reason: {$rejectReason}\n" : '')
                . "\nYour booking remains active. Please contact us for more details.\n\n"
                . "— Harvy Mance Films";
            @mail($row['client_email'], $subject, $message,
                "From: noreply@harvymancefilms.com\r\nContent-Type: text/plain; charset=UTF-8");

            $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Cancellation request rejected and client notified.'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Cancellation request not found or already processed.'];
        }
    }

    header('Location: cancellations.php'); exit;
}

// ── Summary stats ──────────────────────────────────────────────
$stats = $pdo->query("
    SELECT COUNT(*) AS total,
           COALESCE(SUM(deposit_retained), 0) AS retained
    FROM cancellations
    WHERE cancellation_status = 'approved'
    AND MONTH(cancelled_at) = MONTH(CURDATE())
    AND YEAR(cancelled_at)  = YEAR(CURDATE())
")->fetch();

$pendingCount = (int)$pdo->query("
    SELECT COUNT(*) FROM cancellations WHERE cancellation_status = 'pending_approval'
")->fetchColumn();

// ── Pending approval requests ──────────────────────────────────
$pendingRequests = $pdo->query("
    SELECT c.id AS cancellation_id, c.reason, c.cancelled_at AS requested_at,
           u.name AS client, u.email AS client_email,
           p.name AS package, b.booking_date, b.id AS booking_id, b.status AS booking_status
    FROM cancellations c
    JOIN bookings b ON c.booking_id = b.id
    JOIN users u    ON c.client_id  = u.id
    JOIN packages p ON b.package_id = p.id
    WHERE c.cancellation_status = 'pending_approval'
    ORDER BY c.cancelled_at ASC
")->fetchAll();

// ── Approved / rejected log ────────────────────────────────────
$logs = $pdo->query("
    SELECT c.id, c.reason, c.deposit_amount, c.deposit_retained,
           c.cancelled_at, c.cancellation_status,
           u.name AS client, u.email AS client_email,
           p.name AS package, b.booking_date
    FROM cancellations c
    JOIN bookings b ON c.booking_id = b.id
    JOIN users u    ON c.client_id  = u.id
    JOIN packages p ON b.package_id = p.id
    WHERE c.cancellation_status IN ('approved','rejected')
    ORDER BY c.cancelled_at DESC
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
                    <div class="icon-box" style="background:#fff3e0;">
                        <i class="bi bi-hourglass-split" style="color:#fd7e14;"></i>
                    </div>
                    <div>
                        <div class="count"><?= $pendingCount ?></div>
                        <div class="label">Pending Approval</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-white">
                    <div class="icon-box" style="background:#fdecea;">
                        <i class="bi bi-x-circle" style="color:#dc3545;"></i>
                    </div>
                    <div>
                        <div class="count"><?= (int)$stats['total'] ?></div>
                        <div class="label">Approved This Month</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-white">
                    <div class="icon-box" style="background:#fff3cd;">
                        <i class="bi bi-cash-coin" style="color:var(--gold);"></i>
                    </div>
                    <div>
                        <div class="count" style="font-size:1.3rem;">
                            ₱<?= number_format((float)$stats['retained'], 2) ?>
                        </div>
                        <div class="label">Deposits Retained</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Pending Requests ───────────────────────────── -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
                <h6 class="mb-0 fw-semibold">
                    Pending Cancellation Requests
                    <?php if ($pendingCount > 0): ?>
                        <span class="badge bg-warning text-dark ms-2"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Client</th>
                                <th>Package</th>
                                <th>Booking Date</th>
                                <th>Reason</th>
                                <th>Requested At</th>
                                <th class="text-center" style="width:130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($pendingRequests)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No pending cancellation requests.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pendingRequests as $req): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($req['client']) ?></div>
                                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($req['client_email']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($req['package']) ?></td>
                                <td><?= htmlspecialchars($req['booking_date']) ?></td>
                                <td style="max-width:200px;">
                                    <span style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                        <?= htmlspecialchars($req['reason'] ?: '—') ?>
                                    </span>
                                </td>
                                <td style="white-space:nowrap;"><?= date('M d, Y g:i A', strtotime($req['requested_at'])) ?></td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-2">
                                        <!-- Approve -->
                                        <button type="button"
                                                class="btn btn-success btn-sm d-flex align-items-center gap-1"
                                                style="font-size:.78rem;padding:.3rem .65rem;"
                                                onclick="openApproveModal(<?= htmlspecialchars(json_encode($req), ENT_QUOTES) ?>)"
                                                data-bs-toggle="tooltip" title="Approve Cancellation">
                                            <i class="bi bi-check-lg"></i>
                                            <span class="d-none d-xl-inline">Approve</span>
                                        </button>
                                        <!-- Reject -->
                                        <button type="button"
                                                class="btn btn-danger btn-sm d-flex align-items-center gap-1"
                                                style="font-size:.78rem;padding:.3rem .65rem;"
                                                onclick="openRejectModal(<?= (int)$req['cancellation_id'] ?>, '<?= htmlspecialchars(addslashes($req['client'])) ?>')"
                                                data-bs-toggle="tooltip" title="Reject Cancellation">
                                            <i class="bi bi-x-lg"></i>
                                            <span class="d-none d-xl-inline">Reject</span>
                                        </button>
                                    </div>
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
                                <th>#</th>
                                <th>Client</th>
                                <th>Package</th>
                                <th>Booking Date</th>
                                <th>Reason</th>
                                <th>Deposit Paid</th>
                                <th>Retained</th>
                                <th>Refundable</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No records yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log):
                                $refundable = max(0, (float)$log['deposit_amount'] - (float)$log['deposit_retained']);
                            ?>
                            <tr>
                                <td><?= (int)$log['id'] ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($log['client']) ?></div>
                                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($log['client_email']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($log['package']) ?></td>
                                <td><?= htmlspecialchars($log['booking_date']) ?></td>
                                <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= htmlspecialchars($log['reason'] ?: '—') ?>
                                </td>
                                <td>₱<?= number_format((float)$log['deposit_amount'], 2) ?></td>
                                <td class="text-danger fw-semibold">₱<?= number_format((float)$log['deposit_retained'], 2) ?></td>
                                <td class="text-success fw-semibold">₱<?= number_format($refundable, 2) ?></td>
                                <td>
                                    <?php if ($log['cancellation_status'] === 'approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($log['cancelled_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ── Approve Modal ───────────────────────────────────────── -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="cancellation_id" id="appCancelId">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold text-success">
                        <i class="bi bi-check-circle me-1"></i> Approve Cancellation
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Summary -->
                    <div class="bg-light rounded p-3 mb-3" style="font-size:.855rem;">
                        <div class="row g-1">
                            <div class="col-4 text-muted">Client</div>
                            <div class="col-8 fw-semibold" id="appClient"></div>
                            <div class="col-4 text-muted">Package</div>
                            <div class="col-8" id="appPackage"></div>
                            <div class="col-4 text-muted">Booking Date</div>
                            <div class="col-8" id="appDate"></div>
                            <div class="col-4 text-muted">Reason</div>
                            <div class="col-8 text-muted" id="appReason"></div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Deposit Amount Paid (₱)</label>
                            <input type="number" class="form-control" name="deposit_amount"
                                   id="appDepositAmount" min="0" step="0.01" value="0"
                                   oninput="calcAppRefund()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Amount to Retain (₱)</label>
                            <input type="number" class="form-control" name="deposit_retained"
                                   id="appDepositRetained" min="0" step="0.01" value="0"
                                   oninput="calcAppRefund()">
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between bg-light rounded px-3 py-2" style="font-size:.875rem;">
                                <span class="text-muted">Refundable to Client</span>
                                <span class="fw-semibold text-success" id="appRefundDisplay">₱0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-check-lg"></i> Confirm Approval
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ── Reject Modal ────────────────────────────────────────── -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="cancellation_id" id="rejCancelId">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold text-danger">
                        <i class="bi bi-x-circle me-1"></i> Reject Cancellation
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p style="font-size:.875rem;">
                        Reject the cancellation request from <strong id="rejClientName"></strong>?
                        The booking will remain active.
                    </p>
                    <label class="form-label fw-semibold">Reason for Rejection (optional)</label>
                    <textarea class="form-control" name="reject_reason" rows="3"
                              placeholder="Provide a reason to inform the client..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Back</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-x-lg"></i> Confirm Rejection
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Init tooltips
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
            .forEach(el => new bootstrap.Tooltip(el));
    });

    function openApproveModal(req) {
        document.getElementById('appCancelId').value        = req.cancellation_id;
        document.getElementById('appClient').textContent    = req.client;
        document.getElementById('appPackage').textContent   = req.package;
        document.getElementById('appDate').textContent      = req.booking_date;
        document.getElementById('appReason').textContent    = req.reason || '—';
        document.getElementById('appDepositAmount').value   = '0';
        document.getElementById('appDepositRetained').value = '0';
        document.getElementById('appRefundDisplay').textContent = '₱0.00';
        new bootstrap.Modal(document.getElementById('approveModal')).show();
    }

    function openRejectModal(id, name) {
        document.getElementById('rejCancelId').value        = id;
        document.getElementById('rejClientName').textContent = name;
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    }

    function calcAppRefund() {
        const paid     = parseFloat(document.getElementById('appDepositAmount').value)   || 0;
        const retained = parseFloat(document.getElementById('appDepositRetained').value) || 0;
        const refund   = Math.max(0, paid - retained);
        document.getElementById('appRefundDisplay').textContent =
            '₱' + refund.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
</script>
</body>
</html>
