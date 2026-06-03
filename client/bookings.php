<?php
require_once '../auth/client_guard.php';
require_once '../config/db.php';

$clientId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']    ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($action === 'cancel' && $bookingId) {
        $reason = trim($_POST['reason'] ?? '');

        // Verify booking belongs to client and is cancellable
        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE id=? AND client_id=? AND status IN ('pending','approved')");
        $stmt->execute([$bookingId, $clientId]);

        if ($stmt->fetch()) {
            // Check if a pending cancel request already exists
            $existing = $pdo->prepare("SELECT id FROM cancellations WHERE booking_id=? AND cancellation_status='pending_approval'");
            $existing->execute([$bookingId]);

            if ($existing->fetch()) {
                $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'You already have a pending cancellation request for this booking.'];
            } else {
                // Insert cancellation request — booking stays unchanged until admin approves
                $pdo->prepare("INSERT INTO cancellations (booking_id, client_id, reason, deposit_amount, deposit_retained, cancellation_status, initiated_by) VALUES (?,?,?,0,0,'pending_approval','client')")
                    ->execute([$bookingId, $clientId, $reason]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Your cancellation request has been submitted and is awaiting admin approval.'];
            }
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Unable to request cancellation for this booking.'];
        }
    }
    header('Location: bookings.php');
    exit;
}

$stmt = $pdo->prepare("SELECT b.id,p.name AS package,p.price,b.booking_date,b.event_type,b.venue,b.notes,b.status,b.created_at FROM bookings b JOIN packages p ON b.package_id=p.id WHERE b.client_id=? ORDER BY b.created_at DESC");
$stmt->execute([$clientId]);
$bookings = $stmt->fetchAll();

