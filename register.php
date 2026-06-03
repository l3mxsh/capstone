<?php
session_start();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'client/dashboard.php'));
    exit;
}

require_once 'config/db.php';

$errors  = [];
$success = false;
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid request. Please try again.';
    } else {
        $old = [
            'name'     => trim($_POST['name']     ?? ''),
            'email'    => trim($_POST['email']    ?? ''),
            'phone'    => trim($_POST['phone']    ?? ''),
            'password' => $_POST['password']      ?? '',
            'confirm'  => $_POST['confirm']       ?? '',
        ];

        // ── Validate ──────────────────────────────────────────
        if ($old['name'] === '') {
            $errors['name'] = 'Full name is required.';
        } elseif (strlen($old['name']) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        }

        if ($old['email'] === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            $dup = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $dup->execute([$old['email']]);
            if ($dup->fetch()) {
                $errors['email'] = 'This email is already registered. Please log in.';
            }
        }

        if ($old['password'] === '') {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($old['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $old['password'])) {
            $errors['password'] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $old['password'])) {
            $errors['password'] = 'Password must contain at least one number.';
        }

        if ($old['confirm'] === '') {
            $errors['confirm'] = 'Please confirm your password.';
        } elseif ($old['password'] !== $old['confirm']) {
            $errors['confirm'] = 'Passwords do not match.';
        }

        // ── Insert if valid ───────────────────────────────────
        if (empty($errors)) {
            $hash = password_hash($old['password'], PASSWORD_DEFAULT);

            $pdo->prepare("
                INSERT INTO users (name, email, password, role, status)
                VALUES (?, ?, ?, 'client', 'active')
            ")->execute([$old['name'], $old['email'], $hash]);

            $newId = (int)$pdo->lastInsertId();

            // Notify admin
            $subject = 'New Client Registration — Harvy Mance Films';
            $message = "A new client has registered.\n\n"
                . "Name:  {$old['name']}\n"
                . "Email: {$old['email']}\n\n"
                . "Log in to the admin panel to manage their account.";
            @mail(
                'admin@harvymancefilms.com',
                $subject,
                $message,
                "From: noreply@harvymancefilms.com\r\nContent-Type: text/plain; charset=UTF-8"
            );

            // Auto-login
            session_regenerate_id(true);
            $_SESSION['user_id'] = $newId;
            $_SESSION['role']    = 'client';
            $_SESSION['name']    = $old['name'];

            header('Location: client/dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/login.css" rel="stylesheet">
    <style>
        /* Register-specific overrides */
        .login-card {
            max-width: 480px;
        }

        .login-wrap {
            max-width: 480px;
        }

        .strength-bar {
            height: 4px;
            border-radius: 4px;
            background: #e8e8e8;
            overflow: hidden;
            margin-top: .4rem;
        }

        .strength-fill {
            height: 100%;
            border-radius: 4px;
            transition: width .3s, background .3s;
            width: 0;
        }

        .req-item {
            font-size: .75rem;
            color: #aaa;
            display: flex;
            align-items: center;
            gap: .35rem;
            transition: color .2s;
        }

        .req-item.met {
            color: #27ae60;
        }

        .req-item.unmet {
            color: #aaa;
        }

        .req-item i {
            font-size: .75rem;
        }

        .form-control.is-invalid {
            background-image: none;
        }
    </style>
</head>

<body>
    <div class="login-wrap" style="max-width:480px;">
        <div class="login-card">

            <!-- Header -->
            <div class="text-center mb-4">
                <div class="brand-logo mx-auto">HMF</div>
                <h5 class="fw-bold mb-1" style="font-size:1.1rem;">Create Your Account</h5>
                <p class="text-muted mb-0" style="font-size:.82rem;">
                    Harvy Mance Films — Client Portal
                </p>
            </div>

            <!-- General error -->
            <?php if (isset($errors['general'])): ?>
                <div class="d-flex align-items-center gap-2 p-3 mb-3 rounded-2"
                    style="background:#fef2f2;border:1px solid #fecaca;font-size:.82rem;color:#c0392b;">
                    <i class="bi bi-x-circle-fill flex-shrink-0"></i>
                    <span><?= htmlspecialchars($errors['general']) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="registerForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <!-- Full Name -->
                <div class="mb-3">
                    <label class="form-label" for="name">Full Name <span class="text-danger">*</span></label>
                    <input type="text"
                        class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                        id="name" name="name"
                        value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                        placeholder="e.g. Juan dela Cruz"
                        required autofocus>
                    <?php if (isset($errors['name'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Email -->
                <div class="mb-3">
                    <label class="form-label" for="email">Email Address <span class="text-danger">*</span></label>
                    <input type="email"
                        class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                        id="email" name="email"
                        value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                        placeholder="you@email.com"
                        required>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Phone (optional) -->
                <div class="mb-3">
                    <label class="form-label" for="phone">
                        Phone Number
                        <span class="text-muted" style="font-size:.75rem;font-weight:400;">(optional)</span>
                    </label>
                    <input type="tel"
                        class="form-control"
                        id="phone" name="phone"
                        value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                        placeholder="09XX-XXX-XXXX">
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                    <div class="position-relative">
                        <input type="password"
                            class="form-control pe-5 <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                            id="password" name="password"
                            placeholder="Min. 8 characters"
                            oninput="checkStrength(this.value)"
                            required>
                        <button type="button"
                            class="position-absolute top-50 end-0 translate-middle-y border-0 bg-transparent me-2 text-muted"
                            onclick="togglePwd('password','pwdIcon1')"
                            style="font-size:.9rem;cursor:pointer;z-index:5;" tabindex="-1">
                            <i class="bi bi-eye" id="pwdIcon1"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <div class="text-danger mt-1" style="font-size:.875em;"><?= htmlspecialchars($errors['password']) ?></div>
                    <?php endif; ?>

                    <!-- Strength bar -->
                    <div class="strength-bar mt-2">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <div class="d-flex gap-3 mt-2 flex-wrap">
                        <span class="req-item unmet" id="req-len">
                            <i class="bi bi-circle"></i> 8+ characters
                        </span>
                        <span class="req-item unmet" id="req-upper">
                            <i class="bi bi-circle"></i> Uppercase letter
                        </span>
                        <span class="req-item unmet" id="req-num">
                            <i class="bi bi-circle"></i> Number
                        </span>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label class="form-label" for="confirm">Confirm Password <span class="text-danger">*</span></label>
                    <div class="position-relative">
                        <input type="password"
                            class="form-control pe-5 <?= isset($errors['confirm']) ? 'is-invalid' : '' ?>"
                            id="confirm" name="confirm"
                            placeholder="Re-enter your password"
                            oninput="checkMatch()"
                            required>
                        <button type="button"
                            class="position-absolute top-50 end-0 translate-middle-y border-0 bg-transparent me-2 text-muted"
                            onclick="togglePwd('confirm','pwdIcon2')"
                            style="font-size:.9rem;cursor:pointer;z-index:5;" tabindex="-1">
                            <i class="bi bi-eye" id="pwdIcon2"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['confirm'])): ?>
                        <div class="text-danger mt-1" style="font-size:.875em;"><?= htmlspecialchars($errors['confirm']) ?></div>
                    <?php endif; ?>
                    <div id="matchMsg" style="font-size:.75rem;margin-top:.3rem;display:none;"></div>
                </div>

                <!-- Terms notice -->
                <p class="text-muted mb-3" style="font-size:.76rem;">
                    By creating an account, you agree to our booking terms and consent to being contacted by the studio regarding your bookings.
                </p>

                <button type="submit" class="btn-login">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </button>
            </form>

            <hr class="divider mt-4 mb-3">

            <p class="text-center mb-0" style="font-size:.82rem;color:#555;">
                Already have an account?
                <a href="login.php" class="fw-semibold text-dark text-decoration-none">Sign in</a>
            </p>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePwd(inputId, iconId) {
            const inp = document.getElementById(inputId);
            const ico = document.getElementById(iconId);
            if (inp.type === 'password') {
                inp.type = 'text';
                ico.className = 'bi bi-eye-slash';
            } else {
                inp.type = 'password';
                ico.className = 'bi bi-eye';
            }
        }

        function checkStrength(val) {
            const hasLen = val.length >= 8;
            const hasUpper = /[A-Z]/.test(val);
            const hasNum = /[0-9]/.test(val);

            setReq('req-len', hasLen);
            setReq('req-upper', hasUpper);
            setReq('req-num', hasNum);

            const score = [hasLen, hasUpper, hasNum].filter(Boolean).length;
            const fill = document.getElementById('strengthFill');
            const pct = score === 0 ? 0 : score === 1 ? 33 : score === 2 ? 66 : 100;
            const color = score === 1 ? '#c0392b' : score === 2 ? '#e67e22' : '#27ae60';
            fill.style.width = pct + '%';
            fill.style.background = pct === 0 ? '#e8e8e8' : color;

            checkMatch();
        }

        function setReq(id, met) {
            const el = document.getElementById(id);
            el.className = 'req-item ' + (met ? 'met' : 'unmet');
            el.querySelector('i').className = met ? 'bi bi-check-circle-fill' : 'bi bi-circle';
        }

        function checkMatch() {
            const pwd = document.getElementById('password').value;
            const conf = document.getElementById('confirm').value;
            const msg = document.getElementById('matchMsg');
            if (!conf) {
                msg.style.display = 'none';
                return;
            }
            msg.style.display = 'block';
            if (pwd === conf) {
                msg.style.color = '#27ae60';
                msg.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Passwords match';
            } else {
                msg.style.color = '#c0392b';
                msg.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i>Passwords do not match';
            }
        }
    </script>
</body>

</html>