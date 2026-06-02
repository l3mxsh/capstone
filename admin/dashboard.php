<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

$pageTitle  = 'Admin Dashboard — Harvy Mance Films';
$activePage = 'dashboard';

$totalToday     = $pdo->query("SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$pendingCount   = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$activePostProd = $pdo->query("SELECT COUNT(*) FROM post_production WHERE photo_status != 'completed' OR video_status != 'completed' OR other_status != 'completed'")->fetchColumn();
$totalClients   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();

$recentBookings = $pdo->query("
    SELECT b.id, u.name AS client, p.name AS package, b.booking_date, b.status
    FROM bookings b JOIN users u ON b.client_id = u.id JOIN packages p ON b.package_id = p.id
    ORDER BY b.created_at DESC LIMIT 5
")->fetchAll();

$calYear        = (int)date('Y');
$calMonth       = (int)date('m');
$daysInMonth    = cal_days_in_month(CAL_GREGORIAN, $calMonth, $calYear);
$firstDayOfWeek = (int)date('w', mktime(0,0,0,$calMonth,1,$calYear));

$stmt = $pdo->prepare("SELECT DAY(booking_date) AS day FROM bookings WHERE MONTH(booking_date)=? AND YEAR(booking_date)=? AND status!='cancelled'");
$stmt->execute([$calMonth, $calYear]);
$bookedDays = array_column($stmt->fetchAll(), 'day');

$postProjects = $pdo->query("
    SELECT pp.progress_percent, pp.photo_status, pp.video_status, pp.other_status, u.name AS title
    FROM post_production pp JOIN bookings b ON pp.booking_id=b.id JOIN users u ON b.client_id=u.id
    WHERE pp.photo_status!='completed' OR pp.video_status!='completed' OR pp.other_status!='completed'
    ORDER BY pp.progress_percent DESC LIMIT 5
")->fetchAll();

$statusBadge = ['pending'=>'warning','approved'=>'success','rescheduled'=>'info','cancelled'=>'danger'];
$adminInitial = strtoupper(substr($_SESSION['name'], 0, 1));

require_once '../includes/admin_head.php';
?>
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>

<!-- Toast container -->
<div class="toast-container position-fixed top-0 end-0 p-3"></div>

<div id="main-wrapper">
    <div id="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="topbar-btn d-lg-none border-0" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div>
                <div class="topbar-title">Dashboard</div>
                <div class="topbar-sub"><?= date('l, F j, Y') ?></div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="topbar-search d-none d-md-block">
                <i class="bi bi-search"></i>
                <input type="search" placeholder="Search…">
            </div>
            <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
            <div class="d-flex align-items-center gap-2 ms-1">
                <div class="topbar-avatar"><?= htmlspecialchars($adminInitial) ?></div>
                <div class="d-none d-sm-block lh-sm">
                    <div style="font-size:.83rem;font-weight:600;"><?= htmlspecialchars($_SESSION['name']) ?></div>
                    <div style="font-size:.7rem;color:#888;">Administrator</div>
                </div>
            </div>
        </div>
    </div>

    <div class="p-3 p-md-4">

        <!-- Welcome Banner -->
        <div class="welcome-banner mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h5 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?> 👋</h5>
                <p class="mb-0 opacity-50" style="font-size:.875rem;">Here's your studio overview for today.</p>
            </div>
            <a href="bookings.php" class="btn btn-sm fw-semibold px-4"
               style="background:#fff;color:#111;border:none;border-radius:8px;">
                <i class="bi bi-plus-lg me-1"></i> New Booking
            </a>
        </div>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <?php
            $stats = [
                ['label'=>'Bookings Today',        'value'=>$totalToday,     'icon'=>'bi-calendar-day'],
                ['label'=>'Pending Approvals',     'value'=>$pendingCount,   'icon'=>'bi-hourglass-split'],
                ['label'=>'Active Post-Production','value'=>$activePostProd, 'icon'=>'bi-film'],
                ['label'=>'Total Clients',         'value'=>$totalClients,   'icon'=>'bi-people-fill'],
            ];
            foreach ($stats as $s):
            ?>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="icon-wrap"><i class="bi <?= $s['icon'] ?>"></i></div>
                    <div>
                        <div class="count"><?= (int)$s['value'] ?></div>
                        <div class="label"><?= $s['label'] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">
            <!-- Left col -->
            <div class="col-lg-8">

                <!-- Recent Bookings -->
                <div class="dash-card mb-4">
                    <div class="dash-card-header">
                        <h6>Recent Bookings</h6>
                        <a href="bookings.php" class="btn btn-dark btn-sm px-3" style="font-size:.78rem;">
                            View All <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table modern-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Client</th>
                                    <th>Package</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($recentBookings)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No bookings yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentBookings as $b): ?>
                                <tr>
                                    <td class="text-muted">#<?= (int)$b['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar-sm"><?= strtoupper(substr($b['client'],0,1)) ?></span>
                                            <span class="fw-semibold"><?= htmlspecialchars($b['client']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($b['package']) ?></td>
                                    <td><?= htmlspecialchars($b['booking_date']) ?></td>
                                    <td><span class="badge bg-<?= $statusBadge[$b['status']] ?? 'secondary' ?>"><?= ucfirst($b['status']) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Calendar -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h6><i class="bi bi-calendar3 me-2"></i><?= date('F Y', mktime(0,0,0,$calMonth,1,$calYear)) ?></h6>
                    </div>
                    <div class="dash-card-body">
                        <div class="cal-grid">
                            <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                                <div class="day-header"><?= $d ?></div>
                            <?php endforeach; ?>
                            <?php for ($i = 0; $i < $firstDayOfWeek; $i++): ?><div class="cal-day"></div><?php endfor; ?>
                            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                                $isToday  = ($day === (int)date('j') && $calMonth === (int)date('n'));
                                $isBooked = in_array($day, $bookedDays);
                                $cls = 'cal-day' . ($isBooked?' booked':'') . ($isToday?' today':'');
                            ?>
                                <div class="<?= $cls ?>"><?= $day ?></div>
                            <?php endfor; ?>
                        </div>
                        <div class="mt-3 d-flex gap-4" style="font-size:.74rem;color:#888;">
                            <span class="d-flex align-items-center gap-2">
                                <span style="width:11px;height:11px;background:#111;border-radius:3px;display:inline-block;"></span> Booked
                            </span>
                            <span class="d-flex align-items-center gap-2">
                                <span style="width:11px;height:11px;border:2px solid #111;border-radius:3px;display:inline-block;"></span> Today
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right col: Post-Production -->
            <div class="col-lg-4">
                <div class="dash-card h-100">
                    <div class="dash-card-header">
                        <h6>Post-Production</h6>
                        <a href="post_production.php" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem;">View All</a>
                    </div>
                    <div class="dash-card-body">
                        <?php if (empty($postProjects)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-check-circle fs-2 text-muted opacity-50"></i>
                                <p class="mt-2 mb-0 small text-muted">No active projects.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($postProjects as $proj):
                                $pct = min(100, max(0, (int)$proj['progress_percent']));
                                $barClass = $pct < 40 ? 'bg-danger' : ($pct < 75 ? 'bg-warning' : 'bg-success');
                            ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold" style="font-size:.84rem;"><?= htmlspecialchars($proj['title']) ?></span>
                                    <span style="font-size:.75rem;font-weight:700;color:#555;"><?= $pct ?>%</span>
                                </div>
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar <?= $barClass ?>" role="progressbar"
                                         style="width:<?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('show');
    });
</script>
</body>
</html>
