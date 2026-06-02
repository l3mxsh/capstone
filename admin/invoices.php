<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $bookingId   = (int)($_POST['booking_id'] ?? 0);
        $amount      = (float)($_POST['amount'] ?? 0);
        $depositPaid = (float)($_POST['deposit_paid'] ?? 0);
        $issuedDate  = $_POST['issued_date'] ?? date('Y-m-d');

        if (!$bookingId || $amount <= 0) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Booking and amount are required.'];
        } else {
            $exists = $pdo->prepare("SELECT id FROM invoices WHERE booking_id=?");
            $exists->execute([$bookingId]);
            if ($exists->fetch()) {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Invoice already exists for that booking.'];
            } else {
                $balance  = $amount - $depositPaid;
                $status   = $balance <= 0 ? 'paid' : ($depositPaid > 0 ? 'partial' : 'unpaid');
                $clientId = (int)$pdo->query("SELECT client_id FROM bookings WHERE id=$bookingId")->fetchColumn();
                $pdo->prepare("INSERT INTO invoices (booking_id,client_id,amount,deposit_paid,balance,status,issued_date) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$bookingId, $clientId, $amount, $depositPaid, $balance, $status, $issuedDate]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Invoice created successfully.'];
            }
        }
    } elseif ($action === 'update_payment') {
        $id          = (int)($_POST['id'] ?? 0);
        $depositPaid = (float)($_POST['deposit_paid'] ?? 0);
        $amount      = (float)$pdo->query("SELECT amount FROM invoices WHERE id=$id")->fetchColumn();
        $balance     = $amount - $depositPaid;
        $status      = $balance <= 0 ? 'paid' : ($depositPaid > 0 ? 'partial' : 'unpaid');
        $pdo->prepare("UPDATE invoices SET deposit_paid=?,balance=?,status=? WHERE id=?")
            ->execute([$depositPaid, $balance, $status, $id]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Payment updated.'];
    }

    header('Location: invoices.php');
    exit;
}

