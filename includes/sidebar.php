<?php
// $activePage must be set before including this file.
// Accepted values: dashboard, packages, bookings, staff,
//                  cancellations, post_production, clients, reports
$activePage = $activePage ?? '';

$navItems = [
    'dashboard'      => ['label' => 'Dashboard',       'icon' => 'bi-speedometer2',      'href' => 'dashboard.php'],
    'packages'       => ['label' => 'Packages',         'icon' => 'bi-box-seam',           'href' => 'packages.php'],
    'bookings'       => ['label' => 'Bookings',         'icon' => 'bi-calendar-check',     'href' => 'bookings.php'],
    'staff'          => ['label' => 'Staff',            'icon' => 'bi-people',             'href' => 'staff.php'],
    'cancellations'  => ['label' => 'Cancellations',   'icon' => 'bi-x-circle',           'href' => 'cancellations.php'],
    'post_production'=> ['label' => 'Post-Production', 'icon' => 'bi-film',               'href' => 'post_production.php'],
    'clients'        => ['label' => 'Clients',          'icon' => 'bi-person-lines-fill',  'href' => 'clients.php'],
    'reports'        => ['label' => 'Reports',          'icon' => 'bi-bar-chart-line',     'href' => 'reports.php'],
];
?>
<nav id="sidebar">
    <div class="brand">
        Harvy Mance Films
        <small>Admin Panel</small>
    </div>

    <nav class="nav flex-column py-2 flex-grow-1">
        <?php foreach ($navItems as $key => $item): ?>
            <a href="<?= $item['href'] ?>"
               class="nav-link <?= $activePage === $key ? 'active' : '' ?>">
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
