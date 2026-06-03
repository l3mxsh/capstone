<?php
require_once '../auth/client_guard.php';
require_once '../config/db.php';

$clientId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT i.id,i.amount,i.deposit_paid,i.balance,i.issued_date,i.status,i.payment_instructions,p.name AS package,b.booking_date,b.event_type FROM invoices i JOIN bookings b ON i.booking_id=b.id JOIN packages p ON b.package_id=p.id WHERE i.client_id=? ORDER BY i.issued_date DESC");
$stmt->execute([$clientId]);
$invoices = $stmt->fetchAll();

$totals = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total_amount, COALESCE(SUM(deposit_paid),0) AS total_paid, COALESCE(SUM(balance),0) AS total_balance FROM invoices WHERE client_id=?");
$totals->execute([$clientId]);
$summary = $totals->fetch();

$statusBadge = ['unpaid' => 'danger', 'partial' => 'warning', 'paid' => 'success'];

$initials = strtoupper(substr($_SESSION['name'], 0, 1));
$pageTitle = 'Invoices & Payment';
$activeClientPage = 'invoices';
require_once '../includes/client_head.php';
$_clientAvatar  ??= null;
$_clientInitial ??= strtoupper(substr($_SESSION['name'], 0, 1));
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
                    foreach ($summaryCards as [$lbl, $val, $icon]):
                    ?>
                        <div class="col-sm-4">
                            <div class="stat-card">
                                <div class="icon-wrap"><i class="bi <?= $icon ?>"></i></div>
                                <div>
                                    <div class="count" style="font-size:1.35rem;">₱<?= number_format((float)$val, 2) ?></div>
                                    <div class="label"><?= $lbl ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-12">

                    <!-- Invoices Table -->
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <h6>Invoice History <span class="badge bg-secondary ms-2"><?= count($invoices) ?></span></h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table modern-table mobile-cards mb-0">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Package</th>
                                        <th>Event Date</th>
                                        <th>Total</th>
                                        <th>Paid</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($invoices)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-5 text-muted">
                                                <i class="bi bi-receipt d-block fs-3 mb-2 opacity-25"></i>No invoices issued yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($invoices as $inv): ?>
                                            <tr>
                                                <td data-label="Invoice" class="fw-bold">#<?= str_pad((int)$inv['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                                <td data-label="Package" class="fw-semibold"><?= htmlspecialchars($inv['package']) ?></td>
                                                <td data-label="Event Date"><?= htmlspecialchars($inv['booking_date']) ?></td>
                                                <td data-label="Total" class="fw-semibold">₱<?= number_format((float)$inv['amount'], 2) ?></td>
                                                <td data-label="Paid" class="text-success fw-semibold">₱<?= number_format((float)$inv['deposit_paid'], 2) ?></td>
                                                <td data-label="Balance" class="<?= (float)$inv['balance'] > 0 ? 'text-danger fw-bold' : '' ?>">₱<?= number_format((float)$inv['balance'], 2) ?></td>
                                                <td data-label="Status"><span class="badge bg-<?= $statusBadge[$inv['status']] ?? 'secondary' ?>"><?= ucfirst($inv['status']) ?></span></td>
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
            </div>

            <?php
            $anyInstructions = array_filter($invoices, fn($i) => !empty($i['payment_instructions']));
            if (!empty($anyInstructions)):
            ?>
            <div class="dash-card mt-4">
                <div class="dash-card-header">
                    <h6><i class="bi bi-credit-card me-2"></i>How to Pay</h6>
                </div>
                <div class="dash-card-body">
                    <?php foreach ($anyInstructions as $inv): ?>
                        <div class="mb-3 pb-3" style="border-bottom:1px solid #f0f0f0;">
                            <div class="fw-semibold mb-1" style="font-size:.82rem;color:#888;">Invoice #<?= str_pad((int)$inv['id'], 5, '0', STR_PAD_LEFT) ?> — <?= htmlspecialchars($inv['package']) ?></div>
                            <div style="font-size:.875rem;white-space:pre-line;"><?= htmlspecialchars($inv['payment_instructions']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('client-sidebar').classList.toggle('show'));
    </script>
</body>

</html>