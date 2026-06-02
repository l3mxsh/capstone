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

<!-- Mobile bottom nav (5 columns) -->
<nav id="mobile-nav">
    <a href="#" class="mob-menu-btn" onclick="toggleClientSidebar(event)">
        <i class="bi bi-grid-3x3-gap"></i>
        <span>Menu</span>
    </a>
    <a href="bookings.php" class="<?= $activeClientPage==='bookings'?'active':'' ?>">
        <i class="bi bi-calendar-check"></i>
        <span>Bookings</span>
    </a>
    <a href="dashboard.php" class="<?= $activeClientPage==='dashboard'?'active':'' ?>">
        <i class="bi bi-house"></i>
        <span>Dashboard</span>
    </a>
    <a href="invoices.php" class="<?= $activeClientPage==='invoices'?'active':'' ?>">
        <i class="bi bi-receipt"></i>
        <span>Invoices</span>
    </a>
    <a href="post_production.php" class="<?= $activeClientPage==='post_production'?'active':'' ?>">
        <i class="bi bi-film"></i>
        <span>Production</span>
    </a>
</nav>
<script>
function toggleClientSidebar(e) {
    e.preventDefault();
    const sidebar = document.getElementById('client-sidebar');
    const btn = e.currentTarget;
    sidebar.classList.toggle('show');
    btn.classList.toggle('sidebar-open', sidebar.classList.contains('show'));
}
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('client-sidebar');
    const btn = document.querySelector('.mob-menu-btn');
    if (sidebar && sidebar.classList.contains('show') && !sidebar.contains(e.target) && !btn.contains(e.target)) {
        sidebar.classList.remove('show');
        btn.classList.remove('sidebar-open');
    }
});
</script>
