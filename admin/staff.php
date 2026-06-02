<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name']??''); $role = trim($_POST['role']??'');
        $email = trim($_POST['email']??''); $phone = trim($_POST['phone']??'');
        if ($name==='') { $_SESSION['flash'] = ['type'=>'danger','msg'=>'Staff name is required.']; header('Location: staff.php'); exit; }
        if ($action === 'add') {
            $pdo->prepare("INSERT INTO staff (name,role,email,phone) VALUES (?,?,?,?)")->execute([$name,$role,$email,$phone]);
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Staff member added.'];
        } else {
            $id = (int)($_POST['id']??0);
            $pdo->prepare("UPDATE staff SET name=?,role=?,email=?,phone=? WHERE id=?")->execute([$name,$role,$email,$phone,$id]);
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Staff member updated.'];
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id']??0);
        $newStatus = in_array($_POST['new_status']??'',['active','inactive']) ? $_POST['new_status'] : 'inactive';
        $pdo->prepare("UPDATE staff SET status=? WHERE id=?")->execute([$newStatus,$id]);
        $_SESSION['flash'] = ['type'=>'info','msg'=>'Staff status updated.'];
    } elseif ($action === 'assign') {
        $staffId = (int)($_POST['staff_id']??0); $bookingId = (int)($_POST['booking_id']??0);
        $row = $pdo->prepare("SELECT booking_date FROM bookings WHERE id=?"); $row->execute([$bookingId]); $booking = $row->fetch();
        if (!$booking) { $_SESSION['flash'] = ['type'=>'danger','msg'=>'Booking not found.']; }
        else {
            $conflict = $pdo->prepare("SELECT COUNT(*) FROM staff_schedules WHERE staff_id=? AND booking_date=? AND booking_id!=?");
            $conflict->execute([$staffId,$booking['booking_date'],$bookingId]);
            if ((int)$conflict->fetchColumn() > 0) { $_SESSION['flash'] = ['type'=>'danger','msg'=>"Staff already assigned on {$booking['booking_date']}."]; }
            else {
                $exists = $pdo->prepare("SELECT id FROM staff_schedules WHERE booking_id=?"); $exists->execute([$bookingId]);
                if ($exists->fetch()) { $pdo->prepare("UPDATE staff_schedules SET staff_id=?,booking_date=? WHERE booking_id=?")->execute([$staffId,$booking['booking_date'],$bookingId]); }
                else { $pdo->prepare("INSERT INTO staff_schedules (staff_id,booking_id,booking_date) VALUES (?,?,?)")->execute([$staffId,$bookingId,$booking['booking_date']]); }
                $_SESSION['flash'] = ['type'=>'success','msg'=>'Staff assignment updated.'];
            }
        }
    }
    header('Location: staff.php'); exit;
}

