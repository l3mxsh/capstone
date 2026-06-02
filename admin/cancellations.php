<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ''; $bookingId = (int)($_POST['booking_id']??0);
    if ($action === 'process' && $bookingId) {
        $reason = trim($_POST['reason']??'');
        $depositAmount = (float)($_POST['deposit_amount']??0);
        $depositRetained = (float)($_POST['deposit_retained']??0);
        $stmt = $pdo->prepare("SELECT b.*,u.id AS uid,u.name AS client_name,u.email AS client_email,p.name AS package_name FROM bookings b JOIN users u ON b.client_id=u.id JOIN packages p ON b.package_id=p.id WHERE b.id=?");
        $stmt->execute([$bookingId]); $booking = $stmt->fetch();
        if (!$booking) { $_SESSION['flash'] = ['type'=>'danger','msg'=>'Booking not found.']; header('Location: cancellations.php'); exit; }
        $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([$bookingId]);
        $pdo->prepare("DELETE FROM staff_schedules WHERE booking_id=?")->execute([$bookingId]);
        $pdo->prepare("INSERT INTO cancellations (booking_id,client_id,reason,deposit_amount,deposit_retained) VALUES (?,?,?,?,?)")
            ->execute([$bookingId,$booking['uid'],$reason,$depositAmount,$depositRetained]);
        $refund = $depositAmount - $depositRetained;
        $message = "Dear {$booking['client_name']},\n\nYour booking for {$booking['package_name']} on {$booking['booking_date']} has been cancelled.\n\nReason: ".($reason?:'N/A')."\nDeposit Paid: ₱".number_format($depositAmount,2)."\nRetained: ₱".number_format($depositRetained,2)."\nRefundable: ₱".number_format(max(0,$refund),2)."\n\n— Harvy Mance Films";
        @mail($booking['client_email'], 'Booking Cancellation — Harvy Mance Films', $message, "From: noreply@harvymancefilms.com\r\nContent-Type: text/plain; charset=UTF-8");
        $_SESSION['flash'] = ['type'=>'success','msg'=>"Booking #{$bookingId} cancelled and client notified."];
    }
    header('Location: cancellations.php'); exit;
}

