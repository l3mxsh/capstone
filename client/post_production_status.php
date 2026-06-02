<?php
require_once '../auth/client_guard.php';
require_once '../config/db.php';

$clientId = (int)$_SESSION['user_id'];

// ── Fetch all post-production projects for this client ─────────
$stmt = $pdo->prepare("
    SELECT pp.photo_status, pp.video_status, pp.other_status,
           pp.progress_percent, pp.deadline, pp.deadline_status,
           pp.notes, pp.drive_link,
           b.booking_date, b.event_type,
           p.name AS package
    FROM post_production pp
    JOIN bookings b ON pp.booking_id = b.id
    JOIN packages p ON b.package_id  = p.id
    WHERE b.client_id = ? AND b.status = 'approved'
    ORDER BY b.booking_date DESC
");
$stmt->execute([$clientId]);
$projects = $stmt->fetchAll();

// ── Helpers ────────────────────────────────────────────────────
$ppStatusLabel = ['not_started' => 'Not Started', 'in_progress' => 'In Progress', 'completed' => 'Completed'];
$ppStatusColor = ['not_started' => 'secondary',   'in_progress' => 'primary',     'completed' => 'success'];
$ppStatusIcon  = ['not_started' => 'bi-clock',    'in_progress' => 'bi-arrow-repeat', 'completed' => 'bi-check-circle-fill'];
$dlBadge       = ['early' => 'success', 'near' => 'warning', 'late' => 'danger'];
$dlLabel       = ['early' => 'On Track', 'near' => 'Due Soon', 'late' => 'Overdue'];

$initials        = strtoupper(substr($_SESSION['name'], 0, 1));
$pageTitle       = 'Post-Production Status — Client Portal';
$activeClientPage = 'post_production';

require_once '../includes/client_head.php';
?>
<style>
    .project-card {
        background: #fff;
        border: none;
        border-radius: 14px;
        box-shadow: 0 1px 5px rgba(0,0,0,.07);
        overflow: hidden;
        transition: box-shadow .2s;
    }
    .project-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); }
    .project-card .card-accent {
        height: 4px;
        background: linear-gradient(90deg, var(--gold), #e8c96a);
    }
    .project-card.completed .card-accent { background: linear-gradient(90deg, #198754, #40c080); }
    .project-card.late      .card-accent { background: linear-gradient(90deg, #dc3545, #f07080); }
    .project-card.near      .card-accent { background: linear-gradient(90deg, #fd7e14, #ffc107); }

    .deliverable-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .45rem 0;
        border-bottom: 1px solid #f0f0f0;
        font-size: .855rem;
    }
    .deliverable-row:last-child { border-bottom: none; }
    .deliverable-icon { color: var(--gold); width: 20px; text-align: center; }

    .progress-track { height: 10px; border-radius: 20px; }

    .legend-item {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .82rem;
        color: #495057;
    }
    .legend-dot {
        width: 10px; height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }
</style>
</head>
<body>

<?php require_once '../includes/client_sidebar.php'; ?>

<div id="client-main">

    <!-- Topbar -->
    <div id="client-topbar">
        <span class="page-label">Post-Production Status</span>
        <div class="user-pill">
            <div class="avatar"><?= htmlspecialchars($initials) ?></div>
            <span><?= htmlspecialchars($_SESSION['name']) ?></span>
            <a href="../logout.php" class="btn btn-sm btn-outline-secondary py-0 px-2 ms-2"
               style="font-size:.78rem;">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <div class="p-4">

        <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
            <div>
                <h5 class="mb-1 fw-bold">Post-Production Status</h5>
                <p class="text-muted mb-0" style="font-size:.875rem;">
                    Track the editing and delivery progress of your photos, videos, and other outputs.
                </p>
            </div>

            <!-- Status Legend -->
            <div class="portal-card px-3 py-2 d-flex gap-3 flex-wrap align-items-center">
                <span class="legend-item">
                    <span class="legend-dot bg-secondary"></span> Not Started
                </span>
                <span class="legend-item">
                    <span class="legend-dot bg-primary"></span> In Progress
                </span>
                <span class="legend-item">
                    <span class="legend-dot bg-success"></span> Completed
                </span>
            </div>
        </div>

        <!-- ── Status explanation info box ───────────────── -->
        <div class="alert alert-light border mb-4 d-flex gap-3 align-items-start" style="font-size:.83rem;">
            <i class="bi bi-info-circle-fill text-primary mt-1" style="font-size:1.1rem;flex-shrink:0;"></i>
            <div>
                <strong>How to read your status:</strong>
                <span class="text-muted">
                    <strong>Not Started</strong> — work hasn't begun yet. &nbsp;|&nbsp;
                    <strong>In Progress</strong> — your files are being edited. &nbsp;|&nbsp;
                    <strong>Completed</strong> — ready for review or delivery.
                    Once all deliverables are completed, you'll receive an email and a download link will appear below.
                </span>
            </div>
        </div>

        <?php if (empty($projects)): ?>
            <!-- Empty state -->
            <div class="portal-card p-5 text-center">
                <i class="bi bi-film" style="font-size:3rem;color:#dee2e6;display:block;margin-bottom:1rem;"></i>
                <h6 class="fw-semibold text-muted">No post-production projects yet.</h6>
                <p class="text-muted small mb-4">
                    This page will show your project progress once your booking is approved.
                </p>
                <a href="booking_create.php" class="btn btn-sm text-white"
                   style="background:var(--gold);border:none;">
                    <i class="bi bi-plus-lg"></i> Book a Service
                </a>
            </div>

        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($projects as $proj):
                    $pct       = min(100, max(0, (int)$proj['progress_percent']));
                    $barColor  = $pct < 40 ? 'danger' : ($pct < 75 ? 'warning' : 'success');
                    $dl        = $proj['deadline_status'] ?? 'early';
                    $isComplete = $proj['photo_status'] === 'completed'
                               && $proj['video_status'] === 'completed'
                               && $proj['other_status'] === 'completed';
                    $cardClass  = $isComplete ? 'completed' : ($dl === 'late' ? 'late' : ($dl === 'near' ? 'near' : ''));
                ?>
                <div class="col-lg-6">
                    <div class="project-card <?= $cardClass ?>">
                        <div class="card-accent"></div>
                        <div class="p-4">

                            <!-- Card header -->
                            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($proj['package']) ?></h6>
                                    <div class="text-muted" style="font-size:.8rem;">
                                        <i class="bi bi-calendar3"></i>
                                        <?= htmlspecialchars($proj['booking_date']) ?>
                                        <?php if ($proj['event_type']): ?>
                                            &nbsp;·&nbsp; <?= htmlspecialchars($proj['event_type']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($isComplete): ?>
                                    <span class="badge bg-success px-3 py-2">
                                        <i class="bi bi-check2-all"></i> All Complete
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-<?= $dlBadge[$dl] ?> px-3 py-2">
                                        <?= $dlLabel[$dl] ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Overall progress bar -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;">
                                    <span class="text-muted fw-semibold">Overall Progress</span>
                                    <span class="fw-bold" style="color:var(--gold);"><?= $pct ?>%</span>
                                </div>
                                <div class="progress progress-track">
                                    <div class="progress-bar bg-<?= $barColor ?>"
                                         role="progressbar"
                                         style="width:<?= $pct ?>%"
                                         aria-valuenow="<?= $pct ?>"
                                         aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                            </div>

                            <!-- Deliverables -->
                            <div class="mb-3">
                                <?php
                                $deliverables = [
                                    'photo_status' => ['label' => 'Photography Editing', 'icon' => 'bi-camera'],
                                    'video_status' => ['label' => 'Video Production',    'icon' => 'bi-camera-video'],
                                    'other_status' => ['label' => 'Other Deliverables',  'icon' => 'bi-box-seam'],
                                ];
                                foreach ($deliverables as $key => $info):
                                    $val = $proj[$key] ?? 'not_started';
                                ?>
                                <div class="deliverable-row">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi <?= $info['icon'] ?> deliverable-icon"></i>
                                        <span><?= $info['label'] ?></span>
                                    </div>
                                    <span class="badge bg-<?= $ppStatusColor[$val] ?? 'secondary' ?>">
                                        <i class="bi <?= $ppStatusIcon[$val] ?? 'bi-clock' ?> me-1"></i>
                                        <?= $ppStatusLabel[$val] ?? $val ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Deadline row -->
                            <?php if ($proj['deadline']): ?>
                            <div class="d-flex align-items-center gap-2 mb-3" style="font-size:.82rem;">
                                <i class="bi bi-calendar-event" style="color:var(--gold);"></i>
                                <span class="text-muted">Deadline:</span>
                                <span class="fw-semibold"><?= htmlspecialchars($proj['deadline']) ?></span>
                                <span class="badge bg-<?= $dlBadge[$dl] ?>" style="font-size:.7rem;">
                                    <?= $dlLabel[$dl] ?>
                                </span>
                            </div>
                            <?php endif; ?>

                            <!-- Admin notes -->
                            <?php if (!empty($proj['notes'])): ?>
                            <div class="rounded p-2 mb-3" style="background:#f8f9fa;font-size:.82rem;">
                                <div class="text-muted mb-1" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;">
                                    Note from Studio
                                </div>
                                <?= htmlspecialchars($proj['notes']) ?>
                            </div>
                            <?php endif; ?>

                            <!-- Download button -->
                            <?php if (!empty($proj['drive_link'])): ?>
                            <a href="<?= htmlspecialchars($proj['drive_link']) ?>"
                               target="_blank" rel="noopener"
                               class="btn btn-success btn-sm w-100 fw-semibold">
                                <i class="bi bi-cloud-download"></i> Access My Files
                            </a>
                            <?php elseif ($isComplete): ?>
                            <div class="text-center text-muted" style="font-size:.8rem;">
                                <i class="bi bi-hourglass-split"></i>
                                Files ready — download link coming soon.
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div><!-- /content -->
</div><!-- /client-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
