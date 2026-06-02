<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

// ── Filter params ──────────────────────────────────────────────
$filterMonth = (int)($_GET['month'] ?? date('n'));
$filterYear  = (int)($_GET['year']  ?? date('Y'));

// Clamp values
$filterMonth = max(1, min(12, $filterMonth));
$filterYear  = max(2020, min((int)date('Y') + 1, $filterYear));

// ── CSV Export (must happen before any HTML output) ────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csvStmt = $pdo->prepare("
        SELECT b.id, u.name AS client, u.email,
               p.name AS package, b.booking_date,
               b.event_type, b.venue, b.status, b.created_at
        FROM bookings b
        JOIN users u    ON b.client_id  = u.id
        JOIN packages p ON b.package_id = p.id
        WHERE MONTH(b.booking_date) = ? AND YEAR(b.booking_date) = ?
        ORDER BY b.booking_date ASC
    ");
    $csvStmt->execute([$filterMonth, $filterYear]);
    $rows = $csvStmt->fetchAll();

    $monthLabel = date('F_Y', mktime(0, 0, 0, $filterMonth, 1, $filterYear));
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"report_{$monthLabel}.csv\"");
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Client', 'Email', 'Package', 'Booking Date', 'Event Type', 'Venue', 'Status', 'Created At']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['client'], $r['email'],
            $r['package'], $r['booking_date'],
            $r['event_type'] ?? '', $r['venue'] ?? '',
            $r['status'], $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ── Booking summary ────────────────────────────────────────────
$bookingStmt = $pdo->prepare("
    SELECT b.id, u.name AS client, p.name AS package,
           b.booking_date, b.event_type, b.status
    FROM bookings b
    JOIN users u    ON b.client_id  = u.id
    JOIN packages p ON b.package_id = p.id
    WHERE MONTH(b.booking_date) = ? AND YEAR(b.booking_date) = ?
    ORDER BY b.booking_date ASC
");
$bookingStmt->execute([$filterMonth, $filterYear]);
$bookings = $bookingStmt->fetchAll();

// Status counts from the result set
$statusCounts = ['approved' => 0, 'pending' => 0, 'rescheduled' => 0, 'cancelled' => 0];
foreach ($bookings as $b) {
    if (isset($statusCounts[$b['status']])) $statusCounts[$b['status']]++;
}

// ── Cancellation summary ───────────────────────────────────────
$cancelStmt = $pdo->prepare("
    SELECT c.id, u.name AS client, p.name AS package,
           b.booking_date, c.reason,
           c.deposit_amount, c.deposit_retained,
           c.cancelled_at
    FROM cancellations c
    JOIN bookings b ON c.booking_id = b.id
    JOIN users u    ON c.client_id  = u.id
    JOIN packages p ON b.package_id = p.id
    WHERE MONTH(c.cancelled_at) = ? AND YEAR(c.cancelled_at) = ?
    ORDER BY c.cancelled_at DESC
");
$cancelStmt->execute([$filterMonth, $filterYear]);
$cancellations = $cancelStmt->fetchAll();

$totalRetained = array_sum(array_column($cancellations, 'deposit_retained'));

// ── Post-production completion rate ───────────────────────────
$ppStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN pp.progress_percent = 100 THEN 1 ELSE 0 END) AS completed
    FROM post_production pp
    JOIN bookings b ON pp.booking_id = b.id
    WHERE MONTH(b.booking_date) = ? AND YEAR(b.booking_date) = ?
");
$ppStmt->execute([$filterMonth, $filterYear]);
$ppStats = $ppStmt->fetch();
$ppTotal     = (int)$ppStats['total'];
$ppCompleted = (int)$ppStats['completed'];
$ppRate      = $ppTotal > 0 ? round(($ppCompleted / $ppTotal) * 100) : 0;

// ── Revenue from invoices ──────────────────────────────────────
$revStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(amount), 0)       AS total_billed,
        COALESCE(SUM(deposit_paid), 0) AS total_collected,
        COALESCE(SUM(balance), 0)      AS total_outstanding
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    WHERE MONTH(b.booking_date) = ? AND YEAR(b.booking_date) = ?
");
$revStmt->execute([$filterMonth, $filterYear]);
$revenue = $revStmt->fetch();

// ── Build month/year options ───────────────────────────────────
$months = [
    1=>'January',2=>'February',3=>'March',4=>'April',
    5=>'May',6=>'June',7=>'July',8=>'August',
    9=>'September',10=>'October',11=>'November',12=>'December',
];
$years = range(2020, (int)date('Y'));

