<?php
require_once '../auth/client_guard.php';
require_once '../config/db.php';

define('MAX_BOOKINGS_PER_DAY', 3);

// ── AJAX: availability check ───────────────────────────────────
if (isset($_GET['check_date'])) {
    header('Content-Type: application/json');
    $date = $_GET['check_date'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date <= date('Y-m-d')) {
        echo json_encode(['available' => false, 'msg' => 'Invalid or past date.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE booking_date = ? AND status = 'approved'");
    $stmt->execute([$date]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= MAX_BOOKINGS_PER_DAY) {
        echo json_encode(['available' => false, 'msg' => 'This date is fully booked. Please choose another date.']);
    } else {
        $remaining = MAX_BOOKINGS_PER_DAY - $count;
        echo json_encode(['available' => true, 'msg' => "Date available. {$remaining} slot(s) remaining."]);
    }
    exit;
}

// ── POST Handler ───────────────────────────────────────────────
$errors  = [];
$success = false;
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'package_id'   => (int)($_POST['package_id']   ?? 0),
        'event_type'   => trim($_POST['event_type']    ?? ''),
        'booking_date' => trim($_POST['booking_date']  ?? ''),
        'venue'        => trim($_POST['venue']         ?? ''),
        'notes'        => trim($_POST['notes']         ?? ''),
    ];

    // ── Validate ──────────────────────────────────────────────
    if (!$old['package_id']) {
        $errors['package_id'] = 'Please select a package.';
    } else {
        $pkgCheck = $pdo->prepare("SELECT id FROM packages WHERE id = ? AND status = 'active'");
        $pkgCheck->execute([$old['package_id']]);
        if (!$pkgCheck->fetch()) $errors['package_id'] = 'Invalid package selected.';
    }

    if ($old['event_type'] === '') $errors['event_type'] = 'Event type is required.';
    if ($old['venue']      === '') $errors['venue']      = 'Venue is required.';

    if ($old['booking_date'] === '') {
        $errors['booking_date'] = 'Booking date is required.';
    } elseif ($old['booking_date'] <= date('Y-m-d')) {
        $errors['booking_date'] = 'Booking date must be a future date.';
    } else {
        // Server-side availability re-check
        $avail = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE booking_date = ? AND status = 'approved'");
        $avail->execute([$old['booking_date']]);
        if ((int)$avail->fetchColumn() >= MAX_BOOKINGS_PER_DAY) {
            $errors['booking_date'] = 'This date is fully booked. Please choose another date.';
        }
    }

    // ── Insert if valid ───────────────────────────────────────
    if (empty($errors)) {
        $pdo->prepare("
            INSERT INTO bookings (client_id, package_id, booking_date, event_type, venue, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ")->execute([
            $_SESSION['user_id'],
            $old['package_id'],
            $old['booking_date'],
            $old['event_type'],
            $old['venue'],
            $old['notes'],
        ]);

        $bookingId = (int)$pdo->lastInsertId();

        // Init post-production tracker
        $pdo->prepare("INSERT INTO post_production (booking_id) VALUES (?)")
            ->execute([$bookingId]);

        // Notify admin via email
        $adminEmail = 'admin@harvymancefilms.com';
        $subject    = "New Booking Request #{$bookingId} — Harvy Mance Films";
        $message    = "A new booking request has been submitted.\n\n"
            . "Booking ID:   #{$bookingId}\n"
            . "Client:       {$_SESSION['name']}\n"
            . "Package ID:   {$old['package_id']}\n"
            . "Event Type:   {$old['event_type']}\n"
            . "Date:         {$old['booking_date']}\n"
            . "Venue:        {$old['venue']}\n"
            . "Notes:        " . ($old['notes'] ?: 'N/A') . "\n\n"
            . "Please log in to the admin panel to review and approve this booking.";
        $headers = "From: noreply@harvymancefilms.com\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($adminEmail, $subject, $message, $headers);

        $success = true;
        $old     = []; // clear form
    }
}

// ── Fetch active packages ──────────────────────────────────────
$packages = $pdo->query("SELECT id, name, price, inclusions FROM packages WHERE status = 'active' ORDER BY name")->fetchAll();

$initials        = strtoupper(substr($_SESSION['name'], 0, 1));
$pageTitle       = 'Book a Service — Client Portal';
$activeClientPage = 'bookings';

require_once '../includes/client_head.php';
?>
<style>
    /* ── Step indicators ── */
    .step-bar { display:flex; gap:0; margin-bottom:2rem; }
    .step-item {
        flex:1; text-align:center; position:relative;
        padding-bottom:.5rem;
    }
    .step-item::after {
        content:''; position:absolute; top:18px; left:50%; right:-50%;
        height:2px; background:#dee2e6; z-index:0;
    }
    .step-item:last-child::after { display:none; }
    .step-circle {
        width:36px; height:36px; border-radius:50%;
        background:#dee2e6; color:#6c757d;
        display:inline-flex; align-items:center; justify-content:center;
        font-weight:700; font-size:.85rem; position:relative; z-index:1;
        transition:background .2s, color .2s;
    }
    .step-item.active   .step-circle { background:var(--gold); color:#fff; }
    .step-item.done     .step-circle { background:#198754;     color:#fff; }
    .step-item.active::after,
    .step-item.done::after { background:var(--gold); }
    .step-label { font-size:.72rem; color:#6c757d; margin-top:.3rem; }
    .step-item.active .step-label { color:var(--gold); font-weight:600; }

    /* ── Form steps ── */
    .form-step { display:none; }
    .form-step.active { display:block; }

    /* ── Package card selector ── */
    .pkg-option { display:none; }
    .pkg-label {
        border:2px solid #dee2e6; border-radius:10px; padding:1rem;
        cursor:pointer; transition:border-color .2s, box-shadow .2s;
        height:100%;
    }
    .pkg-option:checked + .pkg-label {
        border-color:var(--gold);
        box-shadow:0 0 0 3px rgba(201,168,76,.15);
    }
    .pkg-price { color:var(--gold); font-weight:700; font-size:1rem; }

    /* ── Date availability indicator ── */
    #dateMsg {
        font-size:.8rem; margin-top:.35rem;
        display:none;
    }
</style>
</head>
<body>

<?php require_once '../includes/client_sidebar.php'; ?>

<div id="client-main">

    <!-- Topbar -->
    <div id="client-topbar">
        <span class="page-label">Book a Service</span>
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
        <div class="row justify-content-center">
            <div class="col-xl-8 col-lg-10">

                <!-- ── Success message ────────────────────── -->
                <?php if ($success): ?>
                <div class="portal-card p-5 text-center">
                    <div style="font-size:3rem;color:#198754;"><i class="bi bi-check-circle-fill"></i></div>
                    <h5 class="mt-3 fw-bold">Booking Submitted!</h5>
                    <p class="text-muted mb-4">
                        Your booking request has been submitted.<br>
                        Please wait for admin approval. You'll be notified via email.
                    </p>
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-house"></i> Dashboard
                        </a>
                        <a href="booking_create.php"
                           class="btn btn-sm text-white" style="background:var(--gold);border:none;">
                            <i class="bi bi-plus-lg"></i> Book Again
                        </a>
                    </div>
                </div>
                <?php else: ?>

                <!-- ── Booking Form Card ───────────────────── -->
                <div class="portal-card p-4 p-md-5">
                    <h5 class="fw-bold mb-1">New Booking Request</h5>
                    <p class="text-muted mb-4" style="font-size:.875rem;">
                        Fill in the details below to submit your booking.
                    </p>

                    <!-- Step bar -->
                    <div class="step-bar" id="stepBar">
                        <div class="step-item active" id="stepIndicator1">
                            <div class="step-circle">1</div>
                            <div class="step-label">Package</div>
                        </div>
                        <div class="step-item" id="stepIndicator2">
                            <div class="step-circle">2</div>
                            <div class="step-label">Event Details</div>
                        </div>
                        <div class="step-item" id="stepIndicator3">
                            <div class="step-circle">3</div>
                            <div class="step-label">Review</div>
                        </div>
                    </div>

                    <form method="POST" id="bookingForm" novalidate>

                        <!-- ── STEP 1: Package selection ─── -->
                        <div class="form-step active" id="step1">
                            <p class="fw-semibold mb-3" style="font-size:.9rem;">
                                Choose a Package <span class="text-danger">*</span>
                            </p>

                            <?php if (empty($packages)): ?>
                                <div class="alert alert-warning">No active packages available.</div>
                            <?php else: ?>
                            <div class="row g-3" id="pkgCards">
                                <?php foreach ($packages as $pkg): ?>
                                <div class="col-md-6">
                                    <input type="radio" class="pkg-option" name="package_id"
                                           id="pkg<?= (int)$pkg['id'] ?>"
                                           value="<?= (int)$pkg['id'] ?>"
                                           <?= (int)($old['package_id'] ?? 0) === (int)$pkg['id'] ? 'checked' : '' ?>>
                                    <label class="pkg-label d-block" for="pkg<?= (int)$pkg['id'] ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <span class="fw-bold"><?= htmlspecialchars($pkg['name']) ?></span>
                                            <span class="pkg-price">₱<?= number_format((float)$pkg['price'], 2) ?></span>
                                        </div>
                                        <?php if ($pkg['inclusions']): ?>
                                        <ul class="mb-0 ps-3" style="font-size:.78rem;color:#6c757d;">
                                            <?php foreach (explode("\n", trim($pkg['inclusions'])) as $inc): ?>
                                                <?php if (trim($inc) !== ''): ?>
                                                    <li><?= htmlspecialchars(trim($inc)) ?></li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <?php if (isset($errors['package_id'])): ?>
                                <div class="text-danger mt-2" style="font-size:.82rem;">
                                    <?= htmlspecialchars($errors['package_id']) ?>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="button" class="btn btn-sm text-white px-4"
                                        style="background:var(--gold);border:none;"
                                        onclick="goStep(2)">
                                    Next <i class="bi bi-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- ── STEP 2: Event details ──────── -->
                        <div class="form-step" id="step2">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Event Type <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control <?= isset($errors['event_type']) ? 'is-invalid' : '' ?>"
                                           name="event_type"
                                           value="<?= htmlspecialchars($old['event_type'] ?? '') ?>"
                                           placeholder="e.g. Wedding, Graduation, Birthday"
                                           required>
                                    <?php if (isset($errors['event_type'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['event_type']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        Preferred Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" id="bookingDate"
                                           class="form-control <?= isset($errors['booking_date']) ? 'is-invalid' : '' ?>"
                                           name="booking_date"
                                           value="<?= htmlspecialchars($old['booking_date'] ?? '') ?>"
                                           min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                           required>
                                    <div id="dateMsg"></div>
                                    <?php if (isset($errors['booking_date'])): ?>
                                        <div class="invalid-feedback d-block">
                                            <?= htmlspecialchars($errors['booking_date']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold">
                                        Venue / Location <span class="text-danger">*</span>
                                    </label>
                                    <input type="text"
                                           class="form-control <?= isset($errors['venue']) ? 'is-invalid' : '' ?>"
                                           name="venue"
                                           value="<?= htmlspecialchars($old['venue'] ?? '') ?>"
                                           placeholder="Full event address or venue name"
                                           required>
                                    <?php if (isset($errors['venue'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['venue']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold">Additional Notes</label>
                                    <textarea class="form-control" name="notes" rows="3"
                                              placeholder="Any special requests or details..."><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-sm btn-outline-secondary px-4"
                                        onclick="goStep(1)">
                                    <i class="bi bi-arrow-left"></i> Back
                                </button>
                                <button type="button" class="btn btn-sm text-white px-4"
                                        style="background:var(--gold);border:none;"
                                        onclick="goStep(3)">
                                    Next <i class="bi bi-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- ── STEP 3: Review & submit ─────── -->
                        <div class="form-step" id="step3">
                            <div class="section-title mb-3">Review Your Booking</div>
                            <div class="bg-light rounded p-3 mb-4" style="font-size:.875rem;">
                                <div class="row g-2">
                                    <div class="col-4 text-muted">Package</div>
                                    <div class="col-8 fw-semibold" id="reviewPackage">—</div>
                                    <div class="col-4 text-muted">Event Type</div>
                                    <div class="col-8" id="reviewEvent">—</div>
                                    <div class="col-4 text-muted">Date</div>
                                    <div class="col-8" id="reviewDate">—</div>
                                    <div class="col-4 text-muted">Venue</div>
                                    <div class="col-8" id="reviewVenue">—</div>
                                    <div class="col-4 text-muted">Notes</div>
                                    <div class="col-8 text-muted" id="reviewNotes">—</div>
                                </div>
                            </div>

                            <div class="alert alert-info py-2" style="font-size:.82rem;">
                                <i class="bi bi-info-circle"></i>
                                Your booking will be set to <strong>Pending</strong> until approved by the admin.
                            </div>

                            <div class="d-flex justify-content-between mt-3">
                                <button type="button" class="btn btn-sm btn-outline-secondary px-4"
                                        onclick="goStep(2)">
                                    <i class="bi bi-arrow-left"></i> Back
                                </button>
                                <button type="submit" class="btn btn-sm text-white px-4 fw-semibold"
                                        style="background:var(--gold);border:none;">
                                    <i class="bi bi-send"></i> Submit Booking
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div><!-- /content -->
</div><!-- /client-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Package name map from PHP
    const pkgNames = {
        <?php foreach ($packages as $pkg): ?>
        <?= (int)$pkg['id'] ?>: '<?= addslashes(htmlspecialchars($pkg['name'])) ?> — ₱<?= number_format((float)$pkg['price'], 2) ?>',
        <?php endforeach; ?>
    };

    let dateAvailable = true;

    // ── Step navigation ─────────────────────────────────────────
    function goStep(n) {
        // Validate before advancing
        if (n === 2) {
            const pkg = document.querySelector('input[name="package_id"]:checked');
            if (!pkg) { alert('Please select a package to continue.'); return; }
        }
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

        document.querySelectorAll('.form-step').forEach((s, i) => {
            s.classList.toggle('active', i + 1 === n);
        });

        // Update step bar
        document.querySelectorAll('.step-item').forEach((el, i) => {
            el.classList.remove('active', 'done');
            if (i + 1 === n) el.classList.add('active');
            if (i + 1 < n)  el.classList.add('done');
        });
    }

    // ── Review panel ────────────────────────────────────────────
    function populateReview() {
        const pkg   = document.querySelector('input[name="package_id"]:checked');
        const event = document.querySelector('[name="event_type"]').value.trim();
        const date  = document.querySelector('[name="booking_date"]').value;
        const venue = document.querySelector('[name="venue"]').value.trim();
        const notes = document.querySelector('[name="notes"]').value.trim();

        document.getElementById('reviewPackage').textContent = pkg ? (pkgNames[pkg.value] || '—') : '—';
        document.getElementById('reviewEvent').textContent   = event || '—';
        document.getElementById('reviewDate').textContent    = date  || '—';
        document.getElementById('reviewVenue').textContent   = venue || '—';
        document.getElementById('reviewNotes').textContent   = notes || '—';
    }

    // ── AJAX date availability check ────────────────────────────
    let dateTimer = null;
    document.getElementById('bookingDate')?.addEventListener('change', function () {
        const val = this.value;
        const msg = document.getElementById('dateMsg');
        if (!val) { msg.style.display = 'none'; return; }

        clearTimeout(dateTimer);
        dateTimer = setTimeout(() => {
            msg.style.display = 'block';
            msg.className     = 'text-muted';
            msg.textContent   = 'Checking availability…';

            fetch(`?check_date=${encodeURIComponent(val)}`)
                .then(r => r.json())
                .then(data => {
                    dateAvailable     = data.available;
                    msg.style.display = 'block';
                    msg.className     = data.available ? 'text-success' : 'text-danger';
                    msg.innerHTML     = `<i class="bi bi-${data.available ? 'check-circle' : 'x-circle'}"></i> ${data.msg}`;
                })
                .catch(() => {
                    msg.style.display = 'none';
                    dateAvailable     = true;
                });
        }, 400);
    });

    // If form had server-side errors, jump to the right step
    <?php if (!empty($errors)): ?>
    <?php if (isset($errors['package_id'])): ?>
    goStep(1);
    <?php elseif (isset($errors['event_type']) || isset($errors['booking_date']) || isset($errors['venue'])): ?>
    goStep(2);
    <?php endif; ?>
    <?php endif; ?>
</script>
</body>
</html>
