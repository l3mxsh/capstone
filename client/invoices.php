<?php
require_once '../auth/client_guard.php';
require_once '../config/db.php';

$clientId = (int)$_SESSION['user_id'];

// ── Fetch invoices ─────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT i.id, i.amount, i.deposit_paid, i.balance,
           i.issued_date, i.status,
           p.name AS package,
           b.booking_date, b.event_type
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    JOIN packages p ON b.package_id = p.id
    WHERE i.client_id = ?
    ORDER BY i.issued_date DESC
");
$stmt->execute([$clientId]);
$invoices = $stmt->fetchAll();

// ── Summary totals ─────────────────────────────────────────────
$totals = $pdo->prepare("
    SELECT
        COALESCE(SUM(amount), 0)       AS total_amount,
        COALESCE(SUM(deposit_paid), 0) AS total_paid,
        COALESCE(SUM(balance), 0)      AS total_balance
    FROM invoices WHERE client_id = ?
");
$totals->execute([$clientId]);
$summary = $totals->fetch();

$statusColor = ['unpaid' => 'danger', 'partial' => 'warning', 'paid' => 'success'];
$statusIcon  = ['unpaid' => 'bi-x-circle', 'partial' => 'bi-clock', 'paid' => 'bi-check-circle-fill'];

$initials        = strtoupper(substr($_SESSION['name'], 0, 1));
$pageTitle       = 'My Invoices — Client Portal';
$activeClientPage = 'invoices';

require_once '../includes/client_head.php';
?>
</head>
<body>

<?php require_once '../includes/client_sidebar.php'; ?>

<div id="client-main">

    <!-- Topbar -->
    <div id="client-topbar">
        <span class="page-label">My Invoices</span>
        <div class="user-pill">
            <div class="avatar"><?= htmlspecialchars($initials) ?></div>
            <span><?= htmlspecialchars($_SESSION['name']) ?></span>
            <a href="../logout.php" class="btn btn-sm btn-outline-secondary py-0 px-2 ms-2"
               style="font-size:.78rem;">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <div class="p-4">

        <h5 class="fw-bold mb-4">My Invoices</h5>

        <?php if (!empty($invoices)): ?>
        <!-- ── Summary Cards ──────────────────────────────── -->
        <div class="row g-3 mb-4">
            <?php
            $summaryCards = [
                ['label' => 'Total Billed',   'value' => $summary['total_amount'],  'icon' => 'bi-receipt',      'bg' => '#fff3cd', 'ic' => 'var(--gold)'],
                ['label' => 'Total Paid',     'value' => $summary['total_paid'],    'icon' => 'bi-check-circle', 'bg' => '#e8f5e9', 'ic' => '#198754'],
                ['label' => 'Total Balance',  'value' => $summary['total_balance'], 'icon' => 'bi-hourglass',    'bg' => '#fdecea', 'ic' => '#dc3545'],
            ];
            foreach ($summaryCards as $sc):
            ?>
            <div class="col-sm-4">
                <div class="portal-card p-3 d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;border-radius:10px;background:<?= $sc['bg'] ?>;
                                display:flex;align-items:center;justify-content:center;
                                font-size:1.3rem;flex-shrink:0;">
                        <i class="bi <?= $sc['icon'] ?>" style="color:<?= $sc['ic'] ?>;"></i>
                    </div>
                    <div>
                        <div style="font-size:1.2rem;font-weight:700;line-height:1;">
                            ₱<?= number_format((float)$sc['value'], 2) ?>
                        </div>
                        <div style="font-size:.75rem;color:#6c757d;"><?= $sc['label'] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ── Invoices Table ─────────────────────────────── -->
        <div class="portal-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:100px;">Invoice #</th>
                            <th>Package</th>
                            <th>Event Date</th>
                            <th>Event Type</th>
                            <th>Amount</th>
                            <th>Deposit Paid</th>
                            <th>Balance</th>
                            <th>Issued</th>
                            <th>Status</th>
                            <th style="width:100px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5">
                                <i class="bi bi-receipt" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                                No invoices issued yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td class="fw-semibold" style="color:var(--gold);">
                                #<?= str_pad((int)$inv['id'], 5, '0', STR_PAD_LEFT) ?>
                            </td>
                            <td><?= htmlspecialchars($inv['package']) ?></td>
                            <td><?= htmlspecialchars($inv['booking_date']) ?></td>
                            <td><?= htmlspecialchars($inv['event_type'] ?? '—') ?></td>
                            <td class="fw-semibold">₱<?= number_format((float)$inv['amount'], 2) ?></td>
                            <td class="text-success">₱<?= number_format((float)$inv['deposit_paid'], 2) ?></td>
                            <td class="<?= (float)$inv['balance'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                ₱<?= number_format((float)$inv['balance'], 2) ?>
                            </td>
                            <td><?= htmlspecialchars($inv['issued_date']) ?></td>
                            <td>
                                <span class="badge bg-<?= $statusColor[$inv['status']] ?? 'secondary' ?>">
                                    <i class="bi <?= $statusIcon[$inv['status']] ?? 'bi-circle' ?> me-1"></i>
                                    <?= ucfirst($inv['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="invoice_pdf.php?id=<?= (int)$inv['id'] ?>"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-secondary py-0 px-2"
                                   title="View / Print Invoice">
                                    <i class="bi bi-printer"></i> Print
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /client-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
