<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name']??''); $description = trim($_POST['description']??'');
        $price = $_POST['price']??''; $inclusions = trim($_POST['inclusions']??'');
        $status = in_array($_POST['status']??'',['active','archived']) ? $_POST['status'] : 'active';
        if ($name==='' || $price==='' || !is_numeric($price) || (float)$price < 0) {
            $_SESSION['flash'] = ['type'=>'danger','msg'=>'Name and a valid price are required.'];
            header('Location: packages.php'); exit;
        }
        if ($action === 'add') {
            $pdo->prepare("INSERT INTO packages (name,description,price,inclusions,status) VALUES (?,?,?,?,?)")
                ->execute([$name,$description,(float)$price,$inclusions,$status]);
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Package added successfully.'];
        } else {
            $id = (int)($_POST['id']??0);
            $pdo->prepare("UPDATE packages SET name=?,description=?,price=?,inclusions=?,status=? WHERE id=?")
                ->execute([$name,$description,(float)$price,$inclusions,$status,$id]);
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Package updated successfully.'];
        }
    } elseif ($action === 'archive') {
        $pdo->prepare("UPDATE packages SET status='archived' WHERE id=?")->execute([(int)($_POST['id']??0)]);
        $_SESSION['flash'] = ['type'=>'warning','msg'=>'Package archived.'];
    } elseif ($action === 'restore') {
        $pdo->prepare("UPDATE packages SET status='active' WHERE id=?")->execute([(int)($_POST['id']??0)]);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Package restored.'];
    }
    header('Location: packages.php'); exit;
}

$packages = $pdo->query("SELECT * FROM packages ORDER BY created_at DESC")->fetchAll();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$pageTitle  = 'Packages — Harvy Mance Films';
$activePage = 'packages';
require_once '../includes/admin_head.php';
?>
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>

<!-- Toast -->
<div class="toast-container position-fixed top-0 end-0 p-3">
<?php if ($flash): ?>
    <div id="liveToast" class="toast align-items-center border-0 show" role="alert"
         style="background:<?= $flash['type']==='success'?'#f0fdf4':($flash['type']==='danger'?'#fef2f2':'#fffbeb') ?>">
        <div class="toast-header" style="background:transparent;">
            <i class="bi <?= $flash['type']==='success'?'bi-check-circle-fill text-success':($flash['type']==='danger'?'bi-x-circle-fill text-danger':'bi-exclamation-triangle-fill text-warning') ?> me-2"></i>
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
                <div class="topbar-title">Packages</div>
                <div class="topbar-sub">Manage photography packages</div>
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
                <h6>Photography Packages <span class="badge bg-secondary ms-2"><?= count($packages) ?></span></h6>
                <button class="btn btn-dark btn-sm px-3"
                        data-bs-toggle="modal" data-bs-target="#packageModal"
                        onclick="openAddModal()">
                    <i class="bi bi-plus-lg me-1"></i> Add Package
                </button>
            </div>
            <div class="table-responsive">
                <table class="table modern-table mb-0">
                    <thead>
                        <tr><th>#</th><th>Name</th><th>Description</th><th>Price</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($packages)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-box-seam d-block fs-3 mb-2 opacity-25"></i>No packages found.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($packages as $pkg): ?>
                        <tr>
                            <td class="text-muted"><?= (int)$pkg['id'] ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($pkg['name']) ?></td>
                            <td class="text-muted" style="max-width:240px;">
                                <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= htmlspecialchars($pkg['description']) ?>
                                </div>
                            </td>
                            <td class="fw-semibold">₱<?= number_format((float)$pkg['price'],2) ?></td>
                            <td>
                                <span class="badge <?= $pkg['status']==='active'?'bg-success':'bg-secondary' ?>">
                                    <?= ucfirst($pkg['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn-action" title="Edit"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($pkg),ENT_QUOTES) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($pkg['status'] === 'active'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="archive">
                                            <input type="hidden" name="id" value="<?= (int)$pkg['id'] ?>">
                                            <button type="submit" class="btn-action warning" title="Archive"
                                                    onclick="return confirm('Archive this package?')">
                                                <i class="bi bi-archive"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="id" value="<?= (int)$pkg['id'] ?>">
                                            <button type="submit" class="btn-action success" title="Restore">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
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

<!-- Add/Edit Modal -->
<div class="modal fade" id="packageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="packageForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title" id="packageModalLabel">Add Package</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Package Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="fieldName" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Price (₱) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" id="fieldPrice" min="0" step="0.01" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="fieldDescription" rows="3" placeholder="Short overview…"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Inclusions</label>
                            <textarea class="form-control" name="inclusions" id="fieldInclusions" rows="4" placeholder="List what is included…"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="fieldStatus">
                                <option value="active">Active</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark btn-sm">Save Package</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('show'));

    function openAddModal() {
        document.getElementById('packageModalLabel').textContent = 'Add Package';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value = '';
        ['fieldName','fieldPrice','fieldDescription','fieldInclusions'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('fieldStatus').value = 'active';
    }
    function openEditModal(pkg) {
        document.getElementById('packageModalLabel').textContent = 'Edit Package';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = pkg.id;
        document.getElementById('fieldName').value = pkg.name;
        document.getElementById('fieldPrice').value = pkg.price;
        document.getElementById('fieldDescription').value = pkg.description ?? '';
        document.getElementById('fieldInclusions').value = pkg.inclusions ?? '';
        document.getElementById('fieldStatus').value = pkg.status;
        new bootstrap.Modal(document.getElementById('packageModal')).show();
    }
    document.addEventListener('DOMContentLoaded', () => {
        const t = document.getElementById('liveToast');
        if (t) setTimeout(() => bootstrap.Toast.getOrCreateInstance(t).hide(), 4000);
    });
</script>
</body>
</html>
