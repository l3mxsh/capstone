<?php
$activePage = $activePage ?? '';
$navItems = [
    'dashboard'       => ['label' => 'Dashboard',       'icon' => 'bi-speedometer2',     'href' => 'dashboard.php'],
    'packages'        => ['label' => 'Packages',         'icon' => 'bi-box-seam',          'href' => 'packages.php'],
    'bookings'        => ['label' => 'Bookings',         'icon' => 'bi-calendar-check',    'href' => 'bookings.php'],
    'staff'           => ['label' => 'Staff',            'icon' => 'bi-people',            'href' => 'staff.php'],
    'cancellations'   => ['label' => 'Cancellations',   'icon' => 'bi-x-circle',          'href' => 'cancellations.php'],
    'post_production' => ['label' => 'Post-Production', 'icon' => 'bi-film',              'href' => 'post_production.php'],
    'clients'         => ['label' => 'Clients',          'icon' => 'bi-person-lines-fill', 'href' => 'clients.php'],
    'reports'         => ['label' => 'Reports',          'icon' => 'bi-bar-chart-line',    'href' => 'reports.php'],
];
?>
<nav id="sidebar">
    <div class="brand">
        <span class="brand-logo">HMF</span>
        <div>
            <div class="brand-name">Harvy Mance Films</div>
            <div class="brand-sub">Admin Panel</div>
        </div>
    </div>

    <div class="nav-section-label">Menu</div>
    <nav class="nav flex-column flex-grow-1 pb-2">
        <?php foreach ($navItems as $key => $item): ?>
            <a href="<?= $item['href'] ?>"
               class="nav-link <?= $activePage === $key ? 'active' : '' ?>">
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
