<?php
require_once '../auth/client_guard.php';
require_once '../config/db.php';

$clientId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT pp.photo_status,pp.video_status,pp.other_status,pp.progress_percent,pp.deadline,pp.deadline_status,pp.notes,pp.drive_link,b.booking_date,b.event_type,p.name AS package FROM post_production pp JOIN bookings b ON pp.booking_id=b.id JOIN packages p ON b.package_id=p.id WHERE b.client_id=? AND b.status='approved' ORDER BY b.booking_date DESC");
$stmt->execute([$clientId]); $projects = $stmt->fetchAll();

$ppStatusLabel = ['not_started'=>'Not Started','in_progress'=>'In Progress','completed'=>'Completed'];
$ppStatusColor = ['not_started'=>'secondary','in_progress'=>'primary','completed'=>'success'];
$ppStatusIcon  = ['not_started'=>'bi-clock','in_progress'=>'bi-arrow-repeat','completed'=>'bi-check-circle-fill'];
$dlBadge = ['early'=>'success','near'=>'warning','late'=>'danger'];
$dlLabel = ['early'=>'On Track','near'=>'Due Soon','late'=>'Overdue'];

$initials = strtoupper(substr($_SESSION['name'],0,1));
$pageTitle = 'Post-Production';
$activeClientPage = 'post_production';
require_once '../includes/client_head.php';
?>
</head>
<body>
<?php require_once '../includes/client_sidebar.php'; ?>

<div id="client-main">
    <div id="client-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="topbar-btn d-lg-none border-0" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <div>
                <div class="topbar-title">Post-Production</div>
                <div class="topbar-sub">Track your editing and delivery progress</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="topbar-avatar"><?= htmlspecialchars($initials) ?></div>
            <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>

    <div class="p-3 p-md-4">

        <!-- Info Bar -->
        <div class="info-box mb-4 d-flex gap-3 align-items-start">
            <i class="bi bi-info-circle flex-shrink-0 mt-1" style="font-size:1.1rem;color:#555;"></i>
            <div style="font-size:.82rem;color:#555;">
                <strong class="text-dark">How to read your status:</strong>
                <span class="ms-1"><strong>Not Started</strong> — work hasn't begun. &nbsp;|&nbsp; <strong>In Progress</strong> — files are being edited. &nbsp;|&nbsp; <strong>Completed</strong> — ready for delivery. You'll receive an email and a download link once all deliverables are done.</span>
            </div>
        </div>

        <?php if (empty($projects)): ?>
        <div class="dash-card">
            <div class="dash-card-body text-center py-5">
                <i class="bi bi-film d-block fs-1 mb-3 opacity-25"></i>
                <h6 class="fw-semibold" style="color:#888;">No post-production projects yet.</h6>
                <p class="text-muted small mb-4">This page will show your project progress once your booking is approved.</p>
                <a href="booking_create.php" class="btn btn-dark btn-sm px-4"><i class="bi bi-plus-lg me-1"></i>Book a Service</a>
            </div>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($projects as $proj):
                $pct = min(100,max(0,(int)$proj['progress_percent']));
                $barClass = $pct < 40 ? 'bg-danger' : ($pct < 75 ? 'bg-warning' : 'bg-success');
                $dl = $proj['deadline_status']??'early';
                $isComplete = $proj['photo_status']==='completed' && $proj['video_status']==='completed' && $proj['other_status']==='completed';
            ?>
            <div class="col-lg-6">
                <div class="dash-card">
                    <!-- Top accent line -->
                    <div style="height:3px;background:<?= $isComplete?'#27ae60':($dl==='late'?'#c0392b':($dl==='near'?'#e67e22':'#111')) ?>;"></div>
                    <div class="p-4">
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($proj['package']) ?></div>
                                <div style="font-size:.77rem;color:#888;">
                                    <i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars($proj['booking_date']) ?>
                                    <?php if ($proj['event_type']): ?> &nbsp;·&nbsp; <?= htmlspecialchars($proj['event_type']) ?><?php endif; ?>
                                </div>
                            </div>
                            <?php if ($isComplete): ?>
                                <span class="badge bg-success"><i class="bi bi-check2-all me-1"></i>All Complete</span>
                            <?php else: ?>
                                <span class="badge bg-<?= $dlBadge[$dl] ?>"><?= $dlLabel[$dl] ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Progress -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;">
                                <span style="color:#888;font-weight:600;">Overall Progress</span>
                                <span class="fw-bold"><?= $pct ?>%</span>
                            </div>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar <?= $barClass ?>" role="progressbar" style="width:<?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <!-- Deliverables -->
                        <div class="mb-3">
                            <?php
                            $deliverables = [
                                'photo_status' => ['Photography Editing', 'bi-camera'],
                                'video_status' => ['Video Production',    'bi-camera-video'],
                                'other_status' => ['Other Deliverables',  'bi-box-seam'],
                            ];
                            foreach ($deliverables as $key => [$lbl,$icon]):
                                $val = $proj[$key]??'not_started';
                            ?>
                            <div class="deliverable-row">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi <?= $icon ?>" style="width:16px;color:#555;"></i>
                                    <span><?= $lbl ?></span>
                                </div>
                                <span class="badge bg-<?= $ppStatusColor[$val]??'secondary' ?>">
                                    <i class="bi <?= $ppStatusIcon[$val]??'bi-clock' ?> me-1"></i><?= $ppStatusLabel[$val]??$val ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Deadline -->
                        <?php if ($proj['deadline']): ?>
                        <div class="d-flex align-items-center gap-2 mb-3" style="font-size:.8rem;">
                            <i class="bi bi-calendar-event" style="color:#555;"></i>
                            <span style="color:#888;">Deadline:</span>
                            <span class="fw-semibold"><?= htmlspecialchars($proj['deadline']) ?></span>
                            <span class="badge bg-<?= $dlBadge[$dl] ?>" style="font-size:.68rem;"><?= $dlLabel[$dl] ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Studio Note -->
                        <?php if (!empty($proj['notes'])): ?>
                        <div class="info-box mb-3">
                            <div class="info-label">Note from Studio</div>
                            <div class="mt-1"><?= htmlspecialchars($proj['notes']) ?></div>
                        </div>
                        <?php endif; ?>

                        <!-- Download -->
                        <?php if (!empty($proj['drive_link'])): ?>
                        <a href="<?= htmlspecialchars($proj['drive_link']) ?>" target="_blank" rel="noopener"
                           class="btn btn-dark btn-sm w-100 fw-semibold" style="border-radius:8px;">
                            <i class="bi bi-cloud-download me-1"></i> Access My Files
                        </a>
                        <?php elseif ($isComplete): ?>
                        <div class="text-center text-muted" style="font-size:.8rem;">
                            <i class="bi bi-hourglass-split me-1"></i> Files ready — download link coming soon.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('client-sidebar').classList.toggle('show'));
</script>
</body>
</html>
