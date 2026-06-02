<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

// ── POST Handler (PRG) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    // ── APPROVE ──────────────────────────────────────────────
    if ($action === 'approve' && $id) {
        // Get booking date
        $booking = $pdo->prepare("SELECT booking_date FROM bookings WHERE id = ?");
        $booking->execute([$id]);
        $row = $booking->fetch();

        if ($row) {
            // Find available staff not assigned on that date
            $staffStmt = $pdo->prepare("
                SELECT id FROM staff
                WHERE status = 'active'
                AND id NOT IN (
                    SELECT staff_id FROM staff_schedules WHERE booking_date = ?
                )
                LIMIT 1
            ");
            $staffStmt->execute([$row['booking_date']]);
            $staff = $staffStmt->fetch();

            if ($staff) {
                $pdo->prepare("UPDATE bookings SET status='approved' WHERE id=?")->execute([$id]);
                $pdo->prepare("INSERT INTO staff_schedules (staff_id, booking_id, booking_date) VALUES (?, ?, ?)")
                    ->execute([$staff['id'], $id, $row['booking_date']]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Booking approved and staff assigned.'];
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'No available staff on that date. Cannot approve.'];
            }
        }

    // ── RESCHEDULE ───────────────────────────────────────────
    } elseif ($action === 'reschedule' && $id) {
        $newDate = $_POST['new_date'] ?? '';

        if (!$newDate || !strtotime($newDate)) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Invalid date provided.'];
        } else {
            // Check for conflicts
            $conflict = $pdo->prepare("
                SELECT COUNT(*) FROM bookings
                WHERE booking_date = ? AND status = 'approved' AND id != ?
            ");
            $conflict->execute([$newDate, $id]);

            if ((int)$conflict->fetchColumn() > 0) {
                $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Date conflict: another approved booking exists on that date.'];
            } else {
                // Remove old staff schedule and update booking
                $pdo->prepare("DELETE FROM staff_schedules WHERE booking_id = ?")->execute([$id]);
                $pdo->prepare("UPDATE bookings SET booking_date = ?, status = 'rescheduled' WHERE id = ?")
                    ->execute([$newDate, $id]);
                $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Booking rescheduled successfully.'];
            }
        }

    // ── REJECT / CANCEL ──────────────────────────────────────
    } elseif ($action === 'reject' && $id) {
        $reason = trim($_POST['reason'] ?? '');

        $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([$id]);

        // Log cancellation — client_id fetched from the booking
        $clientStmt = $pdo->prepare("SELECT client_id FROM bookings WHERE id = ?");
        $clientStmt->execute([$id]);
        $clientId = (int)($clientStmt->fetchColumn() ?: 0);

        $pdo->prepare("
            INSERT INTO cancellations (booking_id, client_id, reason)
            VALUES (?, ?, ?)
        ")->execute([$id, $clientId, $reason]);

        // Remove any staff assignment
        $pdo->prepare("DELETE FROM staff_schedules WHERE booking_id = ?")->execute([$id]);

        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Booking has been cancelled.'];
    }

    header('Location: bookings.php');
    exit;
}

// ── GET: fetch bookings with optional status filter ────────────
$filterStatus   = $_GET['status']    ?? '';
$filterClientId = (int)($_GET['client_id'] ?? 0);
$allowed        = ['pending', 'approved', 'rescheduled', 'cancelled'];

$where  = [];
$params = [];

if (in_array($filterStatus, $allowed)) {
    $where[]  = 'b.status = ?';
    $params[] = $filterStatus;
}
if ($filterClientId > 0) {
    $where[]  = 'b.client_id = ?';
    $params[] = $filterClientId;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT b.id, u.name AS client, u.email AS client_email,
           p.name AS package, b.booking_date, b.event_type,
           b.venue, b.notes, b.status, b.created_at
    FROM bookings b
    JOIN users u    ON b.client_id  = u.id
    JOIN packages p ON b.package_id = p.id
    {$whereClause}
    ORDER BY b.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// ── Flash ──────────────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$statusColors = [
    'pending'     => 'warning',
    'approved'    => 'success',
    'rescheduled' => 'info',
    'cancelled'   => 'danger',
];

$pageTitle  = 'Bookings — Harvy Mance Films';
$activePage = 'bookings';
require_once '../includes/admin_head.php';
?>
<style>
    .detail-label { font-size: .78rem; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }
    .detail-value { font-size: .9rem; color: #212529; }
</style>
</head>
<body>

<?php require_once '../includes/sidebar.php'; ?>

<div id="main-wrapper">

    <!-- Topbar -->
    <div id="topbar">
        <div class="welcome">Welcome back, <span><?= htmlspecialchars($_SESSION['name']) ?></span></div>
        <input type="search" class="search-input" placeholder="&#128269; Search...">
    </div>

    <!-- Content -->
    <div class="p-4">

        <!-- Flash -->
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header row -->
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-0 fw-semibold">Booking Management</h5>
                <?php if ($filterClientId > 0): ?>
                    <span class="badge bg-info mt-1" style="font-size:.75rem;">
                        <i class="bi bi-person-fill"></i>
                        Filtered by client #<?= $filterClientId ?>
                        — <a href="bookings.php" class="text-white">Clear</a>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Status filter -->
            <form method="GET" class="d-flex align-items-center gap-2">
                <?php if ($filterClientId > 0): ?>
                    <input type="hidden" name="client_id" value="<?= $filterClientId ?>">
                <?php endif; ?>
                <select name="status" class="form-select form-select-sm" style="width:160px;"
                        onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach ($allowed as $s): ?>
                        <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Client</th>
                                <th>Package</th>
                                <th>Booking Date</th>
                                <th>Event Type</th>
                                <th>Status</th>
                                <th style="width:200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No bookings found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td><?= (int)$b['id'] ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($b['client']) ?></div>
                                    <div class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($b['client_email']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($b['package']) ?></td>
                                <td><?= htmlspecialchars($b['booking_date']) ?></td>
                                <td><?= htmlspecialchars($b['event_type'] ?? '—') ?></td>
                                <td>
                                    <span class="badge bg-<?= $statusColors[$b['status']] ?? 'secondary' ?>">
                                        <?= ucfirst($b['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- View Details -->
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                            onclick="viewBooking(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <?php if ($b['status'] === 'pending'): ?>
                                        <!-- Approve -->
                                        <form method="POST" class="d-inline"
                                              onsubmit="return confirm('Approve this booking?')">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                            <button class="btn btn-sm btn-outline-success py-0 px-2">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>

                                        <!-- Reject -->
                                        <button class="btn btn-sm btn-outline-danger py-0 px-2"
                                                onclick="openRejectModal(<?= (int)$b['id'] ?>)">
                                            <i class="bi bi-x-lg"></i>
                                        </button>

                                    <?php elseif (in_array($b['status'], ['pending','approved','rescheduled'])): ?>
                                    <?php endif; ?>

                                    <?php if (in_array($b['status'], ['approved','rescheduled'])): ?>
                                        <!-- Reschedule -->
                                        <button class="btn btn-sm btn-outline-primary py-0 px-2"
                                                onclick="openRescheduleModal(<?= (int)$b['id'] ?>, '<?= $b['booking_date'] ?>')">
                                            <i class="bi bi-calendar2-event"></i>
                                        </button>

                                        <!-- Cancel approved -->
                                        <button class="btn btn-sm btn-outline-danger py-0 px-2"
                                                onclick="openRejectModal(<?= (int)$b['id'] ?>)">
                                            <i class="bi bi-x-lg"></i>
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
        </div>

    </div><!-- /content -->
</div><!-- /main-wrapper -->

<!-- ── View Details Modal ──────────────────────────────────── -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">Booking Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="detail-label">Client</div>
                        <div class="detail-value" id="vClient"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Email</div>
                        <div class="detail-value" id="vEmail"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Package</div>
                        <div class="detail-value" id="vPackage"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Booking Date</div>
                        <div class="detail-value" id="vDate"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Event Type</div>
                        <div class="detail-value" id="vEventType"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Venue</div>
                        <div class="detail-value" id="vVenue"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Status</div>
                        <div class="detail-value" id="vStatus"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Booked On</div>
                        <div class="detail-value" id="vCreated"></div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label">Notes</div>
                        <div class="detail-value" id="vNotes"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Reschedule Modal ────────────────────────────────────── -->
<div class="modal fade" id="rescheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="action" value="reschedule">
            <input type="hidden" name="id" id="rescheduleId">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Reschedule Booking</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold">Current Date</label>
                    <input type="text" class="form-control mb-3" id="currentDate" disabled>
                    <label class="form-label fw-semibold">New Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="new_date" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm text-white" style="background:var(--gold);">
                        Confirm Reschedule
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ── Reject / Cancel Modal ──────────────────────────────── -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" id="rejectId">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold text-danger">Cancel Booking</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold">Reason for Cancellation</label>
                    <textarea class="form-control" name="reason" rows="3"
                              placeholder="Optional: provide a reason..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Back</button>
                    <button type="submit" class="btn btn-danger btn-sm">Confirm Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const statusColors = {
        pending:     'warning',
        approved:    'success',
        rescheduled: 'info',
        cancelled:   'danger'
    };

    function viewBooking(b) {
        document.getElementById('vClient').textContent    = b.client        || '—';
        document.getElementById('vEmail').textContent     = b.client_email  || '—';
        document.getElementById('vPackage').textContent   = b.package       || '—';
        document.getElementById('vDate').textContent      = b.booking_date  || '—';
        document.getElementById('vEventType').textContent = b.event_type    || '—';
        document.getElementById('vVenue').textContent     = b.venue         || '—';
        document.getElementById('vNotes').textContent     = b.notes         || '—';
        document.getElementById('vCreated').textContent   = b.created_at    || '—';

        const color = statusColors[b.status] || 'secondary';
        document.getElementById('vStatus').innerHTML =
            `<span class="badge bg-${color}">${b.status.charAt(0).toUpperCase() + b.status.slice(1)}</span>`;

        new bootstrap.Modal(document.getElementById('viewModal')).show();
    }

    function openRescheduleModal(id, currentDate) {
        document.getElementById('rescheduleId').value  = id;
        document.getElementById('currentDate').value   = currentDate;
        new bootstrap.Modal(document.getElementById('rescheduleModal')).show();
    }

    function openRejectModal(id) {
        document.getElementById('rejectId').value = id;
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    }
</script>
</body>
</html>
