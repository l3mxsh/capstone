<?php
require_once '../auth/client_guard.php';
require_once '../config/db.php';

$clientId = (int)$_SESSION['user_id'];

// ── POST Handler (PRG) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']     ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($action === 'cancel' && $bookingId) {
        $reason = trim($_POST['reason'] ?? '');

        // Verify the booking belongs to this client and is cancellable
        $stmt = $pdo->prepare("
            SELECT id FROM bookings
            WHERE id = ? AND client_id = ? AND status IN ('pending','approved')
        ");
        $stmt->execute([$bookingId, $clientId]);

        if ($stmt->fetch()) {
            // Cancel booking
            $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id = ? AND client_id = ?")
                ->execute([$bookingId, $clientId]);

            // Remove staff schedule if any
            $pdo->prepare("DELETE FROM staff_schedules WHERE booking_id = ?")
                ->execute([$bookingId]);

            // Log cancellation — deposit_retained = 0 (admin will update)
            $pdo->prepare("
                INSERT INTO cancellations (booking_id, client_id, reason, deposit_amount, deposit_retained)
                VALUES (?, ?, ?, 0, 0)
            ")->execute([$bookingId, $clientId, $reason]);

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Your cancellation request has been submitted.'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Unable to cancel this booking.'];
        }
    }

    header('Location: bookings.php'); exit;
}

// ── Fetch all bookings for this client ─────────────────────────
$stmt = $pdo->prepare("
    SELECT b.id, p.name AS package, p.price,
           b.booking_date, b.event_type, b.venue,
           b.notes, b.status, b.created_at
    FROM bookings b
    JOIN packages p ON b.package_id = p.id
    WHERE b.client_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$clientId]);
$bookings = $stmt->fetchAll();

