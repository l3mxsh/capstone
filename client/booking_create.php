<?php
require_once '../auth/client_guard.php';
require_once '../config/db.php';

define('MAX_BOOKINGS_PER_DAY', 3);

if (isset($_GET['check_date'])) {
    header('Content-Type: application/json');
    $date = $_GET['check_date'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date <= date('Y-m-d')) {
        echo json_encode(['available'=>false,'msg'=>'Invalid or past date.']); exit;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE booking_date=? AND status='approved'");
    $stmt->execute([$date]); $count = (int)$stmt->fetchColumn();
    if ($count >= MAX_BOOKINGS_PER_DAY) { echo json_encode(['available'=>false,'msg'=>'This date is fully booked. Please choose another.']); }
    else { $remaining = MAX_BOOKINGS_PER_DAY - $count; echo json_encode(['available'=>true,'msg'=>"Date available. {$remaining} slot(s) remaining."]); }
    exit;
}

$errors = []; $success = false; $old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'package_id'=>(int)($_POST['package_id']??0), 'event_type'=>trim($_POST['event_type']??''),
        'booking_date'=>trim($_POST['booking_date']??''), 'venue'=>trim($_POST['venue']??''), 'notes'=>trim($_POST['notes']??''),
    ];
    if (!$old['package_id']) { $errors['package_id'] = 'Please select a package.'; }
    else {
        $pkgCheck = $pdo->prepare("SELECT id FROM packages WHERE id=? AND status='active'");
        $pkgCheck->execute([$old['package_id']]);
        if (!$pkgCheck->fetch()) $errors['package_id'] = 'Invalid package selected.';
    }
    if ($old['event_type']==='') $errors['event_type']='Event type is required.';
    if ($old['venue']==='') $errors['venue']='Venue is required.';
    if ($old['booking_date']==='') { $errors['booking_date']='Booking date is required.'; }
    elseif ($old['booking_date'] <= date('Y-m-d')) { $errors['booking_date']='Booking date must be a future date.'; }
    else {
        $avail = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE booking_date=? AND status='approved'");
        $avail->execute([$old['booking_date']]);
        if ((int)$avail->fetchColumn() >= MAX_BOOKINGS_PER_DAY) $errors['booking_date']='This date is fully booked.';
    }
    if (empty($errors)) {
        $pdo->prepare("INSERT INTO bookings (client_id,package_id,booking_date,event_type,venue,notes,status) VALUES (?,?,?,?,?,?,'pending')")
            ->execute([$_SESSION['user_id'],$old['package_id'],$old['booking_date'],$old['event_type'],$old['venue'],$old['notes']]);
        $bookingId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO post_production (booking_id) VALUES (?)")->execute([$bookingId]);
        $subject = "New Booking Request #{$bookingId} — Harvy Mance Films";
        $message = "A new booking request has been submitted.\n\nBooking ID: #{$bookingId}\nClient: {$_SESSION['name']}\nEvent Type: {$old['event_type']}\nDate: {$old['booking_date']}\nVenue: {$old['venue']}\n\nPlease log in to the admin panel to review.";
        @mail('admin@harvymancefilms.com', $subject, $message, "From: noreply@harvymancefilms.com\r\nContent-Type: text/plain; charset=UTF-8");
        $success = true; $old = [];
    }
}

