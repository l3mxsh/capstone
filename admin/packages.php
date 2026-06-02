<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

// ── POST Handler (PRG pattern) ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price       = $_POST['price'] ?? '';
        $inclusions  = trim($_POST['inclusions'] ?? '');
        $status      = in_array($_POST['status'] ?? '', ['active','archived']) ? $_POST['status'] : 'active';

        // Validate
        if ($name === '' || $price === '' || !is_numeric($price) || (float)$price < 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Name and a valid Price are required.'];
            header('Location: packages.php');
            exit;
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO packages (name, description, price, inclusions, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, (float)$price, $inclusions, $status]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Package added successfully.'];
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE packages SET name=?, description=?, price=?, inclusions=?, status=? WHERE id=?");
            $stmt->execute([$name, $description, (float)$price, $inclusions, $status, $id]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Package updated successfully.'];
        }

    } elseif ($action === 'archive') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE packages SET status='archived' WHERE id=?")->execute([$id]);
        $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Package archived.'];

    } elseif ($action === 'restore') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE packages SET status='active' WHERE id=?")->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Package restored to active.'];
    }

    header('Location: packages.php');
    exit;
}

// ── GET: fetch all packages ────────────────────────────────────
$packages = $pdo->query("SELECT * FROM packages ORDER BY created_at DESC")->fetchAll();

// ── Flash message ──────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle  = 'Packages — Harvy Mance Films';
$activePage = 'packages';
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 fw-semibold">Photography Packages</h5>
            <button class="btn btn-sm text-white" style="background:var(--gold);"
                    data-bs-toggle="modal" data-bs-target="#packageModal"
                    onclick="openAddModal()">
                <i class="bi bi-plus-lg"></i> Add Package
            </button>
        </div>

        <!-- Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th style="width:160px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($packages)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No packages found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($packages as $pkg): ?>
                            <tr>
                                <td><?= (int)$pkg['id'] ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($pkg['name']) ?></td>
                                <td class="text-muted" style="max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= htmlspecialchars($pkg['description']) ?>
                                </td>
                                <td>₱<?= number_format((float)$pkg['price'], 2) ?></td>
                                <td>
                                    <?php if ($pkg['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Archived</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <!-- Edit -->
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($pkg), ENT_QUOTES) ?>)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>

                                    <!-- Archive / Restore -->
                                    <?php if ($pkg['status'] === 'active'): ?>
                                        <form method="POST" class="d-inline"
                                              onsubmit="return confirm('Archive this package?')">
                                            <input type="hidden" name="action" value="archive">
                                            <input type="hidden" name="id" value="<?= (int)$pkg['id'] ?>">
                                            <button class="btn btn-sm btn-outline-warning py-0 px-2">
                                                <i class="bi bi-archive"></i> Archive
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="id" value="<?= (int)$pkg['id'] ?>">
                                            <button class="btn btn-sm btn-outline-success py-0 px-2">
                                                <i class="bi bi-arrow-counterclockwise"></i> Restore
                                            </button>
                                        </form>
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

<!-- ── Add / Edit Modal ─────────────────────────────────────── -->
<div class="modal fade" id="packageModal" tabindex="-1" aria-labelledby="packageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="packageForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id"     id="formId"     value="">

            <div class="modal-content">
                <div class="modal-header" style="border-bottom:1px solid #eee;">
                    <h6 class="modal-title fw-semibold" id="packageModalLabel">Add Package</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Package Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="fieldName" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Price (₱) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" id="fieldPrice"
                                   min="0" step="0.01" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" name="description" id="fieldDescription"
                                      rows="3" placeholder="Short overview of the package..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Inclusions</label>
                            <textarea class="form-control" name="inclusions" id="fieldInclusions"
                                      rows="4" placeholder="List what is included, one per line..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" name="status" id="fieldStatus">
                                <option value="active">Active</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="border-top:1px solid #eee;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm text-white" style="background:var(--gold);">
                        Save Package
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openAddModal() {
        document.getElementById('packageModalLabel').textContent = 'Add Package';
        document.getElementById('formAction').value  = 'add';
        document.getElementById('formId').value      = '';
        document.getElementById('fieldName').value        = '';
        document.getElementById('fieldPrice').value       = '';
        document.getElementById('fieldDescription').value = '';
        document.getElementById('fieldInclusions').value  = '';
        document.getElementById('fieldStatus').value      = 'active';
    }

    function openEditModal(pkg) {
        document.getElementById('packageModalLabel').textContent = 'Edit Package';
        document.getElementById('formAction').value  = 'edit';
        document.getElementById('formId').value      = pkg.id;
        document.getElementById('fieldName').value        = pkg.name;
        document.getElementById('fieldPrice').value       = pkg.price;
        document.getElementById('fieldDescription').value = pkg.description ?? '';
        document.getElementById('fieldInclusions').value  = pkg.inclusions ?? '';
        document.getElementById('fieldStatus').value      = pkg.status;

        new bootstrap.Modal(document.getElementById('packageModal')).show();
    }
</script>
</body>
</html>
