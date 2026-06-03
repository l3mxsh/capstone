<?php
require_once '../auth/client_guard.php';
require_once '../config/db.php';

$clientId = (int)$_SESSION['user_id'];

$stmtBooking = $pdo->prepare("SELECT b.*, p.name AS package, p.price FROM bookings b JOIN packages p ON b.package_id=p.id WHERE b.client_id=? ORDER BY b.created_at DESC LIMIT 1");
$stmtBooking->execute([$clientId]);
$latestBooking = $stmtBooking->fetch();

$stmtPP = $pdo->prepare("SELECT pp.photo_status,pp.video_status,pp.other_status,pp.progress_percent,pp.deadline_status,pp.deadline,pp.notes,pp.drive_link,b.booking_date,p.name AS package FROM post_production pp JOIN bookings b ON pp.booking_id=b.id JOIN packages p ON b.package_id=p.id WHERE b.client_id=? AND b.status='approved' ORDER BY b.booking_date DESC LIMIT 1");
$stmtPP->execute([$clientId]);
$postProd = $stmtPP->fetch();

$stmtCounts = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status='pending') AS pending, SUM(status='approved') AS approved, SUM(status='cancelled') AS cancelled FROM bookings WHERE client_id=?");
$stmtCounts->execute([$clientId]);
$counts = $stmtCounts->fetch();

// Latest invoice
$stmtInv = $pdo->prepare("SELECT i.*, p.name AS package, b.booking_date FROM invoices i JOIN bookings b ON i.booking_id=b.id JOIN packages p ON b.package_id=p.id WHERE i.client_id=? ORDER BY i.issued_date DESC LIMIT 1");
$stmtInv->execute([$clientId]);
$latestInvoice = $stmtInv->fetch();

$statusBadge   = ['pending' => 'warning', 'approved' => 'success', 'rescheduled' => 'info', 'cancelled' => 'danger'];
$ppStatusLabel = ['not_started' => 'Not Started', 'in_progress' => 'In Progress', 'completed' => 'Completed'];
$ppStatusColor = ['not_started' => 'secondary', 'in_progress' => 'primary', 'completed' => 'success'];
$dlBadge       = ['early' => 'success', 'near' => 'warning', 'late' => 'danger'];
$dlLabel       = ['early' => 'On Track', 'near' => 'Due Soon', 'late' => 'Overdue'];
$invBadge      = ['unpaid' => 'danger', 'partial' => 'warning', 'paid' => 'success'];

$initials = strtoupper(substr($_SESSION['name'], 0, 1));
$pageTitle = 'Dashboard';
$activeClientPage = 'dashboard';
require_once '../includes/client_head.php';
$_clientAvatar  ??= null;
$_clientInitial ??= strtoupper(substr($_SESSION['name'], 0, 1));
?>
</head>

