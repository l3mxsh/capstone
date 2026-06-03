<?php
require_once '../auth/client_guard.php';
require_once '../config/db.php';

$clientId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT i.id,i.amount,i.deposit_paid,i.balance,i.issued_date,i.status,p.name AS package,b.booking_date,b.event_type FROM invoices i JOIN bookings b ON i.booking_id=b.id JOIN packages p ON b.package_id=p.id WHERE i.client_id=? ORDER BY i.issued_date DESC");
$stmt->execute([$clientId]); $invoices = $stmt->fetchAll();

$totals = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total_amount, COALESCE(SUM(deposit_paid),0) AS total_paid, COALESCE(SUM(balance),0) AS total_balance FROM invoices WHERE client_id=?");
$totals->execute([$clientId]); $summary = $totals->fetch();

$statusBadge = ['unpaid'=>'danger','partial'=>'warning','paid'=>'success'];

$initials = strtoupper(substr($_SESSION['name'],0,1));
$pageTitle = 'Invoices & Payment — Client Portal';
$activeClientPage = 'invoices';
require_once '../includes/client_head.php';
?>
</head>
<body>
<?php require_once '../includes/client_sidebar.php'; ?>

<div id="client-main">
    <div id="client-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="topbar-btn d-lg-none border-0" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <div>
                <div class="topbar-title">Invoices & Payment</div>
                <div class="topbar-sub">View your billing history and payment details</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($_clientAvatar): ?><img src="../assets/avatars/<?= htmlspecialchars($_clientAvatar) ?>" class="topbar-avatar" style="object-fit:cover;"><?php else: ?><div class="topbar-avatar"><?= $_clientInitial ?></div><?php endif; ?>
            <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>

    <div class="p-3 p-md-4">

        <?php if (!empty($invoices)): ?>
        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <?php
            $summaryCards = [
                ['Total Billed',  $summary['total_amount'],  'bi-receipt'],
                ['Total Paid',    $summary['total_paid'],    'bi-check-circle'],
                ['Balance Due',   $summary['total_balance'], 'bi-hourglass-split'],
            ];
            foreach ($summaryCards as [$lbl,$val,$icon]):
            ?>
            <div class="col-sm-4">
                <div class="stat-card">
                    <div class="icon-wrap"><i class="bi <?= $icon ?>"></i></div>
                    <div>
                        <div class="count" style="font-size:1.35rem;">₱<?= number_format((float)$val,2) ?></div>
                        <div class="label"><?= $lbl ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">

                <!-- Invoices Table -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h6>Invoice History <span class="badge bg-secondary ms-2"><?= count($invoices) ?></span></h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table modern-table mobile-cards mb-0">
                            <thead>
                                <tr><th>Invoice #</th><th>Package</th><th>Event Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr><td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-receipt d-block fs-3 mb-2 opacity-25"></i>No invoices issued yet.
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td data-label="Invoice" class="fw-bold">#<?= str_pad((int)$inv['id'],5,'0',STR_PAD_LEFT) ?></td>
                                    <td data-label="Package" class="fw-semibold"><?= htmlspecialchars($inv['package']) ?></td>
                                    <td data-label="Event Date"><?= htmlspecialchars($inv['booking_date']) ?></td>
                                    <td data-label="Total" class="fw-semibold">₱<?= number_format((float)$inv['amount'],2) ?></td>
                                    <td data-label="Paid" class="text-success fw-semibold">₱<?= number_format((float)$inv['deposit_paid'],2) ?></td>
                                    <td data-label="Balance" class="<?= (float)$inv['balance']>0?'text-danger fw-bold':'' ?>">₱<?= number_format((float)$inv['balance'],2) ?></td>
                                    <td data-label="Status"><span class="badge bg-<?= $statusBadge[$inv['status']]??'secondary' ?>"><?= ucfirst($inv['status']) ?></span></td>
                                    <td data-label="Action">
                                        <a href="invoice_pdf.php?id=<?= (int)$inv['id'] ?>" target="_blank"
                                           class="btn-action" title="Print Invoice">
                                            <i class="bi bi-printer"></i>
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

            <!-- Right: Payment Instructions -->
            <div class="col-lg-4">
                <div class="dash-card mb-4">
                    <div class="dash-card-header"><h6><i class="bi bi-credit-card me-2"></i>How to Pay</h6></div>
                    <div class="dash-card-body">
                        <p style="font-size:.83rem;color:#555;" class="mb-3">
                            Pay your deposit or remaining balance using any of the following methods. Send proof of payment to the studio to confirm.
                        </p>

                        <!-- GCash -->
                        <div class="info-box mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="avatar-sm" style="background:#e8f5ff;color:#0d6efd;"><i class="bi bi-phone"></i></div>
                                <div class="fw-bold">GCash</div>
                            </div>
                            <div style="font-size:.82rem;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span style="color:#888;">Account Name</span>
                                    <span class="fw-semibold">Harvy Mance</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span style="color:#888;">Number</span>
                                    <span class="fw-bold">09XX-XXX-XXXX</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Transfer -->
                        <div class="info-box mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="avatar-sm" style="background:#f0fff4;color:#198754;"><i class="bi bi-bank"></i></div>
                                <div class="fw-bold">Bank Transfer</div>
                            </div>
                            <div style="font-size:.82rem;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span style="color:#888;">Bank</span>
                                    <span class="fw-semibold">BDO / BPI</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span style="color:#888;">Account Name</span>
                                    <span class="fw-semibold">Harvy Mance</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span style="color:#888;">Account No.</span>
                                    <span class="fw-bold">XXXX-XXXX-XXXX</span>
                                </div>
                            </div>
                        </div>

                        <!-- Cash on Site -->
                        <div class="info-box mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="avatar-sm"><i class="bi bi-cash-coin"></i></div>
                                <div class="fw-bold">Cash Payment</div>
                            </div>
                            <p style="font-size:.82rem;color:#555;" class="mb-0">
                                Cash payments can be made at the studio or on the event day. Please coordinate with the team in advance.
                            </p>
                        </div>

                        <!-- Steps -->
                        <div style="font-size:.8rem;color:#555;border-top:1px solid #f0f0f0;padding-top:.9rem;">
                            <div class="fw-bold mb-2">After Payment</div>
                            <div class="d-flex gap-2 mb-2">
                                <span class="avatar-sm" style="width:22px;height:22px;font-size:.65rem;background:#111;color:#fff;border-radius:50%;">1</span>
                                <span>Take a screenshot or photo of your receipt.</span>
                            </div>
                            <div class="d-flex gap-2 mb-2">
                                <span class="avatar-sm" style="width:22px;height:22px;font-size:.65rem;background:#111;color:#fff;border-radius:50%;">2</span>
                                <span>Send it to <strong>info@harvymancefilms.com</strong> or via Facebook.</span>
                            </div>
                            <div class="d-flex gap-2">
                                <span class="avatar-sm" style="width:22px;height:22px;font-size:.65rem;background:#111;color:#fff;border-radius:50%;">3</span>
                                <span>Wait for confirmation. Your invoice status will be updated.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact -->
                <div class="dash-card">
                    <div class="dash-card-header"><h6>Need Help?</h6></div>
                    <div class="dash-card-body">
                        <div class="d-flex flex-column gap-3" style="font-size:.84rem;">
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar-sm"><i class="bi bi-envelope"></i></span>
                                <a href="mailto:info@harvymancefilms.com" class="text-dark text-decoration-none">info@harvymancefilms.com</a>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar-sm"><i class="bi bi-facebook"></i></span>
                                <span>Harvy Mance Films</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar-sm"><i class="bi bi-geo-alt"></i></span>
                                <span>Brgy. San Antonio, Biñan, Laguna</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('client-sidebar').classList.toggle('show'));
</script>
</body>
</html>
