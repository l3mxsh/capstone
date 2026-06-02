<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? ''); $password = $_POST['password'] ?? '';
        if ($name==='' || $email==='' || $password==='') {
            $_SESSION['flash'] = ['type'=>'danger','msg'=>'All fields are required.'];
        } else {
            $dup = $pdo->prepare("SELECT id FROM users WHERE email=?"); $dup->execute([$email]);
            if ($dup->fetch()) { $_SESSION['flash'] = ['type'=>'danger','msg'=>'Email already exists.']; }
            else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (name,email,password,role,status) VALUES (?,?,?,'client','active')")->execute([$name,$email,$hash]);
                $subject = 'Your Harvy Mance Films Client Account';
                $message = "Hello {$name},\n\nYour client account has been created.\n\nLogin URL: http://localhost/capstone/login.php\nEmail: {$email}\nPassword: {$password}\n\nPlease change your password after logging in.\n\n— Harvy Mance Films";
                @mail($email, $subject, $message, "From: noreply@harvymancefilms.com\r\nContent-Type: text/plain; charset=UTF-8");
                $_SESSION['flash'] = ['type'=>'success','msg'=>"Client created and credentials sent to {$email}."];
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id']??0); $name = trim($_POST['name']??''); $email = trim($_POST['email']??'');
        if ($name==='' || $email==='') { $_SESSION['flash'] = ['type'=>'danger','msg'=>'Name and email are required.']; }
        else {
            $dup = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?"); $dup->execute([$email,$id]);
            if ($dup->fetch()) { $_SESSION['flash'] = ['type'=>'danger','msg'=>'Email already in use.']; }
            else { $pdo->prepare("UPDATE users SET name=?,email=? WHERE id=? AND role='client'")->execute([$name,$email,$id]); $_SESSION['flash'] = ['type'=>'success','msg'=>'Client updated.']; }
        }
    } elseif ($action === 'reset_password') {
        $id = (int)($_POST['id']??0);
        $row = $pdo->prepare("SELECT name,email FROM users WHERE id=? AND role='client'"); $row->execute([$id]); $client = $row->fetch();
        if ($client) {
            $newPass = bin2hex(random_bytes(4)); $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash,$id]);
            $subject = 'Password Reset — Harvy Mance Films';
            $message = "Hello {$client['name']},\n\nYour password has been reset.\n\nNew Password: {$newPass}\n\nLogin URL: http://localhost/capstone/login.php\n\n— Harvy Mance Films";
            @mail($client['email'], $subject, $message, "From: noreply@harvymancefilms.com\r\nContent-Type: text/plain; charset=UTF-8");
            $_SESSION['flash'] = ['type'=>'success','msg'=>"Password reset and sent to {$client['email']}."];
        } else { $_SESSION['flash'] = ['type'=>'danger','msg'=>'Client not found.']; }
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id']??0); $newStatus = in_array($_POST['new_status']??'',['active','inactive']) ? $_POST['new_status'] : 'inactive';
        $pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='client'")->execute([$newStatus,$id]);
        $_SESSION['flash'] = ['type'=>'info','msg'=>'Client status updated.'];
    }
    header('Location: clients.php'); exit;
}

