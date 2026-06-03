<?php
require_once '../auth/client_guard.php';
require_once '../config/db.php';

$clientId = (int)$_SESSION['user_id'];

// Fetch all post-production projects that have a drive link
$stmt = $pdo->prepare("
    SELECT pp.drive_link, pp.photo_status, pp.video_status, pp.other_status,
           pp.progress_percent, pp.updated_at,
           p.name AS package, b.booking_date, b.event_type, b.venue
    FROM post_production pp
    JOIN bookings b ON pp.booking_id = b.id
    JOIN packages p ON b.package_id  = p.id
    WHERE b.client_id = ?
      AND b.status    = 'approved'
    ORDER BY b.booking_date DESC
");
$stmt->execute([$clientId]);
$projects = $stmt->fetchAll();

// Separate: ready (has drive_link) vs pending
$ready   = array_filter($projects, fn($r) => !empty($r['drive_link']));
$pending = array_filter($projects, fn($r) =>  empty($r['drive_link']));

$ppStatusLabel = ['not_started' => 'Not Started', 'in_progress' => 'In Progress', 'completed' => 'Completed'];
$ppStatusColor = ['not_started' => 'secondary',   'in_progress' => 'primary',     'completed' => 'success'];

$initials         = strtoupper(substr($_SESSION['name'], 0, 1));
$pageTitle        = 'Download Files — Client Portal';
$activeClientPage = 'downloads';
require_once '../includes/client_head.php';
?>
</head>
<body>
<?php require_once '../includes/client_sidebar.php'; ?>

<div id="client-main">

    <!-- Topbar -->
    <div id="client-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="topbar-btn d-lg-none border-0" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div>
                <div class="topbar-title">Download Files</div>
                <div class="topbar-sub">Access your completed photos and videos</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($_clientAvatar): ?><img src="../assets/avatars/<?= htmlspecialchars($_clientAvatar) ?>" class="topbar-avatar" style="object-fit:cover;"><?php else: ?><div class="topbar-avatar"><?= $_clientInitial ?></div><?php endif; ?>
            <a href="../logout.php" class="topbar-btn" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>

    <div class="p-3 p-md-4">

        <!-- Info banner -->
        <div class="info-box mb-4 d-flex gap-3 align-items-start">
            <i class="bi bi-info-circle flex-shrink-0 mt-1" style="font-size:1.1rem;color:#555;"></i>
            <div style="font-size:.82rem;color:#555;">
                <strong class="text-dark">Your files are stored on Google Drive.</strong>
                <span class="ms-1">Click <strong>Download</strong> on any completed project to open your folder. Make sure you are signed in to Google to access the files.</span>
            </div>
        </div>

        <!-- ── Ready to Download ── -->
        <?php if (empty($ready) && empty($pending)): ?>

            <div class="dash-card">
                <div class="dash-card-body text-center py-5">
                    <i class="bi bi-cloud-slash d-block fs-1 mb-3 opacity-25"></i>
                    <h6 class="fw-semibold" style="color:#888;">No files available yet.</h6>
                    <p class="text-muted small mb-4">
                        Download links will appear here once your post-production is complete.
                    </p>
                    <a href="booking_create.php" class="btn btn-dark btn-sm px-4">
                        <i class="bi bi-plus-lg me-1"></i> Book a Service
                    </a>
                </div>
            </div>

        <?php else: ?>

            <?php if (!empty($ready)): ?>
            <div class="dash-card mb-4">
                <div class="dash-card-header">
                    <h6><i class="bi bi-cloud-download me-2"></i>Ready to Download
                        <span class="badge bg-success ms-2"><?= count($ready) ?></span>
                    </h6>
                </div>
                <div class="dash-card-body">
                    <div class="row g-3">
                        <?php foreach ($ready as $proj):
                            $pct = min(100, max(0, (int)$proj['progress_percent']));
                            $isComplete = $proj['photo_status'] === 'completed'
                                       && $proj['video_status'] === 'completed'
                                       && $proj['other_status'] === 'completed';
                        ?>
                        <div class="col-md-6">
                            <div class="file-card">
                                <div class="file-icon">
                                    <i class="bi bi-folder2-open"></i>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate" style="font-size:.875rem;">
                                        <?= htmlspecialchars($proj['package']) ?>
                                    </div>
                                    <div style="font-size:.74rem;color:#888;">
                                        <i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars($proj['booking_date']) ?>
                                        <?php if ($proj['event_type']): ?>
                                            &nbsp;·&nbsp; <?= htmlspecialchars($proj['event_type']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                                        <?php if ($isComplete): ?>
                                            <span class="badge bg-success" style="font-size:.68rem;">
                                                <i class="bi bi-check2-all me-1"></i>Complete
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" style="font-size:.68rem;">
                                                <?= $pct ?>% done
                                            </span>
                                        <?php endif; ?>
                                        <span style="font-size:.72rem;color:#aaa;">
                                            Updated <?= date('M d, Y', strtotime($proj['updated_at'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <a href="<?= htmlspecialchars($proj['drive_link']) ?>"
                                   target="_blank" rel="noopener"
                                   class="btn btn-dark btn-sm flex-shrink-0"
                                   style="border-radius:8px;font-size:.78rem;">
                                    <i class="bi bi-cloud-download me-1"></i>Download
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Pending Projects ── -->
            <?php if (!empty($pending)): ?>
            <div class="dash-card">
                <div class="dash-card-header">
                    <h6><i class="bi bi-hourglass-split me-2"></i>In Progress
                        <span class="badge bg-secondary ms-2"><?= count($pending) ?></span>
                    </h6>
                </div>
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>Package</th>
                                <th>Event Date</th>
                                <th>Photo</th>
                                <th>Video</th>
                                <th>Other</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pending as $proj):
                            $pct = min(100, max(0, (int)$proj['progress_percent']));
                            $barClass = $pct < 40 ? 'bg-danger' : ($pct < 75 ? 'bg-warning' : 'bg-success');
                            $isComplete = $proj['photo_status'] === 'completed'
                                       && $proj['video_status'] === 'completed'
                                       && $proj['other_status'] === 'completed';
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($proj['package']) ?></td>
                            <td><?= htmlspecialchars($proj['booking_date']) ?></td>
                            <?php foreach (['photo_status','video_status','other_status'] as $col): ?>
                            <td>
                                <span class="badge bg-<?= $ppStatusColor[$proj[$col]] ?? 'secondary' ?>"
                                      style="font-size:.67rem;">
                                    <?= $ppStatusLabel[$proj[$col]] ?? $proj[$col] ?>
                                </span>
                            </td>
                            <?php endforeach; ?>
                            <td style="min-width:110px;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:6px;">
                                        <div class="progress-bar <?= $barClass ?>"
                                             role="progressbar"
                                             style="width:<?= $pct ?>%"
                                             aria-valuenow="<?= $pct ?>"
                                             aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <span style="font-size:.72rem;font-weight:700;width:30px;text-align:right;">
                                        <?= $pct ?>%
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php if ($isComplete): ?>
                                    <span class="badge bg-success" style="font-size:.68rem;">
                                        <i class="bi bi-hourglass me-1"></i>Link Pending
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary" style="font-size:.68rem;">
                                        <i class="bi bi-clock me-1"></i>In Production
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        document.getElementById('client-sidebar').classList.toggle('show');
    });
</script>
</body>
</html>
