<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

// ── Helpers ────────────────────────────────────────────────────
function deadlineStatus(?string $deadline): string {
    if (!$deadline) return 'early';
    $today    = new DateTime('today');
    $due      = new DateTime($deadline);
    if ($due < $today) return 'late';
    if ($today->diff($due)->days <= 3) return 'near';
    return 'early';
}

function autoProgress(string $photo, string $video, string $other): int {
    $map   = ['not_started' => 0, 'in_progress' => 1, 'completed' => 2];
    $score = ($map[$photo] ?? 0) + ($map[$video] ?? 0) + ($map[$other] ?? 0);
    return (int) round(($score / 6) * 100);
}

// ── POST Handler (PRG) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    // ── UPDATE PROGRESS ───────────────────────────────────────
    if ($action === 'update' && $id) {
        $photoStatus = $_POST['photo_status'] ?? 'not_started';
        $videoStatus = $_POST['video_status'] ?? 'not_started';
        $otherStatus = $_POST['other_status'] ?? 'not_started';
        $deadline    = $_POST['deadline']  ?? null;
        $notes       = trim($_POST['notes']      ?? '');
        $driveLink   = trim($_POST['drive_link'] ?? '');

        // Auto-calc or use manual override
        $manualPct = $_POST['progress_percent'] ?? '';
        $pct = ($manualPct !== '') ? min(100, max(0, (int)$manualPct))
                                   : autoProgress($photoStatus, $videoStatus, $otherStatus);

        $dlStatus = deadlineStatus($deadline ?: null);

        $pdo->prepare("
            UPDATE post_production
            SET photo_status=?, video_status=?, other_status=?,
                progress_percent=?, deadline=?, deadline_status=?,
                notes=?, drive_link=?
            WHERE id=?
        ")->execute([
            $photoStatus, $videoStatus, $otherStatus,
            $pct, $deadline ?: null, $dlStatus,
            $notes, $driveLink, $id
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Project updated successfully.'];

    // ── MARK AS COMPLETED ─────────────────────────────────────
    } elseif ($action === 'complete' && $id) {
        $pdo->prepare("
            UPDATE post_production
            SET photo_status='completed', video_status='completed',
                other_status='completed', progress_percent=100,
                deadline_status='early'
            WHERE id=?
        ")->execute([$id]);

        // Fetch client email for notification
        $row = $pdo->prepare("
            SELECT u.name, u.email, p.name AS package, b.booking_date
            FROM post_production pp
            JOIN bookings b  ON pp.booking_id = b.id
            JOIN users u     ON b.client_id   = u.id
            JOIN packages p  ON b.package_id  = p.id
            WHERE pp.id = ?
        ");
        $row->execute([$id]);
        $project = $row->fetch();

        if ($project && $project['email']) {
            $subject = 'Your Post-Production is Complete — Harvy Mance Films';
            $message = "Dear {$project['name']},\n\n"
                . "Great news! The post-production for your {$project['package']} booking "
                . "on {$project['booking_date']} is now complete.\n\n"
                . "Your files are ready. Please coordinate with us for the delivery.\n\n"
                . "Thank you for choosing Harvy Mance Films!\n\n"
                . "— Harvy Mance Films Team";
            $headers = "From: noreply@harvymancefilms.com\r\nContent-Type: text/plain; charset=UTF-8";
            @mail($project['email'], $subject, $message, $headers);
        }

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Project marked as completed and client notified.'];

    // ── CREATE NEW ENTRY (from approved booking) ──────────────
    } elseif ($action === 'create') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        if ($bookingId) {
            // Check if entry already exists
            $exists = $pdo->prepare("SELECT id FROM post_production WHERE booking_id = ?");
            $exists->execute([$bookingId]);
            if (!$exists->fetch()) {
                $pdo->prepare("INSERT INTO post_production (booking_id) VALUES (?)")
                    ->execute([$bookingId]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Post-production project created.'];
            } else {
                $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'A project already exists for that booking.'];
            }
        }
    }

    header('Location: post_production.php'); exit;
}

// ── Fetch all projects ─────────────────────────────────────────
$projects = $pdo->query("
    SELECT pp.*, u.name AS client, u.email AS client_email,
           p.name AS package, b.booking_date
    FROM post_production pp
    JOIN bookings b  ON pp.booking_id = b.id
    JOIN users u     ON b.client_id   = u.id
    JOIN packages p  ON b.package_id  = p.id
    ORDER BY pp.updated_at DESC
")->fetchAll();

// Sync deadline_status on every load
foreach ($projects as &$proj) {
    $proj['deadline_status'] = deadlineStatus($proj['deadline'] ?? null);
}
unset($proj);

// Approved bookings without a post-production entry
$unlinked = $pdo->query("
    SELECT b.id, b.booking_date, u.name AS client, p.name AS package
    FROM bookings b
    JOIN users u    ON b.client_id  = u.id
    JOIN packages p ON b.package_id = p.id
    WHERE b.status = 'approved'
    AND b.id NOT IN (SELECT booking_id FROM post_production WHERE booking_id IS NOT NULL)
    ORDER BY b.booking_date ASC
")->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$statusOpts  = ['not_started' => 'Not Started', 'in_progress' => 'In Progress', 'completed' => 'Completed'];
$dlBadge     = ['early' => 'success', 'near' => 'warning', 'late' => 'danger'];
$dlLabel     = ['early' => 'On Track', 'near' => 'Due Soon', 'late' => 'Overdue'];
$statusBadge = ['not_started' => 'secondary', 'in_progress' => 'primary', 'completed' => 'success'];

$pageTitle  = 'Post-Production — Harvy Mance Films';
$activePage = 'post_production';
require_once '../includes/admin_head.php';
?>
<style>
    .status-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:4px; }
    .mini-progress { height:6px; border-radius:10px; }
</style>
</head>
<body>

<?php require_once '../includes/sidebar.php'; ?>

<div id="main-wrapper">

    <!-- Topbar -->
    <div id="topbar">
        <div class="welcome">Welcome back, <span><?= htmlspecialchars($_SESSION['name']) ?></span></div>
        <input type="search" class="search-input" placeholder="&#128269; Search...">
    </div>

    <div class="p-4">

        <!-- Flash -->
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ── Header row ──────────────────────────────────── -->
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0 fw-semibold">Post-Production Tracker</h5>
            <?php if (!empty($unlinked)): ?>
                <button class="btn btn-sm text-white" style="background:var(--gold);"
                        data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="bi bi-plus-lg"></i> New Project
                </button>
            <?php endif; ?>
        </div>

        <!-- ── Project Table ──────────────────────────────── -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.855rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Client</th>
                                <th>Package</th>
                                <th>Event Date</th>
                                <th>Photo</th>
                                <th>Video</th>
                                <th>Other</th>
                                <th style="width:130px;">Progress</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th style="width:130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($projects)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No post-production projects yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($projects as $proj):
                                $pct      = min(100, max(0, (int)$proj['progress_percent']));
                                $barColor = $pct < 40 ? 'danger' : ($pct < 75 ? 'warning' : 'success');
                                $dl       = $proj['deadline_status'];
                                $isComplete = ($proj['photo_status'] === 'completed'
                                            && $proj['video_status'] === 'completed'
                                            && $proj['other_status'] === 'completed');
                            ?>
                            <tr class="<?= $isComplete ? 'table-success' : '' ?>">
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($proj['client']) ?></div>
                                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($proj['client_email']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($proj['package']) ?></td>
                                <td><?= htmlspecialchars($proj['booking_date']) ?></td>

                                <!-- Photo / Video / Other status -->
                                <?php foreach (['photo_status','video_status','other_status'] as $col): ?>
                                <td>
                                    <span class="badge bg-<?= $statusBadge[$proj[$col]] ?? 'secondary' ?>" style="font-size:.7rem;">
                                        <?= $statusOpts[$proj[$col]] ?? $proj[$col] ?>
                                    </span>
                                </td>
                                <?php endforeach; ?>

                                <!-- Progress bar -->
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <div class="progress flex-grow-1 mini-progress">
                                            <div class="progress-bar bg-<?= $barColor ?>"
                                                 style="width:<?= $pct ?>%"
                                                 role="progressbar"
                                                 aria-valuenow="<?= $pct ?>"
                                                 aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                        <span style="font-size:.75rem;width:32px;text-align:right;"><?= $pct ?>%</span>
                                    </div>
                                </td>

                                <!-- Deadline -->
                                <td><?= $proj['deadline'] ? htmlspecialchars($proj['deadline']) : '—' ?></td>

                                <!-- Deadline status badge -->
                                <td>
                                    <?php if ($isComplete): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-<?= $dlBadge[$dl] ?>">
                                            <?= $dlLabel[$dl] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td>
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2 me-1"
                                            onclick="openUpdateModal(<?= htmlspecialchars(json_encode($proj), ENT_QUOTES) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if (!$isComplete): ?>
                                        <form method="POST" class="d-inline"
                                              onsubmit="return confirm('Mark as completed and notify client?')">
                                            <input type="hidden" name="action" value="complete">
                                            <input type="hidden" name="id" value="<?= (int)$proj['id'] ?>">
                                            <button class="btn btn-sm btn-outline-success py-0 px-2">
                                                <i class="bi bi-check2-all"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main-wrapper -->

<!-- ── Update Progress Modal ──────────────────────────────── -->
<div class="modal fade" id="updateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="uId">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Update Project Progress</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Client / Package info -->
                    <div class="bg-light rounded p-3 mb-3" style="font-size:.85rem;">
                        <div class="row g-1">
                            <div class="col-4 text-muted">Client</div>
                            <div class="col-8 fw-semibold" id="uClient"></div>
                            <div class="col-4 text-muted">Package</div>
                            <div class="col-8" id="uPackage"></div>
                            <div class="col-4 text-muted">Event Date</div>
                            <div class="col-8" id="uEventDate"></div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <?php
                        $deliverables = [
                            'photo_status' => 'Photo Status',
                            'video_status' => 'Video Status',
                            'other_status' => 'Other Deliverables',
                        ];
                        foreach ($deliverables as $field => $label):
                        ?>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold"><?= $label ?></label>
                            <select class="form-select form-select-sm" name="<?= $field ?>"
                                    id="u_<?= $field ?>" onchange="syncProgress()">
                                <?php foreach ($statusOpts as $val => $text): ?>
                                    <option value="<?= $val ?>"><?= $text ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Progress %</label>
                            <input type="number" class="form-control form-control-sm"
                                   name="progress_percent" id="uProgress"
                                   min="0" max="100">
                            <div class="form-text">Leave blank to auto-calculate.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Deadline</label>
                            <input type="date" class="form-control form-control-sm"
                                   name="deadline" id="uDeadline">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Google Drive Link</label>
                            <input type="url" class="form-control form-control-sm"
                                   name="drive_link" id="uDriveLink"
                                   placeholder="https://drive.google.com/...">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control form-control-sm" name="notes"
                                      id="uNotes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm text-white" style="background:var(--gold);">
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ── Create New Project Modal ───────────────────────────── -->
<?php if (!empty($unlinked)): ?>
<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">New Post-Production Project</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold">Select Approved Booking</label>
                    <select class="form-select" name="booking_id" required>
                        <option value="">— Choose a booking —</option>
                        <?php foreach ($unlinked as $ul): ?>
                            <option value="<?= (int)$ul['id'] ?>">
                                #<?= (int)$ul['id'] ?> — <?= htmlspecialchars($ul['client']) ?>
                                (<?= htmlspecialchars($ul['package']) ?>)
                                on <?= htmlspecialchars($ul['booking_date']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm text-white" style="background:var(--gold);">
                        Create Project
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const scoreMap = { not_started: 0, in_progress: 1, completed: 2 };

    function syncProgress() {
        const photo = document.getElementById('u_photo_status').value;
        const video = document.getElementById('u_video_status').value;
        const other = document.getElementById('u_other_status').value;
        const score = (scoreMap[photo] + scoreMap[video] + scoreMap[other]);
        document.getElementById('uProgress').value = Math.round((score / 6) * 100);
    }

    function openUpdateModal(p) {
        document.getElementById('uId').value           = p.id;
        document.getElementById('uClient').textContent  = p.client       ?? '—';
        document.getElementById('uPackage').textContent = p.package      ?? '—';
        document.getElementById('uEventDate').textContent = p.booking_date ?? '—';

        document.getElementById('u_photo_status').value = p.photo_status ?? 'not_started';
        document.getElementById('u_video_status').value = p.video_status ?? 'not_started';
        document.getElementById('u_other_status').value = p.other_status ?? 'not_started';
        document.getElementById('uProgress').value      = p.progress_percent ?? '';
        document.getElementById('uDeadline').value      = p.deadline    ?? '';
        document.getElementById('uDriveLink').value     = p.drive_link  ?? '';
        document.getElementById('uNotes').value         = p.notes       ?? '';

        new bootstrap.Modal(document.getElementById('updateModal')).show();
    }
</script>
</body>
</html>
