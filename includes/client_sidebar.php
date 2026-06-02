<?php
$activeClientPage = $activeClientPage ?? '';
$navItems = [
    'dashboard'       => ['label' => 'Dashboard',       'icon' => 'bi-house',          'href' => 'dashboard.php'],
    'bookings'        => ['label' => 'My Bookings',      'icon' => 'bi-calendar-check', 'href' => 'bookings.php'],
    'post_production' => ['label' => 'Post-Production', 'icon' => 'bi-film',           'href' => 'post_production.php'],
    'invoices'        => ['label' => 'Invoices',         'icon' => 'bi-receipt',        'href' => 'invoices.php'],
    'downloads'       => ['label' => 'Downloads',        'icon' => 'bi-cloud-download', 'href' => 'downloads.php'],
];
?>
<nav id="client-sidebar">
    <div class="brand">
        <span class="brand-logo">HMF</span>
        <div>
            <div class="brand-name">Harvy Mance Films</div>
            <div class="brand-sub">Client Portal</div>
        </div>
    </div>

    <div class="nav-section-label">My Portal</div>
    <nav class="nav flex-column flex-grow-1 pb-2">
        <?php foreach ($navItems as $key => $item): ?>
            <a href="<?= $item['href'] ?>"
               class="nav-link <?= $activeClientPage === $key ? 'active' : '' ?>">
                <i class="bi <?= $item['icon'] ?>"></i>
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php" class="nav-link logout-link">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </div>
</nav>

<!-- Mobile bottom nav -->
<nav id="mobile-nav">
    <?php foreach ($navItems as $key => $item): ?>
        <a href="<?= $item['href'] ?>" class="<?= $activeClientPage === $key ? 'active' : '' ?>">
            <i class="bi <?= $item['icon'] ?>"></i>
            <?= $item['label'] ?>
        </a>
    <?php endforeach; ?>
    <a href="../logout.php">
        <i class="bi bi-box-arrow-left"></i>
        Logout
    </a>
</nav>