// ── Fetch post-production data keyed by booking_id ─────────────
$ppStmt = $pdo->prepare("
    SELECT pp.booking_id, pp.photo_status, pp.video_status,
           pp.other_status, pp.progress_percent,
           pp.deadline, pp.deadline_status, pp.notes AS pp_notes,
           pp.drive_link
    FROM post_production pp
    JOIN bookings b ON pp.booking_id = b.id
    WHERE b.client_id = ?
");
$ppStmt->execute([$clientId]);
$ppMap = [];
foreach ($ppStmt->fetchAll() as $row) {
    $ppMap[$row['booking_id']] = $row;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$statusColors = [
    'pending'     => 'warning',
    'approved'    => 'success',
    'rescheduled' => 'info',
    'cancelled'   => 'danger',
];
$ppStatusLabel = ['not_started' => 'Not Started', 'in_progress' => 'In Progress', 'completed' => 'Completed'];
$ppStatusColor = ['not_started' => 'secondary',   'in_progress' => 'primary',     'completed' => 'success'];
$dlBadge       = ['early' => 'success', 'near' => 'warning', 'late' => 'danger'];
$dlLabel       = ['early' => 'On Track', 'near' => 'Due Soon', 'late' => 'Overdue'];

$initials        = strtoupper(substr($_SESSION['name'], 0, 1));
$pageTitle       = 'My Bookings — Client Portal';
$activeClientPage = 'bookings';

require_once '../includes/client_head.php';
?>
<style>
    .detail-label { font-size:.72rem; color:#6c757d; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
    .mini-progress { height:7px; border-radius:10px; }
</style>
</head>
<body>

<?php require_once '../includes/client_sidebar.php'; ?>

<div id="client-main">

    <!-- Topbar -->
    <div id="client-topbar">
        <span class="page-label">My Bookings</span>
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

        <!-- Flash -->
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 fw-bold">My Bookings</h5>
            <a href="booking_create.php"
               class="btn btn-sm text-white fw-semibold"
               style="background:var(--gold);border:none;">
                <i class="bi bi-plus-lg"></i> Book a Service
            </a>
        </div>

        <!-- Table -->
        <div class="portal-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Package</th>
                            <th>Booking Date</th>
                            <th>Event Type</th>
                            <th>Venue</th>
                            <th>Status</th>
                            <th style="width:130px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-calendar-x" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                                No bookings yet.
                                <a href="booking_create.php" style="color:var(--gold);">Book your first service.</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $b):
                            $pp = $ppMap[$b['id']] ?? null;
                            // Build a JSON-safe object for the detail modal
                            $bJson = htmlspecialchars(json_encode([
                                'id'           => $b['id'],
                                'package'      => $b['package'],
                                'price'        => number_format((float)$b['price'], 2),
                                'booking_date' => $b['booking_date'],
                                'event_type'   => $b['event_type']  ?? '',
                                'venue'        => $b['venue']        ?? '',
                                'notes'        => $b['notes']        ?? '',
                                'status'       => $b['status'],
                                'created_at'   => date('M d, Y', strtotime($b['created_at'])),
                                'pp'           => $pp ? [
                                    'photo_status'     => $pp['photo_status'],
                                    'video_status'     => $pp['video_status'],
                                    'other_status'     => $pp['other_status'],
                                    'progress_percent' => $pp['progress_percent'],
                                    'deadline'         => $pp['deadline']       ?? '',
                                    'deadline_status'  => $pp['deadline_status'] ?? 'early',
                                    'pp_notes'         => $pp['pp_notes']        ?? '',
                                    'drive_link'       => $pp['drive_link']      ?? '',
                                ] : null,
                            ]), ENT_QUOTES);
                        ?>
                        <tr>
                            <td class="text-muted"><?= (int)$b['id'] ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($b['package']) ?></td>
                            <td><?= htmlspecialchars($b['booking_date']) ?></td>
                            <td><?= htmlspecialchars($b['event_type'] ?? '—') ?></td>
                            <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?= htmlspecialchars($b['venue'] ?? '—') ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $statusColors[$b['status']] ?? 'secondary' ?>">
                                    <?= ucfirst($b['status']) ?>
                                </span>
                            </td>
                            <td class="d-flex gap-1">
                                <!-- View Details -->
                                <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                        onclick="viewDetails(<?= $bJson ?>)"
                                        title="View Details">
                                    <i class="bi bi-eye"></i>
                                </button>

                                <!-- Cancel (only pending or approved) -->
                                <?php if (in_array($b['status'], ['pending', 'approved'])): ?>
                                <button class="btn btn-sm btn-outline-danger py-0 px-2"
                                        onclick="openCancelModal(<?= (int)$b['id'] ?>)"
                                        title="Request Cancellation">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /client-main -->

<!-- ── View Details Modal ──────────────────────────────────── -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">Booking Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Booking info grid -->
                <div class="row g-3 mb-4" style="font-size:.875rem;">
                    <div class="col-6 col-md-3">
                        <div class="detail-label">Booking #</div>
                        <div class="fw-semibold" id="dId"></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="detail-label">Status</div>
                        <div id="dStatus"></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="detail-label">Package</div>
                        <div id="dPackage"></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="detail-label">Price</div>
                        <div id="dPrice"></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="detail-label">Booking Date</div>
                        <div id="dDate"></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="detail-label">Event Type</div>
                        <div id="dEvent"></div>
                    </div>
                    <div class="col-6 col-md-6">
                        <div class="detail-label">Venue</div>
                        <div id="dVenue"></div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label">Notes</div>
                        <div id="dNotes" class="text-muted"></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="detail-label">Submitted On</div>
                        <div id="dCreated"></div>
                    </div>
                </div>

                <!-- Post-production section -->
                <div id="ppSection" style="display:none;">
                    <hr>
                    <div class="section-title mb-3">Post-Production Status</div>

                    <!-- Progress bar -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;">
                            <span class="text-muted">Overall Progress</span>
                            <span class="fw-semibold" id="ppPct"></span>
                        </div>
                        <div class="progress mini-progress">
                            <div class="progress-bar" id="ppBar"
                                 role="progressbar" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>

                    <!-- Deliverable badges -->
                    <div class="d-flex gap-2 flex-wrap mb-3" id="ppDeliverables"></div>

                    <!-- Deadline + drive link -->
                    <div class="d-flex gap-3 align-items-center flex-wrap mb-2" id="ppMeta"></div>

                    <div id="ppNotesWrap" style="display:none;">
                        <div class="detail-label">Studio Note</div>
                        <div id="ppNotes" class="text-muted" style="font-size:.85rem;"></div>
                    </div>
                </div>

                <div id="noPpSection" style="display:none;">
                    <hr>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle"></i>
                        Post-production tracking will appear once your booking is approved.
                    </p>
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <a id="bookAgainBtn" href="booking_create.php"
                   class="btn btn-sm text-white" style="background:var(--gold);border:none;">
                    <i class="bi bi-plus-lg"></i> Book Again
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ── Cancel Request Modal ────────────────────────────────── -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="action"     value="cancel">
            <input type="hidden" name="booking_id" id="cancelBookingId">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold text-danger">Request Cancellation</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 mb-3" style="font-size:.82rem;">
                        <i class="bi bi-exclamation-triangle"></i>
                        Cancelling an approved booking may result in deposit forfeiture.
                        The admin will process the final deposit decision.
                    </div>
                    <label class="form-label fw-semibold">Reason for Cancellation</label>
                    <textarea class="form-control" name="reason" rows="3"
                              placeholder="Please tell us why you want to cancel..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm"
                            data-bs-dismiss="modal">Back</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-x-circle"></i> Confirm Cancellation
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const statusColors = {
        pending: 'warning', approved: 'success',
        rescheduled: 'info', cancelled: 'danger'
    };
    const ppStatusLabel = { not_started: 'Not Started', in_progress: 'In Progress', completed: 'Completed' };
    const ppStatusColor = { not_started: 'secondary',   in_progress: 'primary',     completed: 'success' };
    const dlLabel       = { early: 'On Track', near: 'Due Soon', late: 'Overdue' };
    const dlBadge       = { early: 'success',  near: 'warning',  late: 'danger' };

    function viewDetails(b) {
        // Booking fields
        document.getElementById('dId').textContent      = '#' + b.id;
        document.getElementById('dPackage').textContent = b.package    || '—';
        document.getElementById('dPrice').textContent   = '₱' + b.price;
        document.getElementById('dDate').textContent    = b.booking_date || '—';
        document.getElementById('dEvent').textContent   = b.event_type   || '—';
        document.getElementById('dVenue').textContent   = b.venue         || '—';
        document.getElementById('dNotes').textContent   = b.notes         || '—';
        document.getElementById('dCreated').textContent = b.created_at    || '—';

        const sc = statusColors[b.status] || 'secondary';
        document.getElementById('dStatus').innerHTML =
            `<span class="badge bg-${sc}">${b.status.charAt(0).toUpperCase() + b.status.slice(1)}</span>`;

        // Post-production
        const ppSec   = document.getElementById('ppSection');
        const noPpSec = document.getElementById('noPpSection');

        if (b.pp) {
            ppSec.style.display   = 'block';
            noPpSec.style.display = 'none';

            const pct      = Math.min(100, Math.max(0, parseInt(b.pp.progress_percent) || 0));
            const barColor = pct < 40 ? 'danger' : (pct < 75 ? 'warning' : 'success');

            document.getElementById('ppPct').textContent = pct + '%';
            const bar = document.getElementById('ppBar');
            bar.style.width        = pct + '%';
            bar.setAttribute('aria-valuenow', pct);
            bar.className          = `progress-bar bg-${barColor}`;

            // Deliverable badges
            const deliverables = [
                { key: 'photo_status', label: 'Photo' },
                { key: 'video_status', label: 'Video' },
                { key: 'other_status', label: 'Other' },
            ];
            document.getElementById('ppDeliverables').innerHTML = deliverables.map(d => {
                const val   = b.pp[d.key] || 'not_started';
                const color = ppStatusColor[val] || 'secondary';
                const lbl   = ppStatusLabel[val] || val;
                return `<span class="badge bg-${color}" style="font-size:.75rem;">
                            ${d.label}: ${lbl}
                        </span>`;
            }).join('');

            // Deadline + drive link
            let meta = '';
            if (b.pp.deadline) {
                const dl    = b.pp.deadline_status || 'early';
                meta += `<span class="badge bg-${dlBadge[dl]}">${dlLabel[dl]} · ${b.pp.deadline}</span>`;
            }
            if (b.pp.drive_link) {
                meta += `<a href="${b.pp.drive_link}" target="_blank" rel="noopener"
                            class="btn btn-sm text-white py-0 px-3"
                            style="background:var(--gold);border:none;font-size:.78rem;">
                            <i class="bi bi-cloud-download"></i> Download Files
                         </a>`;
            }
            document.getElementById('ppMeta').innerHTML = meta;

            // Studio notes
            const notesWrap = document.getElementById('ppNotesWrap');
            if (b.pp.pp_notes) {
                document.getElementById('ppNotes').textContent = b.pp.pp_notes;
                notesWrap.style.display = 'block';
            } else {
                notesWrap.style.display = 'none';
            }
        } else {
            ppSec.style.display   = 'none';
            noPpSec.style.display = 'block';
        }

        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }

    function openCancelModal(id) {
        document.getElementById('cancelBookingId').value = id;
        new bootstrap.Modal(document.getElementById('cancelModal')).show();
    }
</script>
</body>
</html>
