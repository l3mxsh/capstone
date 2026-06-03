<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

$stats = $pdo->query("
    SELECT COUNT(*) AS total,
           COALESCE(SUM(deposit_retained),0) AS retained
    FROM cancellations
    WHERE MONTH(cancelled_at)=MONTH(CURDATE()) AND YEAR(cancelled_at)=YEAR(CURDATE())
")->fetch();

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
                <div class="topbar-sub">View all client cancellation records</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
            <?php if ($_adminAvatar): ?><img src="../assets/avatars/<?= htmlspecialchars($_adminAvatar) ?>" class="topbar-avatar" style="object-fit:cover;"><?php else: ?><div class="topbar-avatar"><?= $_adminInitial ?></div><?php endif; ?>
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
                        <div class="count" style="font-size:1.35rem;">₱<?= number_format((float)$stats['retained'], 2) ?></div>
                        <div class="label">Deposits Retained This Month</div>
                    </div>
                </div>
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
                        <tr>
                            <th>#</th>
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
                        <tr><td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-clipboard-x d-block fs-3 mb-2 opacity-25"></i>No cancellations recorded.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log):
                            $refundable = max(0, (float)$log['deposit_amount'] - (float)$log['deposit_retained']);
                        ?>
                        <tr>
                            <td data-label="#" class="text-muted"><?= (int)$log['id'] ?></td>
                            <td data-label="Client">
                                <div class="fw-semibold"><?= htmlspecialchars($log['client']) ?></div>
                                <div style="font-size:.74rem;color:#aaa;"><?= htmlspecialchars($log['client_email']) ?></div>
                            </td>
                            <td data-label="Package"><?= htmlspecialchars($log['package']) ?></td>
                            <td data-label="Booking Date"><?= htmlspecialchars($log['booking_date']) ?></td>
                            <td data-label="Reason" style="max-width:180px;">
                                <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= htmlspecialchars($log['reason'] ?: '—') ?>
                                </div>
                            </td>
                            <td data-label="Deposit Paid">₱<?= number_format((float)$log['deposit_amount'], 2) ?></td>
                            <td data-label="Retained" class="text-danger fw-semibold">₱<?= number_format((float)$log['deposit_retained'], 2) ?></td>
                            <td data-label="Refundable" class="text-success fw-semibold">₱<?= number_format($refundable, 2) ?></td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('show'));
    document.addEventListener('DOMContentLoaded', () => {
        const t = document.getElementById('liveToast');
        if (t) setTimeout(() => bootstrap.Toast.getOrCreateInstance(t).hide(), 4000);
    });
</script>
</body>
</html>
