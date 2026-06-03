<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $name  = trim($_POST['name']  ?? '');
        $role  = trim($_POST['role']  ?? '');
        $email = trim($_POST['email'] ?? '') ?: null; // NULL instead of empty string to avoid UNIQUE conflict
        $phone = trim($_POST['phone'] ?? '') ?: null;

        if ($name === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Staff name is required.'];
            header('Location: staff.php');
            exit;
        }

        if ($action === 'add') {
            // Check duplicate email only if provided
            if ($email !== null) {
                $dup = $pdo->prepare("SELECT id FROM staff WHERE email = ?");
                $dup->execute([$email]);
                if ($dup->fetch()) {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'A staff member with that email already exists.'];
                    header('Location: staff.php'); exit;
                }
            }
            $pdo->prepare("INSERT INTO staff (name, role, email, phone) VALUES (?, ?, ?, ?)")
                ->execute([$name, $role, $email, $phone]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Staff member added.'];
        } else {
            $id = (int)($_POST['id'] ?? 0);
            // Check duplicate email excluding self
            if ($email !== null) {
                $dup = $pdo->prepare("SELECT id FROM staff WHERE email = ? AND id != ?");
                $dup->execute([$email, $id]);
                if ($dup->fetch()) {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'A staff member with that email already exists.'];
                    header('Location: staff.php'); exit;
                }
            }
            $pdo->prepare("UPDATE staff SET name=?, role=?, email=?, phone=? WHERE id=?")
                ->execute([$name, $role, $email, $phone, $id]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Staff member updated.'];
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Remove schedules first, then delete staff
        $pdo->prepare("DELETE FROM staff_schedules WHERE staff_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM staff WHERE id = ?")->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Staff member deleted.'];
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = in_array($_POST['new_status'] ?? '', ['active', 'inactive']) ? $_POST['new_status'] : 'inactive';
        $pdo->prepare("UPDATE staff SET status=? WHERE id=?")->execute([$newStatus, $id]);
        $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Staff status updated.'];
    } elseif ($action === 'assign') {
        $staffId   = (int)($_POST['staff_id']   ?? 0);
        $bookingId = (int)($_POST['booking_id'] ?? 0);

        $row = $pdo->prepare("SELECT booking_date FROM bookings WHERE id = ?");
        $row->execute([$bookingId]);
        $booking = $row->fetch();

        if (!$booking || !$staffId) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Invalid booking or staff.'];
        } else {
            // Check if already assigned to this booking
            $already = $pdo->prepare("SELECT id FROM staff_schedules WHERE staff_id = ? AND booking_id = ?");
            $already->execute([$staffId, $bookingId]);
            if ($already->fetch()) {
                $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Staff is already assigned to this booking.'];
            } else {
                // Conflict: staff assigned to a DIFFERENT booking on the same date
                $conflict = $pdo->prepare("
                    SELECT COUNT(*) FROM staff_schedules
                    WHERE staff_id = ? AND booking_date = ? AND booking_id != ?
                ");
                $conflict->execute([$staffId, $booking['booking_date'], $bookingId]);

                if ((int)$conflict->fetchColumn() > 0) {
                    $_SESSION['flash'] = ['type' => 'danger', 'msg' => "This staff is already assigned on {$booking['booking_date']}."];
                } else {
                    $pdo->prepare("INSERT INTO staff_schedules (staff_id, booking_id, booking_date) VALUES (?, ?, ?)") 
                        ->execute([$staffId, $bookingId, $booking['booking_date']]);
                    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Staff added to booking.'];
                }
            }
        }

    } elseif ($action === 'unassign') {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $pdo->prepare("DELETE FROM staff_schedules WHERE id = ?")->execute([$scheduleId]);
        $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Staff removed from booking.'];
    }
    header('Location: staff.php');
    exit;
}

$staffList = $pdo->query("SELECT * FROM staff ORDER BY created_at DESC")->fetchAll();