$clients = $pdo->query("
    SELECT u.id, u.name, u.email, u.status, u.created_at, COUNT(b.id) AS booking_count
    FROM users u LEFT JOIN bookings b ON b.client_id=u.id
    WHERE u.role='client' GROUP BY u.id ORDER BY u.created_at DESC
")->fetchAll();

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$pageTitle  = 'Clients — Harvy Mance Films';
$activePage = 'clients';
require_once '../includes/admin_head.php';
?>
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>

<!-- Toast -->
<div class="toast-container position-fixed top-0 end-0 p-3">
<?php if ($flash): ?>
    <div id="liveToast" class="toast align-items-center border-0 show" role="alert" data-bs-delay="4000"
         style="background:<?= $flash['type']==='success'?'#f0fdf4':($flash['type']==='danger'?'#fef2f2':'#fffbeb') ?>">
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
                <div class="topbar-title">Clients</div>
                <div class="topbar-sub">Manage client accounts</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['name'],0,1)) ?></div>
        </div>
    </div>

    <div class="p-3 p-md-4">
        <div class="dash-card">
            <div class="dash-card-header">
                <h6>Client Accounts <span class="badge bg-secondary ms-2"><?= count($clients) ?></span></h6>
                <button class="btn btn-dark btn-sm px-3"
                        data-bs-toggle="modal" data-bs-target="#clientModal"
                        onclick="openCreateModal()">
                    <i class="bi bi-person-plus me-1"></i> Add Client
                </button>
            </div>
            <div class="table-responsive">
                <table class="table modern-table mb-0">
                    <thead>
                        <tr><th>#</th><th>Name</th><th>Email</th><th>Bookings</th><th>Status</th><th>Registered</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($clients)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-people d-block fs-3 mb-2 opacity-25"></i>No clients found.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($clients as $c): ?>
                        <tr>
                            <td class="text-muted"><?= (int)$c['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar-sm"><?= strtoupper(substr($c['name'],0,1)) ?></span>
                                    <span class="fw-semibold"><?= htmlspecialchars($c['name']) ?></span>
                                </div>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($c['email']) ?></td>
                            <td><span class="badge bg-secondary"><?= (int)$c['booking_count'] ?></span></td>
                            <td>
                                <span class="badge <?= $c['status']==='active'?'bg-success':'bg-danger' ?>">
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn-action" title="Edit"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($c),ENT_QUOTES) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <button type="submit" class="btn-action warning" title="Reset Password"
                                                onclick="return confirm('Reset password for <?= htmlspecialchars(addslashes($c['name'])) ?>?')">
                                            <i class="bi bi-key"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $c['status']==='active'?'inactive':'active' ?>">
                                        <button type="submit" class="btn-action <?= $c['status']==='active'?'danger':'success' ?>"
                                                title="<?= $c['status']==='active'?'Deactivate':'Reactivate' ?>"
                                                onclick="return confirm('<?= $c['status']==='active'?'Deactivate':'Reactivate' ?> this client?')">
                                            <i class="bi <?= $c['status']==='active'?'bi-person-dash':'bi-person-check' ?>"></i>
                                        </button>
                                    </form>
                                    <a href="bookings.php?client_id=<?= (int)$c['id'] ?>" class="btn-action" title="View Bookings">
                                        <i class="bi bi-calendar-week"></i>
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
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="formId">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title" id="modalTitle">Add Client</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="fieldName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" id="fieldEmail" required>
                    </div>
                    <div id="passwordField">
                        <label class="form-label">Temporary Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="password" id="fieldPassword">
                            <button type="button" class="btn btn-outline-secondary" onclick="generatePassword()">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                        <div class="form-text">Credentials will be emailed to the client.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark btn-sm">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('show'));

    function openCreateModal() {
        document.getElementById('modalTitle').textContent = 'Add Client';
        document.getElementById('formAction').value = 'create';
        document.getElementById('formId').value = '';
        document.getElementById('fieldName').value = '';
        document.getElementById('fieldEmail').value = '';
        document.getElementById('fieldPassword').value = '';
        document.getElementById('fieldPassword').required = true;
        document.getElementById('passwordField').style.display = 'block';
    }
    function openEditModal(c) {
        document.getElementById('modalTitle').textContent = 'Edit Client';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = c.id;
        document.getElementById('fieldName').value = c.name;
        document.getElementById('fieldEmail').value = c.email;
        document.getElementById('fieldPassword').required = false;
        document.getElementById('passwordField').style.display = 'none';
        new bootstrap.Modal(document.getElementById('clientModal')).show();
    }
    function generatePassword() {
        const chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
        let p = '';
        for (let i = 0; i < 10; i++) p += chars[Math.floor(Math.random() * chars.length)];
        document.getElementById('fieldPassword').value = p;
    }
    document.addEventListener('DOMContentLoaded', () => {
        const t = document.getElementById('liveToast');
        if (t) setTimeout(() => bootstrap.Toast.getOrCreateInstance(t).hide(), 4000);
    });
</script>
</body>
</html>
