<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

// ── POST Handler (PRG) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── CREATE CLIENT ─────────────────────────────────────────
    if ($action === 'create') {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';

        if ($name === '' || $email === '' || $password === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'All fields are required.'];
        } else {
            // Check duplicate email
            $dup = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $dup->execute([$email]);
            if ($dup->fetch()) {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Email already exists.'];
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'client', 'active')")
                    ->execute([$name, $email, $hash]);

                // Email credentials to client
                $subject = 'Your Harvy Mance Films Client Account';
                $message = "Hello {$name},\n\n"
                    . "Your client account has been created.\n\n"
                    . "Login URL:  http://localhost/capstone/login.php\n"
                    . "Email:      {$email}\n"
                    . "Password:   {$password}\n\n"
                    . "Please change your password after logging in.\n\n"
                    . "— Harvy Mance Films";
                @mail($email, $subject, $message,
                    "From: noreply@harvymancefilms.com\r\nContent-Type: text/plain; charset=UTF-8");

                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Client account created and credentials sent to {$email}."];
            }
        }

    // ── EDIT CLIENT ───────────────────────────────────────────
    } elseif ($action === 'edit') {
        $id    = (int)($_POST['id']    ?? 0);
        $name  = trim($_POST['name']   ?? '');
        $email = trim($_POST['email']  ?? '');

        if ($name === '' || $email === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Name and email are required.'];
        } else {
            // Check duplicate email excluding current user
            $dup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $dup->execute([$email, $id]);
            if ($dup->fetch()) {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Email already in use by another account.'];
            } else {
                $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=? AND role='client'")
                    ->execute([$name, $email, $id]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Client account updated.'];
            }
        }

    // ── RESET PASSWORD ────────────────────────────────────────
    } elseif ($action === 'reset_password') {
        $id = (int)($_POST['id'] ?? 0);

        $row = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'client'");
        $row->execute([$id]);
        $client = $row->fetch();

        if ($client) {
            $newPass = bin2hex(random_bytes(4)); // 8-char hex
            $hash    = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $id]);

            $subject = 'Password Reset — Harvy Mance Films';
            $message = "Hello {$client['name']},\n\n"
                . "Your password has been reset by the admin.\n\n"
                . "New Password: {$newPass}\n\n"
                . "Please log in and change your password immediately.\n"
                . "Login URL: http://localhost/capstone/login.php\n\n"
                . "— Harvy Mance Films";
            @mail($client['email'], $subject, $message,
                "From: noreply@harvymancefilms.com\r\nContent-Type: text/plain; charset=UTF-8");

            $_SESSION['flash'] = ['type' => 'success', 'msg' => "Password reset and sent to {$client['email']}."];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Client not found.'];
        }

    // ── TOGGLE STATUS ─────────────────────────────────────────
    } elseif ($action === 'toggle_status') {
        $id        = (int)($_POST['id']         ?? 0);
        $newStatus = $_POST['new_status']        ?? 'inactive';
        $newStatus = in_array($newStatus, ['active','inactive']) ? $newStatus : 'inactive';
        $pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='client'")
            ->execute([$newStatus, $id]);
        $label = $newStatus === 'active' ? 'reactivated' : 'deactivated';
        $_SESSION['flash'] = ['type' => 'info', 'msg' => "Client account {$label}."];
    }

    header('Location: clients.php'); exit;
}

// ── GET: fetch all clients ─────────────────────────────────────
$clients = $pdo->query("
    SELECT u.id, u.name, u.email, u.status, u.created_at,
           COUNT(b.id) AS booking_count
    FROM users u
    LEFT JOIN bookings b ON b.client_id = u.id
    WHERE u.role = 'client'
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle  = 'Clients — Harvy Mance Films';
$activePage = 'clients';
require_once '../includes/admin_head.php';
?>
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

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 fw-semibold">Client Accounts</h5>
            <button class="btn btn-sm text-white" style="background:var(--gold);"
                    data-bs-toggle="modal" data-bs-target="#clientModal"
                    onclick="openCreateModal()">
                <i class="bi bi-person-plus"></i> Add Client
            </button>
        </div>

        <!-- Client Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Bookings</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th style="width:260px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($clients)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No clients found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clients as $c): ?>
                            <tr>
                                <td><?= (int)$c['id'] ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($c['name']) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($c['email']) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= (int)$c['booking_count'] ?></span>
                                </td>
                                <td>
                                    <?php if ($c['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">

                                        <!-- Edit -->
                                        <button class="btn btn-sm btn-outline-primary py-0 px-2"
                                                onclick="openEditModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <!-- Reset Password -->
                                        <form method="POST" class="d-inline"
                                              onsubmit="return confirm('Reset password for <?= htmlspecialchars(addslashes($c['name'])) ?>?')">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <button class="btn btn-sm btn-outline-warning py-0 px-2"
                                                    title="Reset Password">
                                                <i class="bi bi-key"></i>
                                            </button>
                                        </form>

                                        <!-- Toggle Status -->
                                        <form method="POST" class="d-inline"
                                              onsubmit="return confirm('<?= $c['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?> this client?')">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <?php if ($c['status'] === 'active'): ?>
                                                <input type="hidden" name="new_status" value="inactive">
                                                <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                        title="Deactivate">
                                                    <i class="bi bi-person-dash"></i>
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="new_status" value="active">
                                                <button class="btn btn-sm btn-outline-success py-0 px-2"
                                                        title="Reactivate">
                                                    <i class="bi bi-person-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- View Bookings -->
                                        <a href="bookings.php?client_id=<?= (int)$c['id'] ?>"
                                           class="btn btn-sm btn-outline-dark py-0 px-2"
                                           title="View Bookings">
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

    </div><!-- /content -->
</div><!-- /main-wrapper -->

<!-- ── Create / Edit Client Modal ─────────────────────────── -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id"     id="formId">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold" id="modalTitle">Add Client</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="name"
                                   id="fieldName" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control" name="email"
                                   id="fieldEmail" required>
                        </div>
                        <div class="col-12" id="passwordField">
                            <label class="form-label fw-semibold">
                                Temporary Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="password"
                                       id="fieldPassword">
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="generatePassword()">
                                    <i class="bi bi-arrow-repeat"></i> Generate
                                </button>
                            </div>
                            <div class="form-text">
                                Credentials will be emailed to the client.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm text-white"
                            style="background:var(--gold);">
                        Save
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openCreateModal() {
        document.getElementById('modalTitle').textContent   = 'Add Client';
        document.getElementById('formAction').value         = 'create';
        document.getElementById('formId').value             = '';
        document.getElementById('fieldName').value          = '';
        document.getElementById('fieldEmail').value         = '';
        document.getElementById('fieldPassword').value      = '';
        document.getElementById('fieldPassword').required   = true;
        document.getElementById('passwordField').style.display = 'block';
    }

    function openEditModal(c) {
        document.getElementById('modalTitle').textContent   = 'Edit Client';
        document.getElementById('formAction').value         = 'edit';
        document.getElementById('formId').value             = c.id;
        document.getElementById('fieldName').value          = c.name;
        document.getElementById('fieldEmail').value         = c.email;
        document.getElementById('fieldPassword').required   = false;
        document.getElementById('passwordField').style.display = 'none';
        new bootstrap.Modal(document.getElementById('clientModal')).show();
    }

    function generatePassword() {
        const chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
        let pass = '';
        for (let i = 0; i < 10; i++) {
            pass += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('fieldPassword').value = pass;
    }
</script>
</body>
</html>