// Fetch approved bookings
$assignableBookings = $pdo->query("
    SELECT b.id, b.booking_date, b.event_type, b.venue, u.name AS client
    FROM bookings b
    JOIN users u ON b.client_id = u.id
    WHERE b.status IN ('approved','rescheduled')
    ORDER BY b.booking_date ASC
")->fetchAll();

// Fetch ALL assignments grouped by booking_id
$assignStmt = $pdo->query("
    SELECT ss.id AS schedule_id, ss.booking_id, ss.staff_id, s.name AS staff_name
    FROM staff_schedules ss
    JOIN staff s ON ss.staff_id = s.id
");
$assignmentsByBooking = [];
foreach ($assignStmt->fetchAll() as $a) {
    $assignmentsByBooking[$a['booking_id']][] = $a;
}

$scheduleRows = [];
$viewingStaff = null;
if (isset($_GET['view_staff'])) {
    $vsId = (int)$_GET['view_staff'];
    $vs = $pdo->prepare("SELECT * FROM staff WHERE id=?");
    $vs->execute([$vsId]);
    $viewingStaff = $vs->fetch();
    if ($viewingStaff) {
        $sched = $pdo->prepare("
            SELECT b.booking_date, b.event_type, b.venue, u.name AS client
            FROM staff_schedules ss JOIN bookings b ON ss.booking_id=b.id JOIN users u ON b.client_id=u.id
            WHERE ss.staff_id=? ORDER BY b.booking_date ASC
        ");
        $sched->execute([$vsId]);
        $scheduleRows = $sched->fetchAll();
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$pageTitle  = 'Staff';
$activePage = 'staff';
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
            <div id="liveToast" class="toast align-items-center border-0 show" role="alert"
                style="background:<?= $flash['type'] === 'success' ? '#f0fdf4' : ($flash['type'] === 'danger' ? '#fef2f2' : ($flash['type'] === 'info' ? '#f0f9ff' : '#fffbeb')) ?>">
                <div class="toast-header" style="background:transparent;">
                    <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill text-success' : ($flash['type'] === 'danger' ? 'bi-x-circle-fill text-danger' : 'bi-info-circle-fill text-info') ?> me-2"></i>
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
                    <div class="topbar-title">Staff</div>
                    <div class="topbar-sub">Manage staff and assignments</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
                <?php if ($_adminAvatar): ?><img src="../assets/avatars/<?= htmlspecialchars($_adminAvatar) ?>" class="topbar-avatar" style="object-fit:cover;"><?php else: ?><div class="topbar-avatar"><?= $_adminInitial ?></div><?php endif; ?>
            </div>
        </div>

        <div class="p-3 p-md-4">

            <!-- Staff List -->
            <div class="dash-card mb-4">
                <div class="dash-card-header">
                    <h6>Staff Members <span class="badge bg-secondary ms-2"><?= count($staffList) ?></span></h6>
                    <button class="btn btn-dark btn-sm px-3"
                        data-bs-toggle="modal" data-bs-target="#staffModal"
                        onclick="openAddModal()">
                        <i class="bi bi-plus-lg me-1"></i> Add Staff
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table modern-table mobile-cards mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($staffList)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-people d-block fs-3 mb-2 opacity-25"></i>No staff found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($staffList as $s): ?>
                                    <tr>
                                        <td data-label="#" class="text-muted"><?= (int)$s['id'] ?></td>
                                        <td data-label="Name">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="avatar-sm"><?= strtoupper(substr($s['name'], 0, 1)) ?></span>
                                                <span class="fw-semibold"><?= htmlspecialchars($s['name']) ?></span>
                                            </div>
                                        </td>
                                        <td data-label="Role"><?= htmlspecialchars($s['role'] ?? '—') ?></td>
                                        <td data-label="Email" class="text-muted"><?= htmlspecialchars($s['email'] ?? '—') ?></td>
                                        <td data-label="Phone"><?= htmlspecialchars($s['phone'] ?? '—') ?></td>
                                        <td data-label="Status">
                                            <span class="badge <?= $s['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= ucfirst($s['status']) ?>
                                            </span>
                                        </td>
                                        <td data-label="Actions">
                                            <div class="d-flex gap-1">
                                                <button class="btn-action" title="Edit"
                                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn-action <?= $s['status'] === 'active' ? 'warning' : 'success' ?>"
                                                    title="<?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?>"
                                                    onclick="openToggleStaffModal(<?= (int)$s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>', '<?= $s['status'] === 'active' ? 'inactive' : 'active' ?>')">
                                                    <i class="bi <?= $s['status'] === 'active' ? 'bi-person-dash' : 'bi-person-check' ?>"></i>
                                                </button>
                                                <a href="staff.php?view_staff=<?= (int)$s['id'] ?>" class="btn-action" title="View Schedule">
                                                    <i class="bi bi-calendar3"></i>
                                                </a>
                                                <button type="button" class="btn-action danger" title="Delete"
                                                        onclick="openDeleteModal(<?= (int)$s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Staff Schedule -->
            <?php if ($viewingStaff): ?>
                <div class="dash-card mb-4">
                    <div class="dash-card-header">
                        <h6>Schedule — <?= htmlspecialchars($viewingStaff['name']) ?></h6>
                        <a href="staff.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x me-1"></i>Close</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table modern-table mobile-cards mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Event Type</th>
                                    <th>Venue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($scheduleRows)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No assignments found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($scheduleRows as $sr): ?>
                                        <tr>
                                            <td data-label="Date" class="fw-semibold"><?= htmlspecialchars($sr['booking_date']) ?></td>
                                            <td data-label="Client"><?= htmlspecialchars($sr['client']) ?></td>
                                            <td data-label="Event"><?= htmlspecialchars($sr['event_type'] ?? '—') ?></td>
                                            <td data-label="Venue"><?= htmlspecialchars($sr['venue'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Manual Assignment -->
            <div class="dash-card">
                <div class="dash-card-header">
                    <div>
                        <h6>Manual Staff Assignment</h6>
                        <p class="mb-0" style="font-size:.75rem;color:#888;">Add multiple staff to approved bookings. Conflict detection enforced.</p>
                    </div>
                    <span class="badge bg-secondary"><?= count($assignableBookings) ?> booking(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table modern-table mobile-cards mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Event / Venue</th>
                                <th>Assigned Staff</th>
                                <th style="width:220px;">Add Staff</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assignableBookings)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No approved bookings.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assignableBookings as $ab):
                                    $assigned    = $assignmentsByBooking[$ab['id']] ?? [];
                                    $assignedIds = array_column($assigned, 'staff_id');
                                ?>
                                    <tr>
                                        <td data-label="Date" class="fw-semibold"><?= htmlspecialchars($ab['booking_date']) ?></td>
                                        <td data-label="Client"><?= htmlspecialchars($ab['client']) ?></td>
                                        <td data-label="Event">
                                            <div><?= htmlspecialchars($ab['event_type'] ?? '—') ?></div>
                                            <div style="font-size:.74rem;color:#aaa;"><?= htmlspecialchars($ab['venue'] ?? '') ?></div>
                                        </td>
                                        <td data-label="Assigned">
                                            <?php if (empty($assigned)): ?>
                                                <span class="badge bg-secondary">None</span>
                                            <?php else: ?>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php foreach ($assigned as $asg): ?>
                                                        <span class="badge bg-success d-inline-flex align-items-center gap-1" style="font-size:.75rem;">
                                                            <?= htmlspecialchars($asg['staff_name']) ?>
                                                            <form method="POST" class="d-inline m-0 p-0">
                                                                <input type="hidden" name="action" value="unassign">
                                                                <input type="hidden" name="schedule_id" value="<?= (int)$asg['schedule_id'] ?>">
                                                                <button type="submit" title="Remove"
                                                                        style="background:none;border:none;padding:0 0 0 2px;line-height:1;cursor:pointer;color:#fff;">
                                                                    <i class="bi bi-x" style="font-size:.8rem;"></i>
                                                                </button>
                                                            </form>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Add Staff">
                                            <form method="POST" class="d-flex gap-2 align-items-center">
                                                <input type="hidden" name="action" value="assign">
                                                <input type="hidden" name="booking_id" value="<?= (int)$ab['id'] ?>">
                                                <select name="staff_id" class="form-select form-select-sm" style="min-width:130px;" required>
                                                    <option value="">— Add —</option>
                                                    <?php foreach ($staffList as $sm):
                                                        if ($sm['status'] !== 'active') continue;
                                                        $isAssigned = in_array($sm['id'], $assignedIds);
                                                    ?>
                                                        <option value="<?= (int)$sm['id'] ?>" <?= $isAssigned ? 'disabled' : '' ?>>
                                                            <?= htmlspecialchars($sm['name']) ?><?= $isAssigned ? ' (assigned)' : '' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-dark btn-sm">
                                                    <i class="bi bi-plus-lg"></i>
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
    </div>

    <!-- Delete Staff Modal -->
    <div class="modal fade" id="deleteStaffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteStaffId">
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="modal-title fw-semibold text-danger">
                            <i class="bi bi-trash me-1"></i> Delete Staff
                        </span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="font-size:.875rem;">
                        Are you sure you want to delete
                        <strong id="deleteStaffName"></strong>?
                        <div class="alert alert-warning py-2 mt-3 mb-0" style="font-size:.8rem;">
                            <i class="bi bi-exclamation-triangle"></i>
                            All schedule assignments for this staff will also be removed.
                            This cannot be undone.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Toggle Staff Status Modal -->
    <div class="modal fade" id="toggleStaffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="w-100">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" id="tsId">
                <input type="hidden" name="new_status" id="tsNewStatus">
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="modal-title" id="tsTitle">Update Staff Status</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0" style="font-size:.875rem;"><span id="tsVerb">Deactivate</span> staff member <strong id="tsName"></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark btn-sm">Confirm</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Add/Edit Staff Modal -->
    <div class="modal fade" id="staffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId">
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="modal-title" id="staffModalLabel">Add Staff</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="fieldName" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" name="role" id="fieldRole" placeholder="e.g. Photographer">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="fieldEmail">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" id="fieldPhone">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark btn-sm">Save Staff</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('show'));

        function openDeleteModal(id, name) {
            document.getElementById('deleteStaffId').value      = id;
            document.getElementById('deleteStaffName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteStaffModal')).show();
        }

        function openToggleStaffModal(id, name, newStatus) {
            document.getElementById('tsId').value = id;
            document.getElementById('tsNewStatus').value = newStatus;
            document.getElementById('tsName').textContent = name;
            const verb = newStatus === 'inactive' ? 'Deactivate' : 'Activate';
            document.getElementById('tsVerb').textContent = verb;
            document.getElementById('tsTitle').textContent = verb + ' Staff';
            new bootstrap.Modal(document.getElementById('toggleStaffModal')).show();
        }

        function openAddModal() {
            document.getElementById('staffModalLabel').textContent = 'Add Staff';
            document.getElementById('formAction').value = 'add';
            document.getElementById('formId').value = '';
            ['fieldName', 'fieldRole', 'fieldEmail', 'fieldPhone'].forEach(id => document.getElementById(id).value = '');
        }

        function openEditModal(s) {
            document.getElementById('staffModalLabel').textContent = 'Edit Staff';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formId').value = s.id;
            document.getElementById('fieldName').value = s.name ?? '';
            document.getElementById('fieldRole').value = s.role ?? '';
            document.getElementById('fieldEmail').value = s.email ?? '';
            document.getElementById('fieldPhone').value = s.phone ?? '';
            new bootstrap.Modal(document.getElementById('staffModal')).show();
        }
        document.addEventListener('DOMContentLoaded', () => {
            const t = document.getElementById('liveToast');
            if (t) setTimeout(() => bootstrap.Toast.getOrCreateInstance(t).hide(), 4000);
        });
    </script>
</body>

</html>