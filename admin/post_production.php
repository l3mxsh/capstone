<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

function deadlineStatus(?string $deadline): string {
    if (!$deadline) return 'early';
    $today = new DateTime('today'); $due = new DateTime($deadline);
    if ($due < $today) return 'late';
    if ($today->diff($due)->days <= 3) return 'near';
    return 'early';
}
function autoProgress(string $photo, string $video, string $other): int {
    $map = ['not_started'=>0,'in_progress'=>1,'completed'=>2];
    return (int)round((($map[$photo]??0)+($map[$video]??0)+($map[$other]??0))/6*100);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action']??''; $id = (int)($_POST['id']??0);
    if ($action === 'update' && $id) {
        $photoStatus = $_POST['photo_status']??'not_started'; $videoStatus = $_POST['video_status']??'not_started';
        $otherStatus = $_POST['other_status']??'not_started'; $deadline = $_POST['deadline']??null;
        $notes = trim($_POST['notes']??''); $driveLink = trim($_POST['drive_link']??'');
        $manualPct = $_POST['progress_percent']??'';
        $pct = ($manualPct!=='') ? min(100,max(0,(int)$manualPct)) : autoProgress($photoStatus,$videoStatus,$otherStatus);
        $dlStatus = deadlineStatus($deadline?:null);
        $pdo->prepare("UPDATE post_production SET photo_status=?,video_status=?,other_status=?,progress_percent=?,deadline=?,deadline_status=?,notes=?,drive_link=? WHERE id=?")
            ->execute([$photoStatus,$videoStatus,$otherStatus,$pct,$deadline?:null,$dlStatus,$notes,$driveLink,$id]);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Project updated successfully.'];
    } elseif ($action === 'complete' && $id) {
        $pdo->prepare("UPDATE post_production SET photo_status='completed',video_status='completed',other_status='completed',progress_percent=100,deadline_status='early' WHERE id=?")->execute([$id]);
        $row = $pdo->prepare("SELECT u.name,u.email,p.name AS package,b.booking_date FROM post_production pp JOIN bookings b ON pp.booking_id=b.id JOIN users u ON b.client_id=u.id JOIN packages p ON b.package_id=p.id WHERE pp.id=?");
        $row->execute([$id]); $project = $row->fetch();
        if ($project && $project['email']) {
            $msg = "Dear {$project['name']},\n\nGreat news! The post-production for your {$project['package']} booking on {$project['booking_date']} is now complete.\n\n— Harvy Mance Films";
            @mail($project['email'], 'Your Post-Production is Complete — Harvy Mance Films', $msg, "From: noreply@harvymancefilms.com\r\nContent-Type: text/plain; charset=UTF-8");
        }
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Project marked as completed and client notified.'];
    } elseif ($action === 'create') {
        $bookingId = (int)($_POST['booking_id']??0);
        if ($bookingId) {
            $exists = $pdo->prepare("SELECT id FROM post_production WHERE booking_id=?"); $exists->execute([$bookingId]);
            if (!$exists->fetch()) { $pdo->prepare("INSERT INTO post_production (booking_id) VALUES (?)")->execute([$bookingId]); $_SESSION['flash'] = ['type'=>'success','msg'=>'Post-production project created.']; }
            else { $_SESSION['flash'] = ['type'=>'warning','msg'=>'A project already exists for that booking.']; }
        }
    }
    header('Location: post_production.php'); exit;
}

