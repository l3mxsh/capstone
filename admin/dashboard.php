<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

$pageTitle  = 'Admin Dashboard — Harvy Mance Films';
$activePage = 'dashboard';

// --- Summary Cards ---
$totalToday     = $pdo->query("SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$pendingCount   = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$activePostProd = $pdo->query("SELECT COUNT(*) FROM post_production WHERE photo_status != 'completed' OR video_status != 'completed' OR other_status != 'completed'")->fetchColumn();
$totalClients   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();

// --- Recent Bookings (last 5) ---
$recentBookings = $pdo->query("
    SELECT b.id, u.name AS client, p.name AS package, b.booking_date, b.status
    FROM bookings b
    JOIN users u ON b.client_id = u.id
    JOIN packages p ON b.package_id = p.id
    ORDER BY b.created_at DESC LIMIT 5
")->fetchAll();

// --- Calendar: booked dates this month ---
$calYear        = (int) date('Y');
$calMonth       = (int) date('m');
$daysInMonth    = cal_days_in_month(CAL_GREGORIAN, $calMonth, $calYear);
$firstDayOfWeek = (int) date('w', mktime(0, 0, 0, $calMonth, 1, $calYear));

$stmt = $pdo->prepare("
    SELECT DAY(booking_date) AS day FROM bookings
    WHERE MONTH(booking_date) = ? AND YEAR(booking_date) = ? AND status != 'cancelled'
");
$stmt->execute([$calMonth, $calYear]);
$bookedDays = array_column($stmt->fetchAll(), 'day');

// --- Post-Production Progress (top 5 ongoing) ---
$postProjects = $pdo->query("
    SELECT pp.progress_percent,
           pp.photo_status, pp.video_status, pp.other_status,
           u.name AS title
    FROM post_production pp
    JOIN bookings b ON pp.booking_id = b.id
    JOIN users u    ON b.client_id   = u.id
    WHERE pp.photo_status != 'completed'
       OR pp.video_status != 'completed'
       OR pp.other_status != 'completed'
    ORDER BY pp.progress_percent DESC
    LIMIT 5
")->fetchAll();

$statusColors = [
    'pending'     => 'warning',
    'approved'    => 'success',
    'rescheduled' => 'info',
    'cancelled'   => 'danger',
];

require_once '../includes/admin_head.php';
?>
</head>
<body>

<?php require_once '../includes/sidebar.php'; ?>

<div id="main-wrapper">

    <!-- Topbar -->
    <div id="topbar">
        <div class="welcome">Welcome back, <span><?= htmlspecialchars($_SESSION['name']) ?></span></div>
        <input type="search" class="search-input" placeholder="&#128269; Search bookings, clients...">
    </div>

    <!-- Content -->
    <div class="p-4">

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-white">
                    <div class="icon-box" style="background:#fff3cd;">
                        <i class="bi bi-calendar-day" style="color:var(--gold);"></i>
                    </div>
                    <div>
                        <div class="count"><?= (int)$totalToday ?></div>
                        <div class="label">Bookings Today</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-white">
                    <div class="icon-box" style="background:#fff3e0;">
                        <i class="bi bi-hourglass-split" style="color:#fd7e14;"></i>
                    </div>
                    <div>
                        <div class="count"><?= (int)$pendingCount ?></div>
                        <div class="label">Pending Approvals</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-white">
                    <div class="icon-box" style="background:#e8f4fd;">
                        <i class="bi bi-film" style="color:#0d6efd;"></i>
                    </div>
                    <div>
                        <div class="count"><?= (int)$activePostProd ?></div>
                        <div class="label">Active Post-Production</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card bg-white">
                    <div class="icon-box" style="background:#e8f5e9;">
                        <i class="bi bi-people-fill" style="color:#198754;"></i>
                    </div>
                    <div>
                        <div class="count"><?= (int)$totalClients ?></div>
                        <div class="label">Total Clients</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            <!-- Left: Recent Bookings + Calendar -->
            <div class="col-lg-8">

                <!-- Recent Bookings -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
                        <h6 class="mb-0 fw-semibold">Recent Bookings</h6>
                        <a href="bookings.php" class="btn btn-sm text-white" style="background:var(--gold);font-size:.78rem;">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size:.85rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th><th>Client</th><th>Package</th><th>Date</th><th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($recentBookings)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-3">No bookings yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentBookings as $b): ?>
                                    <tr>
                                        <td><?= (int)$b['id'] ?></td>
                                        <td><?= htmlspecialchars($b['client']) ?></td>
                                        <td><?= htmlspecialchars($b['package']) ?></td>
                                        <td><?= htmlspecialchars($b['booking_date']) ?></td>
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

                <!-- Calendar -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3">
                        <h6 class="mb-0 fw-semibold">
                            Schedule — <?= date('F Y', mktime(0, 0, 0, $calMonth, 1, $calYear)) ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="cal-grid">
                            <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                                <div class="day-header"><?= $d ?></div>
                            <?php endforeach; ?>

                            <?php for ($i = 0; $i < $firstDayOfWeek; $i++): ?>
                                <div class="cal-day"></div>
                            <?php endfor; ?>

                            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                                $isToday  = ($day === (int)date('j') && $calMonth === (int)date('n'));
                                $isBooked = in_array($day, $bookedDays);
                                $cls = 'cal-day' . ($isBooked ? ' booked' : '') . ($isToday ? ' today' : '');
                            ?>
                                <div class="<?= $cls ?>" title="<?= $isBooked ? 'Booked' : '' ?>">
                                    <?= $day ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div class="mt-3 d-flex gap-3" style="font-size:.78rem;">
                            <span><span style="display:inline-block;width:12px;height:12px;background:var(--gold);border-radius:3px;"></span> Booked</span>
                            <span><span style="display:inline-block;width:12px;height:12px;border:2px solid var(--gold);border-radius:3px;"></span> Today</span>
                        </div>
                    </div>
                </div>

            </div><!-- /col-lg-8 -->

            <!-- Right: Post-Production -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
                        <h6 class="mb-0 fw-semibold">Post-Production</h6>
                        <a href="post_production.php" class="btn btn-sm text-white" style="background:var(--gold);font-size:.78rem;">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($postProjects)): ?>
                            <p class="text-muted text-center small pt-3">No active projects.</p>
                        <?php else: ?>
                            <?php foreach ($postProjects as $proj):
                                $pct      = min(100, max(0, (int)$proj['progress_percent']));
                                $barColor = $pct < 40 ? 'danger' : ($pct < 75 ? 'warning' : 'success');
                                $allDone  = $proj['photo_status'] === 'completed'
                                         && $proj['video_status'] === 'completed'
                                         && $proj['other_status'] === 'completed';
                                $statusLabel = $allDone ? 'Completed' : 'In Progress';
                            ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold" style="font-size:.85rem;">
                                        <?= htmlspecialchars($proj['title']) ?>
                                    </span>
                                    <span class="badge bg-secondary" style="font-size:.7rem;">
                                        <?= $statusLabel ?>
                                    </span>
                                </div>
                                <div class="progress" style="height:8px;">
                                    <div class="progress-bar bg-<?= $barColor ?>"
                                         role="progressbar"
                                         style="width:<?= $pct ?>%"
                                         aria-valuenow="<?= $pct ?>"
                                         aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <div class="text-end mt-1" style="font-size:.75rem;color:#6c757d;"><?= $pct ?>%</div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div><!-- /row -->
    </div><!-- /content -->
</div><!-- /main-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