$staffList = $pdo->query("SELECT * FROM staff ORDER BY created_at DESC")->fetchAll();
$assignableBookings = $pdo->query("
    SELECT b.id, b.booking_date, b.event_type, b.venue, u.name AS client, ss.staff_id AS assigned_staff_id
    FROM bookings b JOIN users u ON b.client_id=u.id
    LEFT JOIN staff_schedules ss ON ss.booking_id=b.id
    WHERE b.status IN ('approved','rescheduled') ORDER BY b.booking_date ASC
")->fetchAll();

$scheduleRows = []; $viewingStaff = null;
if (isset($_GET['view_staff'])) {
    $vsId = (int)$_GET['view_staff'];
    $vs = $pdo->prepare("SELECT * FROM staff WHERE id=?"); $vs->execute([$vsId]); $viewingStaff = $vs->fetch();
    if ($viewingStaff) {
        $sched = $pdo->prepare("
            SELECT b.booking_date, b.event_type, b.venue, u.name AS client
            FROM staff_schedules ss JOIN bookings b ON ss.booking_id=b.id JOIN users u ON b.client_id=u.id
            WHERE ss.staff_id=? ORDER BY b.booking_date ASC
        ");
        $sched->execute([$vsId]); $scheduleRows = $sched->fetchAll();
    }
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$pageTitle  = 'Staff — Harvy Mance Films';
$activePage = 'staff';
require_once '../includes/admin_head.php';
?>
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>

<!-- Toast -->
<div class="toast-container position-fixed top-0 end-0 p-3">
<?php if ($flash): ?>
    <div id="liveToast" class="toast align-items-center border-0 show" role="alert"
         style="background:<?= $flash['type']==='success'?'#f0fdf4':($flash['type']==='danger'?'#fef2f2':($flash['type']==='info'?'#f0f9ff':'#fffbeb')) ?>">
        <div class="toast-header" style="background:transparent;">
            <i class="bi <?= $flash['type']==='success'?'bi-check-circle-fill text-success':($flash['type']==='danger'?'bi-x-circle-fill text-danger':'bi-info-circle-fill text-info') ?> me-2"></i>
            <strong class="me-auto"><?= ucfirst($flash['type']==='danger'?'Error':$flash['type']) ?></strong>
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
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['name'],0,1)) ?></div>
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
                        <tr><th>#</th><th>Name</th><th>Role</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($staffList)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-people d-block fs-3 mb-2 opacity-25"></i>No staff found.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($staffList as $s): ?>
                        <tr>
                            <td data-label="#" class="text-muted"><?= (int)$s['id'] ?></td>
                            <td data-label="Name">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar-sm"><?= strtoupper(substr($s['name'],0,1)) ?></span>
                                    <span class="fw-semibold"><?= htmlspecialchars($s['name']) ?></span>
                                </div>
                            </td>
                            <td data-label="Role"><?= htmlspecialchars($s['role']??'—') ?></td>
                            <td data-label="Email" class="text-muted"><?= htmlspecialchars($s['email']??'—') ?></td>
                            <td data-label="Phone"><?= htmlspecialchars($s['phone']??'—') ?></td>
                            <td data-label="Status">
                                <span class="badge <?= $s['status']==='active'?'bg-success':'bg-secondary' ?>">
                                    <?= ucfirst($s['status']) ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div class="d-flex gap-1">
                                    <button class="btn-action" title="Edit"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($s),ENT_QUOTES) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn-action <?= $s['status']==='active'?'warning':'success' ?>"
                                            title="<?= $s['status']==='active'?'Deactivate':'Activate' ?>"
                                            onclick="openToggleStaffModal(<?= (int)$s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>', '<?= $s['status']==='active'?'inactive':'active' ?>')">
                                        <i class="bi <?= $s['status']==='active'?'bi-person-dash':'bi-person-check' ?>"></i>
                                    </button>
                                    <a href="staff.php?view_staff=<?= (int)$s['id'] ?>" class="btn-action" title="View Schedule">
                                        <i class="bi bi-calendar3"></i>
                                    </a>
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
                        <tr><th>Date</th><th>Client</th><th>Event Type</th><th>Venue</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($scheduleRows)): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">No assignments found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($scheduleRows as $sr): ?>
                        <tr>
                            <td data-label="Date" class="fw-semibold"><?= htmlspecialchars($sr['booking_date']) ?></td>
                            <td data-label="Client"><?= htmlspecialchars($sr['client']) ?></td>
                            <td data-label="Event"><?= htmlspecialchars($sr['event_type']??'—') ?></td>
                            <td data-label="Venue"><?= htmlspecialchars($sr['venue']??'—') ?></td>
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
                    <p class="mb-0" style="font-size:.75rem;color:#888;">Reassign staff to approved bookings.</p>
                </div>
                <span class="badge bg-secondary"><?= count($assignableBookings) ?> booking(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table modern-table mobile-cards mb-0">
                    <thead>
                        <tr><th>Date</th><th>Client</th><th>Event / Venue</th><th>Assigned Staff</th><th>Reassign</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($assignableBookings)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No approved bookings.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assignableBookings as $ab):
                            $assignedName = '—';
                            foreach ($staffList as $sm) { if ($sm['id'] == $ab['assigned_staff_id']) { $assignedName = $sm['name']; break; } }
                        ?>
                        <tr>
                            <td data-label="Date" class="fw-semibold"><?= htmlspecialchars($ab['booking_date']) ?></td>
                            <td data-label="Client"><?= htmlspecialchars($ab['client']) ?></td>
                            <td data-label="Event">
                                <div><?= htmlspecialchars($ab['event_type']??'—') ?></div>
                                <div style="font-size:.74rem;color:#aaa;"><?= htmlspecialchars($ab['venue']??'') ?></div>
                            </td>
                            <td data-label="Assigned">
                                <span class="badge <?= $ab['assigned_staff_id']?'bg-success':'bg-secondary' ?>">
                                    <?= htmlspecialchars($assignedName) ?>
                                </span>
                            </td>
                            <td data-label="Reassign">
                                <form method="POST" class="d-flex gap-2 align-items-center">
                                    <input type="hidden" name="action" value="assign">
                                    <input type="hidden" name="booking_id" value="<?= (int)$ab['id'] ?>">
                                    <select name="staff_id" class="form-select form-select-sm" style="min-width:130px;" required>
                                        <option value="">— Select —</option>
                                        <?php foreach ($staffList as $sm): if ($sm['status']!=='active') continue; ?>
                                            <option value="<?= (int)$sm['id'] ?>" <?= $ab['assigned_staff_id']==$sm['id']?'selected':'' ?>>
                                                <?= htmlspecialchars($sm['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-dark btn-sm"><i class="bi bi-check2"></i></button>
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

<!-- Toggle Staff Status Modal -->
<div class="modal fade" id="toggleStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST">
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
        ['fieldName','fieldRole','fieldEmail','fieldPhone'].forEach(id => document.getElementById(id).value = '');
    }
    function openEditModal(s) {
        document.getElementById('staffModalLabel').textContent = 'Edit Staff';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = s.id;
        document.getElementById('fieldName').value  = s.name  ?? '';
        document.getElementById('fieldRole').value  = s.role  ?? '';
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