$ppStmt = $pdo->prepare("SELECT pp.booking_id,pp.photo_status,pp.video_status,pp.other_status,pp.progress_percent,pp.deadline,pp.deadline_status,pp.notes AS pp_notes,pp.drive_link FROM post_production pp JOIN bookings b ON pp.booking_id=b.id WHERE b.client_id=?");
$ppStmt->execute([$clientId]);
$ppMap = [];
foreach ($ppStmt->fetchAll() as $row) {
    $ppMap[$row['booking_id']] = $row;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$statusBadge = ['pending' => 'warning', 'approved' => 'success', 'rescheduled' => 'info', 'cancelled' => 'danger'];
$ppStatusLabel = ['not_started' => 'Not Started', 'in_progress' => 'In Progress', 'completed' => 'Completed'];
$ppStatusColor = ['not_started' => 'secondary', 'in_progress' => 'primary', 'completed' => 'success'];
$dlBadge = ['early' => 'success', 'near' => 'warning', 'late' => 'danger'];
$dlLabel = ['early' => 'On Track', 'near' => 'Due Soon', 'late' => 'Overdue'];

$initials = strtoupper(substr($_SESSION['name'], 0, 1));
$pageTitle = 'My Bookings';
$activeClientPage = 'bookings';
require_once '../includes/client_head.php';

$_clientAvatar  ??= null;
$_clientInitial ??= $initials;
?>
</head>

<body>
    <?php require_once '../includes/client_sidebar.php'; ?>

    <!-- Toast -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <?php if ($flash): ?>
            <div id="liveToast" class="toast align-items-center border-0 show" role="alert"
                style="background:<?= $flash['type'] === 'success' ? '#f0fdf4' : '#fef2f2' ?>">
                <div class="toast-header" style="background:transparent;">
                    <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?> me-2"></i>
                    <strong class="me-auto"><?= ucfirst($flash['type'] === 'danger' ? 'Error' : $flash['type']) ?></strong>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body"><?= htmlspecialchars($flash['msg']) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <div id="client-main">
        <div id="client-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="topbar-btn d-lg-none border-0" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
                <div>
                    <div class="topbar-title">My Bookings</div>
                    <div class="topbar-sub">View and manage your bookings</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="booking_create.php" class="btn btn-dark btn-sm px-3">
                    <i class="bi bi-plus-lg me-1"></i> Book a Service
                </a>
                <?php if ($_clientAvatar): ?><img src="../assets/avatars/<?= htmlspecialchars($_clientAvatar) ?>" class="topbar-avatar" style="object-fit:cover;"><?php else: ?><div class="topbar-avatar"><?= $_clientInitial ?></div><?php endif; ?>
            </div>
        </div>

        <div class="p-3 p-md-4">
            <div class="dash-card">
                <div class="dash-card-header">
                    <h6>All Bookings <span class="badge bg-secondary ms-2"><?= count($bookings) ?></span></h6>
                </div>
                <div class="table-responsive">
                    <table class="table modern-table mobile-cards mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Package</th>
                                <th>Booking Date</th>
                                <th>Event Type</th>
                                <th>Venue</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-calendar-x d-block fs-3 mb-2 opacity-25"></i>
                                        No bookings yet. <a href="booking_create.php" class="text-dark fw-semibold">Book your first service →</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $b):
                                    $pp = $ppMap[$b['id']] ?? null;
                                    $bJson = htmlspecialchars(json_encode([
                                        'id' => $b['id'],
                                        'package' => $b['package'],
                                        'price' => number_format((float)$b['price'], 2),
                                        'booking_date' => $b['booking_date'],
                                        'event_type' => $b['event_type'] ?? '',
                                        'venue' => $b['venue'] ?? '',
                                        'notes' => $b['notes'] ?? '',
                                        'status' => $b['status'],
                                        'created_at' => date('M d, Y', strtotime($b['created_at'])),
                                        'pp' => $pp ? [
                                            'photo_status' => $pp['photo_status'],
                                            'video_status' => $pp['video_status'],
                                            'other_status' => $pp['other_status'],
                                            'progress_percent' => $pp['progress_percent'],
                                            'deadline' => $pp['deadline'] ?? '',
                                            'deadline_status' => $pp['deadline_status'] ?? 'early',
                                            'pp_notes' => $pp['pp_notes'] ?? '',
                                            'drive_link' => $pp['drive_link'] ?? ''
                                        ] : null
                                    ]), ENT_QUOTES);
                                ?>
                                    <tr>
                                        <td data-label="#" class="text-muted">#<?= (int)$b['id'] ?></td>
                                        <td data-label="Package" class="fw-semibold"><?= htmlspecialchars($b['package']) ?></td>
                                        <td data-label="Date"><?= htmlspecialchars($b['booking_date']) ?></td>
                                        <td data-label="Event"><?= htmlspecialchars($b['event_type'] ?? '—') ?></td>
                                        <td data-label="Venue" style="max-width:150px;">
                                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($b['venue'] ?? '—') ?></div>
                                        </td>
                                        <td data-label="Status"><span class="badge bg-<?= $statusBadge[$b['status']] ?? 'secondary' ?>"><?= ucfirst($b['status']) ?></span></td>
                                        <td data-label="Actions">
                                            <div class="d-flex gap-1">
                                                <button class="btn-action" title="View Details" onclick="viewDetails(<?= $bJson ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if (in_array($b['status'], ['pending', 'approved'])): ?>
                                                    <button class="btn-action danger" title="Request Cancellation" onclick="openCancelModal(<?= (int)$b['id'] ?>)">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title">Booking Details</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <?php foreach ([['Booking #', 'dId'], ['Status', 'dStatus'], ['Package', 'dPackage'], ['Price', 'dPrice'], ['Booking Date', 'dDate'], ['Event Type', 'dEvent'], ['Venue', 'dVenue'], ['Submitted On', 'dCreated']] as [$l, $id]): ?>
                            <div class="col-6 col-md-3">
                                <div class="info-box">
                                    <div class="info-label"><?= $l ?></div>
                                    <div class="fw-semibold mt-1" id="<?= $id ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-12">
                            <div class="info-box">
                                <div class="info-label">Notes</div>
                                <div class="mt-1" id="dNotes"></div>
                            </div>
                        </div>
                    </div>
                    <div id="ppSection" style="display:none;">
                        <hr style="border-color:#f0f0f0;">
                        <div class="section-hd mb-3">Post-Production Status</div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;">
                                <span style="color:#888;">Overall Progress</span>
                                <span class="fw-bold" id="ppPct"></span>
                            </div>
                            <div class="progress" style="height:7px;">
                                <div class="progress-bar" id="ppBar" role="progressbar" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap mb-3" id="ppDeliverables"></div>
                        <div id="ppMeta"></div>
                        <div id="ppNotesWrap" style="display:none;" class="info-box mt-2">
                            <div class="info-label">Studio Note</div>
                            <div id="ppNotes" class="mt-1"></div>
                        </div>
                    </div>
                    <div id="noPpSection" style="display:none;">
                        <hr style="border-color:#f0f0f0;">
                        <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>Post-production tracking appears once your booking is approved.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <a href="booking_create.php" class="btn btn-dark btn-sm"><i class="bi bi-plus-lg me-1"></i>Book Again</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="booking_id" id="cancelBookingId">
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="modal-title text-danger">Request Cancellation</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="info-box mb-3" style="border-color:#fde8e8;background:#fef9f9;">
                            <i class="bi bi-info-circle text-danger me-1"></i>
                            <span style="font-size:.82rem;color:#555;">
                                Your cancellation request will be sent to the admin for approval.
                                Your booking will remain active until the admin approves the cancellation.
                            </span>
                        </div>
                        <label class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Please tell us why you want to cancel…"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Back</button>
                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-send me-1"></i>Submit Request</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('client-sidebar').classList.toggle('show'));

        const statusBadge = {
            pending: 'warning',
            approved: 'success',
            rescheduled: 'info',
            cancelled: 'danger'
        };
        const ppStatusLabel = {
            not_started: 'Not Started',
            in_progress: 'In Progress',
            completed: 'Completed'
        };
        const ppStatusColor = {
            not_started: 'secondary',
            in_progress: 'primary',
            completed: 'success'
        };
        const dlLabel = {
            early: 'On Track',
            near: 'Due Soon',
            late: 'Overdue'
        };
        const dlBadge = {
            early: 'success',
            near: 'warning',
            late: 'danger'
        };

        function viewDetails(b) {
            document.getElementById('dId').textContent = '#' + b.id;
            document.getElementById('dPackage').textContent = b.package || '—';
            document.getElementById('dPrice').textContent = '₱' + b.price;
            document.getElementById('dDate').textContent = b.booking_date || '—';
            document.getElementById('dEvent').textContent = b.event_type || '—';
            document.getElementById('dVenue').textContent = b.venue || '—';
            document.getElementById('dNotes').textContent = b.notes || '—';
            document.getElementById('dCreated').textContent = b.created_at || '—';
            const sc = statusBadge[b.status] || 'secondary';
            document.getElementById('dStatus').innerHTML = `<span class="badge bg-${sc}">${b.status.charAt(0).toUpperCase()+b.status.slice(1)}</span>`;

            if (b.pp) {
                document.getElementById('ppSection').style.display = 'block';
                document.getElementById('noPpSection').style.display = 'none';
                const pct = Math.min(100, Math.max(0, parseInt(b.pp.progress_percent) || 0));
                const barCls = pct < 40 ? 'bg-danger' : (pct < 75 ? 'bg-warning' : 'bg-success');
                document.getElementById('ppPct').textContent = pct + '%';
                const bar = document.getElementById('ppBar');
                bar.style.width = pct + '%';
                bar.setAttribute('aria-valuenow', pct);
                bar.className = 'progress-bar ' + barCls;
                document.getElementById('ppDeliverables').innerHTML = [
                    ['photo_status', 'Photo'],
                    ['video_status', 'Video'],
                    ['other_status', 'Other']
                ].map(([k, l]) => {
                    const v = b.pp[k] || 'not_started';
                    return `<span class="badge bg-${ppStatusColor[v]||'secondary'}" style="font-size:.74rem;">${l}: ${ppStatusLabel[v]||v}</span>`;
                }).join('');
                let meta = '';
                if (b.pp.deadline) meta += `<span class="badge bg-${dlBadge[b.pp.deadline_status||'early']}">${dlLabel[b.pp.deadline_status||'early']} · ${b.pp.deadline}</span>`;
                if (b.pp.drive_link) meta += ` <a href="${b.pp.drive_link}" target="_blank" rel="noopener" class="btn btn-dark btn-sm py-0 px-3 ms-2"><i class="bi bi-cloud-download me-1"></i>Download</a>`;
                document.getElementById('ppMeta').innerHTML = meta;
                if (b.pp.pp_notes) {
                    document.getElementById('ppNotes').textContent = b.pp.pp_notes;
                    document.getElementById('ppNotesWrap').style.display = 'block';
                } else {
                    document.getElementById('ppNotesWrap').style.display = 'none';
                }
            } else {
                document.getElementById('ppSection').style.display = 'none';
                document.getElementById('noPpSection').style.display = 'block';
            }
            new bootstrap.Modal(document.getElementById('detailModal')).show();
        }

        function openCancelModal(id) {
            document.getElementById('cancelBookingId').value = id;
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }
        document.addEventListener('DOMContentLoaded', () => {
            const t = document.getElementById('liveToast');
            if (t) setTimeout(() => bootstrap.Toast.getOrCreateInstance(t).hide(), 4000);
        });
    </script>
</body>

</html>