$packages = $pdo->query("SELECT id,name,price,inclusions FROM packages WHERE status='active' ORDER BY name")->fetchAll();
$initials = strtoupper(substr($_SESSION['name'],0,1));
$pageTitle = 'Book a Service — Client Portal';
$activeClientPage = 'bookings';
require_once '../includes/client_head.php';
?>
<style>
    .step-bar { display:flex; gap:0; margin-bottom:2rem; }
    .step-item { flex:1; text-align:center; position:relative; padding-bottom:.5rem; }
    .step-item::after { content:''; position:absolute; top:17px; left:50%; right:-50%; height:2px; background:#e8e8e8; z-index:0; }
    .step-item:last-child::after { display:none; }
    .step-circle {
        width:34px; height:34px; border-radius:50%;
        background:#e8e8e8; color:#888;
        display:inline-flex; align-items:center; justify-content:center;
        font-weight:700; font-size:.82rem; position:relative; z-index:1;
        transition:background .2s, color .2s;
    }
    .step-item.active .step-circle  { background:#111; color:#fff; }
    .step-item.done .step-circle    { background:#27ae60; color:#fff; }
    .step-item.active::after, .step-item.done::after { background:#111; }
    .step-label { font-size:.7rem; color:#888; margin-top:.3rem; }
    .step-item.active .step-label { color:#111; font-weight:600; }
    .form-step { display:none; }
    .form-step.active { display:block; }
    .pkg-option { display:none; }
    .pkg-label {
        border: 1.5px solid #e8e8e8; border-radius:10px; padding:1rem;
        cursor:pointer; transition:border-color .2s, box-shadow .2s; height:100%;
    }
    .pkg-option:checked + .pkg-label { border-color:#111; box-shadow:0 0 0 3px rgba(0,0,0,.06); }
    .pkg-price { font-weight:800; font-size:.95rem; color:#111; }
    #dateMsg { font-size:.78rem; margin-top:.35rem; display:none; }
</style>
</head>
<body>
<?php require_once '../includes/client_sidebar.php'; ?>

<div id="client-main">
    <div id="client-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="topbar-btn d-lg-none border-0" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <div>
                <div class="topbar-title">Book a Service</div>
                <div class="topbar-sub">Submit a new booking request</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="topbar-avatar"><?= htmlspecialchars($initials) ?></div>
            <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>

    <div class="p-3 p-md-4">
        <div class="row justify-content-center">
            <div class="col-xl-8 col-lg-10">

                <?php if ($success): ?>
                <div class="dash-card">
                    <div class="dash-card-body text-center py-5">
                        <i class="bi bi-check-circle-fill d-block fs-1 mb-3" style="color:#27ae60;"></i>
                        <h5 class="fw-bold mb-2">Booking Submitted!</h5>
                        <p class="text-muted mb-4" style="font-size:.875rem;">Your request is now <strong>Pending</strong>. The admin will review and approve it. You'll be notified by email.</p>
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm px-4"><i class="bi bi-house me-1"></i>Dashboard</a>
                            <a href="booking_create.php" class="btn btn-dark btn-sm px-4"><i class="bi bi-plus-lg me-1"></i>Book Again</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>

                <div class="dash-card p-4">
                    <div class="fw-bold mb-1">New Booking Request</div>
                    <p class="text-muted mb-4" style="font-size:.82rem;">Fill in the details below to submit your booking request.</p>

                    <!-- Step bar -->
                    <div class="step-bar" id="stepBar">
                        <div class="step-item active" id="stepIndicator1"><div class="step-circle">1</div><div class="step-label">Package</div></div>
                        <div class="step-item" id="stepIndicator2"><div class="step-circle">2</div><div class="step-label">Event Details</div></div>
                        <div class="step-item" id="stepIndicator3"><div class="step-circle">3</div><div class="step-label">Review</div></div>
                    </div>

                    <form method="POST" id="bookingForm" novalidate>

                        <!-- Step 1: Package -->
                        <div class="form-step active" id="step1">
                            <p class="fw-semibold mb-3" style="font-size:.88rem;">Choose a Package <span class="text-danger">*</span></p>
                            <?php if (empty($packages)): ?>
                                <div class="info-box text-center py-3 text-muted">No active packages available.</div>
                            <?php else: ?>
                            <div class="row g-3" id="pkgCards">
                                <?php foreach ($packages as $pkg): ?>
                                <div class="col-md-6">
                                    <input type="radio" class="pkg-option" name="package_id" id="pkg<?= (int)$pkg['id'] ?>" value="<?= (int)$pkg['id'] ?>" <?= (int)($old['package_id']??0)===(int)$pkg['id']?'checked':'' ?>>
                                    <label class="pkg-label d-block" for="pkg<?= (int)$pkg['id'] ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="fw-bold"><?= htmlspecialchars($pkg['name']) ?></span>
                                            <span class="pkg-price">₱<?= number_format((float)$pkg['price'],2) ?></span>
                                        </div>
                                        <?php if ($pkg['inclusions']): ?>
                                        <ul class="mb-0 ps-3" style="font-size:.76rem;color:#888;">
                                            <?php foreach (explode("\n",trim($pkg['inclusions'])) as $inc): if(trim($inc)!==''): ?>
                                                <li><?= htmlspecialchars(trim($inc)) ?></li>
                                            <?php endif; endforeach; ?>
                                        </ul>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($errors['package_id'])): ?>
                                <div class="text-danger mt-2" style="font-size:.8rem;"><?= htmlspecialchars($errors['package_id']) ?></div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="button" class="btn btn-dark btn-sm px-4" onclick="goStep(2)">Next <i class="bi bi-arrow-right ms-1"></i></button>
                            </div>
                        </div>

                        <!-- Step 2: Event Details -->
                        <div class="form-step" id="step2">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Event Type <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?= isset($errors['event_type'])?'is-invalid':'' ?>" name="event_type" value="<?= htmlspecialchars($old['event_type']??'') ?>" placeholder="e.g. Wedding, Graduation, Birthday" required>
                                    <?php if (isset($errors['event_type'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['event_type']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Preferred Date <span class="text-danger">*</span></label>
                                    <input type="date" id="bookingDate" class="form-control <?= isset($errors['booking_date'])?'is-invalid':'' ?>" name="booking_date" value="<?= htmlspecialchars($old['booking_date']??'') ?>" min="<?= date('Y-m-d',strtotime('+1 day')) ?>" required>
                                    <div id="dateMsg"></div>
                                    <?php if (isset($errors['booking_date'])): ?><div class="invalid-feedback d-block"><?= htmlspecialchars($errors['booking_date']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Venue / Location <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?= isset($errors['venue'])?'is-invalid':'' ?>" name="venue" value="<?= htmlspecialchars($old['venue']??'') ?>" placeholder="Full event address or venue name" required>
                                    <?php if (isset($errors['venue'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['venue']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Additional Notes</label>
                                    <textarea class="form-control" name="notes" rows="3" placeholder="Any special requests or details…"><?= htmlspecialchars($old['notes']??'') ?></textarea>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary btn-sm px-4" onclick="goStep(1)"><i class="bi bi-arrow-left me-1"></i>Back</button>
                                <button type="button" class="btn btn-dark btn-sm px-4" onclick="goStep(3)">Next <i class="bi bi-arrow-right ms-1"></i></button>
                            </div>
                        </div>

                        <!-- Step 3: Review -->
                        <div class="form-step" id="step3">
                            <div class="section-hd mb-3">Review Your Booking</div>
                            <div class="info-box mb-4">
                                <div class="row g-2" style="font-size:.85rem;">
                                    <div class="col-4 info-label">Package</div><div class="col-8 fw-semibold" id="reviewPackage">—</div>
                                    <div class="col-4 info-label">Event Type</div><div class="col-8" id="reviewEvent">—</div>
                                    <div class="col-4 info-label">Date</div><div class="col-8" id="reviewDate">—</div>
                                    <div class="col-4 info-label">Venue</div><div class="col-8" id="reviewVenue">—</div>
                                    <div class="col-4 info-label">Notes</div><div class="col-8 text-muted" id="reviewNotes">—</div>
                                </div>
                            </div>
                            <div class="info-box mb-4" style="border-color:#e8e8e8;background:#fafafa;font-size:.82rem;">
                                <i class="bi bi-info-circle me-1"></i>
                                Your booking will be set to <strong>Pending</strong> until approved by the admin. You will be notified by email.
                            </div>
                            <div class="d-flex justify-content-between mt-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm px-4" onclick="goStep(2)"><i class="bi bi-arrow-left me-1"></i>Back</button>
                                <button type="submit" class="btn btn-dark btn-sm px-4 fw-semibold"><i class="bi bi-send me-1"></i>Submit Booking</button>
                            </div>
                        </div>

                    </form>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('client-sidebar').classList.toggle('show'));

    const pkgNames = {
        <?php foreach ($packages as $pkg): ?>
        <?= (int)$pkg['id'] ?>: '<?= addslashes(htmlspecialchars($pkg['name'])) ?> — ₱<?= number_format((float)$pkg['price'],2) ?>',
        <?php endforeach; ?>
    };
    let dateAvailable = true;

    function goStep(n) {
        if (n === 2 && !document.querySelector('input[name="package_id"]:checked')) { alert('Please select a package.'); return; }
        if (n === 3) {
            const event = document.querySelector('[name="event_type"]').value.trim();
            const date  = document.querySelector('[name="booking_date"]').value;
            const venue = document.querySelector('[name="venue"]').value.trim();
            if (!event) { alert('Please enter the event type.'); return; }
            if (!date)  { alert('Please select a booking date.'); return; }
            if (!venue) { alert('Please enter the venue.'); return; }
            if (!dateAvailable) { alert('Please choose an available date.'); return; }
            populateReview();
        }
        document.querySelectorAll('.form-step').forEach((s,i) => s.classList.toggle('active', i+1===n));
        document.querySelectorAll('.step-item').forEach((el,i) => {
            el.classList.remove('active','done');
            if (i+1===n) el.classList.add('active');
            if (i+1<n)  el.classList.add('done');
        });
    }
    function populateReview() {
        const pkg   = document.querySelector('input[name="package_id"]:checked');
        document.getElementById('reviewPackage').textContent = pkg ? (pkgNames[pkg.value]||'—') : '—';
        document.getElementById('reviewEvent').textContent   = document.querySelector('[name="event_type"]').value.trim() || '—';
        document.getElementById('reviewDate').textContent    = document.querySelector('[name="booking_date"]').value || '—';
        document.getElementById('reviewVenue').textContent   = document.querySelector('[name="venue"]').value.trim() || '—';
        document.getElementById('reviewNotes').textContent   = document.querySelector('[name="notes"]').value.trim() || '—';
    }
    let dateTimer = null;
    document.getElementById('bookingDate')?.addEventListener('change', function() {
        const val = this.value; const msg = document.getElementById('dateMsg');
        if (!val) { msg.style.display='none'; return; }
        clearTimeout(dateTimer);
        dateTimer = setTimeout(() => {
            msg.style.display='block'; msg.className='text-muted'; msg.textContent='Checking availability…';
            fetch(`?check_date=${encodeURIComponent(val)}`).then(r=>r.json()).then(data => {
                dateAvailable=data.available; msg.style.display='block';
                msg.className=data.available?'text-success':'text-danger';
                msg.innerHTML=`<i class="bi bi-${data.available?'check-circle':'x-circle'}"></i> ${data.msg}`;
            }).catch(()=>{ msg.style.display='none'; dateAvailable=true; });
        }, 400);
    });
    <?php if (!empty($errors)): ?>
    <?php if (isset($errors['package_id'])): ?>goStep(1);
    <?php elseif (isset($errors['event_type'])||isset($errors['booking_date'])||isset($errors['venue'])): ?>goStep(2);
    <?php endif; ?>
    <?php endif; ?>
</script>
</body>
</html>