$statusColors = [
    'approved'    => 'success',
    'pending'     => 'warning',
    'rescheduled' => 'info',
    'cancelled'   => 'danger',
];

$pageTitle  = 'Reports — Harvy Mance Films';
$activePage = 'reports';
require_once '../includes/admin_head.php';
?>
<style>
    .report-section { margin-bottom: 2rem; }
    .section-heading {
        font-size: .72rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .5px;
        color: #6c757d; margin-bottom: .75rem;
    }
    .rate-circle {
        width: 110px; height: 110px;
        border-radius: 50%;
        border: 8px solid #e9ecef;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
    }
    .rate-circle .rate-pct  { font-size: 1.5rem; font-weight: 800; line-height: 1; color: #198754; }
    .rate-circle .rate-lbl  { font-size: .65rem; color: #6c757d; }
</style>
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

        <!-- ── Page header + filter bar ───────────────────── -->
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <h5 class="mb-0 fw-bold">Reports</h5>
                <p class="text-muted mb-0" style="font-size:.875rem;">
                    Viewing data for
                    <strong><?= $months[$filterMonth] ?> <?= $filterYear ?></strong>
                </p>
            </div>

            <div class="d-flex gap-2 align-items-center flex-wrap">
                <!-- Filter form -->
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <select name="month" class="form-select form-select-sm" style="width:130px;">
                        <?php foreach ($months as $n => $name): ?>
                            <option value="<?= $n ?>" <?= $filterMonth === $n ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="year" class="form-select form-select-sm" style="width:90px;">
                        <?php foreach ($years as $y): ?>
                            <option value="<?= $y ?>" <?= $filterYear === $y ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm text-white"
                            style="background:var(--gold);border:none;">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </form>

                <!-- CSV Export -->
                <a href="reports.php?month=<?= $filterMonth ?>&year=<?= $filterYear ?>&export=csv"
                   class="btn btn-sm btn-outline-success">
                    <i class="bi bi-download"></i> Export CSV
                </a>
            </div>
        </div>

        <!-- ── Summary stat cards ──────────────────────────── -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="stat-card bg-white">
                    <div class="icon-box" style="background:#fff3cd;">
                        <i class="bi bi-calendar-check" style="color:var(--gold);"></i>
                    </div>
                    <div>
                        <div class="count"><?= count($bookings) ?></div>
                        <div class="label">Total Bookings</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card bg-white">
                    <div class="icon-box" style="background:#e8f5e9;">
                        <i class="bi bi-cash-stack" style="color:#198754;"></i>
                    </div>
                    <div>
                        <div class="count" style="font-size:1.3rem;">
                            ₱<?= number_format((float)$revenue['total_collected'], 0) ?>
                        </div>
                        <div class="label">Revenue Collected</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card bg-white">
                    <div class="icon-box" style="background:#fdecea;">
                        <i class="bi bi-x-circle" style="color:#dc3545;"></i>
                    </div>
                    <div>
                        <div class="count"><?= count($cancellations) ?></div>
                        <div class="label">Cancellations</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card bg-white">
                    <div class="icon-box" style="background:#e8f4fd;">
                        <i class="bi bi-film" style="color:#0d6efd;"></i>
                    </div>
                    <div>
                        <div class="count"><?= $ppCompleted ?>/<?= $ppTotal ?></div>
                        <div class="label">Post-Production Done</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            <!-- ── Left col ─────────────────────────────────── -->
            <div class="col-lg-8">

                <!-- Booking Summary Table -->
                <div class="card border-0 shadow-sm report-section">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">Booking Summary</h6>
                        <!-- Status count pills -->
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach (['approved','pending','rescheduled','cancelled'] as $s): ?>
                                <span class="badge bg-<?= $statusColors[$s] ?>" style="font-size:.72rem;">
                                    <?= ucfirst($s) ?>: <?= $statusCounts[$s] ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size:.855rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Client</th>
                                        <th>Package</th>
                                        <th>Date</th>
                                        <th>Event Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($bookings)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            No bookings for this period.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td class="text-muted"><?= (int)$b['id'] ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($b['client']) ?></td>
                                        <td><?= htmlspecialchars($b['package']) ?></td>
                                        <td><?= htmlspecialchars($b['booking_date']) ?></td>
                                        <td><?= htmlspecialchars($b['event_type'] ?? '—') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $statusColors[$b['status']] ?? 'secondary' ?>">
                                                <?= ucfirst($b['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Cancellation Summary Table -->
                <div class="card border-0 shadow-sm report-section">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">Cancellation Summary</h6>
                        <span class="text-muted" style="font-size:.8rem;">
                            Total Retained:
                            <strong class="text-danger">₱<?= number_format($totalRetained, 2) ?></strong>
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size:.855rem;">
                                <thead class="table-light">
                                    <tr>
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
                                <?php if (empty($cancellations)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-3">
                                            No cancellations this period.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cancellations as $c):
                                        $refundable = max(0, (float)$c['deposit_amount'] - (float)$c['deposit_retained']);
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($c['client']) ?></td>
                                        <td><?= htmlspecialchars($c['package']) ?></td>
                                        <td><?= htmlspecialchars($c['booking_date']) ?></td>
                                        <td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                            <?= htmlspecialchars($c['reason'] ?: '—') ?>
                                        </td>
                                        <td>₱<?= number_format((float)$c['deposit_amount'], 2) ?></td>
                                        <td class="text-danger fw-semibold">
                                            ₱<?= number_format((float)$c['deposit_retained'], 2) ?>
                                        </td>
                                        <td class="text-success">
                                            ₱<?= number_format($refundable, 2) ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($c['cancelled_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div><!-- /col-lg-8 -->

            <!-- ── Right col ─────────────────────────────────── -->
            <div class="col-lg-4">

                <!-- Post-Production Completion Rate -->
                <div class="card border-0 shadow-sm report-section">
                    <div class="card-header bg-white border-0 pt-3">
                        <h6 class="mb-0 fw-semibold">Post-Production Rate</h6>
                    </div>
                    <div class="card-body text-center">
                        <div class="rate-circle mx-auto mb-3"
                             style="border-color: <?= $ppRate >= 75 ? '#198754' : ($ppRate >= 40 ? '#ffc107' : '#dc3545') ?>;">
                            <span class="rate-pct"
                                  style="color:<?= $ppRate >= 75 ? '#198754' : ($ppRate >= 40 ? '#fd7e14' : '#dc3545') ?>;">
                                <?= $ppRate ?>%
                            </span>
                            <span class="rate-lbl">Completion</span>
                        </div>
                        <div class="progress mb-2" style="height:8px;">
                            <div class="progress-bar bg-<?= $ppRate >= 75 ? 'success' : ($ppRate >= 40 ? 'warning' : 'danger') ?>"
                                 style="width:<?= $ppRate ?>%"
                                 role="progressbar"
                                 aria-valuenow="<?= $ppRate ?>"
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                        <div class="text-muted" style="font-size:.82rem;">
                            <?= $ppCompleted ?> of <?= $ppTotal ?> project(s) completed
                        </div>
                    </div>
                </div>

                <!-- Revenue Breakdown -->
                <div class="card border-0 shadow-sm report-section">
                    <div class="card-header bg-white border-0 pt-3">
                        <h6 class="mb-0 fw-semibold">Revenue Breakdown</h6>
                    </div>
                    <div class="card-body" style="font-size:.875rem;">
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Total Billed</span>
                            <span class="fw-semibold">₱<?= number_format((float)$revenue['total_billed'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Collected</span>
                            <span class="fw-semibold text-success">
                                ₱<?= number_format((float)$revenue['total_collected'], 2) ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Outstanding</span>
                            <span class="fw-semibold text-danger">
                                ₱<?= number_format((float)$revenue['total_outstanding'], 2) ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between py-2">
                            <span class="text-muted">Deposits Retained</span>
                            <span class="fw-semibold text-warning">
                                ₱<?= number_format((float)$totalRetained, 2) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Booking status breakdown bar -->
                <div class="card border-0 shadow-sm report-section">
                    <div class="card-header bg-white border-0 pt-3">
                        <h6 class="mb-0 fw-semibold">Booking Status Breakdown</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $total = count($bookings) ?: 1;
                        foreach (['approved','pending','rescheduled','cancelled'] as $s):
                            $pct = round(($statusCounts[$s] / $total) * 100);
                        ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;">
                                <span><?= ucfirst($s) ?></span>
                                <span class="text-muted"><?= $statusCounts[$s] ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="progress" style="height:7px;">
                                <div class="progress-bar bg-<?= $statusColors[$s] ?>"
                                     style="width:<?= $pct ?>%"
                                     role="progressbar">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div><!-- /col-lg-4 -->

        </div><!-- /row -->
    </div><!-- /content -->
</div><!-- /main-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
