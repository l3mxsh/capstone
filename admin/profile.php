<?php
require_once '../auth/admin_guard.php';
require_once '../config/db.php';

$adminId = (int)$_SESSION['user_id'];
$flash   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '' || $email === '') {

            $flash = ['type' => 'danger', 'msg' => 'Name and email are required.'];
        } else {
            $dup = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
            $dup->execute([$email, $adminId]);
            if ($dup->fetch()) {
                $flash = ['type' => 'danger', 'msg' => 'Email already in use.'];
            } else {
                $pdo->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=?")
                    ->execute([$name, $email, $phone, $adminId]);
                $_SESSION['name'] = $name;
                $flash = ['type' => 'success', 'msg' => 'Profile updated successfully.'];
            }
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $hash    = $pdo->query("SELECT password FROM users WHERE id=$adminId")->fetchColumn();
        if (!password_verify($current, $hash)) {
            $flash = ['type' => 'danger', 'msg' => 'Current password is incorrect.'];
        } elseif (strlen($new) < 6) {
            $flash = ['type' => 'danger', 'msg' => 'New password must be at least 6 characters.'];
        } elseif ($new !== $confirm) {
            $flash = ['type' => 'danger', 'msg' => 'Passwords do not match.'];
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $adminId]);
            $flash = ['type' => 'success', 'msg' => 'Password changed successfully.'];
        }
    } elseif ($action === 'upload_avatar') {
        $file = $_FILES['avatar'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $flash = ['type' => 'danger', 'msg' => 'Upload failed. Please try again.'];
        } else {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $mime    = mime_content_type($file['tmp_name']);
            if (!in_array($mime, $allowed)) {
                $flash = ['type' => 'danger', 'msg' => 'Only JPG, PNG, WEBP or GIF allowed.'];
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $flash = ['type' => 'danger', 'msg' => 'File must be under 2MB.'];
            } else {
                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $adminId . '_' . time() . '.' . $ext;
                $dest     = '../assets/avatars/' . $filename;
                $old = $pdo->query("SELECT avatar FROM users WHERE id=$adminId")->fetchColumn();
                if ($old && file_exists('../assets/avatars/' . $old)) unlink('../assets/avatars/' . $old);
                move_uploaded_file($file['tmp_name'], $dest);
                $pdo->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$filename, $adminId]);
                $flash = ['type' => 'success', 'msg' => 'Avatar updated successfully.'];
            }
        }
    }
}

$user = $pdo->prepare("SELECT name, email, phone, avatar FROM users WHERE id=?");
$user->execute([$adminId]);
$user = $user->fetch();

$initials   = strtoupper(substr($user['name'], 0, 1));
$pageTitle  = 'Profile Settings';
$activePage = 'profile';
require_once '../includes/admin_head.php';
?>
</head>

<body>
    <?php require_once '../includes/sidebar.php'; ?>

    <div id="main-wrapper">
        <div id="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="topbar-btn d-lg-none border-0" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
                <div>
                    <div class="topbar-title">Profile Settings</div>
                    <div class="topbar-sub">Manage your admin account</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="../logout.php" class="topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
                <?php if ($user['avatar']): ?>
                    <img src="../assets/avatars/<?= htmlspecialchars($user['avatar']) ?>" class="topbar-avatar" style="object-fit:cover;">
                <?php else: ?>
                    <div class="topbar-avatar"><?= $initials ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-3 p-md-4">

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible d-flex align-items-center gap-2 mb-4" style="border-radius:10px;font-size:.85rem;">
                    <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                    <span><?= htmlspecialchars($flash['msg']) ?></span>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">

                <!-- Avatar -->
                <div class="col-lg-4">
                    <div class="dash-card">
                        <div class="dash-card-header">
                            <h6>Profile Photo</h6>
                        </div>
                        <div class="dash-card-body text-center">
                            <div class="mb-3">
                                <?php if ($user['avatar']): ?>
                                    <img src="../assets/avatars/<?= htmlspecialchars($user['avatar']) ?>"
                                        style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid #e8e8e8;">
                                <?php else: ?>
                                    <div style="width:96px;height:96px;border-radius:50%;background:#111;color:#fff;font-size:2rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;">
                                        <?= $initials ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_avatar">
                                <label class="form-label" style="font-size:.78rem;color:#888;">JPG, PNG, WEBP · Max 2MB</label>
                                <input type="file" class="form-control form-control-sm mb-3" name="avatar" accept="image/*" required>
                                <button type="submit" class="btn btn-dark btn-sm w-100">Upload Photo</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Info + Password -->
                <div class="col-lg-8">
                    <div class="dash-card mb-4">
                        <div class="dash-card-header">
                            <h6>Personal Information</h6>
                        </div>
                        <div class="dash-card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 09XX-XXX-XXXX">
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-dark btn-sm px-4">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="dash-card">
                        <div class="dash-card-header">
                            <h6>Change Password</h6>
                        </div>
                        <div class="dash-card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" required minlength="6">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password" required minlength="6">
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-dark btn-sm px-4">Change Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('show'));
    </script>
</body>

</html>