$stats = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(deposit_retained),0) AS retained FROM cancellations WHERE MONTH(cancelled_at)=MONTH(CURDATE()) AND YEAR(cancelled_at)=YEAR(CURDATE())")->fetch();
$logs = $pdo->query("
    SELECT c.id,c.reason,c.deposit_amount,c.deposit_retained,c.cancelled_at,
           u.name AS client,u.email AS client_email,p.name AS package,b.booking_date,b.id AS booking_id
    FROM cancellations c JOIN bookings b ON c.booking_id=b.id JOIN users u ON c.client_id=u.id JOIN packages p ON b.package_id=p.id
    ORDER BY c.cancelled_at DESC
")->fetchAll();
$eligible = $pdo->query("
    SELECT b.id,b.booking_date,b.event_type,u.name AS client,u.email AS client_email,p.name AS package,b.status
    FROM bookings b JOIN users u ON b.client_id=u.id JOIN packages p ON b.package_id=p.id
    WHERE b.status IN ('pending','approved','rescheduled') ORDER BY b.booking_date ASC
")->fetchAll();

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$pageTitle  = 'Cancellations — Harvy Mance Films';
$activePage = 'cancellations';
require_once '../includes/admin_head.php';
?>
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>

<!-- Toast -->
<div class="toast-container position-fixed top-0 end-0 p-3">
<?php if ($flash): ?>
    <div id="liveToast" class="toast align-items-center border-0 show" role="alert"
         style="background:<?= $flash['type']==='success'?'#f0fdf4':'#fef2f2' ?>">
        <div class="toast-header" style="background:transparent;">
            <i class="bi <?= $flash['type']==='success'?'bi-check-circle-fill text-success':'bi-x-circle-fill text-danger' ?> me-2"></i>
            <strong class="me-auto"><?= ucfirst($flash['type']==='danger'?'Error':$flash['type']) ?></strong>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body"><?= htmlspecialchars($flash['msg']) ?></div>
    </div>
<?php endif; ?>
</div>

<div id="main-wrapper">
    <div id="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="topbar-btn d-lg-none border-0" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <div>
                <div class="topbar-title">Cancellations</div>
                <div class="topbar-sub">Process and review cancellations</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['name'],0,1)) ?></div>
        </div>
    </div>

    <div class="p-3 p-md-4">

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="icon-wrap"><i class="bi bi-x-circle"></i></div>
                    <div>
                        <div class="count"><?= (int)$stats['total'] ?></div>
                        <div class="label">Cancellations This Month</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="icon-wrap"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="count" style="font-size:1.35rem;">₱<?= number_format((float)$stats['retained'],2) ?></div>
                        <div class="label">Deposits Retained This Month</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Process Panel -->
        <div class="dash-card mb-4">
            <div class="dash-card-header">
                <h6>Process a Cancellation</h6>
                <span class="badge bg-secondary"><?= count($eligible) ?> active booking(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table modern-table mobile-cards mb-0">
                    <thead>
                        <tr><th>Date</th><th>Client</th><th>Package</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($eligible)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No active bookings.</td></tr>
                    <?php else: ?>
                        <?php foreach ($eligible as $e): ?>
                        <tr>
                            <td data-label="Date" class="fw-semibold"><?= htmlspecialchars($e['booking_date']) ?></td>
                            <td data-label="Client">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar-sm"><?= strtoupper(substr($e['client'],0,1)) ?></span>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($e['client']) ?></div>
                                        <div style="font-size:.74rem;color:#aaa;"><?= htmlspecialchars($e['client_email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Package"><?= htmlspecialchars($e['package']) ?></td>
                            <td data-label="Status">
                                <?php $sc=['pending'=>'warning','approved'=>'success','rescheduled'=>'info']; ?>
                                <span class="badge bg-<?= $sc[$e['status']]??'secondary' ?>"><?= ucfirst($e['status']) ?></span>
                            </td>
                            <td data-label="Action">
                                <button class="btn-action danger" title="Process Cancellation"
                                        onclick="openCancelModal(<?= htmlspecialchars(json_encode($e),ENT_QUOTES) ?>)">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Cancellation Log -->
        <div class="dash-card">
            <div class="dash-card-header">
                <h6>Cancellation Log</h6>
                <span class="badge bg-secondary"><?= count($logs) ?> record(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table modern-table mobile-cards mb-0">
                    <thead>
                        <tr><th>#</th><th>Client</th><th>Package</th><th>Booking Date</th><th>Reason</th><th>Deposit Paid</th><th>Retained</th><th>Refundable</th><th>Cancelled At</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-clipboard-x d-block fs-3 mb-2 opacity-25"></i>No cancellations recorded.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): $refundable = max(0,(float)$log['deposit_amount']-(float)$log['deposit_retained']); ?>
                        <tr>
                            <td data-label="#" class="text-muted"><?= (int)$log['id'] ?></td>
                            <td data-label="Client">
                                <div class="fw-semibold"><?= htmlspecialchars($log['client']) ?></div>
                                <div style="font-size:.74rem;color:#aaa;"><?= htmlspecialchars($log['client_email']) ?></div>
                            </td>
                            <td data-label="Package"><?= htmlspecialchars($log['package']) ?></td>
                            <td data-label="Booking Date"><?= htmlspecialchars($log['booking_date']) ?></td>
                            <td data-label="Reason" style="max-width:180px;"><div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($log['reason']?:'—') ?></div></td>
                            <td data-label="Deposit Paid">₱<?= number_format((float)$log['deposit_amount'],2) ?></td>
                            <td data-label="Retained" class="text-danger fw-semibold">₱<?= number_format((float)$log['deposit_retained'],2) ?></td>
                            <td data-label="Refundable" class="text-success fw-semibold">₱<?= number_format($refundable,2) ?></td>
                            <td data-label="Cancelled At"><?= date('M d, Y g:i A', strtotime($log['cancelled_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Process Cancellation Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST">
            <input type="hidden" name="action" value="process">
            <input type="hidden" name="booking_id" id="cancelBookingId">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title text-danger">Process Cancellation</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="info-box mb-3">
                        <div class="row g-2" style="font-size:.84rem;">
                            <div class="col-4"><div class="info-label">Client</div><div id="cClient" class="fw-semibold mt-1"></div></div>
                            <div class="col-4"><div class="info-label">Package</div><div id="cPackage" class="mt-1"></div></div>
                            <div class="col-4"><div class="info-label">Date</div><div id="cDate" class="mt-1"></div></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Optional…"></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Deposit Paid (₱)</label>
                            <input type="number" class="form-control" name="deposit_amount" id="depositAmount" min="0" step="0.01" value="0" oninput="calcRefund()">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Amount to Retain (₱)</label>
                            <input type="number" class="form-control" name="deposit_retained" id="depositRetained" min="0" step="0.01" value="0" oninput="calcRefund()">
                        </div>
                    </div>
                    <div class="info-box d-flex justify-content-between align-items-center">
                        <span style="font-size:.83rem;color:#888;">Refundable to Client</span>
                        <span class="fw-bold text-success" id="refundDisplay">₱0.00</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Back</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        Confirm Cancellation
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('show'));
    function openCancelModal(b) {
        document.getElementById('cancelBookingId').value = b.id;
        document.getElementById('cClient').textContent  = b.client;
        document.getElementById('cPackage').textContent = b.package;
        document.getElementById('cDate').textContent    = b.booking_date;
        document.getElementById('depositAmount').value   = '0';
        document.getElementById('depositRetained').value = '0';
        document.getElementById('refundDisplay').textContent = '₱0.00';
        new bootstrap.Modal(document.getElementById('cancelModal')).show();
    }
    function calcRefund() {
        const paid     = parseFloat(document.getElementById('depositAmount').value) || 0;
        const retained = parseFloat(document.getElementById('depositRetained').value) || 0;
        document.getElementById('refundDisplay').textContent =
            '₱' + Math.max(0, paid - retained).toLocaleString('en-PH', {minimumFractionDigits:2,maximumFractionDigits:2});
    }
    document.addEventListener('DOMContentLoaded', () => {
        const t = document.getElementById('liveToast');
        if (t) setTimeout(() => bootstrap.Toast.getOrCreateInstance(t).hide(), 4000);
    });
</script>
</body>
</html>