$invoices = $pdo->query("
    SELECT i.*, u.name AS client, u.email AS client_email, p.name AS package, b.booking_date, b.event_type
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    JOIN users u    ON i.client_id  = u.id
    JOIN packages p ON b.package_id = p.id
    ORDER BY i.issued_date DESC
")->fetchAll();

// Bookings without invoices (approved/rescheduled only)
$uninvoiced = $pdo->query("
    SELECT b.id, b.booking_date, b.event_type, u.name AS client, p.name AS package, p.price
    FROM bookings b
    JOIN users u    ON b.client_id  = u.id
    JOIN packages p ON b.package_id = p.id
    WHERE b.status IN ('approved','rescheduled')
      AND b.id NOT IN (SELECT booking_id FROM invoices WHERE booking_id IS NOT NULL)
    ORDER BY b.booking_date ASC
")->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$statusBadge = ['unpaid' => 'danger', 'partial' => 'warning', 'paid' => 'success'];

$pageTitle  = 'Invoices — Harvy Mance Films';
$activePage = 'invoices';
require_once '../includes/admin_head.php';
?>
</head>

<body>
    <?php require_once '../includes/sidebar.php'; ?>

    <!-- Toast -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <?php if ($flash): ?>
            <div id="liveToast" class="toast align-items-center border-0 show" role="alert"
                style="background:<?= $flash['type'] === 'success' ? '#f0fdf4' : ($flash['type'] === 'danger' ? '#fef2f2' : '#fffbeb') ?>">
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
                    <div class="topbar-title">Invoices</div>
                    <div class="topbar-sub">Manage client invoices and payments</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if (!empty($uninvoiced)): ?>
                    <button class="btn btn-dark btn-sm px-3" onclick="openCreateModal()">
                        <i class="bi bi-plus-lg me-1"></i> New Invoice
                    </button>
                <?php endif; ?>
                <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
                <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
            </div>
        </div>

        <div class="p-3 p-md-4">
            <div class="dash-card">
                <div class="dash-card-header">
                    <h6>All Invoices <span class="badge bg-secondary ms-2"><?= count($invoices) ?></span></h6>
                </div>
                <div class="table-responsive">
                    <table class="table modern-table mobile-cards mb-0">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Client</th>
                                <th>Package</th>
                                <th>Event Date</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Issued</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5 text-muted">
                                        <i class="bi bi-receipt d-block fs-3 mb-2 opacity-25"></i>No invoices yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $inv): ?>
                                    <tr>
                                        <td data-label="Invoice" class="fw-bold">#<?= str_pad((int)$inv['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                        <td data-label="Client">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="avatar-sm"><?= strtoupper(substr($inv['client'], 0, 1)) ?></span>
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($inv['client']) ?></div>
                                                    <div style="font-size:.73rem;color:#aaa;"><?= htmlspecialchars($inv['client_email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Package"><?= htmlspecialchars($inv['package']) ?></td>
                                        <td data-label="Event Date"><?= htmlspecialchars($inv['booking_date']) ?></td>
                                        <td data-label="Total" class="fw-semibold">₱<?= number_format((float)$inv['amount'], 2) ?></td>
                                        <td data-label="Paid" class="text-success fw-semibold">₱<?= number_format((float)$inv['deposit_paid'], 2) ?></td>
                                        <td data-label="Balance" class="<?= (float)$inv['balance'] > 0 ? 'text-danger fw-bold' : '' ?>">₱<?= number_format((float)$inv['balance'], 2) ?></td>
                                        <td data-label="Status"><span class="badge bg-<?= $statusBadge[$inv['status']] ?? 'secondary' ?>"><?= ucfirst($inv['status']) ?></span></td>
                                        <td data-label="Issued"><?= htmlspecialchars($inv['issued_date']) ?></td>
                                        <td data-label="Actions">
                                            <div class="d-flex gap-1">
                                                <button class="btn-action" title="Update Payment"
                                                    onclick="openPaymentModal(<?= htmlspecialchars(json_encode($inv), ENT_QUOTES) ?>)">
                                                    <i class="bi bi-cash-coin"></i>
                                                </button>
                                                <a href="invoice_view.php?id=<?= (int)$inv['id'] ?>" target="_blank"
                                                    class="btn-action" title="View Invoice">
                                                    <i class="bi bi-printer"></i>
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

    <!-- Create Invoice Modal -->
    <?php if (!empty($uninvoiced)): ?>
        <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-content">
                        <div class="modal-header">
                            <span class="modal-title">New Invoice</span>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label">Booking <span class="text-danger">*</span></label>
                            <select class="form-select mb-3" name="booking_id" id="invBooking" required onchange="prefillAmount(this)">
                                <option value="">— Select booking —</option>
                                <?php foreach ($uninvoiced as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>" data-price="<?= (float)$u['price'] ?>">
                                        #<?= (int)$u['id'] ?> — <?= htmlspecialchars($u['client']) ?> · <?= htmlspecialchars($u['package']) ?> (<?= $u['booking_date'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label class="form-label">Total Amount (₱) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control mb-3" name="amount" id="invAmount" step="0.01" min="1" required>
                            <label class="form-label">Deposit / Amount Paid (₱)</label>
                            <input type="number" class="form-control mb-3" name="deposit_paid" value="0" step="0.01" min="0">
                            <label class="form-label">Issued Date</label>
                            <input type="date" class="form-control" name="issued_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-dark btn-sm">Create Invoice</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Update Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="w-100">
                <input type="hidden" name="action" value="update_payment">
                <input type="hidden" name="id" id="pmId">
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="modal-title">Update Payment</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="info-box mb-3" style="font-size:.84rem;">
                            <div class="d-flex justify-content-between mb-1">
                                <span style="color:#888;">Client</span><strong id="pmClient"></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span style="color:#888;">Total Amount</span><span id="pmAmount"></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span style="color:#888;">Current Paid</span><span id="pmCurrentPaid"></span>
                            </div>
                        </div>
                        <label class="form-label">Amount Paid (₱) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="deposit_paid" id="pmDepositPaid" step="0.01" min="0" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark btn-sm">Save Payment</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('show'));

        function openCreateModal() {
            new bootstrap.Modal(document.getElementById('createModal')).show();
        }

        function prefillAmount(sel) {
            const price = sel.options[sel.selectedIndex]?.dataset.price;
            if (price) document.getElementById('invAmount').value = price;
        }

        function openPaymentModal(inv) {
            document.getElementById('pmId').value = inv.id;
            document.getElementById('pmClient').textContent = inv.client;
            document.getElementById('pmAmount').textContent = '₱' + parseFloat(inv.amount).toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
            document.getElementById('pmCurrentPaid').textContent = '₱' + parseFloat(inv.deposit_paid).toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
            document.getElementById('pmDepositPaid').value = inv.deposit_paid;
            new bootstrap.Modal(document.getElementById('paymentModal')).show();
        }

        document.addEventListener('DOMContentLoaded', () => {
            const t = document.getElementById('liveToast');
            if (t) setTimeout(() => bootstrap.Toast.getOrCreateInstance(t).hide(), 4000);
        });
    </script>
</body>

</html>