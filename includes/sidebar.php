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

<!-- Admin Mobile Bottom Nav (5 columns) -->
<nav id="admin-mobile-nav">
    <a href="#" class="mob-menu-btn" onclick="toggleAdminSidebar(event)">
        <i class="bi bi-grid-3x3-gap"></i>
        <span>Menu</span>
    </a>
    <a href="bookings.php" class="<?= $activePage === 'bookings' ? 'active' : '' ?>">
        <i class="bi bi-calendar-check"></i>
        <span>Bookings</span>
    </a>
    <a href="dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i>
        <span>Dashboard</span>
    </a>
    <a href="packages.php" class="<?= $activePage === 'packages' ? 'active' : '' ?>">
        <i class="bi bi-box-seam"></i>
        <span>Packages</span>
    </a>
    <a href="clients.php" class="<?= $activePage === 'clients' ? 'active' : '' ?>">
        <i class="bi bi-person-lines-fill"></i>
        <span>Clients</span>
    </a>
</nav>
<script>
    function toggleAdminSidebar(e) {
        e.preventDefault();
        const sidebar = document.getElementById('sidebar');
        const btn = e.currentTarget;
        sidebar.classList.toggle('show');
        btn.classList.toggle('sidebar-open', sidebar.classList.contains('show'));
    }
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        const btn = document.querySelector('.mob-menu-btn');
        if (sidebar && sidebar.classList.contains('show') && !sidebar.contains(e.target) && !btn.contains(e.target)) {
            sidebar.classList.remove('show');
            btn.classList.remove('sidebar-open');
        }
    });
</script>