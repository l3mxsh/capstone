<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'approve' && $id) {
        $booking = $pdo->prepare("SELECT booking_date FROM bookings WHERE id = ?");
        $booking->execute([$id]);
        $row = $booking->fetch();
        if ($row) {
            $staffStmt = $pdo->prepare("SELECT id FROM staff WHERE status='active' AND id NOT IN (SELECT staff_id FROM staff_schedules WHERE booking_date=?) LIMIT 1");
            $staffStmt->execute([$row['booking_date']]);
            $staff = $staffStmt->fetch();
            if ($staff) {
                $pdo->prepare("UPDATE bookings SET status='approved' WHERE id=?")->execute([$id]);
                $pdo->prepare("INSERT INTO staff_schedules (staff_id, booking_id, booking_date) VALUES (?, ?, ?)")->execute([$staff['id'], $id, $row['booking_date']]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Booking approved and staff assigned.'];
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'No available staff on that date.'];
            }
        }
    } elseif ($action === 'reschedule' && $id) {
        $newDate = $_POST['new_date'] ?? '';
        if (!$newDate || !strtotime($newDate)) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Invalid date provided.'];
        } else {
            $conflict = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE booking_date=? AND status='approved' AND id!=?");
            $conflict->execute([$newDate, $id]);
            if ((int)$conflict->fetchColumn() > 0) {
                $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Date conflict: another approved booking exists on that date.'];
            } else {
                $pdo->prepare("DELETE FROM staff_schedules WHERE booking_id=?")->execute([$id]);
                $pdo->prepare("UPDATE bookings SET booking_date=?, status='rescheduled' WHERE id=?")->execute([$newDate, $id]);
                $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Booking rescheduled successfully.'];
            }
        }
    } elseif ($action === 'reject' && $id) {
        $reason = trim($_POST['reason'] ?? '');

        // Fetch client_id before updating
        $clientStmt = $pdo->prepare("SELECT client_id FROM bookings WHERE id = ?");
        $clientStmt->execute([$id]);
        $clientId = (int)($clientStmt->fetchColumn() ?: 0);

        $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM staff_schedules WHERE booking_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM cancellations WHERE booking_id=? AND cancellation_status='pending_approval'")->execute([$id]);

        // Admin cancels directly — insert as 'approved' so it goes straight to the log
        // and does NOT appear as a pending request in cancellations.php
        $pdo->prepare("
            INSERT INTO cancellations (booking_id, client_id, reason, deposit_amount, deposit_retained, cancellation_status, initiated_by)
            VALUES (?, ?, ?, 0, 0, 'approved', 'admin')
        ")->execute([$id, $clientId, $reason]);

        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Booking has been cancelled.'];
    }

    header('Location: bookings.php');
    exit;
}

