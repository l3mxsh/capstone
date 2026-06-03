<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

$filterMonth = max(1, min(12, (int)($_GET['month'] ?? date('n'))));
$filterYear  = max(2020, min((int)date('Y')+1, (int)($_GET['year'] ?? date('Y'))));

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csvStmt = $pdo->prepare("SELECT b.id,u.name AS client,u.email,p.name AS package,b.booking_date,b.event_type,b.venue,b.status,b.created_at FROM bookings b JOIN users u ON b.client_id=u.id JOIN packages p ON b.package_id=p.id WHERE MONTH(b.booking_date)=? AND YEAR(b.booking_date)=? ORDER BY b.booking_date ASC");
    $csvStmt->execute([$filterMonth, $filterYear]); $rows = $csvStmt->fetchAll();
    $monthLabel = date('F_Y', mktime(0,0,0,$filterMonth,1,$filterYear));
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"report_{$monthLabel}.csv\"");
    header('Pragma: no-cache');
    $out = fopen('php://output','w');
    fputcsv($out, ['ID','Client','Email','Package','Booking Date','Event Type','Venue','Status','Created At']);
    foreach ($rows as $r) fputcsv($out, [$r['id'],$r['client'],$r['email'],$r['package'],$r['booking_date'],$r['event_type']??'',$r['venue']??'',$r['status'],$r['created_at']]);
    fclose($out); exit;
}

$bookingStmt = $pdo->prepare("SELECT b.id,u.name AS client,p.name AS package,b.booking_date,b.event_type,b.status FROM bookings b JOIN users u ON b.client_id=u.id JOIN packages p ON b.package_id=p.id WHERE MONTH(b.booking_date)=? AND YEAR(b.booking_date)=? ORDER BY b.booking_date ASC");
$bookingStmt->execute([$filterMonth, $filterYear]); $bookings = $bookingStmt->fetchAll();

$statusCounts = ['approved'=>0,'pending'=>0,'rescheduled'=>0,'cancelled'=>0];
foreach ($bookings as $b) { if (isset($statusCounts[$b['status']])) $statusCounts[$b['status']]++; }

$cancelStmt = $pdo->prepare("SELECT c.id,u.name AS client,p.name AS package,b.booking_date,c.reason,c.deposit_amount,c.deposit_retained,c.cancelled_at FROM cancellations c JOIN bookings b ON c.booking_id=b.id JOIN users u ON c.client_id=u.id JOIN packages p ON b.package_id=p.id WHERE MONTH(c.cancelled_at)=? AND YEAR(c.cancelled_at)=? ORDER BY c.cancelled_at DESC");
$cancelStmt->execute([$filterMonth, $filterYear]); $cancellations = $cancelStmt->fetchAll();
$totalRetained = array_sum(array_column($cancellations,'deposit_retained'));

$ppStmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN pp.progress_percent=100 THEN 1 ELSE 0 END) AS completed FROM post_production pp JOIN bookings b ON pp.booking_id=b.id WHERE MONTH(b.booking_date)=? AND YEAR(b.booking_date)=?");
$ppStmt->execute([$filterMonth, $filterYear]); $ppStats = $ppStmt->fetch();
$ppTotal = (int)$ppStats['total']; $ppCompleted = (int)$ppStats['completed'];
$ppRate = $ppTotal > 0 ? round(($ppCompleted/$ppTotal)*100) : 0;

$revStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total_billed, COALESCE(SUM(deposit_paid),0) AS total_collected, COALESCE(SUM(balance),0) AS total_outstanding FROM invoices i JOIN bookings b ON i.booking_id=b.id WHERE MONTH(b.booking_date)=? AND YEAR(b.booking_date)=?");
$revStmt->execute([$filterMonth, $filterYear]); $revenue = $revStmt->fetch();

$months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
$years  = range(2020,(int)date('Y'));
$statusBadge = ['approved'=>'success','pending'=>'warning','rescheduled'=>'info','cancelled'=>'danger'];

$pageTitle  = 'Reports';
$activePage = 'reports';
require_once '../includes/admin_head.php';
?>
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>