$projects = $pdo->query("
    SELECT pp.*, u.name AS client, u.email AS client_email, p.name AS package, b.booking_date
    FROM post_production pp JOIN bookings b ON pp.booking_id=b.id JOIN users u ON b.client_id=u.id JOIN packages p ON b.package_id=p.id
    ORDER BY pp.updated_at DESC
")->fetchAll();
foreach ($projects as &$proj) { $proj['deadline_status'] = deadlineStatus($proj['deadline']??null); } unset($proj);

$unlinked = $pdo->query("
    SELECT b.id,b.booking_date,u.name AS client,p.name AS package
    FROM bookings b JOIN users u ON b.client_id=u.id JOIN packages p ON b.package_id=p.id
    WHERE b.status='approved' AND b.id NOT IN (SELECT booking_id FROM post_production WHERE booking_id IS NOT NULL)
    ORDER BY b.booking_date ASC
")->fetchAll();

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$statusOpts = ['not_started'=>'Not Started','in_progress'=>'In Progress','completed'=>'Completed'];
$dlBadge = ['early'=>'success','near'=>'warning','late'=>'danger'];
$dlLabel = ['early'=>'On Track','near'=>'Due Soon','late'=>'Overdue'];
$statusBadge = ['not_started'=>'secondary','in_progress'=>'primary','completed'=>'success'];

$pageTitle  = 'Post-Production';
$activePage = 'post_production';
require_once '../includes/admin_head.php';
$_adminAvatar  ??= null;
$_adminInitial ??= strtoupper(substr($_SESSION['name'], 0, 1));
?>
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>

<!-- Toast -->
<div class="toast-container position-fixed top-0 end-0 p-3">
<?php if ($flash): ?>
    <div id="liveToast" class="toast align-items-center border-0 show" role="alert"
         style="background:<?= $flash['type']==='success'?'#f0fdf4':($flash['type']==='danger'?'#fef2f2':'#fffbeb') ?>">
        <div class="toast-header" style="background:transparent;">
            <i class="bi <?= $flash['type']==='success'?'bi-check-circle-fill text-success':($flash['type']==='danger'?'bi-x-circle-fill text-danger':'bi-exclamation-triangle-fill text-warning') ?> me-2"></i>
            <strong class="me-auto"><?= ucfirst($flash['type']==='danger'?'Error':$flash['type']) ?></strong>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body"><?= htmlspecialchars($flash['msg']) ?></div>
    </div>
<?php endif; ?>
</div>

<div id="main-wrapper">
    <div id="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="topbar-btn d-lg-none border-0" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <div>
                <div class="topbar-title">Post-Production</div>
                <div class="topbar-sub">Track project progress and deliverables</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (!empty($unlinked)): ?>
                <button class="btn btn-dark btn-sm px-3" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="bi bi-plus-lg me-1"></i> New Project
                </button>
            <?php endif; ?>
            <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
            <?php if ($_adminAvatar): ?><img src="../assets/avatars/<?= htmlspecialchars($_adminAvatar) ?>" class="topbar-avatar" style="object-fit:cover;"><?php else: ?><div class="topbar-avatar"><?= $_adminInitial ?></div><?php endif; ?>
        </div>
    </div>

    <div class="p-3 p-md-4">
        <div class="dash-card">
            <div class="dash-card-header">
                <h6>Post-Production Projects <span class="badge bg-secondary ms-2"><?= count($projects) ?></span></h6>
            </div>
            <div class="table-responsive">
                <table class="table modern-table mobile-cards mb-0">
                    <thead>
                        <tr><th>Client</th><th>Package</th><th>Event Date</th><th>Photo</th><th>Video</th><th>Other</th><th>Progress</th><th>Deadline</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($projects)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">
                            <i class="bi bi-film d-block fs-3 mb-2 opacity-25"></i>No post-production projects yet.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($projects as $proj):
                            $pct = min(100,max(0,(int)$proj['progress_percent']));
                            $barClass = $pct < 40 ? 'bg-danger' : ($pct < 75 ? 'bg-warning' : 'bg-success');
                            $dl = $proj['deadline_status'];
                            $isComplete = ($proj['photo_status']==='completed' && $proj['video_status']==='completed' && $proj['other_status']==='completed');
                        ?>
                        <tr>
                            <td data-label="Client">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar-sm"><?= strtoupper(substr($proj['client'],0,1)) ?></span>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($proj['client']) ?></div>
                                        <div style="font-size:.73rem;color:#aaa;"><?= htmlspecialchars($proj['client_email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Package"><?= htmlspecialchars($proj['package']) ?></td>
                            <td data-label="Event Date"><?= htmlspecialchars($proj['booking_date']) ?></td>
                            <?php foreach (['photo_status'=>'Photo','video_status'=>'Video','other_status'=>'Other'] as $col=>$lbl): ?>
                            <td data-label="<?= $lbl ?>"><span class="badge bg-<?= $statusBadge[$proj[$col]]??'secondary' ?>" style="font-size:.68rem;"><?= $statusOpts[$proj[$col]]??$proj[$col] ?></span></td>
                            <?php endforeach; ?>
                            <td data-label="Progress" style="min-width:100px;">
                                <div class="d-flex align-items-center gap-1">
                                    <div class="progress flex-grow-1" style="height:6px;">
                                        <div class="progress-bar <?= $barClass ?>" role="progressbar" style="width:<?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <span style="font-size:.72rem;width:28px;text-align:right;font-weight:600;"><?= $pct ?>%</span>
                                </div>
                            </td>
                            <td data-label="Deadline"><?= $proj['deadline'] ? htmlspecialchars($proj['deadline']) : '—' ?></td>
                            <td data-label="Status">
                                <?php if ($isComplete): ?>
                                    <span class="badge bg-success">Completed</span>
                                <?php else: ?>
                                    <span class="badge bg-<?= $dlBadge[$dl] ?>"><?= $dlLabel[$dl] ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions">
                                <div class="d-flex gap-1">
                                    <button class="btn-action" title="Update"
                                            onclick="openUpdateModal(<?= htmlspecialchars(json_encode($proj),ENT_QUOTES) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if (!$isComplete): ?>
                                        <button class="btn-action success" title="Mark Complete"
                                                onclick="openCompleteModal(<?= (int)$proj['id'] ?>, '<?= htmlspecialchars(addslashes($proj['client'])) ?>')">
                                            <i class="bi bi-check2-all"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Complete Modal -->
<div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST">
            <input type="hidden" name="action" value="complete">
            <input type="hidden" name="id" id="completeId">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title">Mark as Completed</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" style="font-size:.875rem;">Mark project for <strong id="completeName"></strong> as fully completed? The client will be notified by email.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">Mark Complete</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Update Modal -->
<div class="modal fade" id="updateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="uId">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title">Update Project Progress</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="info-box mb-3">
                        <div class="row g-2" style="font-size:.84rem;">
                            <div class="col-4"><div class="info-label">Client</div><div id="uClient" class="fw-semibold mt-1"></div></div>
                            <div class="col-4"><div class="info-label">Package</div><div id="uPackage" class="mt-1"></div></div>
                            <div class="col-4"><div class="info-label">Event Date</div><div id="uEventDate" class="mt-1"></div></div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <?php foreach (['photo_status'=>'Photo Status','video_status'=>'Video Status','other_status'=>'Other Deliverables'] as $field=>$label): ?>
                        <div class="col-md-4">
                            <label class="form-label"><?= $label ?></label>
                            <select class="form-select form-select-sm" name="<?= $field ?>" id="u_<?= $field ?>" onchange="syncProgress()">
                                <?php foreach ($statusOpts as $val=>$text): ?><option value="<?= $val ?>"><?= $text ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                        <div class="col-md-4">
                            <label class="form-label">Progress %</label>
                            <input type="number" class="form-control form-control-sm" name="progress_percent" id="uProgress" min="0" max="100">
                            <div class="form-text">Leave blank to auto-calculate.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Deadline</label>
                            <input type="date" class="form-control form-control-sm" name="deadline" id="uDeadline">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Google Drive Link</label>
                            <input type="url" class="form-control form-control-sm" name="drive_link" id="uDriveLink" placeholder="https://drive.google.com/…">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control form-control-sm" name="notes" id="uNotes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark btn-sm">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Create Modal -->
<?php if (!empty($unlinked)): ?>
<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-title">New Post-Production Project</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Select Approved Booking</label>
                    <select class="form-select" name="booking_id" required>
                        <option value="">— Choose a booking —</option>
                        <?php foreach ($unlinked as $ul): ?>
                            <option value="<?= (int)$ul['id'] ?>">#<?= (int)$ul['id'] ?> — <?= htmlspecialchars($ul['client']) ?> (<?= htmlspecialchars($ul['package']) ?>) on <?= htmlspecialchars($ul['booking_date']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark btn-sm">Create Project</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('show'));
    function openCompleteModal(id, name) {
        document.getElementById('completeId').value = id;
        document.getElementById('completeName').textContent = name;
        new bootstrap.Modal(document.getElementById('completeModal')).show();
    }
    const scoreMap = {not_started:0,in_progress:1,completed:2};
    function syncProgress() {
        const score = scoreMap[document.getElementById('u_photo_status').value]
                    + scoreMap[document.getElementById('u_video_status').value]
                    + scoreMap[document.getElementById('u_other_status').value];
        document.getElementById('uProgress').value = Math.round((score/6)*100);
    }
    function openUpdateModal(p) {
        document.getElementById('uId').value = p.id;
        document.getElementById('uClient').textContent   = p.client      ?? '—';
        document.getElementById('uPackage').textContent  = p.package     ?? '—';
        document.getElementById('uEventDate').textContent = p.booking_date ?? '—';
        document.getElementById('u_photo_status').value = p.photo_status ?? 'not_started';
        document.getElementById('u_video_status').value = p.video_status ?? 'not_started';
        document.getElementById('u_other_status').value = p.other_status ?? 'not_started';
        document.getElementById('uProgress').value  = p.progress_percent ?? '';
        document.getElementById('uDeadline').value  = p.deadline   ?? '';
        document.getElementById('uDriveLink').value = p.drive_link ?? '';
        document.getElementById('uNotes').value     = p.notes      ?? '';
        new bootstrap.Modal(document.getElementById('updateModal')).show();
    }
    document.addEventListener('DOMContentLoaded', () => {
        const t = document.getElementById('liveToast');
        if (t) setTimeout(() => bootstrap.Toast.getOrCreateInstance(t).hide(), 4000);
    });
</script>
</body>
</html>
