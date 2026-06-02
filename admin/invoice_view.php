<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

$invoiceId = (int)($_GET['id'] ?? 0);
if (!$invoiceId) { header('Location: invoices.php'); exit; }

$stmt = $pdo->prepare("
    SELECT i.*,
           u.name AS client_name, u.email AS client_email,
           p.name AS package, p.price AS package_price, p.inclusions,
           b.booking_date, b.event_type, b.venue
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    JOIN packages p ON b.package_id = p.id
    JOIN users u    ON i.client_id  = u.id
    WHERE i.id = ?
");
$stmt->execute([$invoiceId]);
$inv = $stmt->fetch();

if (!$inv) { header('Location: invoices.php'); exit; }

$statusColor = ['unpaid' => '#dc3545', 'partial' => '#fd7e14', 'paid' => '#198754'];
$invoiceNo   = '#' . str_pad((int)$inv['id'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= $invoiceNo ?> — Harvy Mance Films</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --gold: #c9a84c; }
        body { background: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .invoice-wrap { max-width: 780px; margin: 2rem auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.1); overflow: hidden; }
        .inv-header { background: #1a1a2e; padding: 2rem 2.5rem; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; }
        .inv-header .studio-name { color: var(--gold); font-size: 1.4rem; font-weight: 800; letter-spacing: .5px; }
        .inv-header .studio-meta { color: #adb5bd; font-size: .8rem; margin-top: .3rem; line-height: 1.7; }
        .inv-header .inv-no { color: var(--gold); font-size: 1.5rem; font-weight: 800; }
        .inv-header .inv-date { color: #adb5bd; font-size: .8rem; margin-top: .2rem; }
        .inv-body { padding: 2rem 2.5rem; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .info-block .label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #adb5bd; margin-bottom: .3rem; }
        .info-block .value { font-size: .9rem; color: #212529; font-weight: 500; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: .875rem; }
        .items-table thead tr { background: #1a1a2e; color: #fff; }
        .items-table thead th { padding: .65rem 1rem; font-weight: 600; font-size: .78rem; text-transform: uppercase; letter-spacing: .4px; }
        .items-table tbody td { padding: .75rem 1rem; border-bottom: 1px solid #f0f0f0; color: #495057; }
        .items-table tbody tr:last-child td { border-bottom: none; }
        .totals-block { margin-left: auto; width: 280px; }
        .totals-row { display: flex; justify-content: space-between; padding: .4rem 0; font-size: .875rem; border-bottom: 1px solid #f0f0f0; }
        .totals-row:last-child { border-bottom: none; }
        .totals-row.grand { font-weight: 700; font-size: 1rem; color: #212529; border-top: 2px solid #dee2e6; padding-top: .6rem; margin-top: .2rem; }
        .totals-row .lbl { color: #6c757d; }
        .totals-row.grand .lbl { color: #212529; }
        .status-stamp { display: inline-block; border: 3px solid <?= $statusColor[$inv['status']] ?? '#6c757d' ?>; color: <?= $statusColor[$inv['status']] ?? '#6c757d' ?>; font-size: 1.1rem; font-weight: 800; text-transform: uppercase; letter-spacing: 3px; padding: .3rem 1rem; border-radius: 6px; transform: rotate(-8deg); opacity: .85; }
        .inv-footer { background: #f8f9fa; border-top: 1px solid #e9ecef; padding: 1rem 2.5rem; font-size: .78rem; color: #6c757d; text-align: center; }
        .toolbar { max-width: 780px; margin: 0 auto 1rem; display: flex; gap: .5rem; justify-content: flex-end; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .invoice-wrap { box-shadow: none; border-radius: 0; margin: 0; }
        }
    </style>
</head>
<body>

<div class="toolbar no-print">
    <a href="invoices.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
    <button class="btn btn-sm text-white" style="background:#1a1a2e;border:none;" onclick="window.print()">
        <i class="bi bi-printer"></i> Print Invoice
    </button>
</div>

<div class="invoice-wrap">
    <div class="inv-header">
        <div>
            <div class="studio-name">Harvy Mance Films</div>
            <div class="studio-meta">
                Brgy. San Antonio, Biñan, Laguna<br>
                info@harvymancefilms.com<br>
                Est. 2019
            </div>
        </div>
        <div style="text-align:right;">
            <div style="color:#adb5bd;font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">Invoice</div>
            <div class="inv-no"><?= $invoiceNo ?></div>
            <div class="inv-date">Issued: <?= htmlspecialchars($inv['issued_date']) ?></div>
            <div class="mt-2"><span class="status-stamp"><?= strtoupper($inv['status']) ?></span></div>
        </div>
    </div>

    <div class="inv-body">
        <div class="info-grid">
            <div>
                <div class="info-block mb-3">
                    <div class="label">Bill To</div>
                    <div class="value fw-bold"><?= htmlspecialchars($inv['client_name']) ?></div>
                    <div class="value text-muted"><?= htmlspecialchars($inv['client_email']) ?></div>
                </div>
            </div>
            <div>
                <div class="info-block mb-2">
                    <div class="label">Event Date</div>
                    <div class="value"><?= htmlspecialchars($inv['booking_date']) ?></div>
                </div>
                <div class="info-block mb-2">
                    <div class="label">Event Type</div>
                    <div class="value"><?= htmlspecialchars($inv['event_type'] ?? '—') ?></div>
                </div>
                <div class="info-block">
                    <div class="label">Venue</div>
                    <div class="value"><?= htmlspecialchars($inv['venue'] ?? '—') ?></div>
                </div>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Inclusions</th>
                    <th style="text-align:right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($inv['package']) ?></td>
                    <td style="color:#6c757d;font-size:.82rem;white-space:pre-line;"><?= htmlspecialchars($inv['inclusions'] ?? '—') ?></td>
                    <td style="text-align:right;font-weight:600;">₱<?= number_format((float)$inv['package_price'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
            <div></div>
            <div class="totals-block">
                <div class="totals-row">
                    <span class="lbl">Package Price</span>
                    <span>₱<?= number_format((float)$inv['amount'], 2) ?></span>
                </div>
                <div class="totals-row">
                    <span class="lbl">Deposit Paid</span>
                    <span class="text-success">− ₱<?= number_format((float)$inv['deposit_paid'], 2) ?></span>
                </div>
                <div class="totals-row grand">
                    <span class="lbl">Balance Due</span>
                    <span style="color:<?= (float)$inv['balance'] > 0 ? '#dc3545' : '#198754' ?>;">
                        ₱<?= number_format((float)$inv['balance'], 2) ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="mt-4 p-3 rounded" style="background:#f8f9fa;font-size:.8rem;color:#6c757d;">
            <strong>Payment Note:</strong>
            Please settle the remaining balance on or before the event date.
            For payment inquiries, contact us at <strong>info@harvymancefilms.com</strong>.
            Thank you for choosing Harvy Mance Films!
        </div>
    </div>

    <div class="inv-footer">
        Harvy Mance Films &nbsp;·&nbsp; Brgy. San Antonio, Biñan, Laguna &nbsp;·&nbsp;
        info@harvymancefilms.com &nbsp;·&nbsp; Est. 2019
    </div>
</div>

<script>
    if (new URLSearchParams(window.location.search).get('print') === '1') window.print();
</script>
</body>
</html>
