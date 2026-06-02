<?php
require_once '../auth/client_guard.php';
require_once '../config/db.php';

$clientId = (int)$_SESSION['user_id'];

// ── Latest booking ─────────────────────────────────────────────
$stmtBooking = $pdo->prepare("
    SELECT b.*, p.name AS package, p.price
    FROM bookings b
    JOIN packages p ON b.package_id = p.id
    WHERE b.client_id = ?
    ORDER BY b.created_at DESC LIMIT 1
");
$stmtBooking->execute([$clientId]);
$latestBooking = $stmtBooking->fetch();

// ── Post-production for latest approved booking ────────────────
$stmtPP = $pdo->prepare("
    SELECT pp.photo_status, pp.video_status, pp.other_status,
           pp.progress_percent, pp.deadline_status, pp.deadline,
           pp.notes, pp.drive_link, b.booking_date, p.name AS package
    FROM post_production pp
    JOIN bookings b  ON pp.booking_id = b.id
    JOIN packages p  ON b.package_id  = p.id
    WHERE b.client_id = ? AND b.status = 'approved'
    ORDER BY b.booking_date DESC LIMIT 1
");
$stmtPP->execute([$clientId]);
$postProd = $stmtPP->fetch();

// ── Booking counts ─────────────────────────────────────────────
$stmtCounts = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'pending')  AS pending,
        SUM(status = 'approved') AS approved,
        SUM(status = 'cancelled') AS cancelled
    FROM bookings WHERE client_id = ?
");
$stmtCounts->execute([$clientId]);
$counts = $stmtCounts->fetch();

// ── Helpers ────────────────────────────────────────────────────
$statusColors  = ['pending'=>'warning','approved'=>'success','rescheduled'=>'info','cancelled'=>'danger'];
$ppStatusLabel = ['not_started'=>'Not Started','in_progress'=>'In Progress','completed'=>'Completed'];
$ppStatusColor = ['not_started'=>'secondary','in_progress'=>'primary','completed'=>'success'];
$dlBadge       = ['early'=>'success','near'=>'warning','late'=>'danger'];
$dlLabel       = ['early'=>'On Track','near'=>'Due Soon','late'=>'Overdue'];

$initials = strtoupper(substr($_SESSION['name'], 0, 1));

$pageTitle       = 'Dashboard — Client Portal';
$activeClientPage = 'dashboard';

require_once '../includes/client_head.php';
?>
</head>
<body>

<?php require_once '../includes/client_sidebar.php'; ?>

<div id="client-main">

    <!-- Topbar -->
    <div id="client-topbar">
        <span class="page-label">My Portal</span>
        <div class="user-pill">
            <div class="avatar"><?= htmlspecialchars($initials) ?></div>
            <span><?= htmlspecialchars($_SESSION['name']) ?></span>
            <a href="../logout.php" class="btn btn-sm btn-outline-secondary py-0 px-2 ms-2"
               style="font-size:.78rem;">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <!-- Content -->
    <div class="p-4">

        <!-- ── Welcome Banner ─────────────────────────────── -->
        <div class="welcome-banner mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h4 class="mb-1 fw-bold">
                        Welcome back, <span class="gold"><?= htmlspecialchars($_SESSION['name']) ?>!</span>
                    </h4>
                    <p class="mb-0 text-white-50" style="font-size:.9rem;">
                        Manage your bookings and track your post-production progress here.
                    </p>
                </div>
                <a href="bookings.php" class="btn btn-sm fw-semibold"
                   style="background:var(--gold);color:#1a1a2e;border:none;">
                    <i class="bi bi-plus-lg"></i> Book a Service
                </a>
            </div>
        </div>

        <!-- ── Booking Count Cards ────────────────────────── -->
        <div class="row g-3 mb-4">
            <?php
            $statCards = [
                ['label'=>'Total Bookings',  'value'=>$counts['total'],     'icon'=>'bi-calendar2',       'bg'=>'#fff3cd','ic'=>'var(--gold)'],
                ['label'=>'Pending',         'value'=>$counts['pending'],   'icon'=>'bi-hourglass-split', 'bg'=>'#fff3e0','ic'=>'#fd7e14'],
                ['label'=>'Approved',        'value'=>$counts['approved'],  'icon'=>'bi-check-circle',    'bg'=>'#e8f5e9','ic'=>'#198754'],
                ['label'=>'Cancelled',       'value'=>$counts['cancelled'], 'icon'=>'bi-x-circle',        'bg'=>'#fdecea','ic'=>'#dc3545'],
            ];
            foreach ($statCards as $sc):
            ?>
            <div class="col-6 col-xl-3">
                <div class="portal-card p-3 d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;border-radius:10px;background:<?= $sc['bg'] ?>;
                                display:flex;align-items:center;justify-content:center;
                                font-size:1.3rem;flex-shrink:0;">
                        <i class="bi <?= $sc['icon'] ?>" style="color:<?= $sc['ic'] ?>;"></i>
                    </div>
                    <div>
                        <div style="font-size:1.6rem;font-weight:700;line-height:1;">
                            <?= (int)$sc['value'] ?>
                        </div>
                        <div style="font-size:.75rem;color:#6c757d;"><?= $sc['label'] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">

            <!-- ── Left col ───────────────────────────────── -->
            <div class="col-lg-7">

                <!-- Latest Booking Card -->
                <div class="portal-card p-4 mb-4">
                    <div class="section-title">Latest Booking</div>
                    <?php if (!$latestBooking): ?>
                        <p class="text-muted small mb-0">You have no bookings yet.</p>
                    <?php else: ?>
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                            <div>
                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($latestBooking['package']) ?></h6>
                                <span class="text-muted" style="font-size:.82rem;">
                                    <?= htmlspecialchars($latestBooking['event_type'] ?? 'Event') ?>
                                </span>
                            </div>
                            <span class="badge bg-<?= $statusColors[$latestBooking['status']] ?? 'secondary' ?> status-badge-lg">
                                <?= ucfirst($latestBooking['status']) ?>
                            </span>
                        </div>
                        <div class="row g-2" style="font-size:.85rem;">
                            <div class="col-6">
                                <div class="text-muted" style="font-size:.72rem;">BOOKING DATE</div>
                                <div class="fw-semibold"><?= htmlspecialchars($latestBooking['booking_date']) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted" style="font-size:.72rem;">VENUE</div>
                                <div class="fw-semibold"><?= htmlspecialchars($latestBooking['venue'] ?? '—') ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted" style="font-size:.72rem;">PACKAGE PRICE</div>
                                <div class="fw-semibold">₱<?= number_format((float)$latestBooking['price'], 2) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted" style="font-size:.72rem;">BOOKED ON</div>
                                <div class="fw-semibold">
                                    <?= date('M d, Y', strtotime($latestBooking['created_at'])) ?>
                                </div>
                            </div>
                            <?php if ($latestBooking['notes']): ?>
                            <div class="col-12">
                                <div class="text-muted" style="font-size:.72rem;">NOTES</div>
                                <div><?= htmlspecialchars($latestBooking['notes']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-3">
                            <a href="bookings.php" class="btn btn-sm btn-outline-secondary py-0 px-3"
                               style="font-size:.8rem;">
                                View All Bookings <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Post-Production Status -->
                <div class="portal-card p-4">
                    <div class="section-title">Post-Production Status</div>
                    <?php if (!$postProd): ?>
                        <p class="text-muted small mb-0">
                            No post-production project yet. This appears once your booking is approved.
                        </p>
                    <?php else:
                        $pct      = min(100, max(0, (int)$postProd['progress_percent']));
                        $barColor = $pct < 40 ? 'danger' : ($pct < 75 ? 'warning' : 'success');
                        $dl       = $postProd['deadline_status'] ?? 'early';
                    ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div style="font-size:.875rem;">
                                <span class="fw-semibold"><?= htmlspecialchars($postProd['package']) ?></span>
                                <span class="text-muted"> — <?= htmlspecialchars($postProd['booking_date']) ?></span>
                            </div>
                            <span class="badge bg-<?= $dlBadge[$dl] ?> status-badge-lg">
                                <?= $dlLabel[$dl] ?>
                                <?= $postProd['deadline'] ? '· ' . htmlspecialchars($postProd['deadline']) : '' ?>
                            </span>
                        </div>

                        <!-- Progress bar -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;">
                                <span class="text-muted">Overall Progress</span>
                                <span class="fw-semibold"><?= $pct ?>%</span>
                            </div>
                            <div class="progress progress-lg">
                                <div class="progress-bar bg-<?= $barColor ?>"
                                     style="width:<?= $pct ?>%"
                                     role="progressbar"
                                     aria-valuenow="<?= $pct ?>"
                                     aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                        </div>

                        <!-- Per-deliverable status -->
                        <div class="row g-2 mb-3">
                            <?php
                            $deliverables = [
                                'photo_status' => ['label'=>'Photography','icon'=>'bi-camera'],
                                'video_status' => ['label'=>'Videography', 'icon'=>'bi-camera-video'],
                                'other_status' => ['label'=>'Other',       'icon'=>'bi-box'],
                            ];
                            foreach ($deliverables as $key => $info):
                                $val = $postProd[$key] ?? 'not_started';
                            ?>
                            <div class="col-4">
                                <div class="text-center p-2 rounded" style="background:#f8f9fa;">
                                    <i class="bi <?= $info['icon'] ?> mb-1"
                                       style="font-size:1.1rem;color:var(--gold);display:block;"></i>
                                    <div style="font-size:.7rem;color:#6c757d;"><?= $info['label'] ?></div>
                                    <span class="badge bg-<?= $ppStatusColor[$val] ?> mt-1"
                                          style="font-size:.65rem;">
                                        <?= $ppStatusLabel[$val] ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($postProd['drive_link']): ?>
                        <a href="<?= htmlspecialchars($postProd['drive_link']) ?>"
                           target="_blank" rel="noopener"
                           class="btn btn-sm fw-semibold w-100"
                           style="background:var(--gold);color:#fff;border:none;font-size:.82rem;">
                            <i class="bi bi-cloud-download"></i> Download My Files
                        </a>
                        <?php endif; ?>

                        <?php if ($postProd['notes']): ?>
                        <div class="mt-3 p-2 rounded" style="background:#f8f9fa;font-size:.82rem;">
                            <span class="text-muted">Note from studio: </span>
                            <?= htmlspecialchars($postProd['notes']) ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div><!-- /col-lg-7 -->

            <!-- ── Right col: Quick Links ─────────────────── -->
            <div class="col-lg-5">
                <div class="portal-card p-4">
                    <div class="section-title">Quick Links</div>
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="bookings.php" class="quick-btn w-100">
                                <i class="bi bi-plus-circle"></i>
                                Book a Service
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="bookings.php" class="quick-btn w-100">
                                <i class="bi bi-calendar-check"></i>
                                My Bookings
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="invoices.php" class="quick-btn w-100">
                                <i class="bi bi-receipt"></i>
                                My Invoices
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="downloads.php" class="quick-btn w-100">
                                <i class="bi bi-cloud-download"></i>
                                Download Files
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="post_production.php" class="quick-btn w-100">
                                <i class="bi bi-film"></i>
                                Production Status
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="../logout.php" class="quick-btn w-100"
                               style="border-color:#f5c6cb;">
                                <i class="bi bi-box-arrow-right" style="color:#dc3545;"></i>
                                <span style="color:#dc3545;">Logout</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Studio contact card -->
                <div class="portal-card p-4 mt-4">
                    <div class="section-title">Contact the Studio</div>
                    <div style="font-size:.875rem;" class="d-flex flex-column gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-geo-alt" style="color:var(--gold);width:18px;"></i>
                            <span>Brgy. San Antonio, Biñan, Laguna</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-envelope" style="color:var(--gold);width:18px;"></i>
                            <a href="mailto:info@harvymancefilms.com"
                               class="text-decoration-none text-dark">
                                info@harvymancefilms.com
                            </a>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-facebook" style="color:var(--gold);width:18px;"></i>
                            <span>Harvy Mance Films</span>
                        </div>
                    </div>
                </div>

            </div><!-- /col-lg-5 -->

        </div><!-- /row -->
    </div><!-- /content -->
</div><!-- /client-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
