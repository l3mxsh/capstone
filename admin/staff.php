<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

// ── POST Handler (PRG) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD / EDIT STAFF ──────────────────────────────────────
    if ($action === 'add' || $action === 'edit') {
        $name  = trim($_POST['name']  ?? '');
        $role  = trim($_POST['role']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Staff name is required.'];
            header('Location: staff.php'); exit;
        }

        if ($action === 'add') {
            $pdo->prepare("INSERT INTO staff (name, role, email, phone) VALUES (?, ?, ?, ?)")
                ->execute([$name, $role, $email, $phone]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Staff member added.'];
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE staff SET name=?, role=?, email=?, phone=? WHERE id=?")
                ->execute([$name, $role, $email, $phone, $id]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Staff member updated.'];
        }

    // ── TOGGLE STATUS (activate / deactivate) ─────────────────
    } elseif ($action === 'toggle_status') {
        $id        = (int)($_POST['id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'inactive';
        $newStatus = in_array($newStatus, ['active','inactive']) ? $newStatus : 'inactive';
        $pdo->prepare("UPDATE staff SET status=? WHERE id=?")->execute([$newStatus, $id]);
        $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Staff status updated.'];

    // ── MANUAL STAFF ASSIGNMENT ───────────────────────────────
    } elseif ($action === 'assign') {
        $staffId   = (int)($_POST['staff_id']  ?? 0);
        $bookingId = (int)($_POST['booking_id'] ?? 0);

        // Get booking date
        $row = $pdo->prepare("SELECT booking_date FROM bookings WHERE id = ?");
        $row->execute([$bookingId]);
        $booking = $row->fetch();

        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Booking not found.'];
        } else {
            $bookingDate = $booking['booking_date'];

            // Conflict check
            $conflict = $pdo->prepare("
                SELECT COUNT(*) FROM staff_schedules
                WHERE staff_id = ? AND booking_date = ? AND booking_id != ?
            ");
            $conflict->execute([$staffId, $bookingDate, $bookingId]);

            if ((int)$conflict->fetchColumn() > 0) {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => "This staff is already assigned on {$bookingDate}."];
            } else {
                // Upsert: update if exists, insert if not
                $exists = $pdo->prepare("SELECT id FROM staff_schedules WHERE booking_id = ?");
                $exists->execute([$bookingId]);

                if ($exists->fetch()) {
                    $pdo->prepare("UPDATE staff_schedules SET staff_id=?, booking_date=? WHERE booking_id=?")
                        ->execute([$staffId, $bookingDate, $bookingId]);
                } else {
                    $pdo->prepare("INSERT INTO staff_schedules (staff_id, booking_id, booking_date) VALUES (?, ?, ?)")
                        ->execute([$staffId, $bookingId, $bookingDate]);
                }
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Staff assignment updated.'];
            }
        }
    }

    header('Location: staff.php'); exit;
}

// ── GET data ───────────────────────────────────────────────────
$staffList = $pdo->query("SELECT * FROM staff ORDER BY created_at DESC")->fetchAll();

// All approved/rescheduled bookings for manual assignment dropdown
$assignableBookings = $pdo->query("
    SELECT b.id, b.booking_date, b.event_type, b.venue,
           u.name AS client,
           ss.staff_id AS assigned_staff_id
    FROM bookings b
    JOIN users u ON b.client_id = u.id
    LEFT JOIN staff_schedules ss ON ss.booking_id = b.id
    WHERE b.status IN ('approved','rescheduled')
    ORDER BY b.booking_date ASC
")->fetchAll();

// Staff schedule: load per-staff view via ?view_staff=ID
$scheduleRows  = [];
$viewingStaff  = null;
if (isset($_GET['view_staff'])) {
    $vsId = (int)$_GET['view_staff'];
    $vs   = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $vs->execute([$vsId]);
    $viewingStaff = $vs->fetch();

    if ($viewingStaff) {
        $sched = $pdo->prepare("
            SELECT b.booking_date, b.event_type, b.venue, u.name AS client
            FROM staff_schedules ss
            JOIN bookings b ON ss.booking_id = b.id
            JOIN users u    ON b.client_id   = u.id
            WHERE ss.staff_id = ?
            ORDER BY b.booking_date ASC
        ");
        $sched->execute([$vsId]);
        $scheduleRows = $sched->fetchAll();
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle  = 'Staff — Harvy Mance Films';
$activePage = 'staff';
require_once '../includes/admin_head.php';
?>
<style>
    .detail-label { font-size:.78rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.4px; }
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

    <div class="p-4">

        <!-- Flash -->
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ── STAFF LIST ──────────────────────────────────── -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 fw-semibold">Staff Management</h5>
            <button class="btn btn-sm text-white" style="background:var(--gold);"
                    data-bs-toggle="modal" data-bs-target="#staffModal"
                    onclick="openAddModal()">
                <i class="bi bi-plus-lg"></i> Add Staff
            </button>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th style="width:210px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($staffList)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No staff found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($staffList as $s): ?>
                            <tr>
                                <td><?= (int)$s['id'] ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($s['name']) ?></td>
                                <td><?= htmlspecialchars($s['role'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($s['email'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($s['phone'] ?? '—') ?></td>
                                <td>
                                    <?php if ($s['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-flex gap-1 flex-wrap">
                                    <!-- Edit -->
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>

                                    <!-- Toggle status -->
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                        <?php if ($s['status'] === 'active'): ?>
                                            <input type="hidden" name="new_status" value="inactive">
                                            <button class="btn btn-sm btn-outline-warning py-0 px-2"
                                                    onclick="return confirm('Deactivate this staff?')">
                                                <i class="bi bi-person-dash"></i> Deactivate
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="new_status" value="active">
                                            <button class="btn btn-sm btn-outline-success py-0 px-2">
                                                <i class="bi bi-person-check"></i> Activate
                                            </button>
                                        <?php endif; ?>
                                    </form>

                                    <!-- View Schedule -->
                                    <a href="staff.php?view_staff=<?= (int)$s['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary py-0 px-2">
                                        <i class="bi bi-calendar3"></i> Schedule
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── STAFF SCHEDULE VIEW ─────────────────────────── -->
        <?php if ($viewingStaff): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
                <h6 class="mb-0 fw-semibold">
                    Schedule for: <span style="color:var(--gold);"><?= htmlspecialchars($viewingStaff['name']) ?></span>
                </h6>
                <a href="staff.php" class="btn btn-sm btn-outline-secondary py-0 px-2">
                    <i class="bi bi-x"></i> Close
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Booking Date</th>
                                <th>Client</th>
                                <th>Event Type</th>
                                <th>Venue</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($scheduleRows)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No assignments found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($scheduleRows as $sr): ?>
                            <tr>
                                <td><?= htmlspecialchars($sr['booking_date']) ?></td>
                                <td><?= htmlspecialchars($sr['client']) ?></td>
                                <td><?= htmlspecialchars($sr['event_type'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($sr['venue'] ?? '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── MANUAL STAFF ASSIGNMENT ────────────────────── -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="mb-0 fw-semibold">Manual Staff Assignment</h6>
                <p class="text-muted mb-0" style="font-size:.8rem;">
                    Reassign staff to approved bookings. Conflict detection is enforced.
                </p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Event / Venue</th>
                                <th>Assigned Staff</th>
                                <th style="width:220px;">Reassign</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($assignableBookings)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No approved bookings.</td></tr>
                        <?php else: ?>
                            <?php foreach ($assignableBookings as $ab):
                                // Find current assigned staff name
                                $assignedName = '—';
                                if ($ab['assigned_staff_id']) {
                                    foreach ($staffList as $sm) {
                                        if ($sm['id'] == $ab['assigned_staff_id']) {
                                            $assignedName = $sm['name'];
                                            break;
                                        }
                                    }
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($ab['booking_date']) ?></td>
                                <td><?= htmlspecialchars($ab['client']) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($ab['event_type'] ?? '—') ?></div>
                                    <div class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($ab['venue'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $ab['assigned_staff_id'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= htmlspecialchars($assignedName) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="d-flex gap-2 align-items-center">
                                        <input type="hidden" name="action" value="assign">
                                        <input type="hidden" name="booking_id" value="<?= (int)$ab['id'] ?>">
                                        <select name="staff_id" class="form-select form-select-sm" style="min-width:130px;" required>
                                            <option value="">— Select —</option>
                                            <?php foreach ($staffList as $sm):
                                                if ($sm['status'] !== 'active') continue; ?>
                                                <option value="<?= (int)$sm['id'] ?>"
                                                    <?= $ab['assigned_staff_id'] == $sm['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($sm['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm text-white py-0 px-2" style="background:var(--gold);">
                                            <i class="bi bi-check2"></i>
                                        </button>
                                    </form>
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

<!-- ── Add / Edit Staff Modal ──────────────────────────────── -->
<div class="modal fade" id="staffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id"     id="formId"     value="">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold" id="staffModalLabel">Add Staff</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="fieldName" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Role</label>
                            <input type="text" class="form-control" name="role" id="fieldRole"
                                   placeholder="e.g. Photographer, Videographer">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" name="email" id="fieldEmail">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" class="form-control" name="phone" id="fieldPhone">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm text-white" style="background:var(--gold);">
                        Save Staff
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openAddModal() {
        document.getElementById('staffModalLabel').textContent = 'Add Staff';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value     = '';
        document.getElementById('fieldName').value  = '';
        document.getElementById('fieldRole').value  = '';
        document.getElementById('fieldEmail').value = '';
        document.getElementById('fieldPhone').value = '';
    }

    function openEditModal(s) {
        document.getElementById('staffModalLabel').textContent = 'Edit Staff';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value     = s.id;
        document.getElementById('fieldName').value  = s.name        ?? '';
        document.getElementById('fieldRole').value  = s.role        ?? '';
        document.getElementById('fieldEmail').value = s.email       ?? '';
        document.getElementById('fieldPhone').value = s.phone       ?? '';
        new bootstrap.Modal(document.getElementById('staffModal')).show();
    }
</script>
</body>
</html>
