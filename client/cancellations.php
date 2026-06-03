<?php
require_once '../auth/client_guard.php';
require_once '../config/db.php';

$clientId = (int)$_SESSION['user_id'];

// ── Fetch all cancellation requests for this client ────────────
$stmt = $pdo->prepare("
    SELECT c.id, c.reason, c.reject_reason, c.deposit_amount, c.deposit_retained,
           c.cancellation_status, c.initiated_by, c.cancelled_at,
           b.booking_date, b.event_type, b.venue, b.status AS booking_status,
           p.name AS package
    FROM cancellations c
    JOIN bookings b ON c.booking_id = b.id
    JOIN packages p ON b.package_id = p.id
    WHERE c.client_id = ?
    ORDER BY c.cancelled_at DESC
");
$stmt->execute([$clientId]);
$cancellations = $stmt->fetchAll();

$statusColor = [
    'pending_approval' => 'warning',
    'approved'         => 'success',
    'rejected'         => 'danger',
];
$statusLabel = [
    'pending_approval' => 'Pending Approval',
    'approved'         => 'Approved',
    'rejected'         => 'Rejected',
];
$statusIcon = [
    'pending_approval' => 'bi-hourglass-split',
    'approved'         => 'bi-check-circle-fill',
    'rejected'         => 'bi-x-circle-fill',
];
$initials        = strtoupper(substr($_SESSION['name'], 0, 1));
$pageTitle        = 'My Cancellations';
$activeClientPage = 'cancellations';

require_once '../includes/client_head.php';
$_clientAvatar  ??= null;
$_clientInitial ??= $initials;
?>
</head>

<body>

    <?php require_once '../includes/client_sidebar.php'; ?>

    <div id="client-main">

        <!-- Topbar -->
        <div id="client-topbar">
            <span class="page-label">My Cancellations</span>
            <div class="d-flex align-items-center gap-2">
                <?php if ($_clientAvatar): ?><img src="../assets/avatars/<?= htmlspecialchars($_clientAvatar) ?>" class="topbar-avatar" style="object-fit:cover;"><?php else: ?><div class="topbar-avatar"><?= $_clientInitial ?></div><?php endif; ?>
                <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>

        <div class="p-4">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold">My Cancellations</h5>
                <a href="bookings.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Bookings
                </a>
            </div>

            <!-- Info banner -->
            <div class="alert alert-light border mb-4 d-flex gap-3 align-items-start" style="font-size:.83rem;">
                <i class="bi bi-info-circle-fill text-primary mt-1" style="font-size:1.1rem;flex-shrink:0;"></i>
                <div>
                    <strong>How cancellations work:</strong>
                    <span class="text-muted">
                        When you request a cancellation, it is sent to the admin for review.
                        Your booking remains active until the admin <strong>approves</strong> the request.
                        If rejected, your booking continues as normal.
                    </span>
                </div>
            </div>

            <!-- Cancellations table -->
            <div class="portal-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Package</th>
                                <th>Booking Date</th>
                                <th>Status</th>
                                <th>Requested At</th>
                                <th style="width:80px;">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cancellations)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="bi bi-x-circle" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.3;"></i>
                                        No cancellation requests yet.
                                        <br>
                                        <a href="bookings.php" style="color:var(--gold);">Go to My Bookings</a>
                                        to submit a cancellation request.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cancellations as $c):
                                    $refundable = max(0, (float)$c['deposit_amount'] - (float)$c['deposit_retained']);
                                    $cs         = $c['cancellation_status'];
                                    $cJson = htmlspecialchars(json_encode([
                                        'id'                  => $c['id'],
                                        'package'             => $c['package'],
                                        'booking_date'        => $c['booking_date'],
                                        'event_type'          => $c['event_type'] ?? '—',
                                        'venue'               => $c['venue'] ?? '—',
                                        'reason'              => $c['reason'] ?: '—',
                                        'deposit_amount'      => number_format((float)$c['deposit_amount'], 2),
                                        'deposit_retained'    => number_format((float)$c['deposit_retained'], 2),
                                        'refundable'          => number_format($refundable, 2),
                                        'cancellation_status' => $cs,
                                        'initiated_by'        => $c['initiated_by'] ?? 'client',
                                        'status_label'        => $statusLabel[$cs] ?? ucfirst($cs),
                                        'status_color'        => $statusColor[$cs] ?? 'secondary',
                                        'status_icon'         => $statusIcon[$cs] ?? 'bi-circle',
                                        'requested_at'        => date('M d, Y g:i A', strtotime($c['cancelled_at'])),
                                        'reject_reason'       => $c['reject_reason'] ?? '',
                                    ]), ENT_QUOTES);
                                ?>
                                    <tr>
                                        <td class="text-muted"><?= (int)$c['id'] ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($c['package']) ?></td>
                                        <td><?= htmlspecialchars($c['booking_date']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $statusColor[$cs] ?? 'secondary' ?> d-inline-flex align-items-center gap-1">
                                                <i class="bi <?= $statusIcon[$cs] ?? 'bi-circle' ?>"></i>
                                                <?= $statusLabel[$cs] ?? ucfirst($cs) ?>
                                            </span>
                                        </td>
                                        <td style="white-space:nowrap;font-size:.82rem;">
                                            <?= date('M d, Y g:i A', strtotime($c['cancelled_at'])) ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                onclick="viewCancellation(<?= $cJson ?>)"
                                                title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- ── View Cancellation Modal ─────────────────────────────── -->
    <div class="modal fade" id="viewCancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Cancellation Details</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    <!-- Status banner -->
                    <div class="rounded p-3 mb-4 d-flex align-items-center gap-3" id="vcStatusBanner">
                        <i class="bi fs-4" id="vcStatusIcon"></i>
                        <div>
                            <div class="fw-bold" id="vcStatusLabel" style="font-size:.95rem;"></div>
                            <div id="vcStatusNote" style="font-size:.8rem;opacity:.85;"></div>
                        </div>
                    </div>

                    <!-- Details grid -->
                    <div class="row g-3" style="font-size:.875rem;">
                        <div class="col-md-6">
                            <div class="section-title">Package</div>
                            <div class="fw-semibold" id="vcPackage"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title">Booking Date</div>
                            <div id="vcDate"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title">Event Type</div>
                            <div id="vcEvent"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title">Venue</div>
                            <div id="vcVenue"></div>
                        </div>
                        <div class="col-12">
                            <div class="section-title">Reason for Cancellation</div>
                            <div id="vcReason" class="text-muted"></div>
                        </div>

                        <!-- Rejection reason — shown only when rejected -->
                        <div class="col-12" id="vcRejectSection" style="display:none;">
                            <div class="rounded p-3" style="background:#fdecea;border-left:4px solid #dc3545;">
                                <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#dc3545;margin-bottom:.3rem;">
                                    <i class="bi bi-shield-x me-1"></i> Admin Rejection Reason
                                </div>
                                <div id="vcRejectReason" style="font-size:.875rem;color:#842029;"></div>
                            </div>
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                        </div>

                        <!-- Deposit breakdown — shown only when approved -->
                        <div class="col-12" id="vcDepositSection">
                            <div class="section-title mb-2">Deposit Breakdown</div>
                            <div class="row g-2">
                                <div class="col-4">
                                    <div class="rounded p-2 text-center" style="background:#f8f9fa;">
                                        <div style="font-size:.7rem;color:#6c757d;">Deposit Paid</div>
                                        <div class="fw-bold" id="vcDepositPaid"></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="rounded p-2 text-center" style="background:#fdecea;">
                                        <div style="font-size:.7rem;color:#6c757d;">Retained</div>
                                        <div class="fw-bold text-danger" id="vcRetained"></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="rounded p-2 text-center" style="background:#e8f5e9;">
                                        <div style="font-size:.7rem;color:#6c757d;">Refundable</div>
                                        <div class="fw-bold text-success" id="vcRefundable"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="section-title">Requested At</div>
                            <div id="vcRequestedAt" class="text-muted"></div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const statusNotes = {
            pending_approval: 'Your request is awaiting admin review. Your booking is still active.',
            approved: 'Your cancellation has been approved. Your booking has been cancelled.',
            rejected: 'Your cancellation request was rejected. Your booking remains active.',
        };
        const adminApprovedNote = 'Your booking was cancelled by an administrator. No cancellation request was required.';
        const bannerBg = {
            pending_approval: '#fff8e1',
            approved: '#e8f5e9',
            rejected: '#fdecea',
        };
        const bannerColor = {
            pending_approval: '#856404',
            approved: '#1b5e20',
            rejected: '#b71c1c',
        };

        function viewCancellation(c) {
            // Status banner
            const banner = document.getElementById('vcStatusBanner');
            banner.style.background = bannerBg[c.cancellation_status] || '#f8f9fa';
            banner.style.color = bannerColor[c.cancellation_status] || '#212529';

            const icon = document.getElementById('vcStatusIcon');
            icon.className = 'bi ' + c.status_icon + ' fs-4';

            document.getElementById('vcStatusLabel').textContent = c.status_label;
            let note = statusNotes[c.cancellation_status] || '';
            if (c.cancellation_status === 'approved' && c.initiated_by === 'admin') {
                note = adminApprovedNote;
            }
            document.getElementById('vcStatusNote').textContent = note;

            // Details
            document.getElementById('vcPackage').textContent = c.package;
            document.getElementById('vcDate').textContent = c.booking_date;
            document.getElementById('vcEvent').textContent = c.event_type;
            document.getElementById('vcVenue').textContent = c.venue;
            document.getElementById('vcReason').textContent = c.reason;
            document.getElementById('vcRequestedAt').textContent = c.requested_at;

            // Rejection reason — only show when rejected
            const rejectSec = document.getElementById('vcRejectSection');
            if (c.cancellation_status === 'rejected' && c.reject_reason) {
                document.getElementById('vcRejectReason').textContent = c.reject_reason;
                rejectSec.style.display = 'block';
            } else {
                rejectSec.style.display = 'none';
            }

            // Deposit — only show when approved
            const depSec = document.getElementById('vcDepositSection');
            if (c.cancellation_status === 'approved') {
                depSec.style.display = 'block';
                document.getElementById('vcDepositPaid').textContent = '₱' + c.deposit_amount;
                document.getElementById('vcRetained').textContent = '₱' + c.deposit_retained;
                document.getElementById('vcRefundable').textContent = '₱' + c.refundable;
            } else {
                depSec.style.display = 'none';
            }

            new bootstrap.Modal(document.getElementById('viewCancelModal')).show();
        }
    </script>
</body>

</html>