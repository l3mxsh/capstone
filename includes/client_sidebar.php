<?php
// $activeClientPage must be set before including this file.
// Accepted values: dashboard, bookings, post_production, invoices, downloads
$activeClientPage = $activeClientPage ?? '';

$navItems = [
    'dashboard'      => ['label' => 'Dashboard',          'icon' => 'bi-house',             'href' => 'dashboard.php'],
    'bookings'       => ['label' => 'My Bookings',        'icon' => 'bi-calendar-check',    'href' => 'bookings.php'],
    'post_production'=> ['label' => 'Post-Production',   'icon' => 'bi-film',              'href' => 'post_production.php'],
    'invoices'       => ['label' => 'My Invoices',        'icon' => 'bi-receipt',           'href' => 'invoices.php'],
    'downloads'      => ['label' => 'Download Files',     'icon' => 'bi-cloud-download',    'href' => 'downloads.php'],
];
?>
<nav id="client-sidebar">
    <div class="brand">
        <span class="brand-logo">HMF</span>
        <div>
            Harvy Mance Films
            <small>Client Portal</small>
        </div>
    </div>

    <nav class="nav flex-column py-2 flex-grow-1">
        <?php foreach ($navItems as $key => $item): ?>
            <a href="<?= $item['href'] ?>"
               class="nav-link <?= $activeClientPage === $key ? 'active' : '' ?>">
                <i class="bi <?= $item['icon'] ?>"></i>
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <nav class="nav flex-column pb-3">
        <a href="../logout.php" class="nav-link logout-link">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </nav>
</nav>