<body>
    <?php require_once '../includes/client_sidebar.php'; ?>

    <div id="client-main">
        <div id="client-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="topbar-btn d-lg-none border-0" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
                <div>
                    <div class="topbar-title">My Portal</div>
                    <div class="topbar-sub"><?= date('l, F j, Y') ?></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="d-none d-sm-inline" style="font-size:.83rem;color:#888;"><?= htmlspecialchars($_SESSION['name']) ?></span>
                <?php if ($_clientAvatar): ?><img src="../assets/avatars/<?= htmlspecialchars($_clientAvatar) ?>" class="topbar-avatar" style="object-fit:cover;"><?php else: ?><div class="topbar-avatar"><?= $_clientInitial ?></div><?php endif; ?>
                <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>

        <div class="p-3 p-md-4">

            <!-- Welcome Banner -->
            <div class="welcome-banner mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?></h5>
                    <p class="mb-0 opacity-50" style="font-size:.875rem;">Manage your bookings and track production progress.</p>
                </div>
                <a href="booking_create.php" class="btn btn-sm fw-semibold px-4"
                    style="background:#fff;color:#111;border:none;border-radius:8px;">
                    <i class="bi bi-plus-lg me-1"></i> Book a Service
                </a>
            </div>

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">
                <?php
                $cards = [
                    ['label' => 'Total Bookings', 'value' => $counts['total'],     'icon' => 'bi-calendar2'],
                    ['label' => 'Pending',       'value' => $counts['pending'],   'icon' => 'bi-hourglass-split'],
                    ['label' => 'Approved',      'value' => $counts['approved'],  'icon' => 'bi-check-circle'],
                    ['label' => 'Cancelled',     'value' => $counts['cancelled'], 'icon' => 'bi-x-circle'],
                ];
                foreach ($cards as $c):
                ?>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="icon-wrap"><i class="bi <?= $c['icon'] ?>"></i></div>
                            <div>
                                <div class="count"><?= (int)$c['value'] ?></div>
                                <div class="label"><?= $c['label'] ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-4">
                <!-- Left col -->
                <div class="col-lg-7">

                    <!-- Latest Booking -->
                    <div class="dash-card mb-4">
                        <div class="dash-card-header">
                            <h6>Latest Booking</h6>
                            <a href="bookings.php" class="btn btn-sm btn-outline-secondary" style="font-size:.76rem;">All Bookings</a>
                        </div>
                        <div class="dash-card-body">
                            <?php if (!$latestBooking): ?>
                                <div class="text-center py-3">
                                    <i class="bi bi-calendar-x fs-2 opacity-25 d-block mb-2"></i>
                                    <p class="mb-0 small text-muted">No bookings yet. <a href="booking_create.php" class="text-dark fw-semibold">Book now →</a></p>
                                </div>
                            <?php else: ?>
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($latestBooking['package']) ?></div>
                                        <div style="font-size:.78rem;color:#888;"><?= htmlspecialchars($latestBooking['event_type'] ?? 'Event') ?></div>
                                    </div>
                                    <span class="badge bg-<?= $statusBadge[$latestBooking['status']] ?? 'secondary' ?>"><?= ucfirst($latestBooking['status']) ?></span>
                                </div>
                                <div class="row g-2">
                                    <?php
                                    $fields = [
                                        ['Booking Date', $latestBooking['booking_date']],
                                        ['Venue', $latestBooking['venue'] ?? '—'],
                                        ['Package Price', '₱' . number_format((float)$latestBooking['price'], 2)],
                                        ['Booked On', date('M d, Y', strtotime($latestBooking['created_at']))],
                                    ];
                                    foreach ($fields as [$lbl, $val]):
                                    ?>
                                        <div class="col-6">
                                            <div class="info-box">
                                                <div class="info-label"><?= $lbl ?></div>
                                                <div class="fw-semibold mt-1"><?= htmlspecialchars($val) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Post-Production -->
                    <div class="dash-card mb-4">
                        <div class="dash-card-header">
                            <h6>Post-Production Status</h6>
                            <a href="post_production.php" class="btn btn-sm btn-outline-secondary" style="font-size:.76rem;">Details</a>
                        </div>
                        <div class="dash-card-body">
                            <?php if (!$postProd): ?>
                                <div class="text-center py-3">
                                    <i class="bi bi-film fs-2 opacity-25 d-block mb-2"></i>
                                    <p class="mb-0 small text-muted">Appears once your booking is approved.</p>
                                </div>
                            <?php else:
                                $pct = min(100, max(0, (int)$postProd['progress_percent']));
                                $barClass = $pct < 40 ? 'bg-danger' : ($pct < 75 ? 'bg-warning' : 'bg-success');
                                $dl = $postProd['deadline_status'] ?? 'early';
                            ?>
                                <div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
                                    <span class="fw-semibold"><?= htmlspecialchars($postProd['package']) ?></span>
                                    <span class="badge bg-<?= $dlBadge[$dl] ?>"><?= $dlLabel[$dl] ?><?= $postProd['deadline'] ? ' · ' . htmlspecialchars($postProd['deadline']) : '' ?></span>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;">
                                        <span style="color:#888;">Overall Progress</span>
                                        <span class="fw-bold"><?= $pct ?>%</span>
                                    </div>
                                    <div class="progress" style="height:8px;">
                                        <div class="progress-bar <?= $barClass ?>" role="progressbar" style="width:<?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <?php foreach (['photo_status' => ['Photography', 'bi-camera'], 'video_status' => ['Videography', 'bi-camera-video'], 'other_status' => ['Other', 'bi-box']] as $key => [$lbl, $icon]): $val = $postProd[$key] ?? 'not_started'; ?>
                                        <div class="col-4">
                                            <div class="info-box text-center">
                                                <i class="bi <?= $icon ?> d-block mb-1" style="font-size:1.1rem;"></i>
                                                <div style="font-size:.68rem;color:#aaa;"><?= $lbl ?></div>
                                                <span class="badge bg-<?= $ppStatusColor[$val] ?> mt-1" style="font-size:.62rem;"><?= $ppStatusLabel[$val] ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($postProd['drive_link']): ?>
                                    <a href="<?= htmlspecialchars($postProd['drive_link']) ?>" target="_blank" rel="noopener"
                                        class="btn btn-dark btn-sm fw-semibold w-100" style="border-radius:8px;">
                                        <i class="bi bi-cloud-download me-1"></i> Download My Files
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Latest Invoice / Payment -->
                    <?php if ($latestInvoice): ?>
                        <div class="dash-card">
                            <div class="dash-card-header">
                                <h6>Latest Invoice</h6>
                                <a href="invoices.php" class="btn btn-sm btn-outline-secondary" style="font-size:.76rem;">All Invoices</a>
                            </div>
                            <div class="dash-card-body">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($latestInvoice['package']) ?></div>
                                        <div style="font-size:.78rem;color:#888;"><?= htmlspecialchars($latestInvoice['booking_date']) ?></div>
                                    </div>
                                    <span class="badge bg-<?= $invBadge[$latestInvoice['status']] ?? 'secondary' ?>"><?= ucfirst($latestInvoice['status']) ?></span>
                                </div>
                                <div class="row g-2">
                                    <div class="col-4">
                                        <div class="info-box text-center">
                                            <div class="info-label">Total</div>
                                            <div class="fw-bold mt-1">₱<?= number_format((float)$latestInvoice['amount'], 2) ?></div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="info-box text-center">
                                            <div class="info-label">Paid</div>
                                            <div class="fw-bold mt-1 text-success">₱<?= number_format((float)$latestInvoice['deposit_paid'], 2) ?></div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="info-box text-center">
                                            <div class="info-label">Balance</div>
                                            <div class="fw-bold mt-1 <?= (float)$latestInvoice['balance'] > 0 ? 'text-danger' : '' ?>">₱<?= number_format((float)$latestInvoice['balance'], 2) ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php if ((float)$latestInvoice['balance'] > 0): ?>
                                    <div class="mt-3 p-3 rounded-2" style="background:#f9f9f9;border:1px solid #e8e8e8;font-size:.82rem;">
                                        <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>How to Pay</div>
                                        <div class="text-muted">Please pay your remaining balance via GCash or bank transfer and contact the studio to confirm. Bring your receipt on your event day.</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Right col -->
                <div class="col-lg-5">

                    <!-- Quick Links -->
                    <div class="dash-card mb-4">
                        <div class="dash-card-header">
                            <h6>Quick Links</h6>
                        </div>
                        <div class="dash-card-body">
                            <div class="row g-2">
                                <?php
                                $links = [
                                    ['Book a Service',     'bi-plus-circle',   'booking_create.php', ''],
                                    ['My Bookings',        'bi-calendar-check', 'bookings.php',        ''],
                                    ['Invoices & Payment', 'bi-receipt',       'invoices.php',        ''],
                                    ['Download Files',     'bi-cloud-download', 'downloads.php',       ''],
                                    ['Production Status',  'bi-film',          'post_production.php', ''],
                                    ['Logout',             'bi-box-arrow-right', '../logout.php',      'danger-btn'],
                                ];
                                foreach ($links as [$lbl, $ico, $href, $cls]):
                                ?>
                                    <div class="col-6">
                                        <a href="<?= $href ?>" class="quick-btn <?= $cls ?> w-100">
                                            <i class="bi <?= $ico ?>"></i>
                                            <?= $lbl ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <h6>Contact the Studio</h6>
                        </div>
                        <div class="dash-card-body">
                            <div class="d-flex flex-column gap-3" style="font-size:.875rem;">
                                <?php
                                $contacts = [
                                    ['bi-geo-alt',  'Location', 'Brgy. San Antonio, Biñan, Laguna', '', ''],
                                    ['bi-envelope', 'Email',    'info@harvymancefilms.com', 'mailto:info@harvymancefilms.com', ''],
                                    ['bi-facebook', 'Facebook', 'Harvy Mance Films', '', ''],
                                ];
                                foreach ($contacts as [$icon, $label, $text, $href, $_]):
                                ?>
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="avatar-sm"><i class="bi <?= $icon ?>"></i></div>
                                        <div>
                                            <div class="info-label"><?= $label ?></div>
                                            <?php if ($href): ?>
                                                <a href="<?= $href ?>" class="d-block text-decoration-none text-dark fw-medium mt-1"><?= htmlspecialchars($text) ?></a>
                                            <?php else: ?>
                                                <div class="fw-medium mt-1"><?= htmlspecialchars($text) ?></div>
                                            <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('client-sidebar').classList.toggle('show'));
    </script>
</body>

</html>