<div id="main-wrapper">
    <div id="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="topbar-btn d-lg-none border-0" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <div>
                <div class="topbar-title">Reports</div>
                <div class="topbar-sub"><?= $months[$filterMonth] ?> <?= $filterYear ?></div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
            <?php if ($_adminAvatar): ?><img src="../assets/avatars/<?= htmlspecialchars($_adminAvatar) ?>" class="topbar-avatar" style="object-fit:cover;"><?php else: ?><div class="topbar-avatar"><?= $_adminInitial ?></div><?php endif; ?>
        </div>
    </div>

    <div class="p-3 p-md-4">

        <!-- Filter Bar -->
        <div class="dash-card mb-4">
            <div class="dash-card-body py-3">
                <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                    <select name="month" class="form-select form-select-sm" style="width:130px;">
                        <?php foreach ($months as $n=>$name): ?>
                            <option value="<?= $n ?>" <?= $filterMonth===$n?'selected':'' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="year" class="form-select form-select-sm" style="width:85px;">
                        <?php foreach ($years as $y): ?>
                            <option value="<?= $y ?>" <?= $filterYear===$y?'selected':'' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-dark btn-sm px-3"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="reports.php?month=<?= $filterMonth ?>&year=<?= $filterYear ?>&export=csv"
                       class="btn btn-sm btn-outline-secondary px-3 ms-auto">
                        <i class="bi bi-download me-1"></i> Export CSV
                    </a>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="icon-wrap"><i class="bi bi-calendar-check"></i></div>
                    <div><div class="count"><?= count($bookings) ?></div><div class="label">Total Bookings</div></div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="icon-wrap"><i class="bi bi-cash-stack"></i></div>
                    <div><div class="count" style="font-size:1.35rem;">₱<?= number_format((float)$revenue['total_collected'],0) ?></div><div class="label">Revenue Collected</div></div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="icon-wrap"><i class="bi bi-x-circle"></i></div>
                    <div><div class="count"><?= count($cancellations) ?></div><div class="label">Cancellations</div></div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="icon-wrap"><i class="bi bi-film"></i></div>
                    <div><div class="count"><?= $ppCompleted ?>/<?= $ppTotal ?></div><div class="label">Post-Production Done</div></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left col -->
            <div class="col-lg-8 order-2 order-lg-1">

                <!-- Booking Summary -->
                <div class="dash-card mb-4">
                    <div class="dash-card-header flex-wrap gap-2">
                        <h6>Booking Summary</h6>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach (['approved','pending','rescheduled','cancelled'] as $s): ?>
                                <span class="badge bg-<?= $statusBadge[$s] ?>"><?= ucfirst($s) ?>: <?= $statusCounts[$s] ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table modern-table mobile-cards mb-0">
                            <thead>
                                <tr><th>#</th><th>Client</th><th>Package</th><th>Date</th><th>Event Type</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No bookings for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td data-label="#" class="text-muted"><?= (int)$b['id'] ?></td>
                                    <td data-label="Client" class="fw-semibold"><?= htmlspecialchars($b['client']) ?></td>
                                    <td data-label="Package"><?= htmlspecialchars($b['package']) ?></td>
                                    <td data-label="Date"><?= htmlspecialchars($b['booking_date']) ?></td>
                                    <td data-label="Event"><?= htmlspecialchars($b['event_type']??'—') ?></td>
                                    <td data-label="Status"><span class="badge bg-<?= $statusBadge[$b['status']]??'secondary' ?>"><?= ucfirst($b['status']) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cancellation Summary -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h6>Cancellation Summary</h6>
                        <span style="font-size:.8rem;color:#888;">Retained: <strong>₱<?= number_format($totalRetained,2) ?></strong></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table modern-table mobile-cards mb-0">
                            <thead>
                                <tr><th>Client</th><th>Package</th><th>Booking Date</th><th>Reason</th><th>Deposit Paid</th><th>Retained</th><th>Refundable</th><th>Cancelled</th></tr>
                            </thead>
                            <tbody>
                            <?php if (empty($cancellations)): ?>
                                <tr><td colspan="8" class="text-center py-4 text-muted">No cancellations this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($cancellations as $c): $refundable = max(0,(float)$c['deposit_amount']-(float)$c['deposit_retained']); ?>
                                <tr>
                                    <td data-label="Client" class="fw-semibold"><?= htmlspecialchars($c['client']) ?></td>
                                    <td data-label="Package"><?= htmlspecialchars($c['package']) ?></td>
                                    <td data-label="Date"><?= htmlspecialchars($c['booking_date']) ?></td>
                                    <td data-label="Reason" style="max-width:140px;"><div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($c['reason']?:'—') ?></div></td>
                                    <td data-label="Deposit Paid">₱<?= number_format((float)$c['deposit_amount'],2) ?></td>
                                    <td data-label="Retained" class="text-danger fw-semibold">₱<?= number_format((float)$c['deposit_retained'],2) ?></td>
                                    <td data-label="Refundable" class="text-success fw-semibold">₱<?= number_format($refundable,2) ?></td>
                                    <td data-label="Cancelled"><?= date('M d, Y', strtotime($c['cancelled_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right col -->
            <div class="col-lg-4 order-1 order-lg-2">
                <div class="row g-3 g-lg-4">

                <!-- Post-Production Rate -->
                <div class="col-6 col-lg-12">
                <div class="dash-card">
                    <div class="dash-card-header"><h6>Post-Production Rate</h6></div>
                    <div class="dash-card-body text-center">
                        <div class="mx-auto mb-3 d-flex flex-column align-items-center justify-content-center"
                             style="width:80px;height:80px;border-radius:50%;border:7px solid #e8e8e8;">
                            <span style="font-size:1.25rem;font-weight:800;color:#111;line-height:1;"><?= $ppRate ?>%</span>
                            <span style="font-size:.6rem;color:#aaa;">Done</span>
                        </div>
                        <div class="progress mb-2" style="height:6px;">
                            <div class="progress-bar bg-dark" role="progressbar" style="width:<?= $ppRate ?>%"></div>
                        </div>
                        <div style="font-size:.78rem;color:#888;"><?= $ppCompleted ?> / <?= $ppTotal ?> completed</div>
                    </div>
                </div>
                </div>

                <!-- Revenue Breakdown -->
                <div class="col-6 col-lg-12">
                <div class="dash-card">
                    <div class="dash-card-header"><h6>Revenue</h6></div>
                    <div class="dash-card-body p-0">
                        <?php
                        $revItems = [
                            ['Billed',      '₱'.number_format((float)$revenue['total_billed'],2),     ''],
                            ['Collected',   '₱'.number_format((float)$revenue['total_collected'],2),  'text-success'],
                            ['Outstanding', '₱'.number_format((float)$revenue['total_outstanding'],2),'text-danger'],
                            ['Retained',    '₱'.number_format((float)$totalRetained,2),               ''],
                        ];
                        ?>
                        <?php foreach ($revItems as [$label,$val,$cls]): ?>
                        <div class="d-flex justify-content-between align-items-center px-3 py-2" style="border-bottom:1px solid #f0f0f0;font-size:.82rem;">
                            <span style="color:#666;"><?= $label ?></span>
                            <span class="fw-semibold <?= $cls ?>"><?= $val ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                </div>

                <!-- Status Breakdown -->
                <div class="col-12">
                <div class="dash-card">
                    <div class="dash-card-header"><h6>Booking Status</h6></div>
                    <div class="dash-card-body">
                        <?php $total = count($bookings) ?: 1; foreach (['approved','pending','rescheduled','cancelled'] as $s): $pct = round(($statusCounts[$s]/$total)*100); ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;">
                                <span class="fw-semibold"><?= ucfirst($s) ?></span>
                                <span style="color:#888;"><?= $statusCounts[$s] ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="progress" style="height:6px;">
                                <div class="progress-bar bg-dark" role="progressbar" style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('show'));
</script>
</body>
</html>