$filterStatus   = $_GET['status'] ?? '';
$filterClientId = (int)($_GET['client_id'] ?? 0);
$allowed        = ['pending', 'approved', 'rescheduled', 'cancelled'];
$where = [];
$params = [];
if (in_array($filterStatus, $allowed)) {
    $where[] = 'b.status=?';
    $params[] = $filterStatus;
}
if ($filterClientId > 0) {
    $where[] = 'b.client_id=?';
    $params[] = $filterClientId;
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT b.id, u.name AS client, u.email AS client_email,
           p.name AS package, b.booking_date, b.event_type, b.venue, b.notes, b.status, b.created_at
    FROM bookings b JOIN users u ON b.client_id=u.id JOIN packages p ON b.package_id=p.id
    {$whereClause} ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$statusBadge = ['pending' => 'warning', 'approved' => 'success', 'rescheduled' => 'info', 'cancelled' => 'danger'];

$pageTitle  = 'Bookings';
$activePage = 'bookings';
require_once '../includes/admin_head.php';
$_adminAvatar  ??= null;
$_adminInitial ??= strtoupper(substr($_SESSION['name'], 0, 1));
?>
</head>

<body>
    <?php require_once '../includes/sidebar.php'; ?>

    <!-- Toast -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <?php if ($flash): ?>
            <div id="liveToast" class="toast align-items-center border-0 show"
                role="alert" data-bs-delay="4000"
                style="background:<?= $flash['type'] === 'success' ? '#f0fdf4' : ($flash['type'] === 'danger' ? '#fef2f2' : ($flash['type'] === 'warning' ? '#fffbeb' : '#f0f9ff')) ?>">
                <div class="toast-header" style="background:transparent;">
                    <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill text-success' : ($flash['type'] === 'danger' ? 'bi-x-circle-fill text-danger' : ($flash['type'] === 'warning' ? 'bi-exclamation-triangle-fill text-warning' : 'bi-info-circle-fill text-info')) ?> me-2"></i>
                    <strong class="me-auto"><?= ucfirst($flash['type'] === 'danger' ? 'Error' : $flash['type']) ?></strong>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body"><?= htmlspecialchars($flash['msg']) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <div id="main-wrapper">
        <div id="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="topbar-btn d-lg-none border-0" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
                <div>
                    <div class="topbar-title">Bookings</div>
                    <div class="topbar-sub">Manage all client bookings</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
                <?php if ($_adminAvatar): ?><img src="../assets/avatars/<?= htmlspecialchars($_adminAvatar) ?>" class="topbar-avatar" style="object-fit:cover;">
                <?php else: ?>
                    <div class="topbar-avatar"><?= $_adminInitial ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-3 p-md-4">
            <div class="dash-card">
                <div class="dash-card-header flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <h6>Booking Management</h6>
                        <?php if ($filterClientId > 0): ?>
                            <span class="badge bg-secondary">
                                Client #<?= $filterClientId ?>
                                <a href="bookings.php" class="text-white ms-1"><i class="bi bi-x"></i></a>
                            </span>
                        <?php endif; ?>
                    </div>
                    <form method="GET" class="d-flex align-items-center gap-2">
                        <?php if ($filterClientId > 0): ?><input type="hidden" name="client_id" value="<?= $filterClientId ?>"><?php endif; ?>
                        <select name="status" class="form-select form-select-sm" style="width:150px;" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <?php foreach ($allowed as $s): ?>
                                <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table modern-table mobile-cards mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Client</th>
                                <th>Package</th>
                                <th>Booking Date</th>
                                <th>Event Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-calendar-x d-block fs-3 mb-2 opacity-25"></i>No bookings found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td data-label="#" class="text-muted">#<?= (int)$b['id'] ?></td>
                                        <td data-label="Client">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="avatar-sm"><?= strtoupper(substr($b['client'], 0, 1)) ?></span>
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($b['client']) ?></div>
                                                    <div style="font-size:.74rem;color:#aaa;"><?= htmlspecialchars($b['client_email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Package"><?= htmlspecialchars($b['package']) ?></td>
                                        <td data-label="Date"><?= htmlspecialchars($b['booking_date']) ?></td>
                                        <td data-label="Event"><?= htmlspecialchars($b['event_type'] ?? '—') ?></td>
                                        <td data-label="Status"><span class="badge bg-<?= $statusBadge[$b['status']] ?? 'secondary' ?>"><?= ucfirst($b['status']) ?></span></td>
                                        <td data-label="Actions">
                                            <div class="d-flex gap-1">
                                                <button class="btn-action" title="View Details"
                                                    onclick="viewBooking(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if ($b['status'] === 'pending'): ?>
                                                    <button class="btn-action success" title="Approve"
                                                        onclick="openApproveModal(<?= (int)$b['id'] ?>)">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button class="btn-action danger" title="Cancel"
                                                        onclick="openRejectModal(<?= (int)$b['id'] ?>)">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (in_array($b['status'], ['approved', 'rescheduled'])): ?>
                                                    <button class="btn-action primary" title="Reschedule"
                                                        onclick="openRescheduleModal(<?= (int)$b['id'] ?>, '<?= $b['booking_date'] ?>')">
                                                        <i class="bi bi-calendar2-event"></i>
                                                    </button>
                                                    <button class="btn-action danger" title="Cancel"
                                                        onclick="openRejectModal(<?= (int)$b['id'] ?>)">
                                                        <i class="bi bi-x-lg"></i>
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

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" id="approveId">
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="modal-title">Approve Booking</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0" style="font-size:.875rem;">Are you sure you want to approve this booking? A staff member will be automatically assigned.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title">Booking Details</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <?php
                        $fields = [
                            ['Client', 'vClient'],
                            ['Email', 'vEmail'],
                            ['Package', 'vPackage'],
                            ['Booking Date', 'vDate'],
                            ['Event Type', 'vEventType'],
                            ['Venue', 'vVenue'],
                            ['Status', 'vStatus'],
                            ['Booked On', 'vCreated']
                        ];
                        foreach ($fields as [$lbl, $id]):
                        ?>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <div class="info-label"><?= $lbl ?></div>
                                    <div class="fw-semibold mt-1" id="<?= $id ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-12">
                            <div class="info-box">
                                <div class="info-label">Notes</div>
                                <div class="mt-1" id="vNotes"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST">
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="id" id="rescheduleId">
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="modal-title">Reschedule Booking</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Current Date</label>
                            <input type="text" class="form-control" id="currentDate" disabled>
                        </div>
                        <div>
                            <label class="form-label">New Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="new_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark btn-sm">Confirm Reschedule</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="w-100">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" id="rejectId">
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="modal-title text-danger">Cancel Booking</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Reason for Cancellation</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Optional reason…"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Back</button>
                        <button type="submit" class="btn btn-danger btn-sm">Confirm Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('show'));

        const statusBadge = {
            pending: 'warning',
            approved: 'success',
            rescheduled: 'info',
            cancelled: 'danger'
        };

        function openApproveModal(id) {
            document.getElementById('approveId').value = id;
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }

        function viewBooking(b) {
            document.getElementById('vClient').textContent = b.client || '—';
            document.getElementById('vEmail').textContent = b.client_email || '—';
            document.getElementById('vPackage').textContent = b.package || '—';
            document.getElementById('vDate').textContent = b.booking_date || '—';
            document.getElementById('vEventType').textContent = b.event_type || '—';
            document.getElementById('vVenue').textContent = b.venue || '—';
            document.getElementById('vNotes').textContent = b.notes || '—';
            document.getElementById('vCreated').textContent = b.created_at || '—';
            const c = statusBadge[b.status] || 'secondary';
            document.getElementById('vStatus').innerHTML = `<span class="badge bg-${c}">${b.status.charAt(0).toUpperCase()+b.status.slice(1)}</span>`;
            new bootstrap.Modal(document.getElementById('viewModal')).show();
        }

        function openRescheduleModal(id, date) {
            document.getElementById('rescheduleId').value = id;
            document.getElementById('currentDate').value = date;
            new bootstrap.Modal(document.getElementById('rescheduleModal')).show();
        }

        function openRejectModal(id) {
            document.getElementById('rejectId').value = id;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
        // Auto-dismiss toast
        document.addEventListener('DOMContentLoaded', () => {
            const t = document.getElementById('liveToast');
            if (t) setTimeout(() => bootstrap.Toast.getOrCreateInstance(t).hide(), 4000);
        });
    </script>
</body>

</html>