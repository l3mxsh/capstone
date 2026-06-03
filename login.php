<?php
session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
if (isset($_SESSION['user_id'])) {
    header('Location: '.($_SESSION['role']==='admin'?'admin/dashboard.php':'client/dashboard.php')); exit;
}
require_once 'config/db.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']??'')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email']??''); $password = $_POST['password']??'';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND status='active'");
        $stmt->execute([$email]); $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id']; $_SESSION['role'] = $user['role']; $_SESSION['name'] = $user['name'];
            header('Location: '.($user['role']==='admin'?'admin/dashboard.php':'client/dashboard.php')); exit;
        }
        $error = 'Invalid credentials. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="icon" type="image/png" href="assets/images/Logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/login.css" rel="stylesheet">
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <div class="text-center mb-4">
                <div class="brand-logo mx-auto" style="background:none;padding:0;width:auto;height:auto;">
                    <img src="assets/images/Black Logo.png" alt="Harvy Mance Films" style="height:64px;object-fit:contain;">
                </div>
                <p class="text-muted mb-0" style="font-size:.82rem;">Sign in to your account</p>
            </div>

            <?php if ($error): ?>
            <div class="d-flex align-items-center gap-2 p-3 mb-3 rounded-2"
                 style="background:#fef2f2;border:1px solid #fecaca;font-size:.82rem;color:#c0392b;">
                <i class="bi bi-x-circle-fill flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= htmlspecialchars($_POST['email']??'') ?>" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <div class="position-relative">
                        <input type="password" class="form-control pe-5" id="password" name="password" required>
                        <button type="button"
                                class="position-absolute top-50 end-0 translate-middle-y border-0 bg-transparent me-2 text-muted"
                                onclick="togglePwd()" style="font-size:.9rem;cursor:pointer;" tabindex="-1">
                            <i class="bi bi-eye" id="pwdIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>

            <hr class="divider mt-4 mb-3">
            <p class="text-center mb-2" style="font-size:.82rem;color:#555;">
                Don't have an account?
                <a href="register.php" class="fw-semibold text-dark text-decoration-none">Create one</a>
            </p>
            <p class="text-center mb-0" style="font-size:.78rem;color:#aaa;">
                Harvy Mance Films &nbsp;·&nbsp; Client &amp; Admin Portal
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePwd() {
            const inp = document.getElementById('password');
            const ico = document.getElementById('pwdIcon');
            if (inp.type === 'password') { inp.type = 'text'; ico.className = 'bi bi-eye-slash'; }
            else { inp.type = 'password'; ico.className = 'bi bi-eye'; }
        }
    </script>
</body>
</html>
